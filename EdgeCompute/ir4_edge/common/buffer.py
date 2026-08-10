"""SQLite outage buffer for idempotent IR4 ingest flush (DOC-08)."""

from __future__ import annotations

import json
import logging
import sqlite3
import threading
import time
from pathlib import Path
from typing import Any, Callable, Dict, List, Mapping, Optional, Sequence

from ir4_edge.common.client import IngestResult, Ir4Client

log = logging.getLogger("ir4_edge.buffer")

MAX_BATCH = 1000


class OutageBuffer:
    """Persist events locally; flush in ≤1000 batches keeping event_uid."""

    def __init__(self, db_path: Path, stream: str) -> None:
        self.db_path = Path(db_path)
        self.stream = stream
        self.db_path.parent.mkdir(parents=True, exist_ok=True)
        self._lock = threading.Lock()
        self._conn = sqlite3.connect(str(self.db_path), check_same_thread=False)
        self._conn.execute(
            """
            CREATE TABLE IF NOT EXISTS events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                stream TEXT NOT NULL,
                event_uid TEXT NOT NULL,
                payload TEXT NOT NULL,
                created_at REAL NOT NULL,
                UNIQUE(stream, event_uid)
            )
            """
        )
        self._conn.commit()

    def close(self) -> None:
        with self._lock:
            self._conn.close()

    def enqueue(self, events: Sequence[Mapping[str, Any]]) -> int:
        """Insert events; ignore duplicates by event_uid. Returns inserted count."""
        inserted = 0
        now = time.time()
        with self._lock:
            for event in events:
                event_uid = str(event.get("event_uid") or "")
                if not event_uid:
                    continue
                try:
                    cursor = self._conn.execute(
                        "INSERT OR IGNORE INTO events (stream, event_uid, payload, created_at) "
                        "VALUES (?, ?, ?, ?)",
                        (self.stream, event_uid, json.dumps(dict(event)), now),
                    )
                    if cursor.rowcount:
                        inserted += 1
                except sqlite3.Error as exc:
                    log.error("Buffer enqueue failed: %s", exc)
            self._conn.commit()
        return inserted

    def pending_count(self) -> int:
        with self._lock:
            row = self._conn.execute(
                "SELECT COUNT(*) FROM events WHERE stream = ?",
                (self.stream,),
            ).fetchone()
        return int(row[0]) if row else 0

    def _peek(self, limit: int = MAX_BATCH) -> List[Dict[str, Any]]:
        with self._lock:
            rows = self._conn.execute(
                "SELECT id, payload FROM events WHERE stream = ? ORDER BY id ASC LIMIT ?",
                (self.stream, limit),
            ).fetchall()
        out: List[Dict[str, Any]] = []
        for row_id, payload in rows:
            item = json.loads(payload)
            item["_buffer_id"] = row_id
            out.append(item)
        return out

    def _delete_ids(self, ids: Sequence[int]) -> None:
        if not ids:
            return
        with self._lock:
            self._conn.executemany(
                "DELETE FROM events WHERE id = ?",
                [(i,) for i in ids],
            )
            self._conn.commit()

    def flush(
        self,
        client: Ir4Client,
        sender: Callable[[Ir4Client, Sequence[Mapping[str, Any]]], IngestResult],
        *,
        max_batches: int = 10,
    ) -> int:
        """Flush queued events. Returns number of events removed from the buffer."""
        removed = 0
        for _ in range(max_batches):
            batch = self._peek(MAX_BATCH)
            if not batch:
                break
            ids = [int(item.pop("_buffer_id")) for item in batch]
            result = sender(client, batch)
            if result.status_code not in (200, 202):
                if result.retriable:
                    log.warning(
                        "Flush deferred (%s); leaving %d events buffered",
                        result.error,
                        len(batch),
                    )
                else:
                    log.error(
                        "Flush rejected status=%s error=%s; buffer retained",
                        result.status_code,
                        result.error,
                    )
                break
            if result.rejected:
                log.warning(
                    "Ingest rejected %d events: %s",
                    len(result.rejected),
                    result.rejected[:5],
                )
            self._delete_ids(ids)
            removed += len(ids)
            log.info(
                "Flushed batch size=%d accepted=%d duplicates=%d rejected=%d",
                len(ids),
                result.accepted,
                result.duplicates,
                len(result.rejected),
            )
        return removed

    def submit(
        self,
        client: Ir4Client,
        events: Sequence[Mapping[str, Any]],
        sender: Callable[[Ir4Client, Sequence[Mapping[str, Any]]], IngestResult],
    ) -> IngestResult:
        """Try live send; on retriable failure enqueue and return retriable result."""
        if not events:
            return IngestResult(status_code=0)
        result = sender(client, events)
        if result.status_code in (200, 202) and not result.retriable:
            # Also flush any backlog while the link is healthy.
            self.flush(client, sender)
            return result
        if result.retriable or result.status_code >= 500 or result.status_code == 0:
            n = self.enqueue(events)
            log.warning(
                "Buffered %d events after send failure (%s)",
                n,
                result.error or result.status_code,
            )
            return result
        # Non-retriable: still buffer so we do not drop machine truth; operator must fix auth.
        self.enqueue(events)
        return result
