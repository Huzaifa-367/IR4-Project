#!/usr/bin/env bash
# Extract/install the offline bundle onto this Orin. No internet.
#
# From the tarball:
#   sudo ./install.sh --pole 2
#
# Resulting layout (same as a live pole):
#   /opt/ir4-edge/EdgeCompute
#   /opt/ir4-edge/venv
#   /opt/ir4-edge/var
#   /opt/ir4-edge/wheels
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
POLE=""
DEST="/opt/ir4-edge"

usage() {
  cat <<'EOF'
Usage: sudo ./install.sh --pole 1|2|3|4
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --pole)
      POLE="${2:-}"
      shift 2
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown argument: $1" >&2
      usage >&2
      exit 1
      ;;
  esac
done

[[ "$(id -u)" -eq 0 ]] || { echo "Use sudo" >&2; exit 1; }
[[ "${POLE}" =~ ^[1-4]$ ]] || { echo "Need --pole 1-4" >&2; usage >&2; exit 1; }

if [[ ! -d "${SCRIPT_DIR}/EdgeCompute" ]]; then
  echo "ERROR: run from the extracted ir4-edge-offline folder (or SCC online payload)" >&2
  exit 1
fi
SRC_CODE="${SCRIPT_DIR}/EdgeCompute"
SRC_WHEELS="${SCRIPT_DIR}/wheels"
SRC_VAR="${SCRIPT_DIR}/var"
HAS_WHEELS=0
if [[ -d "${SRC_WHEELS}" ]] && ls "${SRC_WHEELS}"/* >/dev/null 2>&1; then
  HAS_WHEELS=1
fi

# shellcheck source=/dev/null
source "${SRC_CODE}/deploy/lib.sh"
EDGE_ROOT="${SRC_CODE}"
CONFIG_DIR="${EDGE_ROOT}/configs"
load_edge_yaml
INSTALL_ROOT="${IR4_EDGE_INSTALL_ROOT:-${EDGE_INSTALL_ROOT}}"
ensure_canonical_code_tree

LIVE="${INSTALL_ROOT}/EdgeCompute"
PAD="$(printf '%02d' "${POLE}")"
KEEP_SECRETS=0
[[ -f "${LIVE}/configs/secrets.env" ]] && KEEP_SECRETS=1

echo "==> ir4-edge bundle install pole ${POLE}"
echo "    from ${SCRIPT_DIR}"
echo "    onto ${INSTALL_ROOT}"
mkdir -p "${INSTALL_ROOT}/var" "${INSTALL_ROOT}/wheels"

echo "==> Overlay code (keep secrets.env=${KEEP_SECRETS})"
rsync -a --delete \
  --exclude 'configs/secrets.env' \
  --exclude '.git/' --exclude '__pycache__/' \
  "${SRC_CODE}/" "${LIVE}/"
if [[ "${HAS_WHEELS}" -eq 1 ]]; then
  rsync -a "${SRC_WHEELS}/" "${INSTALL_ROOT}/wheels/"
fi
if [[ -d "${SRC_VAR}" ]]; then
  rsync -a --ignore-existing "${SRC_VAR}/" "${INSTALL_ROOT}/var/"
fi

if [[ "${KEEP_SECRETS}" -eq 0 ]]; then
  echo "==> First install: secrets.pole-${PAD}.env"
  cp "${LIVE}/configs/secrets.pole-${PAD}.env" "${LIVE}/configs/secrets.env"
fi

EDGE_ROOT="${LIVE}"
CONFIG_DIR="${LIVE}/configs"
export IR4_EDGE_WHEELHOUSE="${INSTALL_ROOT}/wheels"

if [[ ! -x "${INSTALL_ROOT}/venv/bin/ir4-edge" ]]; then
  if [[ "${HAS_WHEELS}" -eq 0 ]]; then
    echo "ERROR: first install needs wheels — run ./deploy/pack_bundle.sh then copy the tarball" >&2
    exit 1
  fi
  cd "${LIVE}"
  ./deploy/orin_bootstrap.sh
else
  echo "==> Refresh package + systemd (keeps secrets.env)"
  if ! refresh_edge_package; then
    cd "${LIVE}"
    ./deploy/orin_bootstrap.sh
  else
    render_systemd_units
    link_cli_tools
    fix_config_permissions
  fi
fi

IR4E="${INSTALL_ROOT}/venv/bin/ir4-edge"
if [[ "${KEEP_SECRETS}" -eq 0 ]]; then
  "${IR4E}" secrets --pole "${POLE}"
fi
if id ir4edge >/dev/null 2>&1; then
  chown ir4edge:ir4edge "${LIVE}/configs/secrets.env" || true
fi
chmod 640 "${LIVE}/configs/secrets.env"
grep -E '^(IR4_BASE_URL|APP_TIMEZONE|IR4_GAS_DEVICE_REF|IR4_RFID_READER_REF|IR4_RFID_MQTT_TOPIC)=' \
  "${LIVE}/configs/secrets.env"

echo "==> Enable agents + render systemd units"
"${IR4E}" up

echo "==> Health check"
cd "${LIVE}"
"${IR4E}" doctor || true
echo "==> Done. Layout: ${INSTALL_ROOT}/{EdgeCompute,venv,var,wheels}"
