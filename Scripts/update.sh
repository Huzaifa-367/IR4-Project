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

if [ ! -d "$REPO_CACHE/.git" ]; then
  echo "ERROR: Repo cache missing at $REPO_CACHE"
  echo "Run Scripts/setup.sh first."
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

echo "Installing Composer dependencies..."
composer install

echo "Installing Node dependencies..."
npm install

echo "Building frontend..."
npm run build

echo "Running migrations..."
php artisan migrate --force

echo "Restarting Lerd..."
lerd restart

echo
echo "=================================="
echo "Project Updated Successfully"
echo "App root: $APP_ROOT"
echo "=================================="
