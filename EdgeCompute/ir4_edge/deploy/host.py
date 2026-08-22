"""Host-level setup via deploy/host.sh (root required)."""

from __future__ import annotations

import logging
import os
import subprocess
from pathlib import Path
from typing import List, Optional, Sequence

log = logging.getLogger("ir4_edge.deploy.host")


class HostSetupError(RuntimeError):
    pass


def _host_script(edge_root: Path) -> Path:
    path = edge_root / "deploy" / "host.sh"
    if not path.is_file():
        raise HostSetupError("Missing deploy/host.sh under {}".format(edge_root))
    return path


def run_host(
    edge_root: Path,
    command: str,
    *args: str,
    env: Optional[dict] = None,
) -> None:
    """Run one host.sh subcommand; raise on non-zero exit."""
    script = _host_script(edge_root)
    cmd: List[str] = ["bash", str(script), command, *args]
    merged = dict(os.environ)
    merged["EDGE_ROOT"] = str(edge_root)
    if env:
        merged.update(env)
    log.info("host: %s", " ".join(cmd))
    result = subprocess.run(cmd, env=merged, check=False)
    if result.returncode != 0:
        raise HostSetupError("host.sh {} failed (exit {})".format(command, result.returncode))


def overlay_code(source: Path, live: Path, exclude_secrets: bool = True) -> None:
    """Rsync source tree onto live install (used after transport delivery)."""
    cmd: Sequence[str] = [
        "rsync",
        "-a",
        "--delete",
        "--exclude",
        ".git/",
        "--exclude",
        "__pycache__/",
        "--exclude",
        "*.pyc",
    ]
    if exclude_secrets:
        cmd = list(cmd) + ["--exclude", "configs/secrets.env"]
    cmd = list(cmd) + ["{}/".format(source), "{}/".format(live)]
    log.info("overlay: %s -> %s", source, live)
    result = subprocess.run(cmd, check=False)
    if result.returncode != 0:
        raise HostSetupError("rsync overlay failed (exit {})".format(result.returncode))
