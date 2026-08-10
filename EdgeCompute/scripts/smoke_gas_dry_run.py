#!/usr/bin/env python3
"""Smoke-test gas ingest mapping (dry-run by default)."""

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
from ir4_edge.common.timeutil import new_event_uid, utc_now_iso


def main() -> int:
    load_secrets()
    parser = argparse.ArgumentParser(description="Smoke POST one gas-readings batch")
    parser.add_argument(
        "--base-url",
        default=os.environ.get("IR4_BASE_URL", "http://192.168.3.149:9100"),
    )
    parser.add_argument(
        "--token",
        default=os.environ.get("IR4_GAS_DEVICE_TOKEN")
        or os.environ.get("IR4_DEVICE_TOKEN", ""),
    )
    parser.add_argument(
        "--uuid",
        default=os.environ.get("IR4_GAS_DEVICE_UUID")
        or os.environ.get("IR4_DEVICE_UUID", ""),
    )
    parser.add_argument("--device-ref", default="pole1-gas")
    parser.add_argument("--live", action="store_true", help="Actually POST (requires token)")
    args = parser.parse_args()

    event = {
        "event_uid": new_event_uid(),
        "device_ref": args.device_ref,
        "recorded_at": utc_now_iso(),
        "h2s_ppm": 0.0,
        "co_ppm": 0.0,
        "o2_pct": 20.9,
        "lel_pct": 0.0,
        "co2_ppm": 900.0,
    }
    print(json.dumps({"events": [event]}, indent=2))

    client = Ir4Client(
        base_url=args.base_url or "http://localhost",
        device_token=args.token,
        device_uuid=args.uuid,
        dry_run=not args.live,
    )
    result = client.post_gas_readings([event])
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
