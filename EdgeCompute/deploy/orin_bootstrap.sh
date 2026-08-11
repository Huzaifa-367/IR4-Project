#!/usr/bin/env bash
# Install IR4 edge on Orin. Prefer: sudo ir4-edge install
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
EDGE_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
CONFIG_DIR="${EDGE_ROOT}/configs"
# shellcheck source=/dev/null
source "${SCRIPT_DIR}/lib.sh"

load_edge_yaml
load_secret_env

INSTALL_ROOT="${IR4_EDGE_INSTALL_ROOT:-${EDGE_INSTALL_ROOT}}"
EDGE_USER="${IR4_EDGE_USER:-${EDGE_SERVICE_USER}}"

echo "==> ir4-edge install"
echo "    source=${EDGE_ROOT}"
echo "    root=${INSTALL_ROOT}  user=${EDGE_USER}"
echo "    gas=${EDGE_ENABLE_GAS} rfid=${EDGE_ENABLE_RFID} mqtt_anon=${EDGE_MQTT_ANONYMOUS}"

[[ "$(id -u)" -eq 0 ]] || { echo "Use sudo" >&2; exit 1; }

pkg_ok() { dpkg-query -W -f='${Status}' "$1" 2>/dev/null | grep -q 'install ok installed'; }

ensure_host_packages() {
  local missing=()
  local pkg
  for pkg in "$@"; do
    pkg_ok "${pkg}" || missing+=("${pkg}")
  done
  if [[ ${#missing[@]} -eq 0 ]]; then
    echo "==> Host packages already installed — skipping apt"
    return 0
  fi

  echo "==> Need packages: ${missing[*]}"
  echo "    (Jetson tip: apt often fails on a broken nvidia-l4t-kernel; we try install then continue if python works)"
  export DEBIAN_FRONTEND=noninteractive
  # Do not run apt-get update first — on broken L4T it triggers kernel postinst and aborts.
  if ! apt-get install -y -qq --no-upgrade "${missing[@]}"; then
    echo "WARNING: apt-get install failed (common on Jetson with unconfigured nvidia-l4t-kernel)." >&2
    if ! command -v python3 >/dev/null || ! python3 -c 'import venv' 2>/dev/null; then
      echo "ERROR: python3/venv missing and apt cannot install them." >&2
      echo "       Fix Jetson dpkg first, e.g. hold the broken kernel packages:" >&2
      echo "         sudo apt-mark hold nvidia-l4t-kernel nvidia-l4t-kernel-headers nvidia-l4t-kernel-dtbs nvidia-l4t-display-kernel" >&2
      echo "         sudo dpkg --configure -a   # or reflash/repair L4T if that still fails" >&2
      exit 1
    fi
    echo "    python3 present — continuing without apt."
    if [[ "${EDGE_ENABLE_RFID}" == "true" ]] && ! command -v mosquitto >/dev/null; then
      echo "ERROR: mosquitto not installed and apt failed. Install Mosquitto manually or set services.rfid: false." >&2
      exit 1
    fi
  fi
}

PKGS=(python3 python3-venv python3-pip ca-certificates)
[[ "${EDGE_ENABLE_RFID}" == "true" ]] && PKGS+=(mosquitto mosquitto-clients)
ensure_host_packages "${PKGS[@]}"

if ! id -u "${EDGE_USER}" >/dev/null 2>&1; then
  useradd --system --create-home --home-dir "/home/${EDGE_USER}" \
    --shell /usr/sbin/nologin --groups dialout "${EDGE_USER}"
else
  usermod -aG dialout "${EDGE_USER}"
fi
[[ -n "${SUDO_USER:-}" ]] && usermod -aG dialout "${SUDO_USER}" || true

mkdir -p "${INSTALL_ROOT}/var"
ln -sfn "${EDGE_ROOT}" "${INSTALL_ROOT}/EdgeCompute"
python3 -m venv "${INSTALL_ROOT}/venv"
"${INSTALL_ROOT}/venv/bin/pip" install -q --upgrade pip
"${INSTALL_ROOT}/venv/bin/pip" install -q -e "${EDGE_ROOT}"

if [[ ! -x "${INSTALL_ROOT}/venv/bin/ir4-edge" ]]; then
  echo "ERROR: pip install did not create ${INSTALL_ROOT}/venv/bin/ir4-edge" >&2
  echo "       Check that ${EDGE_ROOT}/pyproject.toml has [project.scripts] ir4-edge." >&2
  exit 1
fi

# Link CLI immediately so a later Mosquitto/udev failure still leaves ir4-edge on PATH.
ln -sfn "${INSTALL_ROOT}/venv/bin/ir4-edge" /usr/local/bin/ir4-edge
if [[ "${EDGE_PATH_LINKS}" == "true" ]]; then
  [[ "${EDGE_ENABLE_GAS}" == "true" ]] && \
    ln -sfn "${INSTALL_ROOT}/venv/bin/ir4-gas-agent" /usr/local/bin/ir4-gas-agent
  [[ "${EDGE_ENABLE_RFID}" == "true" ]] && \
    ln -sfn "${INSTALL_ROOT}/venv/bin/ir4-rfid-agent" /usr/local/bin/ir4-rfid-agent
fi

install -d -m 0755 /etc/systemd/system

if [[ "${EDGE_ENABLE_GAS}" == "true" ]]; then
  install -m 0644 "${EDGE_ROOT}/deploy/udev/99-yt98h-rs485.rules" \
    /etc/udev/rules.d/99-yt98h-rs485.rules
  udevadm control --reload-rules; udevadm trigger || true
  render_unit "${EDGE_ROOT}/deploy/systemd/ir4-gas-agent.service.in" \
    /etc/systemd/system/ir4-gas-agent.service
fi

if [[ "${EDGE_ENABLE_RFID}" == "true" ]]; then
  install -d -m 0755 /etc/mosquitto/conf.d
  render_mosquitto_conf /etc/mosquitto/conf.d/ir4-edge.conf \
    "${EDGE_MQTT_LISTENER}" "${EDGE_MQTT_ANONYMOUS}"
  ensure_mosquitto_users \
    "${EDGE_MQTT_FXR90_USER}" "${EDGE_MQTT_AGENT_USER}" \
    "${IR4_MQTT_FXR90_PASSWORD:-}" "${IR4_MQTT_PASSWORD:-}"
  render_unit "${EDGE_ROOT}/deploy/systemd/ir4-rfid-agent.service.in" \
    /etc/systemd/system/ir4-rfid-agent.service
  start_mosquitto
fi

fix_config_permissions
enable_selected_services

echo
echo "==> Linked CLI:"
ls -la /usr/local/bin/ir4-edge "${INSTALL_ROOT}/venv/bin/ir4-edge"
echo "==> Done. Run: hash -r && ir4-edge doctor"
echo "    (or: ${INSTALL_ROOT}/venv/bin/ir4-edge doctor)"
