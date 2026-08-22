"""Persistent deploy state — SQLite audit log, metadata, and file lock."""

from __future__ import annotations

import json
import logging
import sqlite3
import threading
import time
import uuid
from contextlib import contextmanager
from pathlib import Path
from typing import Any, Dict, Iterator, Optional

from ir4_edge.deploy.models import DeployStatus, OperationKind, OperationRecord, TransportName

log = logging.getLogger("ir4_edge.deploy.state")

INTERRUPTED_AFTER_S = 3600


class DeployStateStore:
    """Track operations, deployed version, and exclusive deploy lock."""

    def __init__(self, var_dir: Path) -> None:
        self.var_dir = Path(var_dir)
        self.var_dir.mkdir(parents=True, exist_ok=True)
        self.db_path = self.var_dir / "deploy_state.sqlite"
        self.lock_path = self.var_dir / "deploy.lock"
        self._lock = threading.Lock()
        self._conn = sqlite3.connect(str(self.db_path), check_same_thread=False)
        self._conn.execute(
            """
            CREATE TABLE IF NOT EXISTS operations (
                id TEXT PRIMARY KEY,
                kind TEXT NOT NULL,
                transport TEXT NOT NULL,
                pole INTEGER NOT NULL,
                target_version TEXT NOT NULL,
                status TEXT NOT NULL,
                started_at REAL NOT NULL,
                finished_at REAL,
                message TEXT NOT NULL DEFAULT '',
                details_json TEXT NOT NULL DEFAULT '{}'
            )
            """
        )
        self._conn.execute(
            """
            CREATE TABLE IF NOT EXISTS meta (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            )
            """
        )
        self._conn.commit()

    def close(self) -> None:
        with self._lock:
            self._conn.close()

    def new_operation_id(self) -> str:
        return uuid.uuid4().hex

    def get_deployed_version(self) -> str:
        row = self._conn.execute(
            "SELECT value FROM meta WHERE key = 'deployed_version'",
        ).fetchone()
        return str(row[0]) if row else ""

    def set_deployed_version(self, version: str) -> None:
        with self._lock:
            self._conn.execute(
                "INSERT OR REPLACE INTO meta (key, value) VALUES ('deployed_version', ?)",
                (version,),
            )
            self._conn.execute(
                "INSERT OR REPLACE INTO meta (key, value) VALUES ('last_success_at', ?)",
                (str(time.time()),),
            )
            self._conn.commit()

    def record(
        self,
        operation_id: str,
        kind: OperationKind,
        transport: TransportName,
        pole: int,
        target_version: str,
        status: DeployStatus,
        message: str = "",
        details: Optional[Dict[str, Any]] = None,
        *,
        finished: bool = False,
    ) -> None:
        now = time.time()
        payload = json.dumps(details or {})
        with self._lock:
            existing = self._conn.execute(
                "SELECT started_at FROM operations WHERE id = ?",
                (operation_id,),
            ).fetchone()
            if existing:
                self._conn.execute(
                    """
                    UPDATE operations
                    SET status = ?, message = ?, details_json = ?, finished_at = ?
                    WHERE id = ?
                    """,
                    (
                        status.value,
                        message,
                        payload,
                        now if finished else None,
                        operation_id,
                    ),
                )
            else:
                self._conn.execute(
                    """
                    INSERT INTO operations
                    (id, kind, transport, pole, target_version, status, started_at, finished_at, message, details_json)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    """,
                    (
                        operation_id,
                        kind.value,
                        transport.value,
                        pole,
                        target_version,
                        status.value,
                        now,
                        now if finished else None,
                        message,
                        payload,
                    ),
                )
            self._conn.commit()

    def latest_operation(self) -> Optional[OperationRecord]:
        row = self._conn.execute(
            """
            SELECT id, kind, transport, pole, target_version, status, message, details_json
            FROM operations ORDER BY started_at DESC LIMIT 1
            """
        ).fetchone()
        if not row:
            return None
        details = json.loads(row[7] or "{}")
        return OperationRecord(
            id=row[0],
            kind=OperationKind(row[1]),
            transport=TransportName(row[2]),
            pole=int(row[3]),
            target_version=row[4],
            status=DeployStatus(row[5]),
            message=row[6] or "",
            details=details,
        )

    def recover_stale_operations(self) -> int:
        """Mark long-running in-progress operations as interrupted."""
        cutoff = time.time() - INTERRUPTED_AFTER_S
        with self._lock:
            cursor = self._conn.execute(
                """
                UPDATE operations
                SET status = ?, message = ?, finished_at = ?
                WHERE status IN (?, ?, ?, ?, ?) AND started_at < ?
                """,
                (
                    DeployStatus.INTERRUPTED.value,
                    "stale — run deploy again to reconcile",
                    time.time(),
                    DeployStatus.PENDING.value,
                    DeployStatus.DELIVERING.value,
                    DeployStatus.HOST_SETUP.value,
                    DeployStatus.CONFIGURING.value,
                    DeployStatus.VERIFYING.value,
                    cutoff,
                ),
            )
            self._conn.commit()
            return cursor.rowcount

    def find_in_progress(self) -> Optional[OperationRecord]:
        row = self._conn.execute(
            """
            SELECT id, kind, transport, pole, target_version, status, message, details_json, started_at
            FROM operations
            WHERE status IN (?, ?, ?, ?, ?)
            ORDER BY started_at DESC LIMIT 1
            """,
            (
                DeployStatus.PENDING.value,
                DeployStatus.DELIVERING.value,
                DeployStatus.HOST_SETUP.value,
                DeployStatus.CONFIGURING.value,
                DeployStatus.VERIFYING.value,
            ),
        ).fetchone()
        if not row:
            return None
        return OperationRecord(
            id=row[0],
            kind=OperationKind(row[1]),
            transport=TransportName(row[2]),
            pole=int(row[3]),
            target_version=row[4],
            status=DeployStatus(row[5]),
            message=row[6] or "",
            details=json.loads(row[7] or "{}"),
        )

    def mark_interrupted(self, operation_id: str, message: str) -> None:
        with self._lock:
            self._conn.execute(
                """
                UPDATE operations
                SET status = ?, message = ?, finished_at = ?
                WHERE id = ? AND status NOT IN (?, ?)
                """,
                (
                    DeployStatus.INTERRUPTED.value,
                    message,
                    time.time(),
                    operation_id,
                    DeployStatus.SUCCESS.value,
                    DeployStatus.FAILED.value,
                ),
            )
            self._conn.commit()

    @contextmanager
    def exclusive_lock(self, operation_id: str, timeout_s: float = 30.0) -> Iterator[None]:
        """File lock — prevents concurrent install/update on one pole."""
        deadline = time.time() + timeout_s
        handle = None
        while time.time() < deadline:
            try:
                handle = self.lock_path.open("w")
                handle.write("{}\n".format(operation_id))
                handle.flush()
                import fcntl

                fcntl.flock(handle.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)
                break
            except (OSError, BlockingIOError):
                if handle:
                    handle.close()
                    handle = None
                time.sleep(0.5)
        else:
            raise TimeoutError("Another deploy operation holds the lock")
        try:
            yield
        finally:
            if handle:
                import fcntl

                fcntl.flock(handle.fileno(), fcntl.LOCK_UN)
                handle.close()
            try:
                self.lock_path.unlink(missing_ok=True)
            except OSError:
                pass
