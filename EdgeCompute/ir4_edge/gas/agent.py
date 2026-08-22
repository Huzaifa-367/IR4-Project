"""IR4 gas ingest agent — YT-98H Modbus → ``POST /api/ingest/gas-readings``.

Runs as systemd unit ``ir4-gas-agent``. Each cycle:

    1. Poll all Modbus addresses in gas.yaml (default 1–5).
    2. Map readings to ingest fields (h2s_ppm, co_ppm, …).
    3. POST one combined event to the pole SCC (``IR4_BASE_URL``).
    4. Buffer events locally when the SCC is unreachable.

Heartbeats run on a separate timer so the SCC knows the agent is alive even
between successful gas reads.
"""

from __future__ import annotations

import argparse
import logging
import signal
import sys
import time
from pathlib import Path
from typing import Any, Dict, List, Optional

from ir4_edge.common.buffer import OutageBuffer
from ir4_edge.common.client import Ir4Client
from ir4_edge.common.config import (
    default_gas_config,
    load_agent_config,
    require_ir4,
    resolve_buffer_path,
)
from ir4_edge.common.heartbeat import HeartbeatLoop
from ir4_edge.common.logging_setup import setup_logging
from ir4_edge.common.timeutil import new_event_uid, now_iso
from ir4_edge.gas import yt98h

log = logging.getLogger("ir4_edge.gas")

# After this many empty polls, report ``modbus_silence`` on the heartbeat.
_DEGRADED_AFTER_EMPTY_POLLS = 6


def build_gas_event(
    fields: Dict[str, float],
    device_ref: Optional[str],
) -> Dict[str, Any]:
    """Build one ingest payload from the latest field values."""
    event: Dict[str, Any] = {
        "event_uid": new_event_uid(),
        "recorded_at": now_iso(),
    }
    if device_ref:
        event["device_ref"] = device_ref
    event.update(fields)
    return event


def run_agent(config_path: Path, dry_run: bool = False) -> int:
    config = load_agent_config(
        config_path,
        token_env="IR4_GAS_DEVICE_TOKEN",
        uuid_env="IR4_GAS_DEVICE_UUID",
    )
    if dry_run:
        config.setdefault("ir4", {})["dry_run"] = True
    ir4 = require_ir4(config)

    serial_cfg = dict(config.get("serial") or {})
    port = serial_cfg.get("port") or ""
    baud = int(serial_cfg.get("baud", yt98h.BAUD))
    parity = str(serial_cfg.get("parity", yt98h.PARITY))
    stopbits = int(serial_cfg.get("stopbits", yt98h.STOPBITS))

    agent_cfg = dict(config.get("agent") or {})
    if agent_cfg.get("enabled") is False:
        log.info("agent.enabled=false in gas.yaml — exiting cleanly")
        return 0
    poll_interval = float(agent_cfg.get("poll_interval_seconds", 30))
    heartbeat_interval = float(agent_cfg.get("heartbeat_interval_seconds", 60))
    device_ref = agent_cfg.get("device_ref")
    buffer_path = resolve_buffer_path(
        agent_cfg.get("buffer_path"),
        "gas_buffer.sqlite",
    )

    addresses = [int(a) for a in (config.get("modbus_addresses") or [1, 2, 3, 4, 5])]
    raw_map = config.get("field_map") or yt98h.DEFAULT_FIELD_MAP
    field_map = {int(k): str(v) for k, v in dict(raw_map).items()}

    resolved_port = yt98h.autodetect_port(port or None)
    log.info("Opening serial %s %s 8%s%s", resolved_port, baud, parity, stopbits)
    ser = yt98h.open_port(resolved_port, baud, parity, stopbits)

    client = Ir4Client(
        base_url=ir4.get("base_url") or "",
        device_token=ir4.get("device_token") or "",
        device_uuid=ir4.get("device_uuid") or "",
        dry_run=bool(ir4.get("dry_run")),
        device_ref=str(device_ref or ""),
    )
    buffer = OutageBuffer(buffer_path, stream="gas_readings")
    stop = {"flag": False}

    def handle_signal(signum: int, _frame: object) -> None:
        log.info("Signal %s received; shutting down", signum)
        stop["flag"] = True

    signal.signal(signal.SIGINT, handle_signal)
    signal.signal(signal.SIGTERM, handle_signal)

    def build_heartbeat_meta() -> Dict[str, Any]:
        return {
            "agent": "ir4-gas-agent",
            "device_ref": device_ref,
            "serial_port": resolved_port,
            "pending_events": buffer.pending_count(),
            "poll_interval_seconds": poll_interval,
        }

    heartbeat = HeartbeatLoop(
        client,
        interval_seconds=heartbeat_interval,
        meta_provider=build_heartbeat_meta,
    )
    heartbeat.start()

    consecutive_failures = 0
    try:
        while not stop["flag"]:
            poll = yt98h.poll_channels(ser, addresses, baud)

            if not poll.has_data:
                consecutive_failures += 1
                summary = poll.failure_summary()
                if summary:
                    log.warning(
                        "No Modbus response (failures=%d): %s",
                        consecutive_failures,
                        summary,
                    )
                else:
                    log.warning("No Modbus response (failures=%d)", consecutive_failures)
                if consecutive_failures >= _DEGRADED_AFTER_EMPTY_POLLS:
                    client.heartbeat(
                        status="degraded",
                        meta={**build_heartbeat_meta(), "reason": "modbus_silence"},
                    )
                time.sleep(poll_interval)
                continue

            consecutive_failures = 0
            partial = poll.failure_summary()
            if partial:
                log.warning("Partial Modbus poll (some channels missing): %s", partial)
            fields = yt98h.channels_to_ingest_fields(poll.channels, field_map)
            if not fields:
                log.warning("Channels answered but field_map produced no values")
                time.sleep(poll_interval)
                continue

            event = build_gas_event(fields, device_ref)
            log.info(
                "Reading %s",
                " ".join("{}={}".format(k, fields[k]) for k in sorted(fields)),
            )
            result = buffer.submit(client, [event], Ir4Client.post_gas_readings)
            if result.rejected:
                log.warning("Rejected: %s", result.rejected)
            time.sleep(poll_interval)
    finally:
        heartbeat.stop()
        try:
            client.heartbeat(status="offline", meta=build_heartbeat_meta())
        except Exception:
            pass
        buffer.close()
        client.close()
        ser.close()
    return 0


def main(argv: Optional[List[str]] = None) -> None:
    parser = argparse.ArgumentParser(description="IR4 YT-98H gas ingest agent")
    parser.add_argument(
        "--config",
        type=Path,
        default=None,
        help="Path to gas.yaml (default: configs/gas.yaml)",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Log payloads only; do not POST to IR4",
    )
    parser.add_argument(
        "--log-level",
        default="INFO",
        help="DEBUG, INFO, WARNING, ERROR",
    )
    args = parser.parse_args(argv)
    setup_logging(args.log_level, "ir4_edge.gas")
    config_path = args.config or default_gas_config()
    try:
        sys.exit(run_agent(config_path, dry_run=args.dry_run))
    except Exception as exc:
        log.exception("Fatal: %s", exc)
        sys.exit(1)


if __name__ == "__main__":
    main()
