"""SQLite outage buffer for idempotent IR4 ingest flush (DOC-08).

Capacity (approx. 400–600 bytes/row including SQLite overhead):

- Gas: 1 reading / 30 s → ~2/min → 200_000 rows ≈ 69 days.
- RFID: debounce 300 s (5 min per EPC); 200_000 rows ≈ long outage headroom
  (FXR90 also holds ~150k / 500 min on the reader).
- 72 GB Orin disk: years at these rates. The row cap is a disk-safety bound,
  not a retention policy.

On overflow, drop oldest first so responders keep the newest readings.
Heartbeat counters are omitted: SCC already sees last_seen / offline from
ingest and heartbeats; overflow is a pole journal line, not a new health
channel. Server-rejected rows in an accepted 202 batch stay deleted — they
are already old relative to live state and not worth a second spool.
"""

from __future__ import annotations

import json
import logging
import sqlite3
import threading
import time
from pathlib import Path
from typing import Any, Callable, Dict, List, Mapping, Sequence

from ir4_edge.common.client import IngestResult, Ir4Client

log = logging.getLogger("ir4_edge.buffer")

MAX_BATCH = 1000
# Pending-row cap (not the flush batch size).
DEFAULT_MAX_ROWS = 200_000


def _rejected_indices(rejected: Sequence[Dict[str, Any]], batch_size: int) -> set:
    """Indices of server-rejected events within a batch (validated against size)."""
    indices = {
        int(item.get("index", -1))
        for item in rejected
        if isinstance(item, dict)
    }
    return {index for index in indices if 0 <= index < batch_size}


class OutageBuffer:
    """Persist events locally; flush in ≤1000 batches keeping event_uid."""

    def __init__(
        self,
        db_path: Path,
        stream: str,
        *,
        max_rows: int = DEFAULT_MAX_ROWS,
    ) -> None:
        self.db_path = Path(db_path)
        self.stream = stream
        self.max_rows = max(1, int(max_rows))
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
        dropped = 0
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
            if inserted:
                dropped = self._evict_oldest_locked()
            self._conn.commit()
        if dropped:
            log.warning(
                "Buffer cap %d: dropped %d oldest %s events (kept newest)",
                self.max_rows,
                dropped,
                self.stream,
            )
        return inserted

    def pending_count(self) -> int:
        with self._lock:
            return self._count_locked()

    def _count_locked(self) -> int:
        row = self._conn.execute(
            "SELECT COUNT(*) FROM events WHERE stream = ?",
            (self.stream,),
        ).fetchone()
        return int(row[0]) if row else 0

    def _evict_oldest_locked(self) -> int:
        """Delete oldest rows until at cap. Caller holds the lock and commits."""
        excess = self._count_locked() - self.max_rows
        if excess <= 0:
            return 0
        self._conn.execute(
            """
            DELETE FROM events
            WHERE id IN (
                SELECT id FROM events
                WHERE stream = ?
                ORDER BY id ASC
                LIMIT ?
            )
            """,
            (self.stream, excess),
        )
        return excess

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
            rejected_indices = _rejected_indices(result.rejected, len(batch))
            keep_ids: List[int] = []
            requeue: List[Dict[str, Any]] = []
            for index, row_id in enumerate(ids):
                if index in rejected_indices:
                    requeue.append(dict(batch[index]))
                else:
                    keep_ids.append(row_id)
            self._delete_ids(keep_ids)
            if requeue:
                self.enqueue(requeue)
            removed += len(keep_ids)
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
            if result.rejected:
                rejected_indices = _rejected_indices(result.rejected, len(events))
                requeue = [
                    dict(events[index])
                    for index in range(len(events))
                    if index in rejected_indices
                ]
                if requeue:
                    self.enqueue(requeue)
                    log.warning(
                        "Re-buffered %d server-rejected live events",
                        len(requeue),
                    )
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
        self.enqueue(events)
        return result
