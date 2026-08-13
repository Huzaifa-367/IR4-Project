"""Shared clock — same APP_TIMEZONE as the IR4 server (Asia/Riyadh).

Jetson L4T ships Python 3.8 (no zoneinfo). Riyadh has no DST, so a fixed
offset is enough and needs no extra packages.
"""

from __future__ import annotations

import os
import re
from datetime import datetime, timedelta, timezone, tzinfo
from typing import Optional
from uuid import uuid4

_TIMEZONE_ENV = "APP_TIMEZONE"
_DEFAULT_TIMEZONE = "Asia/Riyadh"
_FIXED_OFFSETS = {
    "Asia/Riyadh": timezone(timedelta(hours=3)),
    "UTC": timezone.utc,
    "Etc/UTC": timezone.utc,
}
_ISO_AWARE = re.compile(
    r"^(?P<dt>\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?)"
    r"(?P<tz>Z|[+-]\d{2}:?\d{2})$"
)


def configured_timezone() -> tzinfo:
    name = (os.environ.get(_TIMEZONE_ENV) or "").strip() or _DEFAULT_TIMEZONE
    offset = _FIXED_OFFSETS.get(name)
    if offset is None:
        raise ValueError(
            "APP_TIMEZONE must be Asia/Riyadh or UTC on Python 3.8 (got {!r})".format(name)
        )
    return offset


def new_event_uid() -> str:
    return str(uuid4())


def now_iso() -> str:
    """Wall clock in APP_TIMEZONE with offset (gas polls have no device timestamp)."""
    return datetime.now(configured_timezone()).replace(microsecond=0).isoformat()


def to_iso(value: str) -> Optional[str]:
    """Parse a live device timestamp into APP_TIMEZONE. None if it cannot be parsed."""
    parsed = _parse_iso(value)
    if parsed is None:
        return None
    converted = parsed.astimezone(configured_timezone())
    if converted.microsecond:
        return converted.isoformat(timespec="milliseconds")
    return converted.replace(microsecond=0).isoformat()


def _parse_iso(value: str) -> Optional[datetime]:
    text = value.strip()
    if not text:
        return None
    match = _ISO_AWARE.match(text)
    if match is None:
        try:
            naive = datetime.fromisoformat(text.replace(" ", "T"))
        except ValueError:
            return None
        if naive.tzinfo is not None:
            return naive
        return naive.replace(tzinfo=configured_timezone())
    body = match.group("dt").replace(" ", "T")
    try:
        naive = datetime.fromisoformat(body)
    except ValueError:
        return None
    stamp = match.group("tz")
    if stamp == "Z":
        return naive.replace(tzinfo=timezone.utc)
    sign = 1 if stamp[0] == "+" else -1
    digits = stamp[1:].replace(":", "")
    hours = int(digits[0:2])
    minutes = int(digits[2:4]) if len(digits) >= 4 else 0
    return naive.replace(
        tzinfo=timezone(timedelta(hours=sign * hours, minutes=sign * minutes)),
    )
