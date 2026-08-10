#!/usr/bin/env python3
"""Smoke-test RFID tag ingest mapping (dry-run by default)."""

from __future__ import annotations

import argparse
import json
import os
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))

from ir4_edge.common.client import Ir4Client
from ir4_edge.common.config import load_secrets
from ir4_edge.rfid.mapper import extract_tag_fields, to_ingest_event


def main() -> int:
    load_secrets()
    parser = argparse.ArgumentParser(description="Smoke POST one tag-readings batch")
    parser.add_argument(
        "--base-url",
        default=os.environ.get("IR4_BASE_URL", "http://192.168.3.149:9100"),
    )
    parser.add_argument(
        "--token",
        default=os.environ.get("IR4_RFID_DEVICE_TOKEN")
        or os.environ.get("IR4_DEVICE_TOKEN", ""),
    )
    parser.add_argument(
        "--uuid",
        default=os.environ.get("IR4_RFID_DEVICE_UUID")
        or os.environ.get("IR4_DEVICE_UUID", ""),
    )
    parser.add_argument("--reader-ref", default="pole1-reader")
    parser.add_argument("--live", action="store_true", help="Actually POST (requires token)")
    args = parser.parse_args()

    zebra = {
        "data": {
            "idHex": "E280116060000203ABC12345",
            "antenna": 1,
            "peakRssi": -62,
            "firstSeenTimestamp": 1723283101000,
        }
    }
    fields = extract_tag_fields(zebra)
    assert fields is not None
    event = to_ingest_event(fields, args.reader_ref)
    print(json.dumps({"events": [event]}, indent=2))

    client = Ir4Client(
        base_url=args.base_url or "http://localhost",
        device_token=args.token,
        device_uuid=args.uuid,
        dry_run=not args.live,
    )
    result = client.post_tag_readings([event])
    print(
        "result status={} accepted={} duplicates={} rejected={} error={}".format(
            result.status_code,
            result.accepted,
            result.duplicates,
            result.rejected,
            result.error,
        )
    )
    client.close()
    return 0 if not result.error else 1


if __name__ == "__main__":
    sys.exit(main())
