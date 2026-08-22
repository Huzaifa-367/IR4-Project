"""Install path resolution — canonical layout under edge.yaml."""

from __future__ import annotations

import os
import tempfile
import unittest
from pathlib import Path
from unittest import mock

from ir4_edge.common.config import (
    canonical_edge_root,
    config_dir,
    edge_root,
    install_root,
    var_dir,
)


class InstallPathsTest(unittest.TestCase):
    def test_defaults_without_edge_yaml(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            config = Path(tmp) / "configs"
            config.mkdir()
            with mock.patch.dict(os.environ, {"IR4_EDGE_CONFIG_DIR": str(config)}, clear=False):
                os.environ.pop("IR4_EDGE_VAR_DIR", None)
                self.assertEqual(install_root(), Path("/opt/ir4-edge"))
                self.assertEqual(canonical_edge_root(), Path("/opt/ir4-edge/EdgeCompute"))
                self.assertEqual(config_dir(), config)
                self.assertEqual(edge_root(), config.parent)
                self.assertEqual(var_dir(), Path("/opt/ir4-edge/var"))

    def test_custom_install_root_from_edge_yaml(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            config = Path(tmp) / "configs"
            config.mkdir()
            (config / "edge.yaml").write_text(
                'install:\n  root: "/opt/custom"\n',
                encoding="utf-8",
            )
            with mock.patch.dict(
                os.environ,
                {"IR4_EDGE_CONFIG_DIR": str(config), "IR4_EDGE_VAR_DIR": "/opt/custom/var"},
                clear=False,
            ):
                self.assertEqual(install_root(), Path("/opt/custom"))
                self.assertEqual(canonical_edge_root(), Path("/opt/custom/EdgeCompute"))
                self.assertEqual(var_dir(), Path("/opt/custom/var"))

    def test_canonical_paths_without_env_override(self) -> None:
        env = os.environ.copy()
        for key in ("IR4_EDGE_CONFIG_DIR", "IR4_EDGE_VAR_DIR"):
            env.pop(key, None)
        with mock.patch.dict(os.environ, env, clear=True):
            self.assertEqual(canonical_edge_root(), Path("/opt/ir4-edge/EdgeCompute"))
            self.assertEqual(edge_root(), Path("/opt/ir4-edge/EdgeCompute"))
            self.assertEqual(var_dir(), Path("/opt/ir4-edge/var"))


if __name__ == "__main__":
    unittest.main()
