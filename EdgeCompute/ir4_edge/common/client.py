"""HTTP client for IR4 device ingest and heartbeats (DOC-08)."""

from __future__ import annotations

import logging
import time
from dataclasses import dataclass, field
from typing import Any, Dict, List, Mapping, Optional, Sequence

import httpx

log = logging.getLogger("ir4_edge.client")


@dataclass
class IngestResult:
    accepted: int = 0
    duplicates: int = 0
    rejected: List[Dict[str, Any]] = field(default_factory=list)
    status_code: int = 0
    retriable: bool = False
    error: Optional[str] = None


class Ir4Client:
    """Authenticated device-surface client for batch ingest + heartbeat."""

    def __init__(
        self,
        base_url: str,
        device_token: str,
        device_uuid: str,
        *,
        timeout_seconds: float = 30.0,
        dry_run: bool = False,
    ) -> None:
        self.base_url = base_url.rstrip("/")
        self.device_token = device_token
        self.device_uuid = device_uuid
        self.timeout_seconds = timeout_seconds
        self.dry_run = dry_run
        self._client = httpx.Client(
            base_url=self.base_url,
            headers={
                "X-Device-Token": self.device_token,
                "Content-Type": "application/json",
                "Accept": "application/json",
            },
            timeout=timeout_seconds,
        )

    def close(self) -> None:
        self._client.close()

    def __enter__(self) -> "Ir4Client":
        return self

    def __exit__(self, *args: object) -> None:
        self.close()

    def post_ingest(self, path: str, events: Sequence[Mapping[str, Any]]) -> IngestResult:
        if not events:
            return IngestResult(status_code=0)
        body = {"events": list(events)}
        if self.dry_run:
            log.info("DRY-RUN POST %s events=%d sample=%s", path, len(events), events[0])
            return IngestResult(accepted=len(events), status_code=202)

        url = path if path.startswith("/") else f"/{path}"
        attempt = 0
        while True:
            attempt += 1
            try:
                response = self._client.post(url, json=body)
            except httpx.HTTPError as exc:
                log.warning("Ingest transport error %s: %s", path, exc)
                return IngestResult(retriable=True, error=str(exc))

            if response.status_code == 429:
                retry_after = float(response.headers.get("Retry-After", "5"))
                if attempt >= 5:
                    return IngestResult(
                        status_code=429,
                        retriable=True,
                        error="RATE_LIMITED",
                    )
                log.warning("Rate limited; sleeping %.1fs", retry_after)
                time.sleep(max(1.0, retry_after))
                continue

            if response.status_code >= 500:
                return IngestResult(
                    status_code=response.status_code,
                    retriable=True,
                    error=response.text[:500],
                )

            if response.status_code not in (200, 202):
                return IngestResult(
                    status_code=response.status_code,
                    retriable=False,
                    error=response.text[:500],
                )

            try:
                payload = response.json()
            except ValueError:
                payload = {}
            return IngestResult(
                accepted=int(payload.get("accepted", 0)),
                duplicates=int(payload.get("duplicates", 0)),
                rejected=list(payload.get("rejected") or []),
                status_code=response.status_code,
            )

    def post_tag_readings(self, events: Sequence[Mapping[str, Any]]) -> IngestResult:
        return self.post_ingest("/api/ingest/tag-readings", events)

    def post_gas_readings(self, events: Sequence[Mapping[str, Any]]) -> IngestResult:
        return self.post_ingest("/api/ingest/gas-readings", events)

    def heartbeat(
        self,
        status: str = "online",
        meta: Optional[Dict[str, Any]] = None,
    ) -> bool:
        body: Dict[str, Any] = {"status": status}
        if meta is not None:
            body["meta"] = meta
        if self.dry_run:
            log.info("DRY-RUN heartbeat status=%s meta=%s", status, meta)
            return True
        path = f"/api/devices/{self.device_uuid}/heartbeat"
        try:
            response = self._client.post(path, json=body)
        except httpx.HTTPError as exc:
            log.warning("Heartbeat transport error: %s", exc)
            return False
        if response.status_code != 200:
            log.warning(
                "Heartbeat failed status=%s body=%s",
                response.status_code,
                response.text[:300],
            )
            return False
        return True
