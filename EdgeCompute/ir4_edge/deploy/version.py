"""Read package version from pyproject.toml (no TOML parser dependency)."""

from __future__ import annotations

import re
from pathlib import Path

_VERSION_RE = re.compile(r'^version\s*=\s*"([^"]+)"', re.MULTILINE)
_DEFAULT = "0.0.0"


def read_version(tree: Path) -> str:
    """Return semver from ``pyproject.toml`` in *tree*, or ``0.0.0``."""
    path = tree / "pyproject.toml"
    if not path.is_file():
        return _DEFAULT
    text = path.read_text(encoding="utf-8")
    match = _VERSION_RE.search(text)
    return match.group(1) if match else _DEFAULT


def versions_compatible(installed: str, target: str) -> bool:
    """Reject downgrades when both look like semver (major.minor.patch)."""
    if not installed or installed == _DEFAULT:
        return True
    if installed == target:
        return True
    try:
        installed_parts = [int(x) for x in installed.split(".")[:3]]
        target_parts = [int(x) for x in target.split(".")[:3]]
    except ValueError:
        return True
    while len(installed_parts) < 3:
        installed_parts.append(0)
    while len(target_parts) < 3:
        target_parts.append(0)
    return target_parts >= installed_parts
