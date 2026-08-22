"""Post-deploy verification — must pass before marking success."""

from __future__ import annotations

import json
import os
import subprocess
from dataclasses import dataclass
from typing import List

from ir4_edge.common.install_paths import canonical_edge_root, install_root, var_dir


@dataclass(frozen=True)
class VerificationResult:
    ok: bool
    failures: List[str]


_VERIFY_SCRIPT = """
import json
from ir4_edge.doctor import run_checks
rows = [(c.name, c.ok, c.detail or "") for c in run_checks()]
print(json.dumps(rows))
"""


def _verify_env() -> dict:
    env = dict(os.environ)
    env.pop("PYTHONPATH", None)
    env["IR4_EDGE_CONFIG_DIR"] = str(canonical_edge_root() / "configs")
    env["IR4_EDGE_VAR_DIR"] = str(var_dir())
    return env


def verify_pole() -> VerificationResult:
    """Run doctor in the installed venv (clean env — not the SCC payload PYTHONPATH)."""
    venv_py = install_root() / "venv" / "bin" / "python"
    if venv_py.is_file():
        result = subprocess.run(
            [str(venv_py), "-c", _VERIFY_SCRIPT],
            env=_verify_env(),
            capture_output=True,
            text=True,
            check=False,
        )
        if result.returncode != 0:
            detail = (result.stderr or result.stdout or "verify subprocess failed").strip()
            return VerificationResult(ok=False, failures=[detail])
        try:
            rows = json.loads(result.stdout.strip() or "[]")
        except json.JSONDecodeError:
            return VerificationResult(ok=False, failures=["invalid verify output"])
        failures = [
            "{}: {}".format(name, detail or "failed")
            for name, ok, detail in rows
            if not ok
        ]
        return VerificationResult(ok=not failures, failures=failures)

    from ir4_edge.doctor import run_checks

    checks = run_checks()
    failures = [
        "{}: {}".format(check.name, check.detail or "failed")
        for check in checks
        if not check.ok
    ]
    return VerificationResult(ok=not failures, failures=failures)
