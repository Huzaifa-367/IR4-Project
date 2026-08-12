#!/usr/bin/env bash
# Permanently configure MediaMTX-related .env keys for THIS SCC.
# Detects LAN IP, forces same-origin /hls playback, warm RTSP + TCP pull.
#
# Usage (from app root):
#   bash scripts/ensure-mediamtx-env.sh
# Or:
#   bash scripts/ensure-mediamtx-env.sh /data2/laravel/IR4-Project
set -euo pipefail

APP_ROOT="${1:-$(pwd)}"
ENV_FILE="${APP_ROOT}/.env"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "ERROR: missing $ENV_FILE" >&2
  exit 1
fi

detect_scc_ip() {
  local ip=""
  ip="$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{print $7; exit}' || true)"
  if [[ -z "$ip" || "$ip" == "1.1.1.1" ]]; then
    ip="$(hostname -I 2>/dev/null | awk '{print $1}' || true)"
  fi
  echo "$ip"
}

ensure_env() {
  local key="$1"
  local value="$2"
  if grep -qE "^${key}=" "$ENV_FILE"; then
    sed -i.bak "s|^${key}=.*|${key}=${value}|" "$ENV_FILE"
    rm -f "${ENV_FILE}.bak"
    echo "    updated $key"
  else
    printf '\n%s=%s\n' "$key" "$value" >> "$ENV_FILE"
    echo "    added $key"
  fi
}

SCC_IP="$(detect_scc_ip)"
if [[ -z "$SCC_IP" ]]; then
  echo "ERROR: could not detect SCC LAN IP." >&2
  exit 1
fi

echo "==> MediaMTX .env for this SCC (LAN IP: $SCC_IP)"

# Permanent live-wall contract: same-origin HLS proxy (works on IP + HTTPS).
ensure_env "CAMERA_BROWSER_STREAM_URL_TEMPLATE" "/hls/{reference}/"
ensure_env "MEDIAMTX_API_URL" "http://${SCC_IP}:9997"
ensure_env "MEDIAMTX_HOST_IP" "$SCC_IP"
ensure_env "MEDIAMTX_API_USER" ""
ensure_env "MEDIAMTX_API_PASS" ""
ensure_env "MEDIAMTX_SOURCE_ON_DEMAND" "false"
ensure_env "MEDIAMTX_RTSP_TRANSPORT" "tcp"

echo
echo "Current MediaMTX keys:"
grep -E '^(MEDIAMTX_|CAMERA_BROWSER_)' "$ENV_FILE" || true
echo
echo "Next: lerd artisan config:clear && lerd artisan ir4:sync-camera-streams"
