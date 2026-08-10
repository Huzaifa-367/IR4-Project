"""Shared helpers for UTC timestamps and UUIDs."""

from __future__ import annotations

from datetime import datetime, timezone
from typing import Optional
from uuid import uuid4


def new_event_uid() -> str:
    return str(uuid4())


def utc_now_iso() -> str:
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z")


def epoch_ms_to_iso(value: Optional[object]) -> str:
    """Convert Zebra-style epoch ms / seconds / ISO string to UTC ISO-8601 Z."""
    if value is None:
        return utc_now_iso()
    if isinstance(value, str):
        text = value.strip()
        if not text:
            return utc_now_iso()
        if text.endswith("Z") or "+" in text[10:]:
            return text
        try:
            numeric = float(text)
        except ValueError:
            return text
        value = numeric
    if isinstance(value, (int, float)):
        seconds = float(value)
        # Zebra firstSeenTimestamp is typically epoch milliseconds (~1.7e12).
        if seconds >= 1e11:
            seconds = seconds / 1000.0
        return (
            datetime.fromtimestamp(seconds, tz=timezone.utc)
            .replace(microsecond=0)
            .isoformat()
            .replace("+00:00", "Z")
        )
    return utc_now_iso()
