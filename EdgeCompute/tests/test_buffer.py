"""Outage buffer row-cap behaviour."""

from __future__ import annotations

import tempfile
import unittest
from pathlib import Path
from typing import Any, Mapping, Sequence

from ir4_edge.common.buffer import OutageBuffer
from ir4_edge.common.client import IngestResult, Ir4Client


def _event(n: int) -> dict:
    return {"event_uid": "uid-{:04d}".format(n), "n": n}


class _FakeClient:
    pass


class OutageBufferTest(unittest.TestCase):
    def setUp(self) -> None:
        self._tmp = tempfile.TemporaryDirectory()
        self.path = Path(self._tmp.name) / "buf.sqlite"

    def tearDown(self) -> None:
        self._tmp.cleanup()

    def test_cap_drops_oldest_keeps_newest(self) -> None:
        buf = OutageBuffer(self.path, "gas_readings", max_rows=3)
        buf.enqueue([_event(1), _event(2), _event(3), _event(4), _event(5)])
        pending = buf._peek(10)
        buf.close()
        uids = [row["event_uid"] for row in pending]
        self.assertEqual(uids, ["uid-0003", "uid-0004", "uid-0005"])
        self.assertEqual(len(uids), 3)

    def test_happy_path_202_deletes_buffer(self) -> None:
        buf = OutageBuffer(self.path, "gas_readings", max_rows=10)
        buf.enqueue([_event(1), _event(2)])

        def sender(_client: Ir4Client, events: Sequence[Mapping[str, Any]]) -> IngestResult:
            return IngestResult(accepted=len(events), status_code=202)

        removed = buf.flush(_FakeClient(), sender)  # type: ignore[arg-type]
        self.assertEqual(removed, 2)
        self.assertEqual(buf.pending_count(), 0)
        buf.close()

    def test_retriable_failure_leaves_buffer(self) -> None:
        buf = OutageBuffer(self.path, "gas_readings", max_rows=10)
        buf.enqueue([_event(1)])

        def sender(_client: Ir4Client, _events: Sequence[Mapping[str, Any]]) -> IngestResult:
            return IngestResult(status_code=0, retriable=True, error="timeout")

        removed = buf.flush(_FakeClient(), sender)  # type: ignore[arg-type]
        self.assertEqual(removed, 0)
        self.assertEqual(buf.pending_count(), 1)
        buf.close()

    def test_flush_rejected_event_lands_in_dead_letters(self) -> None:
        buf = OutageBuffer(self.path, "gas_readings", max_rows=10)
        buf.enqueue([_event(1)])

        def sender(_client: Ir4Client, events: Sequence[Mapping[str, Any]]) -> IngestResult:
            return IngestResult(
                accepted=0,
                status_code=202,
                rejected=[{"index": 0, "code": "FORBIDDEN_REFERENCE"}],
            )

        removed = buf.flush(_FakeClient(), sender)  # type: ignore[arg-type]
        self.assertEqual(removed, 1)
        self.assertEqual(buf.pending_count(), 0)
        self.assertEqual(buf.dead_letter_count(), 1)
        row = buf._conn.execute(
            "SELECT event_uid, reason_code FROM dead_letters WHERE stream = ?",
            ("gas_readings",),
        ).fetchone()
        self.assertEqual(row, ("uid-0001", "FORBIDDEN_REFERENCE"))
        buf.close()

    def test_submit_rejected_event_lands_in_dead_letters(self) -> None:
        buf = OutageBuffer(self.path, "gas_readings", max_rows=10)

        def sender(_client: Ir4Client, events: Sequence[Mapping[str, Any]]) -> IngestResult:
            return IngestResult(
                accepted=0,
                status_code=202,
                rejected=[{"index": 0, "code": "FORBIDDEN_REFERENCE"}],
            )

        result = buf.submit(_FakeClient(), [_event(1)], sender)  # type: ignore[arg-type]
        self.assertEqual(result.rejected, [{"index": 0, "code": "FORBIDDEN_REFERENCE"}])
        self.assertEqual(buf.dead_letter_count(), 1)
        buf.close()


if __name__ == "__main__":
    unittest.main()
