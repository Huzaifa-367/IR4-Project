"""YT-98H Modbus RTU driver (custom framing for Safegas-class transmitters)."""

from __future__ import annotations

import sys
import time
from typing import Any, Dict, List, Optional, Sequence, Tuple

import serial
from serial.tools import list_ports

# Bench-verified defaults (2026-08-09).
BAUD = 9600
PARITY = "N"
STOPBITS = 1
BLOCK_START = 0
BLOCK_COUNT = 32
R_GAS_TYPE = 25
R_UNIT = 26
R_DECIMALS = 27
R_RANGE = 29
R_VALUE = 31

GAS_TYPES = {
    1: "CO",
    2: "H2S",
    3: "O2",
    18: "CO2",
    65: "LEL",
}

UNITS = {
    0: "ppm",
    1: "mg/m3",
    2: "%VOL",
    3: "%LEL",
}

# Default Modbus address → IR4 ingest field.
DEFAULT_FIELD_MAP = {
    1: "h2s_ppm",
    2: "co_ppm",
    3: "o2_pct",
    4: "lel_pct",
    5: "co2_ppm",
}


def crc16(data: bytes) -> int:
    crc = 0xFFFF
    for byte in data:
        crc ^= byte
        for _ in range(8):
            crc = (crc >> 1) ^ 0xA001 if crc & 1 else crc >> 1
    return crc


def crc_ok(frame: bytes) -> bool:
    if len(frame) < 4:
        return False
    calc = crc16(frame[:-2])
    return frame[-2:] == bytes([calc & 0xFF, (calc >> 8) & 0xFF])


def build_request(addr: int, fc: int, start: int, count: int) -> bytes:
    body = bytes([addr, fc, start >> 8, start & 0xFF, count >> 8, count & 0xFF])
    crc = crc16(body)
    return body + bytes([crc & 0xFF, (crc >> 8) & 0xFF])


def parse_response(raw: bytes, addr: int, fc: int) -> Tuple[str, Any]:
    if not raw:
        return "silence", None
    for off in range(min(12, len(raw))):
        chunk = raw[off:]
        if len(chunk) < 5 or chunk[0] != addr:
            continue
        if chunk[1] == (fc | 0x80) and crc_ok(chunk[:5]):
            return "exception", chunk[2]
        if chunk[1] != fc:
            continue
        count_field = chunk[2]
        for datalen in (count_field, 2 * count_field):
            need = 3 + datalen + 2
            if datalen and len(chunk) >= need and crc_ok(chunk[:need]):
                data = chunk[3 : 3 + datalen]
                return "ok", [
                    (data[i] << 8) | data[i + 1] for i in range(0, len(data) - 1, 2)
                ]
    return "noise", raw


def open_port(
    port: str,
    baud: int = BAUD,
    parity: str = PARITY,
    stopbits: int = STOPBITS,
) -> serial.Serial:
    ser = serial.Serial()
    ser.port = port
    ser.baudrate = baud
    ser.parity = parity
    ser.stopbits = stopbits
    ser.bytesize = 8
    ser.timeout = 0.3
    ser.write_timeout = 2
    ser.open()
    time.sleep(0.05)
    ser.reset_input_buffer()
    return ser


def transact(
    ser: serial.Serial,
    addr: int,
    fc: int,
    start: int,
    count: int,
    baud: int = BAUD,
) -> Tuple[str, Any]:
    req = build_request(addr, fc, start, count)
    ser.reset_input_buffer()
    ser.reset_output_buffer()
    ser.write(req)
    ser.flush()
    char_time = 11.0 / baud
    ser.timeout = max(0.08, 40 * char_time + 0.05)
    buf = ser.read(1)
    if buf:
        ser.timeout = max(0.015, 4 * char_time)
        while len(buf) < 512:
            more = ser.read(128)
            if not more:
                break
            buf += more
    return parse_response(buf, addr, fc)


def autodetect_port(explicit: Optional[str] = None) -> str:
    if explicit:
        return explicit
    candidates: List[str] = []
    for p in list_ports.comports():
        name = p.device
        if sys.platform == "darwin" and "/tty." in name:
            continue
        candidates.append(name)
    if not candidates:
        raise RuntimeError(
            "No serial ports found. Plug in the USB-RS485 adapter "
            "or set serial.port in gas.yaml"
        )
    for hint in ("yt98h", "usbserial", "ttyUSB", "wchusb", "SLAB", "COM"):
        for c in candidates:
            if hint.lower() in c.lower():
                return c
    return candidates[0]


def read_channel(
    ser: serial.Serial,
    addr: int,
    baud: int = BAUD,
) -> Optional[Dict[str, Any]]:
    kind, payload = transact(ser, addr, 3, BLOCK_START, BLOCK_COUNT, baud)
    if kind != "ok" or not isinstance(payload, list) or len(payload) < BLOCK_COUNT:
        return None
    regs = payload
    decimals = int(regs[R_DECIMALS])
    raw = int(regs[R_VALUE])
    return {
        "address": addr,
        "raw_value": raw,
        "value": raw / (10 ** decimals) if decimals else float(raw),
        "decimals": decimals,
        "unit": UNITS.get(regs[R_UNIT], "code{}".format(regs[R_UNIT])),
        "unit_code": regs[R_UNIT],
        "range": regs[R_RANGE],
        "gas_type_code": regs[R_GAS_TYPE],
        "gas": GAS_TYPES.get(regs[R_GAS_TYPE], "code{}".format(regs[R_GAS_TYPE])),
        "registers": regs,
    }


def read_all_channels(
    ser: serial.Serial,
    addresses: Sequence[int],
    baud: int = BAUD,
) -> Dict[int, Dict[str, Any]]:
    out: Dict[int, Dict[str, Any]] = {}
    for addr in addresses:
        ch = read_channel(ser, addr, baud)
        if ch is not None:
            out[addr] = ch
    return out


def channels_to_ingest_fields(
    channels: Dict[int, Dict[str, Any]],
    field_map: Optional[Dict[int, str]] = None,
) -> Dict[str, float]:
    mapping = field_map or DEFAULT_FIELD_MAP
    fields: Dict[str, float] = {}
    for addr, ch in channels.items():
        key = mapping.get(int(addr))
        if not key:
            continue
        fields[key] = float(ch["value"])
    return fields
