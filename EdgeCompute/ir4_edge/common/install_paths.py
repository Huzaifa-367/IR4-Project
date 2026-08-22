"""Canonical install layout for a pole Jetson.

Single source of truth for paths (see ``configs/edge.yaml`` → ``install.root``):

    /opt/ir4-edge/                 venv, var/, outage buffers
    /opt/ir4-edge/EdgeCompute/     code + configs (real directory, not a symlink)

Systemd sets ``IR4_EDGE_CONFIG_DIR`` and ``IR4_EDGE_VAR_DIR``; CLI tools fall back
to the canonical tree when those are unset.
"""

from __future__ import annotations

import os
from pathlib import Path
from typing import Any, Dict, Optional

import yaml

DEFAULT_INSTALL_ROOT = Path("/opt/ir4-edge")


def _read_edge_yaml(config_dir: Path) -> Dict[str, Any]:
    path = config_dir / "edge.yaml"
    if not path.is_file():
        return {}
    with path.open("r", encoding="utf-8") as handle:
        data = yaml.safe_load(handle) or {}
    return data if isinstance(data, dict) else {}


def _config_dir_override() -> Optional[Path]:
    raw = os.environ.get("IR4_EDGE_CONFIG_DIR")
    return Path(raw) if raw else None


def _var_dir_override() -> Optional[Path]:
    raw = os.environ.get("IR4_EDGE_VAR_DIR")
    return Path(raw) if raw else None


def install_root() -> Path:
    """Return ``install.root`` from edge.yaml (default ``/opt/ir4-edge``)."""
    override = _config_dir_override()
    if override is not None:
        edge_yaml_dir = override
    else:
        edge_yaml_dir = DEFAULT_INSTALL_ROOT / "EdgeCompute" / "configs"
    install = dict(_read_edge_yaml(edge_yaml_dir).get("install") or {})
    root = install.get("root")
    if isinstance(root, str) and root.strip():
        return Path(root.strip())
    return DEFAULT_INSTALL_ROOT


def canonical_edge_root() -> Path:
    """Directory that must exist on disk and appear in systemd WorkingDirectory."""
    return install_root() / "EdgeCompute"


def edge_root() -> Path:
    """Active code tree — env override parent, else canonical install."""
    override = _config_dir_override()
    if override is not None:
        return override.parent
    return canonical_edge_root()


def config_dir() -> Path:
    """Live YAML + secrets directory."""
    override = _config_dir_override()
    if override is not None:
        return override
    return canonical_edge_root() / "configs"


def var_dir() -> Path:
    """Runtime data (buffers, spool). Always under install root unless overridden."""
    override = _var_dir_override()
    if override is not None:
        return override
    return install_root() / "var"
