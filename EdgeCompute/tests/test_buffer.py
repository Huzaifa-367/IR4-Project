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


if __name__ == "__main__":
    unittest.main()
