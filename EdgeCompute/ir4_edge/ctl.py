"""``ir4-edge`` CLI — install, configure, and operate pole agents.

Commands map to day-2 ops on a Jetson:

    install / apply   Bootstrap venv + systemd (sudo, from /opt/ir4-edge/EdgeCompute)
    update            Overlay latest code; keeps configs/secrets.env
    secrets --pole N  Copy pole N credentials into configs/secrets.env
    setup             Interactive secrets wizard
    up / down         Enable or disable systemd agents (respects edge.yaml)
    restart           Restart enabled agents
    status / logs     systemd status and journalctl
    doctor            Config, secrets, serial, and systemd layout checks

Gas and RFID are independent — toggled in ``configs/edge.yaml`` → ``services.*``.
"""

from __future__ import annotations

import argparse
import subprocess
import sys
from typing import List, Optional, Sequence

from ir4_edge.common.config import edge_root
from ir4_edge.common.credentials import apply_pole_secrets
from ir4_edge.doctor import print_report, run_checks, services_enabled


def _run(cmd: Sequence[str], *, check: bool = False) -> int:
    print("+ {}".format(" ".join(cmd)))
    code = subprocess.run(list(cmd), check=False).returncode
    if check and code != 0:
        raise SystemExit(code)
    return code


def _systemctl(*args: str, check: bool = False) -> int:
    return _run(["systemctl", *args], check=check)


def _enabled_units() -> List[str]:
    gas_on, rfid_on = services_enabled()
    units: List[str] = []
    if gas_on:
        units.append("ir4-gas-agent")
    if rfid_on:
        units.append("ir4-rfid-agent")
    return units


def _status_units() -> List[str]:
    units = _enabled_units()
    _, rfid_on = services_enabled()
    if rfid_on:
        units.append("mosquitto")
    return units


def cmd_install(_: argparse.Namespace) -> int:
    return _run(["sudo", str(edge_root() / "deploy" / "orin_bootstrap.sh")], check=True)


def cmd_update(args: argparse.Namespace) -> int:
    cmd = ["sudo", str(edge_root() / "deploy" / "orin_update.sh")]
    if args.from_path:
        cmd.extend(["--from", args.from_path])
    if args.branch:
        cmd.extend(["--branch", args.branch])
    return _run(cmd, check=True)


def cmd_setup(args: argparse.Namespace) -> int:
    cmd = [str(edge_root() / "scripts" / "configure.sh")]
    if args.up:
        cmd.append("--up")
    return _run(cmd, check=True)


def cmd_up(_: argparse.Namespace) -> int:
    return _run(["sudo", str(edge_root() / "scripts" / "enable_services.sh")], check=True)


def cmd_down(_: argparse.Namespace) -> int:
    units = _enabled_units()
    return _systemctl("disable", "--now", *units, check=True) if units else 0


def cmd_status(_: argparse.Namespace) -> int:
    units = _status_units()
    return _systemctl("status", "--no-pager", "--full", *units) if units else 0


def cmd_restart(_: argparse.Namespace) -> int:
    units = _enabled_units()
    return _systemctl("restart", *units, check=True) if units else 1


def cmd_logs(args: argparse.Namespace) -> int:
    units = _enabled_units()
    if not units:
        print("No agents enabled in edge.yaml", file=sys.stderr)
        return 1
    cmd = ["journalctl", "--no-pager", "-n", str(args.lines)]
    for unit in units:
        cmd.extend(["-u", unit])
    if args.follow:
        cmd.append("-f")
    return _run(cmd)


def cmd_secrets(args: argparse.Namespace) -> int:
    dest = apply_pole_secrets(args.pole)
    try:
        dest.chmod(0o640)
    except OSError:
        pass
    print("Wrote {} from credentials.md (pole {:02d})".format(dest, args.pole))
    print("Next: ir4-edge doctor && sudo ir4-edge restart")
    return 0


def cmd_doctor(_: argparse.Namespace) -> int:
    return print_report(run_checks())


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(prog="ir4-edge", description="IR4 Edge — install, setup, run")
    sub = parser.add_subparsers(dest="command", required=True)
    sub.add_parser("install", help="Pole with internet: bootstrap venv + units (sudo)").set_defaults(func=cmd_install)
    setup = sub.add_parser("setup", help="Interactive secrets (enabled agents only)", aliases=["configure"])
    setup.add_argument("--up", action="store_true", help="Enable/start after setup")
    setup.set_defaults(func=cmd_setup)
    secrets = sub.add_parser("secrets", help="Copy credentials.md into secrets.env for a pole")
    secrets.add_argument("--pole", required=True, type=int, choices=(1, 2, 3, 4), help="Pole number 1–4")
    secrets.set_defaults(func=cmd_secrets)
    sub.add_parser("up", help="Enable + start agents", aliases=["enable"]).set_defaults(func=cmd_up)
    sub.add_parser("down", help="Disable + stop agents", aliases=["disable"]).set_defaults(func=cmd_down)
    sub.add_parser("status", help="systemd status").set_defaults(func=cmd_status)
    sub.add_parser("restart", help="Restart enabled agents").set_defaults(func=cmd_restart)
    sub.add_parser("doctor", help="Health checks").set_defaults(func=cmd_doctor)
    sub.add_parser("apply", help="Re-run install from configs").set_defaults(func=cmd_install)
    update = sub.add_parser("update", help="Pole with internet: overlay latest (keeps secrets.env)")
    update.add_argument("--from", dest="from_path", default="", help="Existing EdgeCompute or monorepo path")
    update.add_argument("--branch", default="", help="Git branch (default main)")
    update.set_defaults(func=cmd_update)
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
