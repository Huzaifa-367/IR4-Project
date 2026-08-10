"""ir4-edge — install, setup, and day-2 ops (gas / RFID stay independent)."""

from __future__ import annotations

import argparse
import os
import shutil
import subprocess
import sys
from pathlib import Path
from typing import List, Optional, Sequence, Tuple

from ir4_edge.common.config import config_dir, edge_root, load_secrets, load_yaml, var_dir


def _run(cmd: Sequence[str], *, check: bool = False) -> int:
    print("+ {}".format(" ".join(cmd)))
    code = subprocess.run(list(cmd), check=False).returncode
    if check and code != 0:
        raise SystemExit(code)
    return code


def _systemctl(*args: str, check: bool = False) -> int:
    return _run(["systemctl", *args], check=check)


def _services() -> Tuple[bool, bool]:
    path = config_dir() / "edge.yaml"
    if not path.is_file():
        return True, True
    services = dict(load_yaml(path).get("services") or {})
    return bool(services.get("gas", True)), bool(services.get("rfid", True))


def _units() -> List[str]:
    gas_on, rfid_on = _services()
    units: List[str] = []
    if gas_on:
        units.append("ir4-gas-agent")
    if rfid_on:
        units.append("ir4-rfid-agent")
    return units


def _status_units() -> List[str]:
    units = _units()
    _, rfid_on = _services()
    if rfid_on:
        units = units + ["mosquitto"]
    return units


def cmd_install(_: argparse.Namespace) -> int:
    return _run(["sudo", str(edge_root() / "deploy" / "orin_bootstrap.sh")], check=True)


def cmd_setup(args: argparse.Namespace) -> int:
    cmd = [str(edge_root() / "scripts" / "configure.sh")]
    if args.up:
        cmd.append("--up")
    return _run(cmd, check=True)


def cmd_up(_: argparse.Namespace) -> int:
    return _run(["sudo", str(edge_root() / "scripts" / "enable_services.sh")], check=True)


def cmd_down(_: argparse.Namespace) -> int:
    units = _units()
    return _systemctl("disable", "--now", *units, check=True) if units else 0


def cmd_status(_: argparse.Namespace) -> int:
    units = _status_units()
    return _systemctl("status", "--no-pager", "--full", *units) if units else 0


def cmd_restart(_: argparse.Namespace) -> int:
    units = _units()
    return _systemctl("restart", *units, check=True) if units else 1


def cmd_logs(args: argparse.Namespace) -> int:
    units = _units()
    if not units:
        print("No agents enabled in edge.yaml", file=sys.stderr)
        return 1
    cmd = ["journalctl", "--no-pager", "-n", str(args.lines)]
    for unit in units:
        cmd.extend(["-u", unit])
    if args.follow:
        cmd.append("-f")
    return _run(cmd)


def _secret_status(key: str) -> Tuple[bool, str]:
    value = os.environ.get(key) or ""
    if value:
        return True, "set"
    secrets = config_dir() / "secrets.env"
    try:
        readable = secrets.is_file() and os.access(secrets, os.R_OK)
    except OSError:
        readable = False
    if not readable:
        return False, "secrets.env unreadable"
    return False, "empty — ir4-edge setup"


def cmd_doctor(_: argparse.Namespace) -> int:
    load_secrets()
    gas_on, rfid_on = _services()
    print("== ir4-edge doctor ==")
    print("root   ", edge_root())
    print("config ", config_dir())
    print("var    ", var_dir())
    print("gas={} rfid={}".format(gas_on, rfid_on))
    print()
    checks: List[Tuple[str, bool, str]] = [
        ("edge.yaml", (config_dir() / "edge.yaml").is_file(), ""),
        ("secrets.env", (config_dir() / "secrets.env").is_file(), ""),
        ("IR4_BASE_URL", bool(os.environ.get("IR4_BASE_URL")), os.environ.get("IR4_BASE_URL", "")),
    ]
    if gas_on:
        checks.append(("gas.yaml", (config_dir() / "gas.yaml").is_file(), ""))
        ok, detail = _secret_status("IR4_GAS_DEVICE_TOKEN")
        checks.append(("GAS token", ok, detail))
        ok, detail = _secret_status("IR4_GAS_DEVICE_UUID")
        uuid = os.environ.get("IR4_GAS_DEVICE_UUID") or detail
        checks.append(("GAS uuid", ok, uuid if ok else detail))
        port = "/dev/yt98h-rs485"
        try:
            port = str((load_yaml(config_dir() / "gas.yaml").get("serial") or {}).get("port") or port)
        except Exception:
            pass
        checks.append(("serial", Path(port).exists(), port))
    if rfid_on:
        checks.append(("rfid.yaml", (config_dir() / "rfid.yaml").is_file(), ""))
        ok, detail = _secret_status("IR4_RFID_DEVICE_TOKEN")
        checks.append(("RFID token", ok, detail))
        ok, detail = _secret_status("IR4_RFID_DEVICE_UUID")
        uuid = os.environ.get("IR4_RFID_DEVICE_UUID") or detail
        checks.append(("RFID uuid", ok, uuid if ok else detail))
        checks.append(("MQTT USE_AUTH", True, os.environ.get("IR4_MQTT_USE_AUTH", "0")))
        checks.append(("mosquitto", shutil.which("mosquitto") is not None, ""))
    failed = 0
    for name, ok, detail in checks:
        if not ok:
            failed += 1
        print("[{}] {}{}".format("PASS" if ok else "FAIL", name, (" — " + detail) if detail else ""))
    if shutil.which("systemctl"):
        print()
        for unit in _status_units():
            active = subprocess.run(["systemctl", "is-active", unit], capture_output=True, text=True, check=False)
            enabled = subprocess.run(["systemctl", "is-enabled", unit], capture_output=True, text=True, check=False)
            print("systemd {:<16} {} / {}".format(
                unit,
                (active.stdout or "").strip(),
                (enabled.stdout or "").strip(),
            ))
    return 1 if failed else 0


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(prog="ir4-edge", description="IR4 Edge — install, setup, run")
    sub = parser.add_subparsers(dest="command", required=True)
    sub.add_parser("install", help="Bootstrap host (sudo)").set_defaults(func=cmd_install)
    setup = sub.add_parser("setup", help="Interactive secrets (enabled agents only)", aliases=["configure"])
    setup.add_argument("--up", action="store_true", help="Enable/start after setup")
    setup.set_defaults(func=cmd_setup)
    sub.add_parser("up", help="Enable + start agents", aliases=["enable"]).set_defaults(func=cmd_up)
    sub.add_parser("down", help="Disable + stop agents", aliases=["disable"]).set_defaults(func=cmd_down)
    sub.add_parser("status", help="systemd status").set_defaults(func=cmd_status)
    sub.add_parser("restart", help="Restart enabled agents").set_defaults(func=cmd_restart)
    sub.add_parser("doctor", help="Health checks").set_defaults(func=cmd_doctor)
    sub.add_parser("apply", help="Re-run install from configs").set_defaults(func=cmd_install)
    logs = sub.add_parser("logs", help="Agent journals")
    logs.add_argument("-f", "--follow", action="store_true")
    logs.add_argument("-n", "--lines", type=int, default=80)
    logs.set_defaults(func=cmd_logs)
    return parser


def main(argv: Optional[List[str]] = None) -> None:
    args = build_parser().parse_args(argv)
    try:
        raise SystemExit(int(args.func(args)))
    except PermissionError as exc:
        print(
            "Permission denied ({}).\n"
            "Fix: sudo chown -R \"$USER:ir4edge\" configs && sudo chmod 640 configs/secrets.env".format(exc),
            file=sys.stderr,
        )
        raise SystemExit(1)
    except KeyboardInterrupt:
        raise SystemExit(130)


if __name__ == "__main__":
    main()
