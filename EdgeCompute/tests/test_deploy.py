"""Deploy pipeline, state, transports, and service behaviour."""

from __future__ import annotations

import sqlite3
import tempfile
import threading
import time
import unittest
from pathlib import Path
from unittest import mock

from ir4_edge.deploy.models import (
    CodeArtifact,
    DeployContext,
    DeployStatus,
    OperationKind,
    TransportName,
)
from ir4_edge.deploy.pipeline import DeployPipeline
from ir4_edge.deploy.service import DeployService
from ir4_edge.deploy.state import DeployStateStore
from ir4_edge.deploy.transport.direct import DirectTransport
from ir4_edge.deploy.transport.scc import SccReceiveTransport
from ir4_edge.deploy.verifier import VerificationResult
from ir4_edge.deploy.version import read_version, versions_compatible


def _write_edge_tree(root: Path, version: str = "1.0.0") -> Path:
    edge = root / "EdgeCompute"
    configs = edge / "configs"
    configs.mkdir(parents=True)
    (edge / "pyproject.toml").write_text(
        'version = "{}"\n'.format(version),
        encoding="utf-8",
    )
    (configs / "edge.yaml").write_text("install:\n  root: \"{}\"\n".format(root), encoding="utf-8")
    (edge / "deploy").mkdir(exist_ok=True)
    (edge / "deploy" / "host.sh").write_text("#!/bin/bash\n", encoding="utf-8")
    return edge


class VersionTest(unittest.TestCase):
    def test_read_and_compatible(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            edge = _write_edge_tree(Path(tmp), "1.2.3")
            self.assertEqual(read_version(edge), "1.2.3")
            self.assertTrue(versions_compatible("1.0.0", "1.2.3"))
            self.assertFalse(versions_compatible("2.0.0", "1.9.9"))


class DeployStateStoreTest(unittest.TestCase):
    def test_record_and_deployed_version(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            store = DeployStateStore(Path(tmp))
            op_id = store.new_operation_id()
            store.record(
                op_id,
                OperationKind.INSTALL,
                TransportName.DIRECT,
                2,
                "1.0.0",
                DeployStatus.PENDING,
                "start",
            )
            store.set_deployed_version("1.0.0")
            self.assertEqual(store.get_deployed_version(), "1.0.0")
            latest = store.latest_operation()
            assert latest is not None
            self.assertEqual(latest.status, DeployStatus.PENDING)
            store.close()

    def test_recover_stale_operations(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            store = DeployStateStore(Path(tmp))
            op_id = store.new_operation_id()
            store.record(
                op_id,
                OperationKind.UPDATE,
                TransportName.SCC,
                1,
                "1.0.0",
                DeployStatus.DELIVERING,
                "stuck",
            )
            old = time.time() - 7200
            store._conn.execute(
                "UPDATE operations SET started_at = ? WHERE id = ?",
                (old, op_id),
            )
            store._conn.commit()
            count = store.recover_stale_operations()
            self.assertEqual(count, 1)
            row = store._conn.execute(
                "SELECT status FROM operations WHERE id = ?",
                (op_id,),
            ).fetchone()
            self.assertEqual(row[0], DeployStatus.INTERRUPTED.value)
            store.close()

    def test_exclusive_lock_blocks_concurrent(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            store = DeployStateStore(Path(tmp))
            blocked = threading.Event()
            released = threading.Event()

            def holder() -> None:
                with store.exclusive_lock("a"):
                    blocked.set()
                    released.wait(timeout=5)

            thread = threading.Thread(target=holder)
            thread.start()
            blocked.wait(timeout=2)
            with self.assertRaises(TimeoutError):
                with store.exclusive_lock("b", timeout_s=0.5):
                    pass
            released.set()
            thread.join(timeout=2)
            store.close()


class TransportTest(unittest.TestCase):
    def test_direct_local_path(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            edge = _write_edge_tree(Path(tmp), "1.1.0")
            ctx = DeployContext(
                pole=2,
                kind=OperationKind.INSTALL,
                transport=TransportName.DIRECT,
                from_path=edge.parent,
            )
            artifact = DirectTransport().deliver(ctx)
            self.assertEqual(artifact.version, "1.1.0")
            self.assertTrue(artifact.source_root.is_dir())

    def test_scc_payload(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            payload = Path(tmp)
            edge = _write_edge_tree(payload, "1.2.0")
            ctx = DeployContext(
                pole=3,
                kind=OperationKind.UPDATE,
                transport=TransportName.SCC,
                payload_dir=payload,
            )
            artifact = SccReceiveTransport().deliver(ctx)
            self.assertEqual(artifact.version, "1.2.0")
            self.assertEqual(artifact.source_root, edge.resolve())

    def test_scc_requires_payload(self) -> None:
        ctx = DeployContext(pole=1, kind=OperationKind.INSTALL, transport=TransportName.SCC)
        with self.assertRaises(RuntimeError):
            SccReceiveTransport().deliver(ctx)


class DeployPipelineTest(unittest.TestCase):
    def _run_pipeline(
        self,
        tmp: Path,
        *,
        installed: str = "",
        verify_ok: bool = True,
        force: bool = False,
        transport: TransportName = TransportName.DIRECT,
    ):
        install_root = tmp / "opt"
        source = _write_edge_tree(tmp / "src", "1.0.0")
        live = install_root / "EdgeCompute"
        live.mkdir(parents=True)
        (live / "configs").mkdir()
        (live / "configs" / "secrets.env").write_text("IR4_BASE_URL=http://test\n", encoding="utf-8")

        store = DeployStateStore(install_root / "var")
        if installed:
            store.set_deployed_version(installed)

        ctx = DeployContext(
            pole=2,
            kind=OperationKind.UPDATE,
            transport=transport,
            install_root=install_root,
            from_path=source.parent,
            force=force,
        )

        class FakeTransport:
            name = transport.value

            def deliver(self, inner_ctx: DeployContext) -> CodeArtifact:
                return CodeArtifact(
                    source_root=source,
                    version=read_version(source),
                    transport=transport,
                )

        pipeline = DeployPipeline(store, FakeTransport())
        verification = VerificationResult(ok=verify_ok, failures=[] if verify_ok else ["mock fail"])

        with mock.patch("ir4_edge.deploy.pipeline.run_host"), mock.patch(
            "ir4_edge.deploy.pipeline.overlay_code"
        ), mock.patch("ir4_edge.deploy.pipeline.apply_pole_secrets"), mock.patch(
            "ir4_edge.deploy.pipeline.verify_pole",
            return_value=verification,
        ):
            result = pipeline.run(ctx)
        return result, store

    def test_success_records_version_after_verify(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            result, store = self._run_pipeline(Path(tmp), installed="0.9.0")
            try:
                self.assertTrue(result.ok)
                self.assertEqual(store.get_deployed_version(), "1.0.0")
            finally:
                store.close()

    def test_already_current_skips_host_setup(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            install_root = Path(tmp) / "opt"
            source = _write_edge_tree(Path(tmp) / "src", "1.0.0")
            live = install_root / "EdgeCompute"
            (live / "configs").mkdir(parents=True)
            (live / "configs" / "secrets.env").write_text("x=1\n", encoding="utf-8")
            store = DeployStateStore(install_root / "var")
            store.set_deployed_version("1.0.0")
            ctx = DeployContext(
                pole=2,
                kind=OperationKind.UPDATE,
                transport=TransportName.DIRECT,
                install_root=install_root,
                from_path=source.parent,
            )

            class FakeTransport:
                name = "direct"

                def deliver(self, _: DeployContext) -> CodeArtifact:
                    return CodeArtifact(source, "1.0.0", TransportName.DIRECT)

            pipeline = DeployPipeline(store, FakeTransport())
            with mock.patch("ir4_edge.deploy.pipeline.run_host") as host, mock.patch(
                "ir4_edge.deploy.pipeline.overlay_code"
            ) as overlay, mock.patch(
                "ir4_edge.deploy.pipeline.verify_pole",
                return_value=VerificationResult(ok=True, failures=[]),
            ):
                result = pipeline.run(ctx)
            self.assertTrue(result.already_current)
            host.assert_not_called()
            overlay.assert_not_called()
            store.close()

    def test_verification_failure_does_not_set_deployed_version(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            result, store = self._run_pipeline(Path(tmp), verify_ok=False)
            try:
                self.assertFalse(result.ok)
                self.assertEqual(store.get_deployed_version(), "")
            finally:
                store.close()

    def test_downgrade_refused(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            result, _store = self._run_pipeline(Path(tmp), installed="2.0.0")
            self.assertFalse(result.ok)
            self.assertIn("downgrade", result.message)


class DeployServiceTest(unittest.TestCase):
    def test_retry_on_transient_failure(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            install_root = Path(tmp) / "opt"
            install_root.mkdir()
            ctx = DeployContext(
                pole=2,
                kind=OperationKind.UPDATE,
                transport=TransportName.DIRECT,
                install_root=install_root,
            )
            service = DeployService(install_root)
            attempts = {"n": 0}

            def fake_run(inner_ctx: DeployContext):
                attempts["n"] += 1
                if attempts["n"] < 2:
                    from ir4_edge.deploy.models import DeployResult

                    return DeployResult(
                        ok=False,
                        status=DeployStatus.FAILED,
                        operation_id=inner_ctx.operation_id,
                        message="transient",
                    )
                from ir4_edge.deploy.models import DeployResult

                return DeployResult(
                    ok=True,
                    status=DeployStatus.SUCCESS,
                    operation_id=inner_ctx.operation_id,
                    message="ok",
                )

            with mock.patch.object(DeployPipeline, "run", side_effect=fake_run):
                with mock.patch.object(service.store, "exclusive_lock"):
                    result = service.run(ctx)
            self.assertTrue(result.ok)
            self.assertEqual(attempts["n"], 2)
            service.close()


if __name__ == "__main__":
    unittest.main()
