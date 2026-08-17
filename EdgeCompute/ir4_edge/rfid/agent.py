"""FXR90 MQTT → IR4 /api/ingest/tag-readings agent."""

from __future__ import annotations

import argparse
import json
import logging
import signal
import sys
import threading
import time
from collections import deque
from pathlib import Path
from typing import Any, Deque, Dict, List, Optional

import paho.mqtt.client as mqtt

from ir4_edge.common.buffer import OutageBuffer
from ir4_edge.common.client import Ir4Client
from ir4_edge.common.config import (
    default_rfid_config,
    load_agent_config,
    require_ir4,
    resolve_buffer_path,
)
from ir4_edge.common.heartbeat import HeartbeatLoop
from ir4_edge.common.logging_setup import setup_logging
from ir4_edge.rfid.mapper import events_from_payload

log = logging.getLogger("ir4_edge.rfid")


class TagBatcher:
    """Debounce EPC repeats and flush batches on size or interval."""

    def __init__(
        self,
        *,
        debounce_seconds: float,
        max_batch: int,
        flush_interval_seconds: float,
    ) -> None:
        self.debounce_seconds = debounce_seconds
        self.max_batch = max_batch
        self.flush_interval_seconds = flush_interval_seconds
        self._lock = threading.Lock()
        self._queue: Deque[Dict[str, Any]] = deque()
        self._last_seen: Dict[str, float] = {}
        self._last_flush = time.monotonic()

    def offer(self, event: Dict[str, Any]) -> List[Dict[str, Any]]:
        """Enqueue event unless debounced; return a batch ready to send (may be empty)."""
        now = time.monotonic()
        tag_uid = str(event.get("tag_uid") or "")
        with self._lock:
            last = self._last_seen.get(tag_uid, 0.0)
            if tag_uid and (now - last) < self.debounce_seconds:
                return []
            if tag_uid:
                self._last_seen[tag_uid] = now
            self._queue.append(event)
            return self._maybe_flush_locked(now, force=False)

    def flush_due(self) -> List[Dict[str, Any]]:
        with self._lock:
            return self._maybe_flush_locked(time.monotonic(), force=False)

    def flush_all(self) -> List[Dict[str, Any]]:
        with self._lock:
            return self._maybe_flush_locked(time.monotonic(), force=True)

    def _maybe_flush_locked(self, now: float, force: bool) -> List[Dict[str, Any]]:
        due = force or len(self._queue) >= self.max_batch
        due = due or (
            self._queue and (now - self._last_flush) >= self.flush_interval_seconds
        )
        if not due:
            return []
        batch = list(self._queue)
        self._queue.clear()
        self._last_flush = now
        # Cap at 1000 for DOC-08.
        if len(batch) > 1000:
            rest = batch[1000:]
            batch = batch[:1000]
            self._queue.extendleft(reversed(rest))
        return batch


class RfidAgentState:
    def __init__(self) -> None:
        self.mqtt_connected = False
        self.messages = 0
        self.ingested = 0


def run_agent(config_path: Path, dry_run: bool = False) -> int:
    config = load_agent_config(
        config_path,
        token_env="IR4_RFID_DEVICE_TOKEN",
        uuid_env="IR4_RFID_DEVICE_UUID",
    )
    if dry_run:
        config.setdefault("ir4", {})["dry_run"] = True
    ir4 = require_ir4(config)

    agent_cfg = dict(config.get("agent") or {})
    if agent_cfg.get("enabled") is False:
        log.info("agent.enabled=false in rfid.yaml — exiting cleanly")
        return 0
    reader_ref = str(agent_cfg.get("reader_ref") or "").strip()
    if not reader_ref:
        raise ValueError("agent.reader_ref is required")
    debounce = float(agent_cfg.get("debounce_seconds", 2.0))
    max_batch = int(agent_cfg.get("max_batch", 50))
    flush_interval = float(agent_cfg.get("flush_interval_seconds", 1.0))
    heartbeat_interval = float(agent_cfg.get("heartbeat_interval_seconds", 60))
    buffer_path = resolve_buffer_path(
        agent_cfg.get("buffer_path"),
        "rfid_buffer.sqlite",
    )
    log_raw = bool(agent_cfg.get("log_raw", False))

    mqtt_cfg = dict(config.get("mqtt") or {})
    broker = str(mqtt_cfg.get("broker", "localhost"))
    port = int(mqtt_cfg.get("port", 1883))
    topic = str(mqtt_cfg.get("topic") or "").strip()
    if not topic:
        raise ValueError("mqtt.topic is required")
    username = mqtt_cfg.get("username")
    password = mqtt_cfg.get("password")
    use_auth = bool(mqtt_cfg.get("use_auth", False))

    client = Ir4Client(
        base_url=ir4.get("base_url") or "",
        device_token=ir4.get("device_token") or "",
        device_uuid=ir4.get("device_uuid") or "",
        dry_run=bool(ir4.get("dry_run")),
        device_ref=reader_ref,
    )
    buffer = OutageBuffer(buffer_path, stream="tag_readings")
    batcher = TagBatcher(
        debounce_seconds=debounce,
        max_batch=max_batch,
        flush_interval_seconds=flush_interval,
    )
    state = RfidAgentState()
    stop = {"flag": False}

    def send_batch(events: List[Dict[str, Any]]) -> None:
        if not events:
            return
        result = buffer.submit(client, events, Ir4Client.post_tag_readings)
        state.ingested += result.accepted
        if result.rejected:
            log.warning("Rejected: %s", result.rejected[:5])

    def meta_provider() -> Dict[str, Any]:
        return {
            "agent": "ir4-rfid-agent",
            "mqtt_connected": state.mqtt_connected,
            "mqtt_topic": topic,
            "messages": state.messages,
            "pending_events": buffer.pending_count(),
            "reader_ref": reader_ref,
        }

    heartbeat = HeartbeatLoop(
        client,
        interval_seconds=heartbeat_interval,
        meta_provider=meta_provider,
    )

    def on_connect(
        mqtt_client: mqtt.Client,
        _userdata: object,
        _flags: object,
        reason_code: object,
        _properties: object = None,
    ) -> None:
        code = int(getattr(reason_code, "value", reason_code))
        if code == 0:
            state.mqtt_connected = True
            mqtt_client.subscribe(topic)
            log.info("MQTT connected; subscribed to %s", topic)
        else:
            state.mqtt_connected = False
            log.error("MQTT connect failed reason=%s", reason_code)

    def on_disconnect(
        _mqtt_client: mqtt.Client,
        _userdata: object,
        _flags: object,
        reason_code: object,
        _properties: object = None,
    ) -> None:
        state.mqtt_connected = False
        log.warning("MQTT disconnected reason=%s", reason_code)

    def on_message(
        _mqtt_client: mqtt.Client,
        _userdata: object,
        msg: mqtt.MQTTMessage,
    ) -> None:
        state.messages += 1
        raw = msg.payload.decode("utf-8", errors="replace")
        if log_raw:
            log.info("RAW [%s] %s", msg.topic, raw)
        try:
            payload = json.loads(raw)
        except json.JSONDecodeError:
            log.warning("Non-JSON MQTT payload on %s", msg.topic)
            return
        for event in events_from_payload(payload, reader_ref):
            log.debug(
                "TAG epc=%s rssi=%s",
                event["tag_uid"],
                event.get("rssi"),
            )
            ready = batcher.offer(event)
            send_batch(ready)

    mqtt_client = mqtt.Client(
        mqtt.CallbackAPIVersion.VERSION2,
        client_id="ir4-rfid-agent",
    )
    if use_auth and username:
        mqtt_client.username_pw_set(str(username), str(password) if password else None)
        log.info("MQTT auth enabled (user=%s)", username)
    else:
        log.info("MQTT anonymous mode (IR4_MQTT_USE_AUTH=0; credentials kept in secrets.env)")
    mqtt_client.on_connect = on_connect
    mqtt_client.on_disconnect = on_disconnect
    mqtt_client.on_message = on_message

    def _handle_signal(signum: int, _frame: object) -> None:
        log.info("Signal %s received; shutting down", signum)
        stop["flag"] = True

    signal.signal(signal.SIGINT, _handle_signal)
    signal.signal(signal.SIGTERM, _handle_signal)

    heartbeat.start()
    log.info("Connecting MQTT %s:%s topic=%s", broker, port, topic)
    mqtt_client.connect(broker, port, keepalive=60)
    mqtt_client.loop_start()

    try:
        while not stop["flag"]:
            send_batch(batcher.flush_due())
            time.sleep(0.2)
    finally:
        send_batch(batcher.flush_all())
        mqtt_client.loop_stop()
        mqtt_client.disconnect()
        heartbeat.stop()
        try:
            client.heartbeat(status="offline", meta=meta_provider())
        except Exception:
            pass
        buffer.close()
        client.close()
    return 0


def main(argv: Optional[List[str]] = None) -> None:
    parser = argparse.ArgumentParser(description="IR4 FXR90 RFID ingest agent")
    parser.add_argument(
        "--config",
        type=Path,
        default=None,
        help="Path to rfid.yaml (default: configs/rfid.yaml)",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Log payloads only; do not POST to IR4",
    )
    parser.add_argument("--log-level", default="INFO")
    args = parser.parse_args(argv)
    setup_logging(args.log_level, "ir4_edge.rfid")
    config_path = args.config or default_rfid_config()
    try:
        sys.exit(run_agent(config_path, dry_run=args.dry_run))
    except Exception as exc:
        log.exception("Fatal: %s", exc)
        sys.exit(1)


if __name__ == "__main__":
    main()
