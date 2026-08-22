"""Result types for one gas poll cycle (all Modbus slaves on the YT-98H block)."""

from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any, Dict, List, Optional

from ir4_edge.gas.modbus_rtu import ModbusOutcome


@dataclass(frozen=True)
class ChannelReading:
    """Outcome of reading one Modbus slave address on the gas block."""

    address: int
    status: ModbusOutcome
    # Populated only when status == "ok"; see yt98h._decode_channel().
    channel: Optional[Dict[str, Any]] = None


@dataclass
class PollResult:
    """All slave reads from a single agent poll (typically addresses 1–5)."""

    readings: List[ChannelReading] = field(default_factory=list)

    @property
    def channels(self) -> Dict[int, Dict[str, Any]]:
        """Successful reads only — keyed by Modbus address."""
        return {
            reading.address: reading.channel
            for reading in self.readings
            if reading.channel is not None
        }

    @property
    def has_data(self) -> bool:
        """True when at least one slave returned a decoded gas value."""
        return bool(self.channels)

    def failure_summary(self, limit: int = 5) -> str:
        """Human-readable per-address failures for logs (e.g. ``addr 3: noise``)."""
        failed = [r for r in self.readings if r.status != "ok"]
        return "; ".join("addr {}: {}".format(r.address, r.status) for r in failed[:limit])
