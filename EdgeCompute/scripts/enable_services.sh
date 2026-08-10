#!/usr/bin/env bash
# Enable/start only agents selected in edge.yaml. Prefer: ir4-edge up
set -euo pipefail
EDGE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG_DIR="${EDGE_ROOT}/configs"
# shellcheck source=/dev/null
source "${EDGE_ROOT}/deploy/lib.sh"
load_edge_yaml
INSTALL_ROOT="${IR4_EDGE_INSTALL_ROOT:-${EDGE_INSTALL_ROOT}}"
EDGE_USER="${IR4_EDGE_USER:-${EDGE_SERVICE_USER}}"
[[ "$(id -u)" -eq 0 ]] || { echo "Use sudo" >&2; exit 1; }

if [[ "${EDGE_ENABLE_GAS}" == "true" ]]; then
  render_unit "${EDGE_ROOT}/deploy/systemd/ir4-gas-agent.service.in" \
    /etc/systemd/system/ir4-gas-agent.service
fi
if [[ "${EDGE_ENABLE_RFID}" == "true" ]]; then
  render_unit "${EDGE_ROOT}/deploy/systemd/ir4-rfid-agent.service.in" \
    /etc/systemd/system/ir4-rfid-agent.service
fi
EDGE_AUTO_START="true"
enable_selected_services
