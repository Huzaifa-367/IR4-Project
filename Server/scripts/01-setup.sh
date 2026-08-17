#!/usr/bin/env bash

set -euo pipefail

#########################################
# Configuration
#########################################

PROJECT_NAME="IR4-Project"
REPO_URL="https://github.com/Huzaifa-367/IR4-Project.git"
PROJECT_ROOT="/data2/laravel"
APP_ROOT="${PROJECT_ROOT}/${PROJECT_NAME}"
REPO_CACHE="${PROJECT_ROOT}/.ir4-repo"

#########################################
# Update System
#########################################

sudo apt update
sudo apt upgrade -y

#########################################
# Install Packages
#########################################

sudo apt install -y \
  git \
  curl \
  wget \
  zip \
  unzip \
  rsync \
  composer \
  flatpak

#########################################
# Install fnm
#########################################

if ! command -v fnm >/dev/null 2>&1; then
  curl -fsSL https://fnm.vercel.app/install | bash
fi

# Ensure fnm is on PATH for this (possibly already-installed) run.
export PATH="$HOME/.local/share/fnm:$PATH"

if ! command -v fnm >/dev/null 2>&1; then
  echo "ERROR: fnm not found on PATH after install." >&2
  echo "Open a new shell or check https://github.com/Schniz/fnm#shell-setup" >&2
  exit 1
fi

# Load fnm into THIS shell so 'fnm use/default' can switch versions.
# (Without this you get: "We can't find the necessary environment variables
# to replace the Node version.")
eval "$(fnm env --use-on-cd)"

# Persist fnm setup to the shell profile for future logins.
FNM_INIT_MARKER='# fnm (IR4 setup)'
if ! grep -qF "$FNM_INIT_MARKER" "$HOME/.bashrc" 2>/dev/null; then
  {
    echo ''
    echo "$FNM_INIT_MARKER"
    echo 'export PATH="$HOME/.local/share/fnm:$PATH"'
    echo 'eval "$(fnm env --use-on-cd)"'
  } >>"$HOME/.bashrc"
fi

#########################################
# Install Node
#########################################

fnm install --lts
fnm default lts-latest
fnm use lts-latest

#########################################
# Install Lerd
#########################################

if ! command -v lerd >/dev/null; then
  curl -fsSL https://lerd.sh/install.sh | bash
  # shellcheck disable=SC1090
  source ~/.bashrc
fi

# Lerd's Alpine MySQL client needs this plugin to authenticate to MySQL 8
# accounts that use caching_sha2_password.
lerd php:pkg add mariadb-connector-c
lerd php:rebuild 8.4

#########################################
# Workspace
#########################################

sudo mkdir -p "$PROJECT_ROOT"
sudo chown -R "$USER":"$USER" "$PROJECT_ROOT"
mkdir -p "$APP_ROOT"

#########################################
# Sparse clone (Server/ only)
#########################################
# Repo cache keeps a sparse checkout of Server/.
# App root gets Laravel files flattened (no nested Server/).
# Mobile/, Docs/, and other monorepo paths are never synced.

if [ ! -d "$REPO_CACHE/.git" ]; then
  git clone --filter=blob:none --sparse "$REPO_URL" "$REPO_CACHE"
  cd "$REPO_CACHE"
  git sparse-checkout set Server
else
  cd "$REPO_CACHE"
  git sparse-checkout set Server
fi

git fetch origin
git checkout main
git reset --hard origin/main
git clean -fd

if [ ! -d "$REPO_CACHE/Server" ]; then
  echo "ERROR: Server/ missing after sparse checkout." >&2
  exit 1
fi

rsync -a --delete \
  --exclude='.env' \
  --exclude='.env.*' \
  --exclude='auto.crt' \
  --exclude='auto.key' \
  --exclude='vendor/' \
  --exclude='node_modules/' \
  --exclude='storage/app/' \
  --exclude='storage/framework/' \
  --exclude='storage/logs/' \
  --exclude='storage/*.key' \
  --exclude='public/storage/' \
  --exclude='public/hot' \
  --exclude='bootstrap/cache/*.php' \
  "$REPO_CACHE/Server/" "$APP_ROOT/"

#########################################
# Laravel
#########################################

cd "$APP_ROOT"

# shellcheck source=resolve-artisan.sh
source "$APP_ROOT/scripts/resolve-artisan.sh"
# shellcheck source=ensure-no-vite-hmr.sh
source "$APP_ROOT/scripts/ensure-no-vite-hmr.sh"
ir4_stop_vite_hmr "$APP_ROOT" "setup"

composer install

if [ ! -f ".env" ]; then
  cp .env.example .env
fi

bash "$APP_ROOT/scripts/ensure-storage-dirs.sh" "$APP_ROOT"
bash "$APP_ROOT/scripts/ensure-mediamtx-env.sh" "$APP_ROOT" || true

ir4_artisan key:generate --force

#########################################
# Frontend
#########################################

npm install
export WAYFINDER_COMMAND
ir4_artisan wayfinder:generate --with-form
npm run build
ir4_stop_vite_hmr "$APP_ROOT" "after setup build"

#########################################
# Database
#########################################

read -r -p "Run migrations? (y/N): " MIGRATE

if [[ "$MIGRATE" =~ ^[Yy]$ ]]; then
  ir4_artisan migrate
fi

#########################################
# Start Lerd
#########################################

lerd start

ir4_stop_vite_hmr "$APP_ROOT" "after lerd start"
ir4_verify_production_frontend "$APP_ROOT"

if ! php -r 'exit(is_dir("/data") ? 0 : 1);'; then
  echo "ERROR: /data is not mounted inside the Lerd PHP runtime." >&2
  echo "Add /data to mounts in ~/.config/lerd/config.yaml, then restart Lerd." >&2
  exit 1
fi

if ! php -r 'passthru("/usr/bin/mysqldump --version", $status); exit($status);'; then
  echo "ERROR: mysqldump is unavailable in the Lerd PHP runtime." >&2
  echo "Install Lerd's MySQL client before starting the scheduler." >&2
  exit 1
fi

lerd schedule:start

if ! lerd worker list 2>/dev/null | grep -qi 'schedule'; then
  echo "ERROR: Lerd schedule worker is not running after schedule:start." >&2
  echo "Daily Spatie backups will not run until: lerd schedule:start" >&2
  exit 1
fi

INSTALLER="$APP_ROOT/scripts/02-install-systemd-units.sh"
if [ -f "$INSTALLER" ]; then
  echo "==> Installing system units (ir4.target → /etc/systemd/system/)"
  APP_ROOT="$APP_ROOT" bash "$INSTALLER"
else
  echo "WARN: missing $INSTALLER — boot units not installed." >&2
fi

echo
echo "=================================="
echo "Setup Complete"
echo "App root: $APP_ROOT"
echo "(Server/ contents flattened; Mobile/Docs skipped)"
echo "Lerd scheduler: running (daily backup:clean 01:00, backup:run 01:30, backup:monitor 03:00)"
echo "Boot: systemctl status ir4.target"
echo "Next: follow SCC-SETUP.md (monorepo root) steps 02–04 if not already done"
echo "=================================="
