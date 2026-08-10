"""Map Zebra FXR90 / ZIOTC MQTT payloads to IR4 tag-reading events."""

from __future__ import annotations

from typing import Any, Dict, Mapping, Optional

from ir4_edge.common.timeutil import epoch_ms_to_iso, new_event_uid


def extract_tag_fields(payload: Mapping[str, Any]) -> Optional[Dict[str, Any]]:
    """Return normalized tag fields or None if EPC is missing."""
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
