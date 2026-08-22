#!/usr/bin/env bash
# SCC → pole deploy (Method 1). Thin wrapper around the deploy controller.
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
EDGE_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
POLES=""
PACK=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --poles) POLES="${2:-}"; shift 2 ;;
    --pack) PACK=1; shift ;;
    -h|--help)
      echo "Usage: deploy/scc_push.sh [--poles 1,2] [--pack]"
      exit 0
      ;;
    *) echo "Unknown: $1" >&2; exit 1 ;;
  esac
done

PY=python3
[[ -x "${EDGE_ROOT}/../venv/bin/python" ]] && PY="${EDGE_ROOT}/../venv/bin/python"
export PYTHONPATH="${EDGE_ROOT}${PYTHONPATH:+:${PYTHONPATH}}"

ARGS=(scc-push --edge-root "${EDGE_ROOT}")
[[ -n "${POLES}" ]] && ARGS+=(--poles "${POLES}")
[[ "${PACK}" -eq 1 ]] && ARGS+=(--pack)

exec "${PY}" -m ir4_edge.deploy.controller "${ARGS[@]}"
