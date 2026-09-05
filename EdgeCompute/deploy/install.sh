#!/usr/bin/env bash
# Pole-side entry for SCC/USB offline payload. Runs the deploy controller.
set -euo pipefail

POLE=""
PAYLOAD="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

usage() {
  echo "Usage: sudo ./install.sh --pole 1|2|3|4|5|6|7|8" >&2
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --pole) POLE="${2:-}"; shift 2 ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown: $1" >&2; usage; exit 1 ;;
  esac
done

[[ "$(id -u)" -eq 0 ]] || { echo "Use sudo" >&2; exit 1; }
[[ "${POLE}" =~ ^[1-8]$ ]] || { usage; exit 1; }

EDGE="${PAYLOAD}/EdgeCompute"
# Always prefer the payload tree for this apply (venv may still be an older
# ir4_edge that rejects poles 5–8 until pip-install finishes).
export PYTHONPATH="${EDGE}${PYTHONPATH:+:${PYTHONPATH}}"
PY=python3
if [[ -x /opt/ir4-edge/venv/bin/python ]]; then
  PY=/opt/ir4-edge/venv/bin/python
fi

exec "${PY}" -m ir4_edge.deploy.controller apply \
  --transport scc \
  --pole "${POLE}" \
  --payload "${PAYLOAD}"
