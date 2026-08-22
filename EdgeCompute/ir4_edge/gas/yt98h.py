"""YT-98H multi-gas transmitter driver (Safegas-class Modbus block).

Hardware layout on each pole:
    USB-RS485 dongle  →  YT-98H block  →  up to five gas channels (Modbus 1–5)

Configuration lives in ``configs/gas.yaml`` (serial port, poll interval) and
``configs/secrets.env`` (``IR4_GAS_DEVICE_REF`` overrides the yaml default ref).

Public entry point for the agent: ``poll_channels()`` → ``PollResult``.
"""

from __future__ import annotations

import sys
import time
from typing import Any, Dict, List, Optional, Sequence

import serial
from serial.tools import list_ports

from ir4_edge.gas import modbus_rtu
from ir4_edge.gas.types import ChannelReading, PollResult

# Serial defaults — match gas.yaml and bench commissioning (2026-08-09).
BAUD = 9600
PARITY = "N"
STOPBITS = 1

# One FC03 read pulls registers 0–31 from each slave (YT-98H block layout).
BLOCK_START = 0
BLOCK_COUNT = 32

# Register indices inside the 32-register block (same for every channel).
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

# Modbus address → IR4 ingest field name (overridden per pole via secrets.env).
DEFAULT_FIELD_MAP = {
    1: "h2s_ppm",
    2: "co_ppm",
    3: "o2_pct",
    4: "lel_pct",
    5: "co2_ppm",
}


def open_port(
    port: str,
    baud: int = BAUD,
    parity: str = PARITY,
    stopbits: int = STOPBITS,
) -> serial.Serial:
    """Open the RS-485 serial port and discard stale RX data."""
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


def autodetect_port(explicit: Optional[str] = None) -> str:
    """Return gas.yaml port, or the first likely USB-RS485 device on the Jetson."""
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


def _decode_channel(addr: int, registers: List[int]) -> Dict[str, Any]:
    """Turn one 32-register Modbus block into a decoded channel dict."""
    decimals = int(registers[R_DECIMALS])
    raw = int(registers[R_VALUE])
    return {
        "address": addr,
        "raw_value": raw,
        "value": raw / (10 ** decimals) if decimals else float(raw),
        "decimals": decimals,
        "unit": UNITS.get(registers[R_UNIT], "code{}".format(registers[R_UNIT])),
        "unit_code": registers[R_UNIT],
        "range": registers[R_RANGE],
        "gas_type_code": registers[R_GAS_TYPE],
        "gas": GAS_TYPES.get(registers[R_GAS_TYPE], "code{}".format(registers[R_GAS_TYPE])),
        "registers": registers,
    }


def read_channel(
    ser: serial.Serial,
    addr: int,
    baud: int = BAUD,
) -> ChannelReading:
    """Read and decode one Modbus slave address on the YT-98H block."""
    status, payload = modbus_rtu.transact(
        ser, addr, 3, BLOCK_START, BLOCK_COUNT, baud,
    )
    if status != "ok" or not isinstance(payload, list):
        return ChannelReading(address=addr, status=status)
    # Truncated FC03 replies (common on CH341 with a short gap timeout) can CRC-match
    # a prefix — treat short blocks as noise so failure_summary and logs stay honest.
    if len(payload) < BLOCK_COUNT:
        return ChannelReading(address=addr, status="noise")
    return ChannelReading(
        address=addr,
        status="ok",
        channel=_decode_channel(addr, payload),
    )


def poll_channels(
    ser: serial.Serial,
    addresses: Sequence[int],
    baud: int = BAUD,
) -> PollResult:
    """Poll every configured Modbus address once (agent calls this each cycle)."""
    result = PollResult()
    for addr in addresses:
        result.readings.append(read_channel(ser, addr, baud))
    return result


def channels_to_ingest_fields(
    channels: Dict[int, Dict[str, Any]],
    field_map: Optional[Dict[int, str]] = None,
) -> Dict[str, float]:
    """Map decoded channels to IR4 ``/api/ingest/gas-readings`` field names."""
    mapping = field_map or DEFAULT_FIELD_MAP
    fields: Dict[str, float] = {}
    for addr, ch in channels.items():
        key = mapping.get(int(addr))
        if not key:
            continue
        fields[key] = float(ch["value"])
    return fields
