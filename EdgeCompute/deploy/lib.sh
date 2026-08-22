#!/usr/bin/env bash
# Shared helpers for install / enable / setup (sourced, not executed).
# shellcheck shell=bash

EDGE_ROOT="${EDGE_ROOT:?EDGE_ROOT must be set before sourcing lib.sh}"
CONFIG_DIR="${CONFIG_DIR:-${EDGE_ROOT}/configs}"

EDGE_INSTALL_ROOT="/opt/ir4-edge"
EDGE_SERVICE_USER="ir4edge"
EDGE_ENABLE_GAS="true"
EDGE_ENABLE_RFID="true"
EDGE_AUTO_START="true"
EDGE_PATH_LINKS="true"
EDGE_MQTT_LISTENER="1883 0.0.0.0"
EDGE_MQTT_ANONYMOUS="true"
EDGE_MQTT_FXR90_USER="fxr90"
EDGE_MQTT_AGENT_USER="ir4-rfid"

load_edge_yaml() {
  local edge_yaml="${CONFIG_DIR}/edge.yaml"
  [[ -f "${edge_yaml}" ]] || return 0
  local pybin="python3"
  [[ -x "${IR4_EDGE_INSTALL_ROOT:-/opt/ir4-edge}/venv/bin/python" ]] && \
    pybin="${IR4_EDGE_INSTALL_ROOT:-/opt/ir4-edge}/venv/bin/python"
  eval "$(
    "${pybin}" - "${edge_yaml}" <<'PY'
import sys
from pathlib import Path
text = Path(sys.argv[1]).read_text(encoding="utf-8")
try:
    import yaml
    data = yaml.safe_load(text) or {}
except Exception:
    data = {}

def b(v, d=True):
    return "true" if (d if v is None else bool(v)) else "false"

def s(v, d):
    return d if v is None or v == "" else str(v)

install = (data or {}).get("install") or {}
services = (data or {}).get("services") or {}
mqtt = (data or {}).get("mosquitto") or {}
print("EDGE_INSTALL_ROOT={}".format(repr(s(install.get("root"), "/opt/ir4-edge"))))
print("EDGE_SERVICE_USER={}".format(repr(s(install.get("service_user"), "ir4edge"))))
print("EDGE_PATH_LINKS={}".format(repr(b(install.get("path_links"), True))))
print("EDGE_ENABLE_GAS={}".format(repr(b(services.get("gas"), True))))
print("EDGE_ENABLE_RFID={}".format(repr(b(services.get("rfid"), True))))
print("EDGE_AUTO_START={}".format(repr(b(services.get("auto_start"), True))))
print("EDGE_MQTT_LISTENER={}".format(repr(s(mqtt.get("listener"), "1883 0.0.0.0"))))
print("EDGE_MQTT_ANONYMOUS={}".format(repr(b(mqtt.get("anonymous"), True))))
print("EDGE_MQTT_FXR90_USER={}".format(repr(s(mqtt.get("fxr90_user"), "fxr90"))))
print("EDGE_MQTT_AGENT_USER={}".format(repr(s(mqtt.get("agent_user"), "ir4-rfid"))))
PY
  )"
}

load_env_file_safe() {
  local file="$1"
  [[ -f "${file}" ]] || return 0
  local line key value
  while IFS= read -r line || [[ -n "${line}" ]]; do
    line="${line#"${line%%[![:space:]]*}"}"
    line="${line%"${line##*[![:space:]]}"}"
    [[ -z "${line}" || "${line}" == \#* || "${line}" != *=* ]] && continue
    key="${line%%=*}"; value="${line#*=}"
    key="${key%"${key##*[![:space:]]}"}"; key="${key#"${key%%[![:space:]]*}"}"
    [[ "${key}" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]] || continue
    value="${value#"${value%%[![:space:]]*}"}"; value="${value%"${value##*[![:space:]]}"}"
    if [[ "${value}" == \"*\" ]]; then value="${value:1:${#value}-2}"; fi
    if [[ "${value}" == \'*\' ]]; then value="${value:1:${#value}-2}"; fi
    printf -v "${key}" '%s' "${value}"
    export "${key}"
  done < "${file}"
}

load_secret_env() {
  load_env_file_safe "${CONFIG_DIR}/secrets.env"
}

# Materialize /opt/ir4-edge/EdgeCompute as a real directory (migrate symlink targets).
ensure_canonical_code_tree() {
  local expected="${INSTALL_ROOT}/EdgeCompute"
  if [[ -L "${expected}" ]]; then
    echo "==> Migrating symlink ${expected} to a real directory"
    local link_target
    link_target="$(readlink -f "${expected}")"
    rm -f "${expected}"
    mkdir -p "${expected}"
    if [[ -d "${link_target}" ]]; then
      rsync -a "${link_target}/" "${expected}/"
    fi
  elif [[ ! -d "${expected}" ]]; then
    mkdir -p "${expected}"
  fi
  EDGE_ROOT="${expected}"
  CONFIG_DIR="${EDGE_ROOT}/configs"
}

wheelhouse_for_install() {
  local wheelhouse="${IR4_EDGE_WHEELHOUSE:-}"
  [[ -z "${wheelhouse}" && -d "${INSTALL_ROOT}/wheels" ]] && wheelhouse="${INSTALL_ROOT}/wheels"
  [[ -z "${wheelhouse}" && -d "${EDGE_ROOT}/.wheels" ]] && wheelhouse="${EDGE_ROOT}/.wheels"
  if [[ -n "${wheelhouse}" && -d "${wheelhouse}" && -n "$(ls -A "${wheelhouse}" 2>/dev/null)" ]]; then
    echo "${wheelhouse}"
  fi
}

# Reinstall the editable package into an existing venv (updates, not first install).
refresh_edge_package() {
  local venv="${INSTALL_ROOT}/venv"
  local wheelhouse
  wheelhouse="$(wheelhouse_for_install || true)"
  if [[ ! -x "${venv}/bin/pip" ]]; then
    return 1
  fi
  if [[ -n "${wheelhouse}" ]]; then
    echo "==> Refresh offline pip from ${wheelhouse}"
    "${venv}/bin/pip" install -q --no-index --find-links "${wheelhouse}" \
      pip wheel setuptools || true
    "${venv}/bin/pip" install -q --no-index --find-links "${wheelhouse}" -e "${EDGE_ROOT}"
  else
    echo "==> Refresh online pip"
    "${venv}/bin/pip" install -q -e "${EDGE_ROOT}"
  fi
  [[ -x "${venv}/bin/ir4-edge" ]]
}

link_cli_tools() {
  ln -sfn "${INSTALL_ROOT}/venv/bin/ir4-edge" /usr/local/bin/ir4-edge
  if [[ "${EDGE_PATH_LINKS}" == "true" ]]; then
    [[ "${EDGE_ENABLE_GAS}" == "true" ]] && \
      ln -sfn "${INSTALL_ROOT}/venv/bin/ir4-gas-agent" /usr/local/bin/ir4-gas-agent
    [[ "${EDGE_ENABLE_RFID}" == "true" ]] && \
      ln -sfn "${INSTALL_ROOT}/venv/bin/ir4-rfid-agent" /usr/local/bin/ir4-rfid-agent
  fi
}

# Resolve install.root/EdgeCompute from edge.yaml (ignores stray checkout paths).
# Call after sourcing lib.sh; optional arg is any tree that contains configs/edge.yaml.
resolve_canonical_paths() {
  local seed_root="${1:-${EDGE_ROOT}}"
  EDGE_ROOT="${seed_root}"
  CONFIG_DIR="${EDGE_ROOT}/configs"
  load_edge_yaml
  INSTALL_ROOT="${IR4_EDGE_INSTALL_ROOT:-${EDGE_INSTALL_ROOT}}"
  if [[ "$(id -u)" -eq 0 ]]; then
    ensure_canonical_code_tree
  else
    EDGE_ROOT="${INSTALL_ROOT}/EdgeCompute"
    CONFIG_DIR="${EDGE_ROOT}/configs"
  fi
  EDGE_USER="${IR4_EDGE_USER:-${EDGE_SERVICE_USER}}"
}

# Write ir4-gas-agent.service and ir4-rfid-agent.service from templates.
render_systemd_units() {
  if [[ "${EDGE_ENABLE_GAS}" == "true" ]]; then
    render_unit "${EDGE_ROOT}/deploy/systemd/ir4-gas-agent.service.in" \
      /etc/systemd/system/ir4-gas-agent.service
  fi
  if [[ "${EDGE_ENABLE_RFID}" == "true" ]]; then
    render_unit "${EDGE_ROOT}/deploy/systemd/ir4-rfid-agent.service.in" \
      /etc/systemd/system/ir4-rfid-agent.service
  fi
}

render_unit() {
  local edge_user="${EDGE_USER:-${EDGE_SERVICE_USER}}"
  sed \
    -e "s|@EDGE_ROOT@|${EDGE_ROOT}|g" \
    -e "s|@INSTALL_ROOT@|${INSTALL_ROOT}|g" \
    -e "s|@EDGE_USER@|${edge_user}|g" \
    "$1" > "$2"
  chmod 0644 "$2"
}

render_mosquitto_conf() {
  local dest="$1"
  local listener="${2:-${EDGE_MQTT_LISTENER}}"
  local anonymous="${3:-${EDGE_MQTT_ANONYMOUS}}"
  if [[ "${anonymous}" == "true" ]]; then
    cat > "${dest}" <<EOF
# Generated from configs/edge.yaml — anonymous LAN broker
listener ${listener}
allow_anonymous true
EOF
  else
    cat > "${dest}" <<EOF
# Generated from configs/edge.yaml — password auth
per_listener_settings true
listener ${listener}
allow_anonymous false
password_file /etc/mosquitto/ir4_passwd
EOF
  fi
  chmod 0644 "${dest}"
}

ensure_mosquitto_users() {
  local fxr90_user="$1" agent_user="$2" fxr90_pass="$3" agent_pass="$4"
  if [[ "${EDGE_MQTT_ANONYMOUS}" == "true" ]]; then
    echo "==> Mosquitto: anonymous (edge.yaml mosquitto.anonymous=true)"
    return 0
  fi
  if [[ -z "${fxr90_pass}" || -z "${agent_pass}" ]]; then
    echo "ERROR: mosquitto.anonymous=false but MQTT passwords missing in secrets.env" >&2
    return 1
  fi
  local passwd_file="/etc/mosquitto/ir4_passwd"
  echo "==> Writing Mosquitto password file"
  python3 - "${passwd_file}" "${fxr90_user}" "${fxr90_pass}" "${agent_user}" "${agent_pass}" <<'PY'
import crypt, os, pwd, grp, sys
from pathlib import Path
path = Path(sys.argv[1])
lines = []
for user, password in ((sys.argv[2], sys.argv[3]), (sys.argv[4], sys.argv[5])):
    hashed = crypt.crypt(password, crypt.mksalt(crypt.METHOD_SHA512))
    lines.append("{}:{}".format(user, hashed))
path.write_text("\n".join(lines) + "\n", encoding="utf-8")
os.chown(path, pwd.getpwnam("mosquitto").pw_uid, grp.getgrnam("mosquitto").gr_gid)
os.chmod(path, 0o640)
print("Wrote", path)
PY
}

secrets_ready() {
  load_secret_env
  [[ -n "${IR4_BASE_URL:-}" ]] || return 1
  if [[ "${EDGE_ENABLE_GAS}" == "true" ]]; then
    [[ -n "${IR4_GAS_DEVICE_TOKEN:-}" && -n "${IR4_GAS_DEVICE_UUID:-}" ]] || return 1
  fi
  if [[ "${EDGE_ENABLE_RFID}" == "true" ]]; then
    [[ -n "${IR4_RFID_DEVICE_TOKEN:-}" && -n "${IR4_RFID_DEVICE_UUID:-}" ]] || return 1
  fi
  return 0
}

fix_config_permissions() {
  local operator="${SUDO_USER:-}"
  local edge_user="${EDGE_USER:-${EDGE_SERVICE_USER}}"
  [[ -z "${operator}" || "${operator}" == "root" ]] && \
    operator="$(stat -c '%U' "${EDGE_ROOT}" 2>/dev/null || echo "${edge_user}")"
  echo "==> Config permissions (owner=${operator} group=${edge_user})"
  chown -R "${operator}:${edge_user}" "${CONFIG_DIR}"
  chmod 775 "${CONFIG_DIR}"
  chmod 664 "${CONFIG_DIR}"/*.yaml "${CONFIG_DIR}/secrets.example.env" 2>/dev/null || true
  [[ -f "${CONFIG_DIR}/secrets.env" ]] && chmod 640 "${CONFIG_DIR}/secrets.env"
  usermod -aG "${edge_user}" "${operator}" 2>/dev/null || true
  chown -R "${edge_user}:${edge_user}" "${INSTALL_ROOT}/var"
  chmod 775 "${INSTALL_ROOT}/var"
}

start_mosquitto() {
  systemctl daemon-reload
  systemctl enable mosquitto
  if ! systemctl restart mosquitto; then
    echo "ERROR: mosquitto failed to start" >&2
    journalctl -u mosquitto -n 30 --no-pager >&2 || true
    return 1
  fi
}

enable_selected_services() {
  systemctl daemon-reload
  local units=()
  if [[ "${EDGE_ENABLE_GAS}" == "true" ]]; then
    units+=("ir4-gas-agent")
  else
    systemctl disable --now ir4-gas-agent 2>/dev/null || true
  fi
  if [[ "${EDGE_ENABLE_RFID}" == "true" ]]; then
    units+=("ir4-rfid-agent")
  else
    systemctl disable --now ir4-rfid-agent 2>/dev/null || true
  fi
  [[ ${#units[@]} -eq 0 ]] && return 0
  systemctl enable "${units[@]}"
  if [[ "${EDGE_AUTO_START}" == "true" ]] && secrets_ready; then
    echo "==> Starting ${units[*]}"
    systemctl restart "${units[@]}"
    systemctl --no-pager --full status "${units[@]}" || true
  else
    echo "==> Enabled for boot; start with: ir4-edge up"
  fi
}
