#!/usr/bin/env bash
# Enable and start IR4 agents selected in configs/edge.yaml.
# Prefer: sudo ir4-edge up
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
EDGE_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
[[ "$(id -u)" -eq 0 ]] || { echo "Use sudo" >&2; exit 1; }
# shellcheck source=/dev/null
source "${EDGE_ROOT}/deploy/host_lib.sh"
resolve_canonical_paths "${EDGE_ROOT}"
export EDGE_ROOT INSTALL_ROOT CONFIG_DIR EDGE_USER
exec bash "${EDGE_ROOT}/deploy/host.sh" enable-services
