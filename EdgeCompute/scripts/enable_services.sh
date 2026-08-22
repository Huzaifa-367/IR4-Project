#!/usr/bin/env bash
# Enable and start IR4 agents selected in configs/edge.yaml.
# Prefer: sudo ir4-edge up
#
# Always renders systemd units against /opt/ir4-edge/EdgeCompute (canonical install),
# even when this script is invoked from another checkout path.
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
source "${SCRIPT_DIR}/../deploy/lib.sh"
resolve_canonical_paths "${SCRIPT_DIR}/.."
[[ "$(id -u)" -eq 0 ]] || { echo "Use sudo" >&2; exit 1; }
[[ -d "${EDGE_ROOT}" ]] || {
  echo "ERROR: install tree missing at ${EDGE_ROOT}" >&2
  echo "       Copy EdgeCompute there, then: sudo ./deploy/orin_bootstrap.sh" >&2
  exit 1
}
render_systemd_units
EDGE_AUTO_START="true"
enable_selected_services
