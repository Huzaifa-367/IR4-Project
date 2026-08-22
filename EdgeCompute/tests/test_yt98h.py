"""Modbus RTU timing/framing and YT-98H poll behaviour — real-life scenarios."""

from __future__ import annotations

import unittest
from typing import Any, Dict, List, Tuple
from unittest import mock

from ir4_edge.gas import modbus_rtu, yt98h
from ir4_edge.gas.modbus_rtu import ModbusOutcome


def _fc03_frame(addr: int, registers: List[int]) -> bytes:
    """Build a valid FC03 read-holding-registers reply for tests."""
    data = b"".join(bytes([(value >> 8) & 0xFF, value & 0xFF]) for value in registers)
    body = bytes([addr, 3, len(data)]) + data
    crc = modbus_rtu.crc16(body)
    return body + bytes([crc & 0xFF, (crc >> 8) & 0xFF])


def _exception_frame(addr: int, fc: int, code: int) -> bytes:
    body = bytes([addr, fc | 0x80, code])
    crc = modbus_rtu.crc16(body)
    return body + bytes([crc & 0xFF, (crc >> 8) & 0xFF])


class ModbusRtuTimingTest(unittest.TestCase):
    def test_inter_byte_timeout_covers_32_register_frame_at_9600(self) -> None:
        timeout = modbus_rtu.inter_byte_timeout(9600, yt98h.BLOCK_COUNT)
        wire_seconds = (
            modbus_rtu.fc03_response_byte_count(yt98h.BLOCK_COUNT)
            * modbus_rtu.char_time_seconds(9600)
        )
        self.assertGreaterEqual(timeout, modbus_rtu._MIN_INTER_BYTE_TIMEOUT_S)
        self.assertGreaterEqual(timeout, wire_seconds)

    def test_legacy_fixed_gap_timeout_truncates_full_frame(self) -> None:
        char_time = modbus_rtu.char_time_seconds(9600)
        legacy = max(0.015, 4 * char_time)
        frame_wire = modbus_rtu.fc03_response_byte_count(32) * char_time
        self.assertLess(legacy, frame_wire)


class ModbusRtuFramingTest(unittest.TestCase):
    def test_crc_round_trip(self) -> None:
        body = bytes([1, 3, 0, 0, 0, 32])
        crc = modbus_rtu.crc16(body)
        frame = body + bytes([crc & 0xFF, (crc >> 8) & 0xFF])
        self.assertTrue(modbus_rtu.crc_ok(frame))

    def test_parse_valid_fc03_response(self) -> None:
        regs = list(range(32))
        frame = _fc03_frame(1, regs)
        kind, payload = modbus_rtu.parse_response(frame, 1, 3)
        self.assertEqual(kind, "ok")
        self.assertEqual(payload, regs)

    def test_parse_silence_on_empty_buffer(self) -> None:
        kind, _payload = modbus_rtu.parse_response(b"", 1, 3)
        self.assertEqual(kind, "silence")

    def test_parse_truncated_frame_is_noise(self) -> None:
        """CH341 with a ~15 ms gap timeout often delivers only a prefix of the reply."""
        full = _fc03_frame(1, list(range(32)))
        truncated = full[:40]
        kind, _payload = modbus_rtu.parse_response(truncated, 1, 3)
        self.assertEqual(kind, "noise")

    def test_parse_skips_rs485_echo_prefix(self) -> None:
        """Line echo or idle bytes before the real frame should not break parsing."""
        frame = _fc03_frame(2, [0] * 32)
        kind, payload = modbus_rtu.parse_response(b"\xff\xfe" + frame, 2, 3)
        self.assertEqual(kind, "ok")
        self.assertEqual(len(payload), 32)

    def test_parse_modbus_exception(self) -> None:
        frame = _exception_frame(3, 3, 0x02)
        kind, code = modbus_rtu.parse_response(frame, 3, 3)
        self.assertEqual(kind, "exception")
        self.assertEqual(code, 0x02)


class BurstFakeSerial:
    """Simulate USB-UART delivering a Modbus reply in small bursts (CH341 pattern)."""

    timeout = 0.1

    def __init__(self, response: bytes, chunk_size: int = 8) -> None:
        self._response = response
        self._pos = 0
        self._chunk_size = chunk_size

    def reset_input_buffer(self) -> None:
        return None

    def reset_output_buffer(self) -> None:
        return None

    def write(self, _data: bytes) -> None:
        return None

    def flush(self) -> None:
        return None

    def read(self, size: int = 1) -> bytes:
        if self._pos >= len(self._response):
            return b""
        if size == 1:
            chunk = self._response[self._pos : self._pos + 1]
            self._pos += 1
            return chunk
        end = min(self._pos + max(size, self._chunk_size), len(self._response))
        chunk = self._response[self._pos : end]
        self._pos = end
        return chunk


class FakeSerial:
    timeout = 0.1

    def reset_input_buffer(self) -> None:
        return None

    def reset_output_buffer(self) -> None:
        return None

    def write(self, _data: bytes) -> None:
        return None

    def flush(self) -> None:
        return None

    def read(self, _size: int = 1) -> bytes:
        return b""


class ModbusTransactTest(unittest.TestCase):
    def test_transact_reads_full_frame_delivered_in_bursts(self) -> None:
        registers = list(range(32))
        frame = _fc03_frame(1, registers)
        ser = BurstFakeSerial(frame, chunk_size=8)
        kind, payload = modbus_rtu.transact(ser, 1, 3, 0, 32, yt98h.BAUD)
        self.assertEqual(kind, "ok")
        self.assertEqual(payload, registers)

    def test_transact_reports_silence_when_sensor_off(self) -> None:
        kind, payload = modbus_rtu.transact(FakeSerial(), 1, 3, 0, 32, yt98h.BAUD)
        self.assertEqual(kind, "silence")
        self.assertIsNone(payload)


class Yt98hDecodeTest(unittest.TestCase):
    def test_decode_channel_applies_decimal_places(self) -> None:
        registers = [0] * yt98h.BLOCK_COUNT
        registers[yt98h.R_DECIMALS] = 2
        registers[yt98h.R_VALUE] = 1234
        registers[yt98h.R_GAS_TYPE] = 1
        registers[yt98h.R_UNIT] = 0
        decoded = yt98h._decode_channel(1, registers)
        self.assertEqual(decoded["value"], 12.34)
        self.assertEqual(decoded["gas"], "CO")

    def test_channels_to_ingest_fields_maps_configured_addresses(self) -> None:
        channels: Dict[int, Dict[str, Any]] = {
            1: {"value": 0.5},
            3: {"value": 20.9},
        }
        fields = yt98h.channels_to_ingest_fields(channels)
        self.assertEqual(fields["h2s_ppm"], 0.5)
        self.assertEqual(fields["o2_pct"], 20.9)
        self.assertNotIn("co_ppm", fields)


class Yt98hPollTest(unittest.TestCase):
    def test_poll_channels_reports_silence_per_address(self) -> None:
        poll = yt98h.poll_channels(FakeSerial(), [1, 2])
        self.assertFalse(poll.has_data)
        self.assertEqual(len(poll.readings), 2)
        self.assertIn("addr 1: silence", poll.failure_summary())
        self.assertIn("addr 2: silence", poll.failure_summary())

    def test_poll_partial_success_still_has_data(self) -> None:
        """One dead channel on the block must not block ingest from the others."""

        def fake_transact(
            _ser: object,
            addr: int,
            _fc: int,
            _start: int,
            _count: int,
            _baud: int,
        ) -> Tuple[ModbusOutcome, Any]:
            if addr == 1:
                return "ok", list(range(yt98h.BLOCK_COUNT))
            return "silence", None

        with mock.patch.object(modbus_rtu, "transact", side_effect=fake_transact):
            poll = yt98h.poll_channels(FakeSerial(), [1, 2, 3])
        self.assertTrue(poll.has_data)
        self.assertEqual(set(poll.channels.keys()), {1})
        self.assertIn("addr 2: silence", poll.failure_summary())
        self.assertIn("addr 3: silence", poll.failure_summary())

    def test_read_channel_short_payload_reported_as_noise(self) -> None:
        """Guard against truncated replies that parse as ok but lack all registers."""

        def short_ok(
            _ser: object,
            addr: int,
            _fc: int,
            _start: int,
            _count: int,
            _baud: int,
        ) -> Tuple[ModbusOutcome, Any]:
            return "ok", list(range(16))

        with mock.patch.object(modbus_rtu, "transact", side_effect=short_ok):
            reading = yt98h.read_channel(FakeSerial(), 1)
        self.assertEqual(reading.status, "noise")
        self.assertIsNone(reading.channel)


if __name__ == "__main__":
    unittest.main()
