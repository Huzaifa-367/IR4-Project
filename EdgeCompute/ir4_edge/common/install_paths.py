"""Where the edge software is installed on a pole Jetson.

Production layout (see ``configs/edge.yaml`` → ``install.root``):

    /opt/ir4-edge/                 ← venv, var/, buffers
    /opt/ir4-edge/EdgeCompute/     ← this repo (code + configs)

Systemd units must use ``/opt/ir4-edge/EdgeCompute`` as WorkingDirectory even
if an operator cloned a copy elsewhere (e.g. ~/Downloads). ``ir4-edge doctor``
checks that systemd matches these paths.
"""

from __future__ import annotations

from pathlib import Path

from ir4_edge.common.config import config_dir, load_yaml

DEFAULT_INSTALL_ROOT = Path("/opt/ir4-edge")


def install_root() -> Path:
    """Return ``install.root`` from edge.yaml, or ``/opt/ir4-edge``."""
    edge_yaml = config_dir() / "edge.yaml"
    if not edge_yaml.is_file():
        return DEFAULT_INSTALL_ROOT
    install = dict(load_yaml(edge_yaml).get("install") or {})
    root = install.get("root")
    if isinstance(root, str) and root.strip():
        return Path(root.strip())
    return DEFAULT_INSTALL_ROOT


def canonical_edge_root() -> Path:
    """Return the directory that must appear in systemd WorkingDirectory."""
    return install_root() / "EdgeCompute"
