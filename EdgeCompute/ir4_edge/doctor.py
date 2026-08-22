"""Pre-flight checks for a pole Jetson — run via ``ir4-edge doctor``.

Validates config files, secrets, serial port presence, canonical code layout,
and that systemd unit files point at the install tree declared in edge.yaml.
"""

from __future__ import annotations

import os
import shutil
import subprocess
from dataclasses import dataclass
from pathlib import Path
from typing import List, Optional, Tuple

from ir4_edge.common.config import (
    canonical_edge_root,
    config_dir,
    edge_root,
    install_root,
    load_secrets,
    load_yaml,
    var_dir,
)


@dataclass(frozen=True)
class DoctorCheck:
    """One line in the doctor report."""

    name: str
    ok: bool
    detail: str = ""


def _secret_status(key: str) -> Tuple[bool, str]:
    """Return whether a secrets.env key is set (without printing the value)."""
    value = os.environ.get(key) or ""
    if value:
        return True, "set"
    secrets = config_dir() / "secrets.env"
    try:
        readable = secrets.is_file() and os.access(secrets, os.R_OK)
    except OSError:
        readable = False
    if not readable:
        return False, "secrets.env unreadable (agents use systemd EnvironmentFile; chmod 640)"
    return False, "empty — run: ir4-edge secrets --pole N"


def services_enabled() -> Tuple[bool, bool]:
    """Return (gas_enabled, rfid_enabled) from edge.yaml services flags."""
    path = config_dir() / "edge.yaml"
    if not path.is_file():
        return True, True
    services = dict(load_yaml(path).get("services") or {})
    return bool(services.get("gas", True)), bool(services.get("rfid", True))


def _systemd_working_directory(unit: str) -> Optional[str]:
    """Read WorkingDirectory from an installed systemd unit, if present."""
    if not shutil.which("systemctl"):
        return None
    result = subprocess.run(
        ["systemctl", "show", unit, "--property=WorkingDirectory", "--value"],
        capture_output=True,
        text=True,
        check=False,
    )
    value = (result.stdout or "").strip()
    return value if value and result.returncode == 0 else None


def _code_layout_check() -> DoctorCheck:
    """Ensure code lives at the canonical real directory (not a stray checkout/symlink)."""
    canonical = canonical_edge_root()
    if canonical.is_symlink():
        return DoctorCheck(
            "code layout",
            False,
            "{} is a symlink — run: sudo ir4-edge apply".format(canonical),
        )
    if not canonical.is_dir():
        return DoctorCheck("code layout", False, "missing {}".format(canonical))
    try:
        import ir4_edge.gas.modbus_rtu as modbus_module
        package_root = Path(modbus_module.__file__).resolve().parents[2]
    except Exception:
        package_root = edge_root().resolve()
    if package_root != canonical.resolve():
        return DoctorCheck(
            "code layout",
            False,
            "venv loads {} (expected {}) — run: sudo ir4-edge apply".format(
                package_root,
                canonical.resolve(),
            ),
        )
    return DoctorCheck("code layout", True, str(canonical.resolve()))


def _systemd_path_checks(gas_on: bool, rfid_on: bool) -> DoctorCheck:
    """Fail when agent units point at the wrong checkout directory."""
    expected = str(canonical_edge_root().resolve())
    issues: List[str] = []
    for unit, enabled in (("ir4-gas-agent", gas_on), ("ir4-rfid-agent", rfid_on)):
        if not enabled:
            continue
        wd = _systemd_working_directory(f"{unit}.service")
        if wd is None:
            issues.append("{}: not installed".format(unit))
        elif wd != expected:
            issues.append("{} WorkingDirectory={} (expected {})".format(unit, wd, expected))
    if issues:
        return DoctorCheck("systemd layout", False, "; ".join(issues))
    return DoctorCheck("systemd layout", True, expected)


def run_checks() -> List[DoctorCheck]:
    """Collect all doctor checks (does not print)."""
    load_secrets()
    gas_on, rfid_on = services_enabled()
    checks: List[DoctorCheck] = [
        DoctorCheck("edge.yaml", (config_dir() / "edge.yaml").is_file()),
        DoctorCheck("secrets.env", (config_dir() / "secrets.env").is_file()),
        DoctorCheck(
            "IR4_BASE_URL",
            bool(os.environ.get("IR4_BASE_URL")),
            os.environ.get("IR4_BASE_URL", ""),
        ),
        DoctorCheck(
            "APP_TIMEZONE",
            bool(os.environ.get("APP_TIMEZONE")),
            os.environ.get("APP_TIMEZONE", ""),
        ),
        _code_layout_check(),
    ]
    if gas_on:
        checks.append(DoctorCheck("gas.yaml", (config_dir() / "gas.yaml").is_file()))
        ok, detail = _secret_status("IR4_GAS_DEVICE_TOKEN")
        checks.append(DoctorCheck("GAS token", ok, detail))
        ok, detail = _secret_status("IR4_GAS_DEVICE_UUID")
        uuid = os.environ.get("IR4_GAS_DEVICE_UUID") or detail
        checks.append(DoctorCheck("GAS uuid", ok, uuid if ok else detail))
        port = "/dev/yt98h-rs485"
        try:
            port = str((load_yaml(config_dir() / "gas.yaml").get("serial") or {}).get("port") or port)
        except Exception:
            pass
        checks.append(DoctorCheck("serial", Path(port).exists(), port))
    if rfid_on:
        checks.append(DoctorCheck("rfid.yaml", (config_dir() / "rfid.yaml").is_file()))
        ok, detail = _secret_status("IR4_RFID_DEVICE_TOKEN")
        checks.append(DoctorCheck("RFID token", ok, detail))
        ok, detail = _secret_status("IR4_RFID_DEVICE_UUID")
        uuid = os.environ.get("IR4_RFID_DEVICE_UUID") or detail
        checks.append(DoctorCheck("RFID uuid", ok, uuid if ok else detail))
        checks.append(
            DoctorCheck("MQTT USE_AUTH", True, os.environ.get("IR4_MQTT_USE_AUTH", "0")),
        )
        checks.append(DoctorCheck("mosquitto", shutil.which("mosquitto") is not None))
    checks.append(_systemd_path_checks(gas_on, rfid_on))
    return checks


def print_report(checks: List[DoctorCheck]) -> int:
    """Print doctor output; return exit code 0 on success, 1 when any check failed."""
    gas_on, rfid_on = services_enabled()
    print("== ir4-edge doctor ==")
    print("install", install_root())
    print("code   ", canonical_edge_root())
    print("config ", config_dir())
    print("var    ", var_dir())
    print("gas={} rfid={}".format(gas_on, rfid_on))
    print()
    failed = 0
    for check in checks:
        if not check.ok:
            failed += 1
        suffix = " — {}".format(check.detail) if check.detail else ""
        print("[{}] {}{}".format("PASS" if check.ok else "FAIL", check.name, suffix))
    if shutil.which("systemctl"):
        print()
        units: List[str] = []
        if gas_on:
            units.append("ir4-gas-agent")
        if rfid_on:
            units.extend(["ir4-rfid-agent", "mosquitto"])
        for unit in units:
            active = subprocess.run(
                ["systemctl", "is-active", unit],
                capture_output=True,
                text=True,
                check=False,
            )
            enabled = subprocess.run(
                ["systemctl", "is-enabled", unit],
                capture_output=True,
                text=True,
                check=False,
            )
            print(
                "systemd {:<16} {} / {}".format(
                    unit,
                    (active.stdout or "").strip(),
                    (enabled.stdout or "").strip(),
                ),
            )
    return 1 if failed else 0
