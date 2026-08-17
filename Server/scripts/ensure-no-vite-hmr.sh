#!/usr/bin/env bash
# SCC must serve compiled Vite assets from public/build — never HMR.
# `npm run dev` / `lerd worker start vite` writes public/hot; Laravel then
# injects @vite/client and the browser reload-loops on HTTPS.
#
# Usage (from 05-update.sh / 01-setup.sh):
#   source "$APP_ROOT/scripts/ensure-no-vite-hmr.sh"
#   ir4_stop_vite_hmr "$APP_ROOT"
#   ir4_verify_production_frontend "$APP_ROOT"

ir4_stop_vite_hmr() {
  local app_root="${1:?app root required}"
  local label="${2:-}"

  echo "Stopping Vite HMR${label:+ ($label)} so SCC uses public/build..."

  if command -v lerd >/dev/null 2>&1; then
    # Lerd prints "→ stopping vite…"; ignore if already stopped.
    lerd worker stop vite >/dev/null 2>&1 || true
  fi

  # Cover any leftover user unit from an older Lerd start.
  if command -v systemctl >/dev/null 2>&1; then
    systemctl --user stop 'lerd-vite-*.service' >/dev/null 2>&1 || true
  fi

  rm -f "${app_root}/public/hot"

  if [ -e "${app_root}/public/hot" ]; then
    echo "ERROR: ${app_root}/public/hot still exists after removal." >&2
    echo "Delete it manually and do not run: npm run dev / lerd worker start vite" >&2
    return 1
  fi
}

ir4_verify_production_frontend() {
  local app_root="${1:?app root required}"
  local manifest="${app_root}/public/build/manifest.json"
  local hot="${app_root}/public/hot"

  echo "Verifying production frontend (no Vite HMR)..."

  if [ -e "$hot" ]; then
    echo "ERROR: $hot is present — Laravel will inject Vite HMR and the UI will reload-loop." >&2
    echo "Run: lerd worker stop vite && rm -f $hot" >&2
    return 1
  fi

  if [ ! -f "$manifest" ]; then
    echo "ERROR: $manifest missing — run npm run build on the SCC." >&2
    return 1
  fi

  # Live HTML check when the site answers (skip quietly if unreachable).
  local app_url=""
  if [ -f "${app_root}/.env" ]; then
    app_url="$(grep -E '^APP_URL=' "${app_root}/.env" | tail -1 | cut -d= -f2- | tr -d '"' | tr -d "'")"
  fi
  app_url="${app_url%/}"

  if [ -n "$app_url" ] && command -v curl >/dev/null 2>&1; then
    local html=""
    html="$(curl -sk -m 8 "${app_url}/login" 2>/dev/null || true)"
    if [ -n "$html" ]; then
      if printf '%s' "$html" | grep -qE '@lerd-vite|/@vite/client|127\.0\.0\.1:5173|localhost:5173'; then
        echo "ERROR: ${app_url}/login still references Vite HMR." >&2
        echo "public/hot or an active vite worker is still in play." >&2
        return 1
      fi
      if ! printf '%s' "$html" | grep -qE '/build/assets/'; then
        echo "ERROR: ${app_url}/login does not reference /build/assets/." >&2
        echo "Expected compiled assets from public/build." >&2
        return 1
      fi
      echo "OK: login page serves /build/assets/ (no HMR)."
    else
      echo "WARN: could not fetch ${app_url}/login — skipped live HTML check."
      echo "OK: public/hot absent and manifest present."
    fi
  else
    echo "OK: public/hot absent and manifest present."
  fi
}
