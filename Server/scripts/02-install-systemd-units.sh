#!/usr/bin/env bash
# Install IR4 systemd units into /etc/systemd/system/ and enable on boot.
#
# Usage (deploy user on SCC):
#   cd /data2/laravel/IR4-Project && bash scripts/02-install-systemd-units.sh
#
# See SCC-SETUP.md (repo root).
set -euo pipefail

APP_ROOT="${APP_ROOT:-/data2/laravel/IR4-Project}"
DEPLOY_USER="${DEPLOY_USER:-$(whoami)}"
DEPLOY_GROUP="${DEPLOY_GROUP:-$(id -gn)}"
DEPLOY_HOME="${DEPLOY_HOME:-$(getent passwd "$DEPLOY_USER" | cut -d: -f6)}"
DEPLOY_UID="$(id -u "$DEPLOY_USER")"
XDG_RUNTIME_DIR="${XDG_RUNTIME_DIR:-/run/user/${DEPLOY_UID}}"

UNIT_SRC="${UNIT_SRC:-$APP_ROOT/scripts/systemd}"
UNIT_DST="/etc/systemd/system"

UNITS=(
  ir4-lerd.service
  ir4-workers.service
  ir4-mediamtx.service
  ir4-sync-camera-streams.service
  ir4.target
)

if [ ! -f "$APP_ROOT/artisan" ]; then
  echo "ERROR: artisan not found at $APP_ROOT" >&2
  exit 1
fi

if [ ! -d "$UNIT_SRC" ]; then
  echo "ERROR: unit templates missing at $UNIT_SRC" >&2
  exit 1
fi

echo "==> Installing IR4 system units as $DEPLOY_USER (uid $DEPLOY_UID)"
echo "    APP_ROOT=$APP_ROOT"
echo "    HOME=$DEPLOY_HOME"
echo "    XDG_RUNTIME_DIR=$XDG_RUNTIME_DIR"

# Linger so rootless Podman / Lerd user runtime exists at boot.
if ! loginctl show-user "$DEPLOY_USER" -p Linger 2>/dev/null | grep -q 'Linger=yes'; then
  echo "==> Enabling linger for $DEPLOY_USER"
  sudo loginctl enable-linger "$DEPLOY_USER"
fi

render_unit() {
  local src="$1"
  local dst="$2"
  sed \
    -e "s|__IR4_USER__|${DEPLOY_USER}|g" \
    -e "s|__IR4_GROUP__|${DEPLOY_GROUP}|g" \
    -e "s|__IR4_HOME__|${DEPLOY_HOME}|g" \
    -e "s|__IR4_APP_ROOT__|${APP_ROOT}|g" \
    -e "s|__IR4_XDG_RUNTIME_DIR__|${XDG_RUNTIME_DIR}|g" \
    "$src" | sudo tee "$dst" >/dev/null
}

for unit in "${UNITS[@]}"; do
  if [ ! -f "$UNIT_SRC/$unit" ]; then
    echo "ERROR: missing $UNIT_SRC/$unit" >&2
    exit 1
  fi
  render_unit "$UNIT_SRC/$unit" "$UNIT_DST/$unit"
  echo "    installed $UNIT_DST/$unit"
done

if command -v docker >/dev/null 2>&1; then
  echo "==> Enabling Docker on boot"
  sudo systemctl enable --now docker 2>/dev/null \
    || sudo systemctl enable --now docker.service 2>/dev/null \
    || true
fi

echo "==> Reloading systemd and enabling ir4.target"
sudo systemctl daemon-reload
sudo systemctl enable ir4-lerd.service ir4-workers.service \
  ir4-mediamtx.service ir4-sync-camera-streams.service ir4.target
sudo systemctl start ir4.target

echo
echo "=================================="
echo "System units installed under $UNIT_DST"
echo "  systemctl status ir4.target"
echo "  systemctl list-dependencies ir4.target"
echo "  After reboot: ir4.* start via multi-user.target"
echo "=================================="
