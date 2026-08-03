#!/usr/bin/env bash
# Start MediaMTX so Lerd PHP can reach the API by container DNS name.
# Host curl / browsers still use published ports on the SCC LAN IP.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
NAME="${IR4_MEDIAMTX_NAME:-ir4-mediamtx}"
IMAGE="${IR4_MEDIAMTX_IMAGE:-bluenviron/mediamtx:latest}"
CFG="${IR4_MEDIAMTX_CONFIG:-$ROOT/scripts/mediamtx.yml}"

if [[ ! -f "$CFG" ]]; then
  echo "Missing MediaMTX config: $CFG" >&2
  exit 1
fi

if [[ -d "$CFG" ]]; then
  echo "MediaMTX config path is a directory (Docker created it). Replace with the real file:" >&2
  echo "  rm -rf '$CFG' && cp /path/to/mediamtx.yml '$CFG'" >&2
  exit 1
fi

docker rm -f "$NAME" >/dev/null 2>&1 || true

# Publish HLS/API on the host; join bridge first so -p works.
docker run -d \
  --name "$NAME" \
  --restart unless-stopped \
  --network bridge \
  -p 8888:8888 \
  -p 8889:8889 \
  -p 9997:9997 \
  -v "$CFG:/mediamtx.yml:ro" \
  "$IMAGE" >/dev/null

# Attach MediaMTX to every Docker network used by running Lerd/PHP containers
# so `http://ir4-mediamtx:9997` resolves from `lerd artisan` / PHP-FPM.
attach_networks() {
  local cid nets net
  for cid in $(docker ps -q); do
    nets="$(docker inspect "$cid" --format '{{range $k, $_ := .NetworkSettings.Networks}}{{$k}}{{"\n"}}{{end}}' 2>/dev/null || true)"
    while IFS= read -r net; do
      [[ -z "$net" || "$net" == "bridge" || "$net" == "host" || "$net" == "none" ]] && continue
      docker network connect "$net" "$NAME" >/dev/null 2>&1 || true
    done <<< "$nets"
  done
}
attach_networks

echo "MediaMTX started as ${NAME}"
echo "From Lerd PHP set:  MEDIAMTX_API_URL=http://${NAME}:9997"
echo "                    MEDIAMTX_API_USER="
echo "                    MEDIAMTX_API_PASS="
echo "Browser template:   CAMERA_BROWSER_STREAM_URL_TEMPLATE=http://<SCC-LAN-IP>:8888/{reference}"
echo "Host check:         curl -s http://127.0.0.1:9997/v3/config/paths/list"
