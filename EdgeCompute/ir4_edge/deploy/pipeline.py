"""Shared install/update pipeline — transport-agnostic steps on the pole."""

from __future__ import annotations

import logging
import os
import shutil
from pathlib import Path
from typing import Optional

from ir4_edge.common.credentials import apply_pole_secrets
from ir4_edge.common.install_paths import canonical_edge_root, install_root, var_dir
from ir4_edge.deploy.host import HostSetupError, overlay_code, run_host
from ir4_edge.deploy.models import (
    CodeArtifact,
    DeployContext,
    DeployResult,
    DeployStatus,
    OperationKind,
)
from ir4_edge.deploy.state import DeployStateStore
from ir4_edge.deploy.transport.base import Transport
from ir4_edge.deploy.verifier import verify_pole
from ir4_edge.deploy.version import versions_compatible

log = logging.getLogger("ir4_edge.deploy.pipeline")


class DeployPipeline:
    """Execute one install or update on a pole (caller holds deploy lock)."""

    def __init__(self, store: DeployStateStore, transport: Transport) -> None:
        self.store = store
        self.transport = transport

    def run(self, ctx: DeployContext) -> DeployResult:
        operation_id = ctx.operation_id or self.store.new_operation_id()
        live = ctx.install_root / "EdgeCompute"
        installed = self.store.get_deployed_version()
        first_install = not (live / "configs" / "secrets.env").is_file()

        if ctx.kind == OperationKind.UPDATE and not first_install and not live.is_dir():
            ctx = DeployContext(
                pole=ctx.pole,
                kind=OperationKind.INSTALL,
                transport=ctx.transport,
                install_root=ctx.install_root,
                branch=ctx.branch,
                repo_url=ctx.repo_url,
                payload_dir=ctx.payload_dir,
                from_path=ctx.from_path,
                operation_id=operation_id,
                force=ctx.force,
            )
            first_install = True

        self.store.record(
            operation_id,
            ctx.kind,
            ctx.transport,
            ctx.pole,
            "",
            DeployStatus.PENDING,
            "starting",
        )

        try:
            artifact = self._deliver(ctx, operation_id)
            if (
                not ctx.force
                and not first_install
                and installed
                and installed == artifact.version
            ):
                self.store.record(
                    operation_id,
                    ctx.kind,
                    ctx.transport,
                    ctx.pole,
                    artifact.version,
                    DeployStatus.VERIFYING,
                    "already at version {}".format(artifact.version),
                )
                verification = verify_pole()
                if verification.ok:
                    self.store.record(
                        operation_id,
                        ctx.kind,
                        ctx.transport,
                        ctx.pole,
                        artifact.version,
                        DeployStatus.SUCCESS,
                        "already current",
                        finished=True,
                    )
                    return DeployResult(
                        ok=True,
                        status=DeployStatus.SUCCESS,
                        operation_id=operation_id,
                        target_version=artifact.version,
                        deployed_version=installed,
                        message="already at version {}".format(artifact.version),
                        already_current=True,
                    )

            if not versions_compatible(installed, artifact.version):
                raise RuntimeError(
                    "refusing downgrade {} -> {}".format(installed, artifact.version)
                )

            self._host_setup(ctx, artifact, operation_id, first_install)
            verification = self._verify(operation_id, ctx, artifact.version)
            if not verification.ok:
                self.store.record(
                    operation_id,
                    ctx.kind,
                    ctx.transport,
                    ctx.pole,
                    artifact.version,
                    DeployStatus.FAILED,
                    "verification failed",
                    {"failures": verification.failures},
                    finished=True,
                )
                return DeployResult(
                    ok=False,
                    status=DeployStatus.FAILED,
                    operation_id=operation_id,
                    target_version=artifact.version,
                    message="verification failed",
                    verification_failures=verification.failures,
                )

            self.store.set_deployed_version(artifact.version)
            self.store.record(
                operation_id,
                ctx.kind,
                ctx.transport,
                ctx.pole,
                artifact.version,
                DeployStatus.SUCCESS,
                "verified",
                finished=True,
            )
            return DeployResult(
                ok=True,
                status=DeployStatus.SUCCESS,
                operation_id=operation_id,
                target_version=artifact.version,
                deployed_version=artifact.version,
                message="verified",
            )
        except Exception as exc:
            log.exception("deploy failed")
            self.store.record(
                operation_id,
                ctx.kind,
                ctx.transport,
                ctx.pole,
                "",
                DeployStatus.FAILED,
                str(exc),
                finished=True,
            )
            return DeployResult(
                ok=False,
                status=DeployStatus.FAILED,
                operation_id=operation_id,
                message=str(exc),
            )

    def _deliver(self, ctx: DeployContext, operation_id: str) -> CodeArtifact:
        self.store.record(
            operation_id,
            ctx.kind,
            ctx.transport,
            ctx.pole,
            "",
            DeployStatus.DELIVERING,
            "transport={}".format(self.transport.name),
        )
        artifact = self.transport.deliver(ctx)
        self.store.record(
            operation_id,
            ctx.kind,
            ctx.transport,
            ctx.pole,
            artifact.version,
            DeployStatus.DELIVERING,
            "delivered {}".format(artifact.version),
        )
        return artifact

    def _host_setup(
        self,
        ctx: DeployContext,
        artifact: CodeArtifact,
        operation_id: str,
        first_install: bool,
    ) -> None:
        live = ctx.install_root / "EdgeCompute"
        self.store.record(
            operation_id,
            ctx.kind,
            ctx.transport,
            ctx.pole,
            artifact.version,
            DeployStatus.HOST_SETUP,
            "overlay",
        )
        env = {
            "INSTALL_ROOT": str(ctx.install_root),
            "IR4_EDGE_WHEELHOUSE": str(ctx.install_root / "wheels"),
        }
        run_host(artifact.source_root, "ensure-tree", env=env)
        overlay_code(artifact.source_root, live)
        wheels = ctx.payload_dir / "wheels" if ctx.payload_dir else ctx.install_root / "wheels"
        if wheels.is_dir():
            shutil.copytree(wheels, ctx.install_root / "wheels", dirs_exist_ok=True)
        run_host(live, "ensure-host", env=env)
        run_host(live, "ensure-venv", env=env)
        run_host(live, "pip-install", env=env)
        run_host(live, "render-systemd", env=env)
        run_host(live, "render-mosquitto", env=env)
        run_host(live, "fix-permissions", env=env)

        self.store.record(
            operation_id,
            ctx.kind,
            ctx.transport,
            ctx.pole,
            artifact.version,
            DeployStatus.CONFIGURING,
            "configure",
        )
        prev_config = os.environ.get("IR4_EDGE_CONFIG_DIR")
        prev_var = os.environ.get("IR4_EDGE_VAR_DIR")
        os.environ["IR4_EDGE_CONFIG_DIR"] = str(live / "configs")
        os.environ["IR4_EDGE_VAR_DIR"] = str(ctx.install_root / "var")
        try:
            if first_install:
                secrets_template = live / "configs" / "secrets.pole-{:02d}.env".format(ctx.pole)
                live_secrets = live / "configs" / "secrets.env"
                if secrets_template.is_file() and not live_secrets.is_file():
                    shutil.copy2(secrets_template, live_secrets)
                apply_pole_secrets(ctx.pole, live_secrets)
            run_host(live, "enable-services", env=env)
        finally:
            if prev_config is None:
                os.environ.pop("IR4_EDGE_CONFIG_DIR", None)
            else:
                os.environ["IR4_EDGE_CONFIG_DIR"] = prev_config
            if prev_var is None:
                os.environ.pop("IR4_EDGE_VAR_DIR", None)
            else:
                os.environ["IR4_EDGE_VAR_DIR"] = prev_var

    def _verify(self, operation_id: str, ctx: DeployContext, version: str):
        self.store.record(
            operation_id,
            ctx.kind,
            ctx.transport,
            ctx.pole,
            version,
            DeployStatus.VERIFYING,
            "doctor",
        )
        return verify_pole()
