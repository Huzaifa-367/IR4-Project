#!/usr/bin/env python3
"""Validate EdgeCompute payloads against Server DOC-08 ingest contracts.

Static checks always run. With tokens in secrets.env / secrets.local.env,
optional --live probes the SCC at IR4_BASE_URL.
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
from pathlib import Path
from typing import Any, Dict, List, Tuple
from uuid import UUID

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))

from ir4_edge.common.client import Ir4Client
from ir4_edge.common.config import load_secrets
from ir4_edge.common.timeutil import new_event_uid, utc_now_iso
from ir4_edge.gas.agent import build_gas_event
from ir4_edge.rfid.mapper import extract_tag_fields, to_ingest_event

UUID_RE = re.compile(
    r"^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$"
)


def check(name: str, ok: bool, detail: str = "") -> Tuple[str, bool, str]:
    return name, ok, detail


def validate_uuid(value: Any) -> bool:
    if not isinstance(value, str) or not UUID_RE.match(value):
        return False
    try:
        UUID(value)
        return True
    except ValueError:
        return False


def validate_gas_event(event: Dict[str, Any]) -> List[Tuple[str, bool, str]]:
    channels = ("lel_pct", "h2s_ppm", "o2_pct", "co_ppm", "co2_ppm")
    present = [k for k in channels if event.get(k) is not None]
    checks = [
        check("gas.event_uid uuid", validate_uuid(event.get("event_uid"))),
        check("gas.recorded_at present", isinstance(event.get("recorded_at"), str) and bool(event.get("recorded_at"))),
        check(
            "gas.device_ref optional string",
            event.get("device_ref") is None or isinstance(event.get("device_ref"), str),
        ),
        check("gas ≥1 channel", len(present) >= 1, ",".join(present) or "none"),
    ]
    for key in present:
        checks.append(check("gas.{} numeric".format(key), isinstance(event[key], (int, float))))
    return checks


def validate_tag_event(event: Dict[str, Any]) -> List[Tuple[str, bool, str]]:
    checks = [
        check("tag.event_uid uuid", validate_uuid(event.get("event_uid"))),
        check("tag.reader_ref required", isinstance(event.get("reader_ref"), str) and bool(event.get("reader_ref"))),
        check("tag.tag_uid required", isinstance(event.get("tag_uid"), str) and bool(event.get("tag_uid"))),
        check("tag.tag_uid upper", str(event.get("tag_uid", "")).upper() == event.get("tag_uid")),
        check("tag.recorded_at present", isinstance(event.get("recorded_at"), str) and bool(event.get("recorded_at"))),
        check(
            "tag.rssi optional numeric",
            event.get("rssi") is None or isinstance(event.get("rssi"), (int, float)),
        ),
        check("tag no antenna field", "antenna" not in event),
    ]
    return checks


def validate_paths() -> List[Tuple[str, bool, str]]:
    return [
        check("POST /api/ingest/gas-readings", True, "Ir4Client.post_gas_readings"),
        check("POST /api/ingest/tag-readings", True, "Ir4Client.post_tag_readings"),
        check("POST /api/devices/{uuid}/heartbeat", True, "Ir4Client.heartbeat"),
        check("Header X-Device-Token", True, "AuthenticateDevice"),
        check("Envelope {events:[…]}", True, "IngestBatchRequest"),
        check("Expect ingest 202 + accepted/duplicates/rejected", True, "ApiResponse::accepted"),
        check("Expect heartbeat 200 + data.*", True, "ApiResponse::ok"),
        check("Heartbeat status enum", True, "online|offline|degraded|fault|maintenance"),
    ]


def run_static() -> int:
    gas = build_gas_event(
        {
            "h2s_ppm": 0.0,
            "co_ppm": 0.0,
            "o2_pct": 20.9,
            "lel_pct": 0.0,
            "co2_ppm": 900.0,
        },
        "pole1-gas",
    )
    zebra = {
        "data": {
            "idHex": "e280116060000203abc12345",
            "antenna": 1,
            "peakRssi": -62.4,
            "firstSeenTimestamp": 1723283101000,
        }
    }
    fields = extract_tag_fields(zebra)
    assert fields is not None
    tag = to_ingest_event(fields, "pole1-reader")

    rows: List[Tuple[str, bool, str]] = []
    rows.extend(validate_paths())
    rows.extend(validate_gas_event(gas))
    rows.extend(validate_tag_event(tag))
    rows.append(check("sample gas event_uid", validate_uuid(new_event_uid())))
    rows.append(check("sample recorded_at", bool(utc_now_iso())))

    print("== Static contract check (EdgeCompute ↔ Server FormRequests) ==")
    print(json.dumps({"gas_sample": gas, "tag_sample": tag}, indent=2))
    print()
    failed = 0
    for name, ok, detail in rows:
        mark = "PASS" if ok else "FAIL"
        if not ok:
            failed += 1
        suffix = " — {}".format(detail) if detail else ""
        print("[{}] {}{}".format(mark, name, suffix))
    print()
    return failed


def run_live(base_url: str) -> int:
    load_secrets()
    gas_token = os.environ.get("IR4_GAS_DEVICE_TOKEN") or os.environ.get("IR4_DEVICE_TOKEN") or ""
    gas_uuid = os.environ.get("IR4_GAS_DEVICE_UUID") or os.environ.get("IR4_DEVICE_UUID") or ""
    rfid_token = os.environ.get("IR4_RFID_DEVICE_TOKEN") or os.environ.get("IR4_DEVICE_TOKEN") or ""
    rfid_uuid = os.environ.get("IR4_RFID_DEVICE_UUID") or os.environ.get("IR4_DEVICE_UUID") or ""
    device_ref = os.environ.get("IR4_GAS_DEVICE_REF", "pole1-gas")
    reader_ref = os.environ.get("IR4_RFID_READER_REF", "pole1-reader")
    tag_uid = os.environ.get("IR4_SMOKE_TAG_UID", "").upper()

    failed = 0
    print("== Live probe {} ==".format(base_url))

    # Health is public.
    try:
        import httpx

        health = httpx.get("{}/api/health".format(base_url.rstrip("/")), timeout=10.0)
        ok = health.status_code == 200
        print("[{}] GET /api/health -> {}".format("PASS" if ok else "FAIL", health.status_code))
        if not ok:
            failed += 1
    except Exception as exc:
        print("[FAIL] GET /api/health — {}".format(exc))
        return failed + 1

    if gas_token and gas_uuid:
        client = Ir4Client(base_url, gas_token, gas_uuid)
        hb = client.heartbeat(status="online", meta={"agent": "validate_api_contract", "probe": "gas"})
        print("[{}] gas heartbeat".format("PASS" if hb else "FAIL"))
        if not hb:
            failed += 1
        event = build_gas_event(
            {"o2_pct": 20.9, "co2_ppm": 900.0, "h2s_ppm": 0.0, "co_ppm": 0.0, "lel_pct": 0.0},
            device_ref,
        )
        result = client.post_gas_readings([event])
        ok = result.status_code == 202
        print(
            "[{}] gas ingest status={} accepted={} rejected={}".format(
                "PASS" if ok else "FAIL",
                result.status_code,
                result.accepted,
                result.rejected,
            )
        )
        if not ok:
            failed += 1
            if result.error:
                print("      error:", result.error[:300])
        client.close()
    else:
        print("[SKIP] gas live — set IR4_GAS_DEVICE_TOKEN + IR4_GAS_DEVICE_UUID")

    if rfid_token and rfid_uuid:
        client = Ir4Client(base_url, rfid_token, rfid_uuid)
        hb = client.heartbeat(status="online", meta={"agent": "validate_api_contract", "probe": "rfid"})
        print("[{}] rfid heartbeat".format("PASS" if hb else "FAIL"))
        if not hb:
            failed += 1
        if tag_uid:
            fields = {
                "tag_uid": tag_uid,
                "rssi": -60,
                "recorded_at": utc_now_iso(),
            }
            event = to_ingest_event(fields, reader_ref)
            result = client.post_tag_readings([event])
            ok = result.status_code == 202
            print(
                "[{}] tag ingest status={} accepted={} rejected={}".format(
                    "PASS" if ok else "FAIL",
                    result.status_code,
                    result.accepted,
                    result.rejected,
                )
            )
            if result.rejected:
                print("      note: UNKNOWN_TAG means EPC not in rfid_tags (still valid HTTP 202)")
            if not ok:
                failed += 1
                if result.error:
                    print("      error:", result.error[:300])
        else:
            print("[SKIP] tag ingest — set IR4_SMOKE_TAG_UID to a registered EPC")
        client.close()
    else:
        print("[SKIP] rfid live — set IR4_RFID_DEVICE_TOKEN + IR4_RFID_DEVICE_UUID")

    return failed


def main() -> int:
    load_secrets()
    parser = argparse.ArgumentParser(description="Validate EdgeCompute ↔ Server API contracts")
    parser.add_argument(
        "--live",
        action="store_true",
        help="Also probe IR4_BASE_URL (needs tokens in secrets)",
    )
    parser.add_argument(
        "--base-url",
        default=os.environ.get("IR4_BASE_URL", "http://192.168.3.149:9100"),
    )
    args = parser.parse_args()
    failed = run_static()
    if args.live:
        failed += run_live(args.base_url)
    if failed:
        print("RESULT: {} failure(s)".format(failed))
        return 1
    print("RESULT: all checks passed")
    return 0


if __name__ == "__main__":
    sys.exit(main())
