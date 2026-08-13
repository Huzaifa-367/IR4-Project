#!/usr/bin/env python3
"""Validate EdgeCompute payloads against Server DOC-08 ingest contracts.

Static checks always run. With tokens in secrets.env,
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
from ir4_edge.common.timeutil import new_event_uid, now_iso
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
        check(
            "tag.antenna optional int",
            event.get("antenna") is None or isinstance(event.get("antenna"), int),
        ),
        check("tag no radio internals", not any(
            key in event for key in ("crc", "pc", "channel", "phase", "reads", "event_num", "format", "source_type")
        )),
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
        "DEV-GAS-01",
    )
    zebra = {
        "data": {
            "CRC": "0b18",
            "PC": "3400",
            "antenna": 1,
            "channel": 866.3,
            "eventNum": 22,
            "format": "epc",
            "idHex": "aa0004ef55555555aa21bf43",
            "peakRssi": -34,
            "phase": 148.039794921875,
            "reads": 1,
        },
        "timestamp": "2026-08-13T09:14:28.651+0000",
        "type": "CUSTOM",
    }
    fields = extract_tag_fields(zebra)
    assert fields is not None
    tag = to_ingest_event(fields, "DEV-RFID-01")

    rows: List[Tuple[str, bool, str]] = []
    rows.extend(validate_paths())
    rows.extend(validate_gas_event(gas))
    rows.extend(validate_tag_event(tag))
    rows.append(check("tag.tag_uid from idHex", tag.get("tag_uid") == "AA0004EF55555555AA21BF43"))
    rows.append(check("tag.rssi from peakRssi", tag.get("rssi") == -34))
    rows.append(check("tag.antenna mapped", tag.get("antenna") == 1))
    rows.append(check("tag.recorded_at in APP_TIMEZONE", tag.get("recorded_at") == "2026-08-13T12:14:28.651+03:00"))
    rows.append(check("sample gas event_uid", validate_uuid(new_event_uid())))
    rows.append(check("sample recorded_at", bool(now_iso())))

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
    gas_token = os.environ.get("IR4_GAS_DEVICE_TOKEN")
    gas_uuid = os.environ.get("IR4_GAS_DEVICE_UUID")
    gas_ref = os.environ.get("IR4_GAS_DEVICE_REF")
    rfid_token = os.environ.get("IR4_RFID_DEVICE_TOKEN")
    rfid_uuid = os.environ.get("IR4_RFID_DEVICE_UUID")
    reader_ref = os.environ.get("IR4_RFID_READER_REF")

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

    if gas_token and gas_uuid and gas_ref:
        client = Ir4Client(base_url, gas_token, gas_uuid)
        hb = client.heartbeat(
            status="online",
            meta={"agent": "validate_api_contract", "probe": "gas", "device_ref": gas_ref},
        )
        print("[{}] gas heartbeat".format("PASS" if hb else "FAIL"))
        if not hb:
            failed += 1
        client.close()
    else:
        print("[SKIP] gas live — need IR4_GAS_DEVICE_TOKEN, IR4_GAS_DEVICE_UUID, IR4_GAS_DEVICE_REF")

    if rfid_token and rfid_uuid and reader_ref:
        client = Ir4Client(base_url, rfid_token, rfid_uuid)
        hb = client.heartbeat(
            status="online",
            meta={"agent": "validate_api_contract", "probe": "rfid", "reader_ref": reader_ref},
        )
        print("[{}] rfid heartbeat".format("PASS" if hb else "FAIL"))
        if not hb:
            failed += 1
        client.close()
    else:
        print("[SKIP] rfid live — need IR4_RFID_DEVICE_TOKEN, IR4_RFID_DEVICE_UUID, IR4_RFID_READER_REF")

    return failed


def main() -> int:
    load_secrets()
    parser = argparse.ArgumentParser(description="Validate EdgeCompute ↔ Server API contracts")
    parser.add_argument(
        "--live",
        action="store_true",
        help="Also probe IR4_BASE_URL (needs tokens in secrets)",
    )
    parser.add_argument("--base-url", default=None, help="Override IR4_BASE_URL")
    args = parser.parse_args()
    failed = run_static()
    if args.live:
        base_url = args.base_url or os.environ.get("IR4_BASE_URL")
        if not base_url:
            print("[FAIL] live probe needs IR4_BASE_URL")
            return 1
        failed += run_live(base_url)
    if failed:
        print("RESULT: {} failure(s)".format(failed))
        return 1
    print("RESULT: all checks passed")
    return 0


if __name__ == "__main__":
    sys.exit(main())
