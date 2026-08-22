"""``ir4-edge`` CLI — install, configure, and operate pole agents.

Commands map to day-2 ops on a Jetson:

    install / apply   Bootstrap venv + systemd (sudo, direct transport)
    update            Overlay latest code; keeps configs/secrets.env
    secrets --pole N  Copy pole N credentials into configs/secrets.env
    setup             Interactive secrets wizard
    up / down         Enable or disable systemd agents (respects edge.yaml)
    restart           Restart enabled agents
    status / logs     systemd status and journalctl
    deploy-status     SQLite deploy audit + deployed version
    verify            Post-deploy doctor gate (exit 1 on failure)
    doctor            Config, secrets, serial, and systemd layout checks
    scc push          SCC → pole offline push (run on SCC)

Gas and RFID are independent — toggled in ``configs/edge.yaml`` → ``services.*``.
"""

from __future__ import annotations

import argparse
import os
import subprocess
import sys
from pathlib import Path
from typing import List, Optional, Sequence

from ir4_edge.common.config import edge_root
from ir4_edge.common.credentials import apply_pole_secrets
from ir4_edge.common.install_paths import install_root
from ir4_edge.deploy.models import DeployContext, OperationKind, TransportName
from ir4_edge.deploy.service import DeployService
from ir4_edge.deploy.transport.scc import SccPushCoordinator
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


def _require_root() -> None:
    if os.geteuid() != 0:
        print("This command must run as root: sudo ir4-edge …", file=sys.stderr)
        raise SystemExit(1)


def _deploy_context(args: argparse.Namespace, kind: OperationKind) -> DeployContext:
    return DeployContext(
        pole=args.pole,
        kind=kind,
        transport=TransportName(getattr(args, "transport", "direct")),
        install_root=install_root(),
        branch=getattr(args, "branch", None) or "main",
        repo_url=getattr(args, "repo_url", None)
        or "https://github.com/Huzaifa-367/IR4-Project.git",
        payload_dir=None,
        from_path=Path(args.from_path) if getattr(args, "from_path", "") else None,
        force=getattr(args, "force", False),
    )


def _run_deploy(args: argparse.Namespace, kind: Optional[OperationKind]) -> int:
    _require_root()
    ctx = _deploy_context(args, kind or OperationKind.UPDATE)
    service = DeployService(install_root())
    try:
        if kind is None:
            result = service.run(ctx)
        else:
            result = service.run(ctx, kind=kind)
    finally:
        service.close()
    print(result.message)
    if result.verification_failures:
        for line in result.verification_failures:
            print("  FAIL:", line)
    if result.ok:
        print_report(run_checks())
    return 0 if result.ok else 1


def cmd_install(args: argparse.Namespace) -> int:
    return _run_deploy(args, OperationKind.INSTALL)


def cmd_apply(args: argparse.Namespace) -> int:
    return _run_deploy(args, None)


def cmd_update(args: argparse.Namespace) -> int:
    return _run_deploy(args, OperationKind.UPDATE)


def cmd_deploy_status(_: argparse.Namespace) -> int:
    service = DeployService(install_root())
    try:
        print(service.status_text())
    finally:
        service.close()
    return 0


def cmd_verify(_: argparse.Namespace) -> int:
    service = DeployService(install_root())
    try:
        result = service.verify_only()
    finally:
        service.close()
    if result.verification_failures:
        for line in result.verification_failures:
            print("FAIL:", line)
    return 0 if result.ok else 1


def cmd_scc_push(args: argparse.Namespace) -> int:
    poles: Optional[List[int]] = None
    if args.poles:
        poles = [int(x.strip()) for x in args.poles.split(",") if x.strip()]
    coordinator = SccPushCoordinator(edge_root())
    return coordinator.push(poles, pack_wheels=args.pack)


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

    pole_help = "Pole number 1–4"

    install = sub.add_parser("install", help="Fresh install on pole (direct transport, sudo)")
    install.add_argument("--pole", required=True, type=int, choices=(1, 2, 3, 4), help=pole_help)
    install.add_argument("--from", dest="from_path", default="", help="Local EdgeCompute or monorepo path")
    install.add_argument("--branch", default="main")
    install.add_argument("--force", action="store_true")
    install.set_defaults(func=cmd_install)

    apply_cmd = sub.add_parser("apply", help="Install or update (auto-detect, sudo)")
    apply_cmd.add_argument("--pole", required=True, type=int, choices=(1, 2, 3, 4), help=pole_help)
    apply_cmd.add_argument("--from", dest="from_path", default="")
    apply_cmd.add_argument("--branch", default="main")
    apply_cmd.add_argument("--force", action="store_true")
    apply_cmd.set_defaults(func=cmd_apply)

    update = sub.add_parser("update", help="Update pole over internet (direct transport, sudo)")
    update.add_argument("--pole", required=True, type=int, choices=(1, 2, 3, 4), help=pole_help)
    update.add_argument("--from", dest="from_path", default="", help="Local EdgeCompute or monorepo path")
    update.add_argument("--branch", default="main")
    update.add_argument("--force", action="store_true")
    update.set_defaults(func=cmd_update)

    sub.add_parser("deploy-status", help="Deploy SQLite state + deployed version").set_defaults(
        func=cmd_deploy_status
    )
    sub.add_parser("verify", help="Doctor gate — must pass before deploy success").set_defaults(func=cmd_verify)

    setup = sub.add_parser("setup", help="Interactive secrets (enabled agents only)", aliases=["configure"])
    setup.add_argument("--up", action="store_true", help="Enable/start after setup")
    setup.set_defaults(func=cmd_setup)

    secrets = sub.add_parser("secrets", help="Copy credentials.md into secrets.env for a pole")
    secrets.add_argument("--pole", required=True, type=int, choices=(1, 2, 3, 4), help=pole_help)
    secrets.set_defaults(func=cmd_secrets)

    sub.add_parser("up", help="Enable + start agents", aliases=["enable"]).set_defaults(func=cmd_up)
    sub.add_parser("down", help="Disable + stop agents", aliases=["disable"]).set_defaults(func=cmd_down)
    sub.add_parser("status", help="systemd status").set_defaults(func=cmd_status)
    sub.add_parser("restart", help="Restart enabled agents").set_defaults(func=cmd_restart)
    sub.add_parser("doctor", help="Health checks").set_defaults(func=cmd_doctor)

    scc = sub.add_parser("scc", help="SCC-side deploy commands")
    scc_sub = scc.add_subparsers(dest="scc_command", required=True)
    push = scc_sub.add_parser("push", help="Rsync payload to poles and apply")
    push.add_argument("--poles", default="", help="Comma-separated pole numbers, default all")
    push.add_argument("--pack", action="store_true", help="Run pack_bundle for wheels first")
    push.set_defaults(func=cmd_scc_push)

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
