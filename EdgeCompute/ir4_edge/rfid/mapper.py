"""Map Zebra FXR90 / ZIOTC payloads (MQTT or WS-shaped JSON) to IR4 tag events.

Verified field names from Research/Edge/Zebra FXR90 Configuration (2026-08-10):
  idHex, antenna, peakRssi, timestamp — tag reads
  system / radio_control — reader heartbeats (ignored here; not ingest)
"""

from __future__ import annotations

from typing import Any, Dict, List, Mapping, Optional, Sequence

from ir4_edge.common.timeutil import epoch_ms_to_iso, new_event_uid


def iter_tag_objects(payload: object) -> List[Mapping[str, Any]]:
    """Normalize ZIOTC envelopes to a list of candidate tag dicts."""
    if isinstance(payload, list):
        return [item for item in payload if isinstance(item, Mapping)]
    if not isinstance(payload, Mapping):
        return []
    data = payload.get("data", payload)
    if isinstance(data, list):
        return [item for item in data if isinstance(item, Mapping)]
    if isinstance(data, Mapping):
        return [data]
    return [payload]


def extract_tag_fields(payload: Mapping[str, Any]) -> Optional[Dict[str, Any]]:
    """Return normalized tag fields or None if this event is not a tag read."""
    # Reader health heartbeats (ambient/pa temp, numTagReads) — not tags.
    if payload.get("idHex") is None and (
        "system" in payload or "radio_control" in payload
    ):
        return None
    data = payload.get("data", payload)
    if not isinstance(data, Mapping):
        data = payload
    epc = data.get("idHex") or data.get("epc") or data.get("tag_uid")
    if not epc:
        return None
    rssi = data.get("peakRssi")
    if rssi is None:
        rssi = data.get("rssi")
    ts = data.get("firstSeenTimestamp") or data.get("timestamp") or data.get("recorded_at")
    return {
        "tag_uid": str(epc).upper(),
        # Server TagReading stores rssi as int (TrackingService).
        "rssi": int(round(float(rssi))) if rssi is not None else None,
        "recorded_at": epoch_ms_to_iso(ts),
        "antenna": data.get("antenna"),
    }


def to_ingest_event(fields: Mapping[str, Any], reader_ref: str) -> Dict[str, Any]:
    event: Dict[str, Any] = {
        "event_uid": new_event_uid(),
        "reader_ref": reader_ref,
        "tag_uid": fields["tag_uid"],
        "recorded_at": fields["recorded_at"],
    }
    if fields.get("rssi") is not None:
        event["rssi"] = fields["rssi"]
    return event


def events_from_payload(
    payload: object,
    reader_ref: str,
) -> Sequence[Dict[str, Any]]:
    """Parse one MQTT/WS JSON payload into zero or more ingest events."""
    out: List[Dict[str, Any]] = []
    for item in iter_tag_objects(payload):
        fields = extract_tag_fields(item)
        if fields is None:
            continue
        out.append(to_ingest_event(fields, reader_ref))
    return out
