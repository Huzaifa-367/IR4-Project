#!/usr/bin/env bash
# Host-level setup on a pole Jetson (root). Invoked by ir4_edge.deploy.host.run_host.
# Subcommands: ensure-tree | ensure-host | ensure-venv | pip-install | render-systemd |
#              render-mosquitto | fix-permissions | enable-services
set -euo pipefail

EDGE_ROOT="${EDGE_ROOT:?EDGE_ROOT must point at EdgeCompute tree}"
CONFIG_DIR="${EDGE_ROOT}/configs"
INSTALL_ROOT="${INSTALL_ROOT:-/opt/ir4-edge}"
EDGE_SERVICE_USER="ir4edge"

# shellcheck source=/dev/null
source "${EDGE_ROOT}/deploy/host_lib.sh"

cmd="${1:-}"
shift || true

case "${cmd}" in
  ensure-tree)
    load_edge_yaml
    INSTALL_ROOT="${IR4_EDGE_INSTALL_ROOT:-${EDGE_INSTALL_ROOT}}"
    ensure_canonical_code_tree
    mkdir -p "${INSTALL_ROOT}/var" "${INSTALL_ROOT}/wheels"
    ;;
  ensure-host)
    load_edge_yaml
    INSTALL_ROOT="${IR4_EDGE_INSTALL_ROOT:-${EDGE_INSTALL_ROOT}}"
    EDGE_USER="${IR4_EDGE_USER:-${EDGE_SERVICE_USER}}"
    PKGS=(python3 python3-venv python3-pip ca-certificates)
    [[ "${EDGE_ENABLE_RFID}" == "true" ]] && PKGS+=(mosquitto mosquitto-clients)
    ensure_host_packages "${PKGS[@]}"
    ensure_service_user
    ;;
  ensure-venv)
    load_edge_yaml
    INSTALL_ROOT="${IR4_EDGE_INSTALL_ROOT:-${EDGE_INSTALL_ROOT}}"
    if [[ ! -x "${INSTALL_ROOT}/venv/bin/python" ]]; then
      python3 -m venv "${INSTALL_ROOT}/venv"
    fi
    ;;
  pip-install)
    load_edge_yaml
    INSTALL_ROOT="${IR4_EDGE_INSTALL_ROOT:-${EDGE_INSTALL_ROOT}}"
    EDGE_ROOT="${INSTALL_ROOT}/EdgeCompute"
    WHEELHOUSE="$(wheelhouse_for_install || true)"
    VENV="${INSTALL_ROOT}/venv"
    if [[ -n "${WHEELHOUSE}" ]]; then
      "${VENV}/bin/pip" install -q --no-index --find-links "${WHEELHOUSE}" pip wheel setuptools || true
      "${VENV}/bin/pip" install -q --no-index --find-links "${WHEELHOUSE}" -e "${EDGE_ROOT}"
    else
      "${VENV}/bin/pip" install -q -e "${EDGE_ROOT}"
    fi
    [[ -x "${VENV}/bin/ir4-edge" ]] || { echo "pip install did not create ir4-edge CLI" >&2; exit 1; }
    link_cli_tools
    ;;
  render-systemd)
    load_edge_yaml
    INSTALL_ROOT="${IR4_EDGE_INSTALL_ROOT:-${EDGE_INSTALL_ROOT}}"
    EDGE_ROOT="${INSTALL_ROOT}/EdgeCompute"
    EDGE_USER="${IR4_EDGE_USER:-${EDGE_SERVICE_USER}}"
    install -d -m 0755 /etc/systemd/system
    if [[ "${EDGE_ENABLE_GAS}" == "true" ]]; then
      install -m 0644 "${EDGE_ROOT}/deploy/udev/99-yt98h-rs485.rules" /etc/udev/rules.d/99-yt98h-rs485.rules
      udevadm control --reload-rules; udevadm trigger || true
    fi
    render_systemd_units
    systemctl daemon-reload
    ;;
  render-mosquitto)
    load_edge_yaml
    INSTALL_ROOT="${IR4_EDGE_INSTALL_ROOT:-${EDGE_INSTALL_ROOT}}"
    EDGE_ROOT="${INSTALL_ROOT}/EdgeCompute"
    CONFIG_DIR="${EDGE_ROOT}/configs"
    load_secret_env
    if [[ "${EDGE_ENABLE_RFID}" == "true" ]]; then
      install -d -m 0755 /etc/mosquitto/conf.d
      render_mosquitto_conf /etc/mosquitto/conf.d/ir4-edge.conf
      ensure_mosquitto_users \
        "${EDGE_MQTT_FXR90_USER}" "${EDGE_MQTT_AGENT_USER}" \
        "${IR4_MQTT_FXR90_PASSWORD:-}" "${IR4_MQTT_PASSWORD:-}"
      start_mosquitto
    fi
    ;;
  fix-permissions)
    load_edge_yaml
    INSTALL_ROOT="${IR4_EDGE_INSTALL_ROOT:-${EDGE_INSTALL_ROOT}}"
    EDGE_ROOT="${INSTALL_ROOT}/EdgeCompute"
    CONFIG_DIR="${EDGE_ROOT}/configs"
    EDGE_USER="${IR4_EDGE_USER:-${EDGE_SERVICE_USER}}"
    fix_config_permissions
    ;;
  enable-services)
    load_edge_yaml
    INSTALL_ROOT="${IR4_EDGE_INSTALL_ROOT:-${EDGE_INSTALL_ROOT}}"
    EDGE_ROOT="${INSTALL_ROOT}/EdgeCompute"
    CONFIG_DIR="${EDGE_ROOT}/configs"
    EDGE_USER="${IR4_EDGE_USER:-${EDGE_SERVICE_USER}}"
    enable_selected_services
    ;;
  *)
    echo "Usage: host.sh {ensure-tree|ensure-host|ensure-venv|pip-install|render-systemd|render-mosquitto|fix-permissions|enable-services}" >&2
    exit 1
    ;;
esac
