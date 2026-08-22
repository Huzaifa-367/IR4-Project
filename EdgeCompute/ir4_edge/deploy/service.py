"""Deploy service — locking, recovery, retries, status."""

from __future__ import annotations

import logging
import time
from pathlib import Path
from typing import Optional

from ir4_edge.common.install_paths import install_root, var_dir
from ir4_edge.deploy.models import (
    DeployContext,
    DeployResult,
    DeployStatus,
    OperationKind,
    OperationRecord,
    TransportName,
)
from ir4_edge.deploy.pipeline import DeployPipeline
from ir4_edge.deploy.state import DeployStateStore
from ir4_edge.deploy.transport.direct import DirectTransport
from ir4_edge.deploy.transport.scc import SccReceiveTransport
from ir4_edge.deploy.verifier import verify_pole

log = logging.getLogger("ir4_edge.deploy.service")

MAX_ATTEMPTS = 3
RETRY_BACKOFF_S = 5.0


class DeployService:
    """High-level install/update API used by the CLI controller."""

    def __init__(self, root: Optional[Path] = None) -> None:
        self.install_root = Path(root or install_root())
        self.store = DeployStateStore(self.install_root / "var")

    def close(self) -> None:
        self.store.close()

    def _transport(self, name: TransportName):
        if name == TransportName.DIRECT:
            return DirectTransport()
        if name == TransportName.SCC:
            return SccReceiveTransport()
        raise ValueError("unknown transport {}".format(name))

    def _detect_kind(self) -> OperationKind:
        live = self.install_root / "EdgeCompute"
        if (live / "venv" / "bin" / "ir4-edge").exists():
            return OperationKind.UPDATE
        if (self.install_root / "venv" / "bin" / "ir4-edge").exists():
            return OperationKind.UPDATE
        return OperationKind.INSTALL

    def recover_if_needed(self) -> None:
        """Reconcile stale in-progress rows before starting a new operation."""
        self.store.recover_stale_operations()

    def status_text(self) -> str:
        deployed = self.store.get_deployed_version() or "(none)"
        latest = self.store.latest_operation()
        lines = [
            "install_root: {}".format(self.install_root),
            "deployed_version: {}".format(deployed),
        ]
        if latest:
            lines.append(
                "last_operation: {} {} {} — {}".format(
                    latest.id[:8],
                    latest.kind.value,
                    latest.status.value,
                    latest.message,
                )
            )
        verification = verify_pole()
        lines.append("doctor: {}".format("PASS" if verification.ok else "FAIL"))
        return "\n".join(lines)

    def run(
        self,
        ctx: DeployContext,
        *,
        kind: Optional[OperationKind] = None,
    ) -> DeployResult:
        self.recover_if_needed()
        if kind is not None:
            ctx = DeployContext(
                pole=ctx.pole,
                kind=kind,
                transport=ctx.transport,
                install_root=ctx.install_root,
                branch=ctx.branch,
                repo_url=ctx.repo_url,
                payload_dir=ctx.payload_dir,
                from_path=ctx.from_path,
                operation_id=ctx.operation_id,
                force=ctx.force,
            )
        elif ctx.kind == OperationKind.UPDATE and self._detect_kind() == OperationKind.INSTALL:
            ctx = DeployContext(
                pole=ctx.pole,
                kind=OperationKind.INSTALL,
                transport=ctx.transport,
                install_root=ctx.install_root,
                branch=ctx.branch,
                repo_url=ctx.repo_url,
                payload_dir=ctx.payload_dir,
                from_path=ctx.from_path,
                operation_id=ctx.operation_id,
                force=ctx.force,
            )

        operation_id = ctx.operation_id or self.store.new_operation_id()
        ctx = DeployContext(
            pole=ctx.pole,
            kind=ctx.kind,
            transport=ctx.transport,
            install_root=ctx.install_root,
            branch=ctx.branch,
            repo_url=ctx.repo_url,
            payload_dir=ctx.payload_dir,
            from_path=ctx.from_path,
            operation_id=operation_id,
            force=ctx.force,
        )

        pipeline = DeployPipeline(self.store, self._transport(ctx.transport))
        last: Optional[DeployResult] = None
        for attempt in range(1, MAX_ATTEMPTS + 1):
            try:
                with self.store.exclusive_lock(operation_id):
                    last = pipeline.run(ctx)
                if last.ok or last.already_current:
                    return last
                if last.status == DeployStatus.FAILED and attempt < MAX_ATTEMPTS:
                    log.warning("attempt %d failed: %s — retrying", attempt, last.message)
                    time.sleep(RETRY_BACKOFF_S * attempt)
                    continue
                return last
            except TimeoutError as exc:
                return DeployResult(
                    ok=False,
                    status=DeployStatus.FAILED,
                    operation_id=operation_id,
                    message=str(exc),
                )
        return last or DeployResult(
            ok=False,
            status=DeployStatus.FAILED,
            operation_id=operation_id,
            message="unknown failure",
        )

    def verify_only(self) -> DeployResult:
        verification = verify_pole()
        return DeployResult(
            ok=verification.ok,
            status=DeployStatus.SUCCESS if verification.ok else DeployStatus.FAILED,
            operation_id="",
            message="verified" if verification.ok else "verification failed",
            verification_failures=verification.failures,
        )
