"""CLI controller for pole deploy and SCC push."""

from __future__ import annotations

import argparse
import os
import sys
from pathlib import Path
from typing import List, Optional

from ir4_edge.common.install_paths import canonical_edge_root, install_root
from ir4_edge.common.logging_setup import setup_logging
from ir4_edge.deploy.models import DeployContext, OperationKind, TransportName
from ir4_edge.deploy.service import DeployService
from ir4_edge.deploy.transport.scc import SccPushCoordinator
from ir4_edge.doctor import print_report, run_checks


def _require_root() -> None:
    if os.geteuid() != 0:
        print("This command must run as root (sudo).", file=sys.stderr)
        raise SystemExit(1)


def _pole_apply(args: argparse.Namespace) -> int:
    _require_root()
    transport = TransportName(args.transport)
    kind = OperationKind.INSTALL if args.install else OperationKind.UPDATE
    if args.apply:
        kind = OperationKind.UPDATE
    ctx = DeployContext(
        pole=args.pole,
        kind=kind,
        transport=transport,
        install_root=install_root(),
        branch=args.branch,
        repo_url=args.repo_url,
        payload_dir=Path(args.payload) if args.payload else None,
        from_path=Path(args.from_path) if args.from_path else None,
        force=args.force,
    )
    service = DeployService(ctx.install_root)
    try:
        if args.apply:
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


def _pole_status(_: argparse.Namespace) -> int:
    service = DeployService(install_root())
    try:
        print(service.status_text())
    finally:
        service.close()
    return 0


def _pole_verify(_: argparse.Namespace) -> int:
    service = DeployService(install_root())
    try:
        result = service.verify_only()
    finally:
        service.close()
    if result.verification_failures:
        for line in result.verification_failures:
            print("FAIL:", line)
    return 0 if result.ok else 1


def _scc_push(args: argparse.Namespace) -> int:
    edge = Path(args.edge_root) if args.edge_root else _find_edge_root()
    poles: Optional[List[int]] = None
    if args.poles:
        poles = [int(x.strip()) for x in args.poles.split(",") if x.strip()]
    coordinator = SccPushCoordinator(edge)
    return coordinator.push(poles, pack_wheels=args.pack)


def _find_edge_root() -> Path:
    candidate = Path(__file__).resolve().parents[2]
    if (candidate / "pyproject.toml").is_file():
        return candidate
    live = canonical_edge_root()
    if live.is_dir():
        return live
    raise SystemExit("Cannot locate EdgeCompute root")


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(prog="ir4-edge-deploy", description="IR4 pole install/update")
    sub = parser.add_subparsers(dest="command", required=True)

    apply_cmd = sub.add_parser("apply", help="Install or update on this pole (auto-detect)")
    apply_cmd.add_argument("--pole", type=int, required=True, choices=(1, 2, 3, 4))
    apply_cmd.add_argument("--transport", choices=("direct", "scc"), default="scc")
    apply_cmd.add_argument("--payload", default="", help="SCC offline payload directory")
    apply_cmd.add_argument("--from", dest="from_path", default="")
    apply_cmd.add_argument("--branch", default="main")
    apply_cmd.add_argument("--repo-url", default="https://github.com/Huzaifa-367/IR4-Project.git")
    apply_cmd.add_argument("--force", action="store_true")
    apply_cmd.set_defaults(func=_pole_apply, apply=True, install=False)

    install_cmd = sub.add_parser("install", help="Fresh install on pole (direct transport)")
    install_cmd.add_argument("--pole", type=int, required=True, choices=(1, 2, 3, 4))
    install_cmd.add_argument("--transport", choices=("direct", "scc"), default="direct")
    install_cmd.add_argument("--payload", default="")
    install_cmd.add_argument("--from", dest="from_path", default="")
    install_cmd.add_argument("--branch", default="main")
    install_cmd.add_argument("--repo-url", default="https://github.com/Huzaifa-367/IR4-Project.git")
    install_cmd.add_argument("--force", action="store_true")
    install_cmd.set_defaults(func=_pole_apply, apply=False, install=True)

    update_cmd = sub.add_parser("update", help="Update pole (direct transport)")
    update_cmd.add_argument("--pole", type=int, required=True, choices=(1, 2, 3, 4))
    update_cmd.add_argument("--transport", choices=("direct", "scc"), default="direct")
    update_cmd.add_argument("--payload", default="")
    update_cmd.add_argument("--from", dest="from_path", default="")
    update_cmd.add_argument("--branch", default="main")
    update_cmd.add_argument("--repo-url", default="https://github.com/Huzaifa-367/IR4-Project.git")
    update_cmd.add_argument("--force", action="store_true")
    update_cmd.set_defaults(func=_pole_apply, apply=False, install=False)

    sub.add_parser("status", help="Show deploy state").set_defaults(func=_pole_status)
    sub.add_parser("verify", help="Run doctor verification only").set_defaults(func=_pole_verify)

    scc = sub.add_parser("scc-push", help="SCC → pole push (run on SCC)")
    scc.add_argument("--edge-root", default="")
    scc.add_argument("--poles", default="", help="Comma-separated pole numbers, default all")
    scc.add_argument("--pack", action="store_true", help="Run pack_bundle for wheels first")
    scc.set_defaults(func=_scc_push)

    return parser


def main(argv: Optional[List[str]] = None) -> None:
    setup_logging(os.environ.get("IR4_EDGE_LOG_LEVEL", "INFO"), "ir4_edge.deploy")
    args = build_parser().parse_args(argv)
    raise SystemExit(int(args.func(args)))


if __name__ == "__main__":
    main()
