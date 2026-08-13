"""Map FXR90 CUSTOM MQTT JSON to IR4 /api/ingest/tag-readings events.

Topic zebra/fxr90-NN/tags publishes only this shape:

  {"data":{"CRC","PC","antenna","channel","eventNum","format",
           "idHex","peakRssi","phase","reads"},
   "timestamp":"...","type":"CUSTOM"}

Ingest keeps idHex, timestamp, peakRssi, antenna.
Missing live fields are omitted — nothing is invented.
"""

from __future__ import annotations

from typing import Any, Dict, List, Mapping, Optional, Sequence

from ir4_edge.common.timeutil import new_event_uid, to_iso


def extract_tag_fields(payload: Mapping[str, Any]) -> Optional[Dict[str, Any]]:
    if payload.get("type") != "CUSTOM":
        return None
    data = payload.get("data")
    if not isinstance(data, Mapping):
        return None
    epc = data.get("idHex")
    timestamp = payload.get("timestamp")
    if not isinstance(epc, str) or not epc.strip():
        return None
    if not isinstance(timestamp, str):
        return None
    recorded_at = to_iso(timestamp)
    if recorded_at is None:
        return None
    fields: Dict[str, Any] = {
        "tag_uid": epc.strip().upper(),
        "recorded_at": recorded_at,
    }
    rssi = data.get("peakRssi")
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
