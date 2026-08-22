"""Install path resolution — matches production systemd layout."""

from __future__ import annotations

import os
import tempfile
import unittest
from pathlib import Path
from unittest import mock

from ir4_edge.common.install_paths import canonical_edge_root, install_root


class InstallPathsTest(unittest.TestCase):
    def test_default_install_root_when_no_edge_yaml(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            config = Path(tmp) / "configs"
            config.mkdir()
            with mock.patch.dict(os.environ, {"IR4_EDGE_CONFIG_DIR": str(config)}):
                self.assertEqual(install_root(), Path("/opt/ir4-edge"))
                self.assertEqual(
                    canonical_edge_root(),
                    Path("/opt/ir4-edge/EdgeCompute"),
                )

    def test_custom_install_root_from_edge_yaml(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            config = Path(tmp) / "configs"
            config.mkdir()
            (config / "edge.yaml").write_text(
                'install:\n  root: "/opt/custom"\n',
                encoding="utf-8",
            )
            with mock.patch.dict(os.environ, {"IR4_EDGE_CONFIG_DIR": str(config)}):
                self.assertEqual(install_root(), Path("/opt/custom"))
                self.assertEqual(
                    canonical_edge_root(),
                    Path("/opt/custom/EdgeCompute"),
                )


if __name__ == "__main__":
    unittest.main()
