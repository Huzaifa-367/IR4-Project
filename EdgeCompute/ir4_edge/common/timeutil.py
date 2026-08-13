"""Shared clock — same APP_TIMEZONE as the IR4 server (Asia/Riyadh)."""

from __future__ import annotations

import os
from datetime import datetime
from typing import Optional
from uuid import uuid4
from zoneinfo import ZoneInfo

_TIMEZONE_ENV = "APP_TIMEZONE"
_DEFAULT_TIMEZONE = "Asia/Riyadh"


def configured_timezone() -> ZoneInfo:
    name = (os.environ.get(_TIMEZONE_ENV) or "").strip() or _DEFAULT_TIMEZONE
    return ZoneInfo(name)


def new_event_uid() -> str:
    return str(uuid4())


def now_iso() -> str:
    """Wall clock in APP_TIMEZONE with offset (gas polls have no device timestamp)."""
    return datetime.now(configured_timezone()).replace(microsecond=0).isoformat()


def to_iso(value: str) -> Optional[str]:
    """Parse a live device timestamp into APP_TIMEZONE. None if it cannot be parsed."""
    text = value.strip()
    if not text:
        return None
    if text.endswith("Z"):
        text = text[:-1] + "+00:00"
    elif len(text) >= 5 and text[-5] in "+-" and text[-3] != ":":
        text = text[:-2] + ":" + text[-2:]
    try:
        parsed = datetime.fromisoformat(text)
    except ValueError:
        return None
    zone = configured_timezone()
    if parsed.tzinfo is None:
        parsed = parsed.replace(tzinfo=zone)
    converted = parsed.astimezone(zone)
    if converted.microsecond:
        return converted.isoformat(timespec="milliseconds")
    return converted.replace(microsecond=0).isoformat()
