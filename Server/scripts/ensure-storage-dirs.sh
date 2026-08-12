#!/usr/bin/env bash
# Ensure Laravel writable dirs exist (rsync excludes storage/framework/ on SCC updates).
set -euo pipefail

APP_ROOT="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"

mkdir -p \
  "$APP_ROOT/storage/app/public" \
  "$APP_ROOT/storage/app/private" \
  "$APP_ROOT/storage/framework/cache/data" \
  "$APP_ROOT/storage/framework/sessions" \
  "$APP_ROOT/storage/framework/testing" \
  "$APP_ROOT/storage/framework/views" \
  "$APP_ROOT/storage/logs" \
  "$APP_ROOT/bootstrap/cache"

# Best-effort permissions for web/PHP user (Lerd runs as current user on SCC).
chmod -R ug+rwx "$APP_ROOT/storage" "$APP_ROOT/bootstrap/cache" 2>/dev/null || true
