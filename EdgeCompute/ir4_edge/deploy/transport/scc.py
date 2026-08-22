"""SCC transport — code arrives as a pre-built offline payload on the pole."""

from __future__ import annotations

import logging
import subprocess
from pathlib import Path
from typing import List, Optional, Sequence

from ir4_edge.deploy.models import CodeArtifact, DeployContext, TransportName
from ir4_edge.deploy.transport.base import Transport
from ir4_edge.deploy.version import read_version

log = logging.getLogger("ir4_edge.deploy.transport.scc")

DEFAULT_POLES: Sequence[tuple] = (
    (1, "pole1", "172.16.3.2"),
    (2, "pole2", "172.16.2.2"),
    (3, "pole3", "172.16.1.50"),
    (4, "pole4", "172.16.4.2"),
)


class SccReceiveTransport(Transport):
    """Pole-side: read code from /tmp/ir4-edge-offline (or similar) payload."""

    name = TransportName.SCC.value

    def deliver(self, ctx: DeployContext) -> CodeArtifact:
        if ctx.payload_dir is None:
            raise RuntimeError("SCC transport requires --payload directory")
        payload = ctx.payload_dir.resolve()
        edge = payload / "EdgeCompute"
        if not edge.is_dir():
            raise RuntimeError("payload missing EdgeCompute/: {}".format(payload))
        version = read_version(edge)
        return CodeArtifact(source_root=edge.resolve(), version=version, transport=TransportName.SCC)


class SccPushCoordinator:
    """SCC-side: stage payload, rsync to poles, invoke remote apply."""

    def __init__(self, edge_root: Path) -> None:
        self.edge_root = edge_root.resolve()

    def push(
        self,
        poles: Optional[List[int]] = None,
        *,
        pack_wheels: bool = False,
    ) -> int:
        import tempfile

        selected = set(poles or [n for n, _, _ in DEFAULT_POLES])
        stage = Path(tempfile.mkdtemp(prefix="ir4-edge-scc-"))
        payload = stage / "ir4-edge-offline"
        try:
            self._build_payload(payload, pack_wheels=pack_wheels)
            ok = 0
            fail = 0
            for number, user, ip in DEFAULT_POLES:
                if number not in selected:
                    continue
                if self._push_one(payload, number, user, ip):
                    ok += 1
                else:
                    fail += 1
            print("# SUMMARY ok={} fail={}".format(ok, fail))
            return 0 if fail == 0 else 1
        finally:
            import shutil

            shutil.rmtree(stage, ignore_errors=True)

    def _build_payload(self, payload: Path, *, pack_wheels: bool) -> None:
        edge_dest = payload / "EdgeCompute"
        wheels_dest = payload / "wheels"
        var_dest = payload / "var"
        edge_dest.mkdir(parents=True)
        wheels_dest.mkdir(parents=True)
        var_dest.mkdir(parents=True)
        rsync_cmd = [
            "rsync",
            "-a",
            "--exclude",
            ".git/",
            "--exclude",
            ".venv/",
            "--exclude",
            "venv/",
            "--exclude",
            ".wheels/",
            "--exclude",
            "dist/",
            "--exclude",
            "__pycache__/",
            "--exclude",
            "configs/secrets.env",
            "--exclude",
            "var/",
            "{}/".format(self.edge_root),
            "{}/".format(edge_dest),
        ]
        subprocess.run(rsync_cmd, check=True)
        install_sh = self.edge_root / "deploy" / "install.sh"
        subprocess.run(["cp", str(install_sh), str(payload / "install.sh")], check=True)
        subprocess.run(["chmod", "0755", str(payload / "install.sh")], check=True)
        (var_dest / ".keep").touch()
        wheel_src = self.edge_root / ".wheels"
        if pack_wheels or (wheel_src.is_dir() and any(wheel_src.iterdir())):
            pack = self.edge_root / "deploy" / "pack_bundle.sh"
            if pack.is_file():
                subprocess.run(["bash", str(pack)], cwd=str(self.edge_root), check=False)
        if wheel_src.is_dir():
            subprocess.run(
                ["rsync", "-a", "{}/".format(wheel_src), "{}/".format(wheels_dest)],
                check=False,
            )

    def _push_one(self, payload: Path, pole: int, user: str, ip: str) -> bool:
        print("\n############################################")
        print("# POLE {}  {}@{}".format(pole, user, ip))
        print("############################################")
        ping = subprocess.run(
            ["ping", "-c", "1", "-W", "2", ip],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            check=False,
        )
        if ping.returncode != 0:
            print("SKIP: Jetson down")
            return True
        rsync = subprocess.run(
            [
                "rsync",
                "-az",
                "--delete",
                "--rsync-path=sudo rsync",
                "--exclude",
                "configs/secrets.env",
                "--exclude",
                "__pycache__/",
                "{}/".format(payload),
                "{}@{}:/tmp/ir4-edge-offline/".format(user, ip),
            ],
            check=False,
        )
        if rsync.returncode != 0:
            print("FAIL: rsync pole {}".format(pole))
            return False
        remote = subprocess.run(
            [
                "ssh",
                "-o",
                "BatchMode=yes",
                "-o",
                "ConnectTimeout=15",
                "{}@{}".format(user, ip),
                "sudo /tmp/ir4-edge-offline/install.sh --pole {}".format(pole),
            ],
            check=False,
        )
        if remote.returncode != 0:
            print("FAIL: remote apply pole {}".format(pole))
            return False
        return True
