#!/usr/bin/env bash
# Start MediaMTX on the host network so HLS/API bind on the SCC.
# Accepts Docker or Podman (Lerd SCCs often have Podman only — no docker.service).
# Lerd PHP cannot use 127.0.0.1 — set MEDIAMTX_API_URL to the SCC LAN IP in .env.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
NAME="${IR4_MEDIAMTX_NAME:-ir4-mediamtx}"
IMAGE="${IR4_MEDIAMTX_IMAGE:-bluenviron/mediamtx:latest}"
CFG="${IR4_MEDIAMTX_CONFIG:-$ROOT/scripts/mediamtx.yml}"

RUNTIME=""
if command -v docker >/dev/null 2>&1; then
  RUNTIME=docker
elif command -v podman >/dev/null 2>&1; then
  RUNTIME=podman
else
  echo "ERROR: need docker or podman on PATH to run MediaMTX." >&2
  echo "  sudo apt install -y docker.io   # or use Podman from Lerd/host" >&2
  exit 1
fi

if [[ ! -f "$CFG" ]]; then
  echo "Missing MediaMTX config: $CFG" >&2
  exit 1
fi

if [[ -d "$CFG" ]]; then
  echo "MediaMTX config path is a directory (container created it). Replace with the real file:" >&2
  echo "  rm -rf '$CFG' && cp /path/to/mediamtx.yml '$CFG'" >&2
  exit 1
fi

echo "==> Using container runtime: $RUNTIME"

"$RUNTIME" rm -f "$NAME" >/dev/null 2>&1 || true

# Host network: RTSP to LAN cameras works; API/HLS on host :9997/:8888.
"$RUNTIME" run -d \
  --name "$NAME" \
  --restart unless-stopped \
  --network host \
  -v "$CFG:/mediamtx.yml:ro" \
  "$IMAGE" >/dev/null

sleep 1

echo "MediaMTX started as ${NAME} (--network host, runtime=$RUNTIME)"
echo
echo "Host check (must NOT say authentication error):"
echo "  curl -s http://127.0.0.1:9997/v3/config/paths/list"
curl -s http://127.0.0.1:9997/v3/config/paths/list || true
echo
echo
echo "From Lerd PHP set in .env (use SCC LAN IP — not 127.0.0.1, not 10.89.x.x):"
echo "  MEDIAMTX_API_URL=http://<SCC-LAN-IP>:9997"
echo "  MEDIAMTX_API_USER="
echo "  MEDIAMTX_API_PASS="
echo "  CAMERA_BROWSER_STREAM_URL_TEMPLATE=/hls/{reference}/"
echo
echo "Then:"
echo "  lerd artisan config:clear"
echo "  lerd artisan ir4:sync-camera-streams --probe"
echo "  lerd artisan ir4:sync-camera-streams"
