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


def _summarize_events(events: Sequence[Mapping[str, Any]], *, limit: int = 5) -> List[Dict[str, Any]]:
    """Compact event view for journals (no tokens)."""
    sample: List[Dict[str, Any]] = []
    for event in list(events)[:limit]:
        row: Dict[str, Any] = {}
        for key in (
            "device_ref",
            "reader_ref",
            "tag_uid",
            "recorded_at",
            "lel_pct",
            "h2s_ppm",
            "o2_pct",
            "co_ppm",
            "co2_ppm",
            "rssi",
            "antenna",
            "event_uid",
        ):
            if key in event:
                row[key] = event[key]
        sample.append(row or dict(event))
    return sample


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
        device_ref: str = "",
    ) -> None:
        self.base_url = base_url.rstrip("/")
        self.device_token = device_token
        self.device_uuid = device_uuid
        self.device_ref = device_ref
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
        sample = _summarize_events(events)
        extra = "" if len(events) <= 5 else " …+{}".format(len(events) - 5)
        prefix = "DRY-RUN " if self.dry_run else ""
        log.info(
            "%sPOST %s device_ref=%s uuid=%s events=%d payload=%s%s",
            prefix,
            path,
            self.device_ref or "-",
            self.device_uuid or "-",
            len(events),
            sample,
            extra,
        )
        if self.dry_run:
            return IngestResult(accepted=len(events), status_code=202)

        url = path if path.startswith("/") else f"/{path}"
        attempt = 0
        while True:
            attempt += 1
            try:
                response = self._client.post(url, json=body)
            except httpx.HTTPError as exc:
                log.warning(
                    "Ingest transport error %s device_ref=%s: %s",
                    path,
                    self.device_ref or "-",
                    exc,
                )
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
            result = IngestResult(
                accepted=int(payload.get("accepted", 0)),
                duplicates=int(payload.get("duplicates", 0)),
                rejected=list(payload.get("rejected") or []),
                status_code=response.status_code,
            )
            log.info(
                "Ingest OK %s device_ref=%s status=%s accepted=%d duplicates=%d rejected=%d",
                path,
                self.device_ref or "-",
                result.status_code,
                result.accepted,
                result.duplicates,
                len(result.rejected),
            )
            return result

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
        path = f"/api/devices/{self.device_uuid}/heartbeat"
        prefix = "DRY-RUN " if self.dry_run else ""
        log.info(
            "%sHeartbeat POST %s device_ref=%s uuid=%s status=%s meta=%s",
            prefix,
            path,
            self.device_ref or "-",
            self.device_uuid or "-",
            status,
            meta or {},
        )
        if self.dry_run:
            return True
        try:
            response = self._client.post(path, json=body)
        except httpx.HTTPError as exc:
            log.warning(
                "Heartbeat transport error device_ref=%s: %s",
                self.device_ref or "-",
                exc,
            )
            return False
        if response.status_code != 200:
            log.warning(
                "Heartbeat failed device_ref=%s status=%s body=%s",
                self.device_ref or "-",
                response.status_code,
                response.text[:300],
            )
            return False
        log.info(
            "Heartbeat OK device_ref=%s uuid=%s status=%s",
            self.device_ref or "-",
            self.device_uuid or "-",
            status,
        )
        return True
