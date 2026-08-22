"""Direct pole transport — pole fetches code over the internet (git)."""

from __future__ import annotations

import logging
import shutil
import subprocess
import tempfile
from pathlib import Path

from ir4_edge.deploy.models import CodeArtifact, DeployContext, TransportName
from ir4_edge.deploy.transport.base import Transport
from ir4_edge.deploy.version import read_version

log = logging.getLogger("ir4_edge.deploy.transport.direct")


class DirectTransport(Transport):
    name = TransportName.DIRECT.value

    def deliver(self, ctx: DeployContext) -> CodeArtifact:
        if ctx.from_path:
            source = self._resolve_local(ctx.from_path)
            version = read_version(source)
            return CodeArtifact(source_root=source, version=version, transport=TransportName.DIRECT)

        workdir = Path(tempfile.mkdtemp(prefix="ir4-edge-direct-"))
        repo_dir = workdir / "repo"
        clone_cmd = [
            "git",
            "clone",
            "--depth",
            "1",
            "--branch",
            ctx.branch,
            ctx.repo_url,
            str(repo_dir),
        ]
        log.info("direct: %s", " ".join(clone_cmd))
        result = subprocess.run(clone_cmd, check=False, capture_output=True, text=True)
        if result.returncode != 0:
            raise RuntimeError(
                "git clone failed: {}".format((result.stderr or result.stdout or "").strip())
            )
        edge = repo_dir / "EdgeCompute"
        if not edge.is_dir():
            edge = repo_dir if (repo_dir / "pyproject.toml").is_file() else edge
        if not (edge / "pyproject.toml").is_file():
            shutil.rmtree(workdir, ignore_errors=True)
            raise RuntimeError("cloned tree is not EdgeCompute")
        version = read_version(edge)
        return CodeArtifact(source_root=edge.resolve(), version=version, transport=TransportName.DIRECT)

    def _resolve_local(self, raw: Path) -> Path:
        path = raw.resolve()
        if (path / "EdgeCompute" / "pyproject.toml").is_file():
            return (path / "EdgeCompute").resolve()
        if (path / "pyproject.toml").is_file():
            return path
        raise RuntimeError("--from must be EdgeCompute or monorepo root")
