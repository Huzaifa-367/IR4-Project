"""Periodic device heartbeat loop."""

from __future__ import annotations

import logging
import threading
import time
from typing import Any, Callable, Dict, Optional

from ir4_edge.common.client import Ir4Client

log = logging.getLogger("ir4_edge.heartbeat")


class HeartbeatLoop:
    """Call Ir4Client.heartbeat on an interval until stop()."""

    def __init__(
        self,
        client: Ir4Client,
        *,
        interval_seconds: float = 60.0,
        status: str = "online",
        meta_provider: Optional[Callable[[], Dict[str, Any]]] = None,
    ) -> None:
        self.client = client
        self.interval_seconds = interval_seconds
        self.status = status
        self.meta_provider = meta_provider
        self._stop = threading.Event()
        self._thread: Optional[threading.Thread] = None

    def start(self) -> None:
        if self._thread and self._thread.is_alive():
            return
        self._stop.clear()
        self._thread = threading.Thread(target=self._run, name="ir4-heartbeat", daemon=True)
        self._thread.start()

    def stop(self) -> None:
        self._stop.set()
        if self._thread:
            self._thread.join(timeout=5.0)

    def _run(self) -> None:
        while not self._stop.is_set():
            meta = self.meta_provider() if self.meta_provider else {}
            ok = self.client.heartbeat(status=self.status, meta=meta)
            if not ok:
                log.warning(
                    "Heartbeat unsuccessful device_ref=%s uuid=%s",
                    self.client.device_ref or "-",
                    self.client.device_uuid or "-",
                )
            self._stop.wait(self.interval_seconds)
