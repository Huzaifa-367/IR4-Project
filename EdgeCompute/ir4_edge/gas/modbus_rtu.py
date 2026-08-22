"""Modbus RTU over RS-485 — low-level serial framing used by the gas agent.

This module knows about Modbus bytes and timing only. It does not know about
YT-98H gas types or IR4 ingest. Higher layers (``yt98h.py``, ``agent.py``) call
``transact()`` to read register blocks from each Modbus slave address.

Typical call chain on a pole Jetson:

    agent.py  →  yt98h.poll_channels()  →  modbus_rtu.transact()  →  RS-485 dongle

``ModbusOutcome`` values returned by ``transact()`` / ``parse_response()``:

    ok         Full, valid reply; payload is a list of 16-bit register values.
    silence    No bytes received (wrong wiring, wrong port, sensor off).
    noise      Bytes received but CRC/address/function did not match (often a
               truncated frame when the serial timeout is too short).
    exception  Sensor returned a Modbus error code (fc | 0x80).
"""

from __future__ import annotations

from typing import Any, List, Literal, Tuple

import serial

ModbusOutcome = Literal["ok", "silence", "noise", "exception"]

# Some USB-UART chips (e.g. CH341) deliver data in bursts with long gaps.
# Never use a fixed ~15 ms gap timeout for multi-byte Modbus replies.
_MIN_INTER_BYTE_TIMEOUT_S = 0.15


def char_time_seconds(baud: int) -> float:
    """Seconds to send one 8N1 byte (10 bits plus start/stop guard)."""
    return 11.0 / max(int(baud), 1)


def fc03_response_byte_count(register_count: int) -> int:
    """Byte length of an FC03 read-holding-registers reply (including CRC)."""
    count = max(int(register_count), 1)
    # [slave][fc][byte_count][data × 2 bytes per register][crc_lo][crc_hi]
    return 3 + (2 * count) + 2


def inter_byte_timeout(baud: int, register_count: int) -> float:
    """How long to wait for the next byte while assembling one RTU reply.

    Scales with register count so a 32-register block (~69 bytes at 9600 baud)
    has enough time to arrive on slow USB-serial bridges.
    """
    char_time = char_time_seconds(baud)
    wire_time = fc03_response_byte_count(register_count) * char_time
    return max(_MIN_INTER_BYTE_TIMEOUT_S, wire_time + 0.05)


def crc16(data: bytes) -> int:
    """Modbus RTU CRC-16 (polynomial 0xA001, init 0xFFFF)."""
    crc = 0xFFFF
    for byte in data:
        crc ^= byte
        for _ in range(8):
            crc = (crc >> 1) ^ 0xA001 if crc & 1 else crc >> 1
    return crc


def crc_ok(frame: bytes) -> bool:
    """Return True when the trailing CRC matches the frame body."""
    if len(frame) < 4:
        return False
    calc = crc16(frame[:-2])
    return frame[-2:] == bytes([calc & 0xFF, (calc >> 8) & 0xFF])


def build_request(addr: int, fc: int, start: int, count: int) -> bytes:
    """Build one Modbus RTU request frame (address, function, start, count, CRC)."""
    body = bytes([addr, fc, start >> 8, start & 0xFF, count >> 8, count & 0xFF])
    crc = crc16(body)
    return body + bytes([crc & 0xFF, (crc >> 8) & 0xFF])


def parse_response(raw: bytes, addr: int, fc: int) -> Tuple[ModbusOutcome, Any]:
    """Interpret bytes read from the serial port after one ``transact()`` call."""
    if not raw:
        return "silence", None

    # RS-485 echo or line noise can shift the frame — try a few byte offsets.
    for off in range(min(12, len(raw))):
        chunk = raw[off:]
        if len(chunk) < 5 or chunk[0] != addr:
            continue

        # Modbus exception response: [addr][fc|0x80][exception_code][crc]
        if chunk[1] == (fc | 0x80) and crc_ok(chunk[:5]):
            return "exception", chunk[2]

        if chunk[1] != fc:
            continue

        count_field = chunk[2]
        # Some devices report byte count; others use register count in that field.
        for datalen in (count_field, 2 * count_field):
            need = 3 + datalen + 2
            if datalen and len(chunk) >= need and crc_ok(chunk[:need]):
                data = chunk[3 : 3 + datalen]
                registers: List[int] = [
                    (data[i] << 8) | data[i + 1] for i in range(0, len(data) - 1, 2)
                ]
                return "ok", registers

    return "noise", raw


def transact(
    ser: serial.Serial,
    addr: int,
    fc: int,
    start: int,
    count: int,
    baud: int,
) -> Tuple[ModbusOutcome, Any]:
    """Send one Modbus RTU request and read back the full reply frame.

    Uses a two-step read: wait for the first byte, then read the rest with a
    timeout sized to the expected reply length.
    """
    req = build_request(addr, fc, start, count)
    ser.reset_input_buffer()
    ser.reset_output_buffer()
    ser.write(req)
    ser.flush()

    char_time = char_time_seconds(baud)
    # Phase 1 — wait for the sensor to start replying.
    ser.timeout = max(0.08, 40 * char_time + 0.05)
    buf = ser.read(1)

    if buf:
        # Phase 2 — collect the remainder of the frame (timeout scales with count).
        ser.timeout = inter_byte_timeout(baud, count)
        while len(buf) < 512:
            more = ser.read(128)
            if not more:
                break
            buf += more

    return parse_response(buf, addr, fc)
