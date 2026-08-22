#!/usr/bin/env bash

set -euo pipefail

PROJECT_ROOT="/data2/laravel"
APP_ROOT="${PROJECT_ROOT}/IR4-Project"
REPO_CACHE="${PROJECT_ROOT}/.ir4-repo"
REPO_URL="https://github.com/Huzaifa-367/IR4-Project.git"

# Load fnm so npm/node are available in non-login shells (cron, CI, ssh -c).
if command -v fnm >/dev/null 2>&1 || [ -x "$HOME/.local/share/fnm/fnm" ]; then
  export PATH="$HOME/.local/share/fnm:$PATH"
  eval "$(fnm env --use-on-cd)" 2>/dev/null || true
fi

# Prefer the invoking SCC user when someone typed `sudo scripts/05-update.sh`.
if [ "$(id -u)" -eq 0 ]; then
  if [ -n "${SUDO_USER:-}" ] && [ "$SUDO_USER" != "root" ]; then
    echo "ERROR: do not run 05-update.sh with sudo." >&2
    echo "Lerd lives under ${SUDO_USER}'s home; root falls back to host PHP and" >&2
    echo "cannot resolve DB_HOST=lerd-mysql. Re-run as ${SUDO_USER}:" >&2
    echo "  cd /data2/laravel/IR4-Project && bash scripts/05-update.sh" >&2
    exit 1
  fi
  echo "ERROR: do not run 05-update.sh as root." >&2
  echo "Run as the SCC operator user that owns Lerd (~/.local/share/lerd)." >&2
  exit 1
fi

export PATH="$HOME/.local/share/lerd/bin:$HOME/.local/bin:$PATH"

if [ ! -d "$REPO_CACHE/.git" ]; then
  echo "ERROR: Repo cache missing at $REPO_CACHE"
  echo "Run scripts/01-setup.sh first (or Server/scripts/01-setup.sh from the monorepo)."
  exit 1
fi

mkdir -p "$APP_ROOT"

echo "Updating sparse repository (Server/ only)..."

cd "$REPO_CACHE"
git remote set-url origin "$REPO_URL"
git sparse-checkout set Server
git fetch origin
git checkout main
git reset --hard origin/main
git clean -fd

if [ ! -d "$REPO_CACHE/Server" ]; then
  echo "ERROR: Server/ missing after sparse checkout." >&2
  exit 1
fi

echo "Syncing Server/ → $APP_ROOT (flattened, skipping Mobile/Docs)..."

# Preserve runtime state; replace application source from Server/.
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

cd "$APP_ROOT"

# shellcheck source=resolve-artisan.sh
source "$APP_ROOT/scripts/resolve-artisan.sh"
# shellcheck source=ensure-no-vite-hmr.sh
source "$APP_ROOT/scripts/ensure-no-vite-hmr.sh"
ir4_require_lerd

if [ ! -f ".env" ]; then
  echo "ERROR: .env missing at $APP_ROOT/.env" >&2
  echo "Run scripts/01-setup.sh first, or: cp .env.example .env && ir4_artisan key:generate --force" >&2
  exit 1
fi

# Stop Vite before build: HMR writes public/hot and causes HTTPS reload-loops.
# rsync --exclude='public/hot' can leave a stale hot file from a prior `npm run dev`.
ir4_stop_vite_hmr "$APP_ROOT" "before build"

bash "$APP_ROOT/scripts/ensure-storage-dirs.sh" "$APP_ROOT"

echo "Installing Composer dependencies..."
composer install

echo "Refreshing Laravel bootstrap cache..."
ir4_artisan package:discover --ansi
ir4_artisan optimize:clear

echo "Installing Node dependencies..."
npm install

echo "Generating Wayfinder types (${IR4_ARTISAN})..."
export WAYFINDER_COMMAND
ir4_artisan wayfinder:generate --with-form

echo "Building frontend..."
npm run build

# Build must not leave HMR on; fail the update if it did.
ir4_stop_vite_hmr "$APP_ROOT" "after build"
if [ ! -f "$APP_ROOT/public/build/manifest.json" ]; then
  echo "ERROR: public/build/manifest.json missing after npm run build." >&2
  echo "Vite assets were not compiled; the UI will try to load HMR / 127.0.0.1:5173." >&2
  exit 1
fi

echo "Running migrations..."
ir4_artisan migrate --force

echo "Syncing camera streams to MediaMTX..."
if ! ir4_artisan ir4:sync-camera-streams; then
  echo "WARN: camera stream sync failed — run: ir4_artisan ir4:sync-camera-streams" >&2
fi

echo "Restarting Lerd..."
lerd restart

# `lerd restart` must not leave Vite running — stop again and prove login HTML.
ir4_stop_vite_hmr "$APP_ROOT" "after lerd restart"
ir4_verify_production_frontend "$APP_ROOT"

echo
echo "=================================="
echo "Project Updated Successfully"
echo "App root: $APP_ROOT"
echo "Frontend: public/build only (Vite HMR stopped)"
echo "=================================="
