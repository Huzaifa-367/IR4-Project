"""Clock sanity helpers — Orin RTC often resets to 1970 until SCC NTP syncs."""

from __future__ import annotations

import unittest
from datetime import datetime, timezone
from unittest import mock

from ir4_edge.common import timeutil


class ClockSaneTest(unittest.TestCase):
    def test_epoch_clock_is_not_sane(self) -> None:
        self.assertFalse(
            timeutil.clock_is_sane(datetime(1970, 1, 1, tzinfo=timezone.utc))
        )

    def test_current_year_is_sane(self) -> None:
        self.assertTrue(
            timeutil.clock_is_sane(datetime(2026, 9, 3, tzinfo=timezone.utc))
        )

    def test_wait_returns_immediately_when_sane(self) -> None:
        with mock.patch.object(timeutil, "clock_is_sane", return_value=True):
            self.assertTrue(timeutil.wait_for_sane_clock(timeout_seconds=1.0))

    def test_wait_times_out_when_never_sane(self) -> None:
        with mock.patch.object(timeutil, "clock_is_sane", return_value=False):
            with mock.patch.object(timeutil.time, "sleep", return_value=None):
                self.assertFalse(
                    timeutil.wait_for_sane_clock(timeout_seconds=0.01, poll_seconds=0.01)
                )


if __name__ == "__main__":
    unittest.main()
