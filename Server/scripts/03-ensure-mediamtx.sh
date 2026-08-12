#!/usr/bin/env bash
# Start MediaMTX on the host network so HLS/API bind on the SCC.
# Accepts Docker or Podman (Lerd SCCs often have Podman only — no docker.service).
# Lerd PHP cannot use 127.0.0.1 — set MEDIAMTX_API_URL to the SCC LAN IP in .env.
set -euo pipefail

# systemd units have a minimal PATH — resolve binaries explicitly.
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:${PATH:-}"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
NAME="${IR4_MEDIAMTX_NAME:-ir4-mediamtx}"
IMAGE="${IR4_MEDIAMTX_IMAGE:-bluenviron/mediamtx:latest}"
CFG="${IR4_MEDIAMTX_CONFIG:-$ROOT/scripts/mediamtx.yml}"

resolve_runtime() {
  local candidate
  for candidate in /usr/bin/docker /usr/local/bin/docker docker \
    /usr/bin/podman /usr/local/bin/podman podman; do
    if command -v "$candidate" >/dev/null 2>&1; then
      command -v "$candidate"
      return 0
    fi
    if [[ -x "$candidate" ]]; then
      echo "$candidate"
      return 0
    fi
  done
  return 1
}

RUNTIME="$(resolve_runtime || true)"
if [[ -z "${RUNTIME}" ]]; then
  echo "ERROR: need docker or podman to run MediaMTX." >&2
  echo "  sudo apt install -y docker.io" >&2
  echo "  # or ensure podman is on PATH for root (systemctl runs as root)" >&2
  exit 1
fi

RUNTIME_NAME="$(basename "$RUNTIME")"

if [[ ! -f "$CFG" ]]; then
  echo "Missing MediaMTX config: $CFG" >&2
  exit 1
fi

if [[ -d "$CFG" ]]; then
  echo "MediaMTX config path is a directory (container created it). Replace with the real file:" >&2
  echo "  rm -rf '$CFG' && cp '$ROOT/scripts/mediamtx.yml' '$CFG'" >&2
  exit 1
fi

echo "==> Using container runtime: $RUNTIME"
echo "==> Config: $CFG"
echo "==> Image:  $IMAGE"

# Drop any previous container (podman prefers --replace on run).
"$RUNTIME" rm -f "$NAME" >/dev/null 2>&1 || true

run_mediamtx() {
  local -a args=(
    run -d
    --name "$NAME"
    --network host
    -v "${CFG}:/mediamtx.yml:ro"
  )
  # Podman: --replace avoids name conflicts; restart policy optional under systemd oneshot.
  if [[ "$RUNTIME_NAME" == "podman" ]]; then
    args+=(--replace --restart=always)
  else
    args+=(--restart=unless-stopped)
  fi
  args+=("$IMAGE")
  "$RUNTIME" "${args[@]}"
}

set +e
OUT="$(run_mediamtx 2>&1)"
RC=$?
set -e

if [[ "$RC" -ne 0 ]]; then
  echo "ERROR: $RUNTIME_NAME failed to start MediaMTX (exit $RC):" >&2
  echo "$OUT" >&2
  echo >&2
  echo "Common fixes:" >&2
  echo "  1) Image missing offline: $RUNTIME pull $IMAGE" >&2
  echo "  2) Manual test: sudo bash $ROOT/scripts/03-ensure-mediamtx.sh" >&2
  echo "  3) Config must be a file, not a directory: ls -la $CFG" >&2
  exit "$RC"
fi

echo "$OUT"
sleep 1

if ! "$RUNTIME" inspect "$NAME" >/dev/null 2>&1; then
  echo "ERROR: container $NAME did not stay running." >&2
  "$RUNTIME" ps -a --filter "name=${NAME}" >&2 || true
  exit 1
fi

echo "MediaMTX started as ${NAME} (--network host, runtime=$RUNTIME_NAME)"
echo
echo "Host check (must NOT say authentication error):"
echo "  curl -s http://127.0.0.1:9997/v3/config/paths/list"
curl -s http://127.0.0.1:9997/v3/config/paths/list || true
echo
echo

# Permanent .env: this SCC's LAN IP + same-origin /hls template + warm TCP RTSP.
if [[ -f "$ROOT/.env" ]]; then
  bash "$ROOT/scripts/ensure-mediamtx-env.sh" "$ROOT" || true
  echo
  echo "Apply Laravel config + push camera paths:"
  echo "  lerd artisan config:clear && lerd artisan ir4:sync-camera-streams"
else
  echo "From Lerd PHP set in .env (use SCC LAN IP — not 127.0.0.1):"
  echo "  bash scripts/ensure-mediamtx-env.sh"
  echo "  # or manually:"
  echo "  MEDIAMTX_API_URL=http://<SCC-LAN-IP>:9997"
  echo "  CAMERA_BROWSER_STREAM_URL_TEMPLATE=/hls/{reference}/"
  echo "  MEDIAMTX_SOURCE_ON_DEMAND=false"
  echo "  MEDIAMTX_RTSP_TRANSPORT=tcp"
  echo
  echo "Then: lerd artisan config:clear && lerd artisan ir4:sync-camera-streams"
fi
