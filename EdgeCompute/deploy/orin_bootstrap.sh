#!/usr/bin/env bash
# Orin J4012 (Ubuntu 20.04) host bootstrap for IR4 edge agents.
#   cd EdgeCompute && sudo ./deploy/orin_bootstrap.sh
# Then: ./scripts/configure.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
EDGE_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
INSTALL_ROOT="${IR4_EDGE_INSTALL_ROOT:-/opt/ir4-edge}"
EDGE_USER="${IR4_EDGE_USER:-ir4edge}"

echo "==> IR4 Orin edge bootstrap"
echo "    EdgeCompute source : ${EDGE_ROOT}"
echo "    Install root       : ${INSTALL_ROOT}"
echo "    Service user       : ${EDGE_USER}"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Re-run with sudo: sudo $0" >&2
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y \
  python3 \
  python3-venv \
  python3-pip \
  mosquitto \
  mosquitto-clients \
  git \
  ca-certificates

if ! id -u "${EDGE_USER}" >/dev/null 2>&1; then
  useradd --system --create-home --home-dir "/home/${EDGE_USER}" \
    --shell /usr/sbin/nologin --groups dialout "${EDGE_USER}"
else
  usermod -aG dialout "${EDGE_USER}"
fi

if [[ -n "${SUDO_USER:-}" ]]; then
  usermod -aG dialout "${SUDO_USER}" || true
fi

mkdir -p "${INSTALL_ROOT}/var"
if [[ ! -e "${INSTALL_ROOT}/EdgeCompute" ]]; then
  ln -sfn "${EDGE_ROOT}" "${INSTALL_ROOT}/EdgeCompute"
fi

python3 -m venv "${INSTALL_ROOT}/venv"
"${INSTALL_ROOT}/venv/bin/pip" install --upgrade pip
"${INSTALL_ROOT}/venv/bin/pip" install -e "${EDGE_ROOT}"

install -d -m 0755 /etc/mosquitto/conf.d
install -m 0644 "${EDGE_ROOT}/deploy/mosquitto/ir4-edge.conf" /etc/mosquitto/conf.d/ir4-edge.conf

if [[ ! -f /etc/mosquitto/ir4_passwd ]]; then
  echo "==> Creating Mosquitto users (you will be prompted for passwords)"
  mosquitto_passwd -c /etc/mosquitto/ir4_passwd fxr90
  mosquitto_passwd /etc/mosquitto/ir4_passwd ir4-rfid
  chown mosquitto:mosquitto /etc/mosquitto/ir4_passwd
  chmod 0640 /etc/mosquitto/ir4_passwd
else
  echo "==> Mosquitto password file already exists; leaving it unchanged"
fi

install -m 0644 "${EDGE_ROOT}/deploy/udev/99-yt98h-rs485.rules" /etc/udev/rules.d/99-yt98h-rs485.rules
udevadm control --reload-rules
udevadm trigger || true

install -d -m 0755 /etc/systemd/system
install -m 0644 "${EDGE_ROOT}/deploy/systemd/ir4-gas-agent.service" /etc/systemd/system/ir4-gas-agent.service
install -m 0644 "${EDGE_ROOT}/deploy/systemd/ir4-rfid-agent.service" /etc/systemd/system/ir4-rfid-agent.service

# Main configs already live in the repo (gas.yaml / rfid.yaml / secrets.env).
chmod 600 "${EDGE_ROOT}/configs/secrets.env" || true
if [[ -f "${EDGE_ROOT}/configs/secrets.local.env" ]]; then
  chmod 600 "${EDGE_ROOT}/configs/secrets.local.env"
fi
chown -R "${EDGE_USER}:${EDGE_USER}" "${INSTALL_ROOT}/var" || true
chown "${EDGE_USER}:${EDGE_USER}" \
  "${EDGE_ROOT}/configs/secrets.env" \
  "${EDGE_ROOT}/configs/secrets.local.env" 2>/dev/null || true

systemctl daemon-reload
systemctl enable --now mosquitto
systemctl restart mosquitto

echo
echo "==> Bootstrap complete."
echo "Next (as a normal user in EdgeCompute/):"
echo "  1. ./scripts/configure.sh          # tokens, UUIDs, device refs"
echo "  2. ir4-gas-agent --dry-run          # or: ${INSTALL_ROOT}/venv/bin/ir4-gas-agent --dry-run"
echo "  3. ir4-rfid-agent --dry-run"
echo "  4. sudo systemctl enable --now ir4-gas-agent ir4-rfid-agent"
echo "  5. journalctl -u ir4-gas-agent -f"
echo
echo "If you were added to dialout, log out and back in before using the serial port."
