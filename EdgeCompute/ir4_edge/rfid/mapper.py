"""Map FXR90 MQTT JSON to IR4 /api/ingest/tag-readings events.

Primary shape (ZIOTC Tag Data Interface):

  {"data":{"idHex","peakRssi","antenna",...},"timestamp":"...","type":"CUSTOM"}

Also accepts tag reads without ``type=CUSTOM`` when ``idHex`` is present, and
skips management/health envelopes (``system`` / ``radio_control``) that share
the MQTT topic but are not tag reads.
"""

from __future__ import annotations

import logging
from typing import Any, Dict, List, Mapping, Optional, Sequence

from ir4_edge.common.timeutil import new_event_uid, to_iso

log = logging.getLogger("ir4_edge.rfid.mapper")

_NON_TAG_TYPES = frozenset(
    {
        "heartbeat",
        "status",
        "management",
        "health",
        "radio_control",
        "system",
    }
)


def _is_health_envelope(payload: Mapping[str, Any]) -> bool:
    if payload.get("system") or payload.get("radio_control"):
        return True
    msg_type = str(payload.get("type") or "").lower()
    return msg_type in _NON_TAG_TYPES


def extract_tag_fields(payload: Mapping[str, Any]) -> Optional[Dict[str, Any]]:
    if _is_health_envelope(payload):
        return None
    data = payload.get("data")
    if not isinstance(data, Mapping):
        data = payload
    epc = data.get("idHex") or data.get("epc")
    if not isinstance(epc, str) or not epc.strip():
        return None
    timestamp = payload.get("timestamp")
    if not isinstance(timestamp, str):
        timestamp = data.get("firstSeenTimestamp") or data.get("timestamp")
    if not isinstance(timestamp, str):
        return None
    recorded_at = to_iso(timestamp)
    if recorded_at is None:
        log.warning("Tag read skipped — unparseable timestamp: %r", timestamp[:80])
        return None
    fields: Dict[str, Any] = {
        "tag_uid": epc.strip().upper(),
        "recorded_at": recorded_at,
    }
    rssi = data.get("peakRssi")
    if rssi is None:
        rssi = data.get("rssi")
    if rssi is not None:
        fields["rssi"] = int(round(float(rssi)))
    antenna = data.get("antenna")
    if antenna is not None:
        fields["antenna"] = int(antenna)
    return fields


def to_ingest_event(fields: Mapping[str, Any], reader_ref: str) -> Dict[str, Any]:
    event: Dict[str, Any] = {
        "event_uid": new_event_uid(),
        "reader_ref": reader_ref,
        "tag_uid": fields["tag_uid"],
        "recorded_at": fields["recorded_at"],
    }
    if "rssi" in fields:
        event["rssi"] = fields["rssi"]
    if "antenna" in fields:
        event["antenna"] = fields["antenna"]
    return event


def events_from_payload(payload: object, reader_ref: str) -> Sequence[Dict[str, Any]]:
    if isinstance(payload, Mapping):
        envelopes: List[Mapping[str, Any]] = [payload]
    elif isinstance(payload, list):
        envelopes = [item for item in payload if isinstance(item, Mapping)]
    else:
        return []
    events: List[Dict[str, Any]] = []
    for envelope in envelopes:
        fields = extract_tag_fields(envelope)
        if fields is not None:
            events.append(to_ingest_event(fields, reader_ref))
    return events
