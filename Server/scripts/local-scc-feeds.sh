#!/usr/bin/env bash
# Local laptop keep-alive: play SCC MediaMTX feeds in the local IR4 dashboard.
#
# What it does (idempotent — safe to re-run, no DB re-import):
#   1) Point Laravel HLS proxy at SCC MediaMTX (:8888 over Tailscale)
#   2) Stretch idle + stale timeouts so tiles stay "online"
#   3) Point camera stream_url rows at SCC RTSP (:8554)
#   4) Loop forever: refresh camera/device presence + print feed health
#
# Prerequisites:
#   - Tailscale up, SCC reachable (default scc2@100.118.103.39)
#   - Local `php artisan serve` (or equivalent) on :8000
#   - Local DB already has SCC data (or any cameras with CAM-* refs)
#
# Usage (leave this terminal open):
#   cd Server && bash scripts/local-scc-feeds.sh
#
# Env overrides:
#   IR4_SCC_HOST=100.118.103.39
#   IR4_FEED_INTERVAL=30          # seconds between presence heartbeats
#   IR4_SESSION_TIMEOUT_MIN=240
#   IR4_STALE_TIMEOUT_MIN=360
set -euo pipefail

export PATH="/usr/bin:/bin:/usr/sbin:/sbin:/opt/homebrew/bin:${PATH:-}"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

SCC_HOST="${IR4_SCC_HOST:-100.118.103.39}"
INTERVAL="${IR4_FEED_INTERVAL:-30}"
SESSION_MIN="${IR4_SESSION_TIMEOUT_MIN:-240}"
STALE_MIN="${IR4_STALE_TIMEOUT_MIN:-360}"
ENV_FILE="${ROOT}/.env"
HLS_BASE="http://${SCC_HOST}:8888"
RTSP_BASE="rtsp://${SCC_HOST}:8554"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "ERROR: missing $ENV_FILE" >&2
  exit 1
fi

if ! command -v php >/dev/null 2>&1; then
  echo "ERROR: php not on PATH" >&2
  exit 1
fi

ensure_env() {
  local key="$1"
  local value="$2"
  if grep -qE "^${key}=" "$ENV_FILE"; then
    # macOS + GNU sed
    sed -i.bak "s|^${key}=.*|${key}=${value}|" "$ENV_FILE"
    rm -f "${ENV_FILE}.bak"
  else
    printf '\n%s=%s\n' "$key" "$value" >> "$ENV_FILE"
  fi
}

echo "==> Local SCC feed keep-alive"
echo "    SCC host:  $SCC_HOST"
echo "    HLS up:    $HLS_BASE  (Laravel proxies → /hls/{reference}/)"
echo "    RTSP up:   $RTSP_BASE"
echo "    Interval:  ${INTERVAL}s"
echo "    Dashboard: http://127.0.0.1:8000/live"
echo

# Same-origin browser playback via Laravel /hls proxy → SCC MediaMTX.
# Do NOT point the browser at SCC :8888 directly: MediaMTX cookieCheck sets
# Secure cookies that browsers refuse on http://127.0.0.1 (black live wall).
ensure_env "CAMERA_BROWSER_STREAM_URL_TEMPLATE" "/hls/{reference}/"
ensure_env "MEDIAMTX_HLS_URL" "$HLS_BASE"
# Local API unused for playback when HLS is remote — leave as-is if already set.
ensure_env "MEDIAMTX_SOURCE_ON_DEMAND" "false"
ensure_env "MEDIAMTX_RTSP_TRANSPORT" "tcp"

php artisan config:clear >/dev/null

echo "==> Timeouts + stream_url → SCC (once)"
php artisan tinker --execute="
\$s = app(App\Services\Settings\SettingsService::class);
\$s->set('auth.session_timeout_minutes', ${SESSION_MIN}, confirmed: true);
foreach ([
  'health.camera_stale_minutes' => ${STALE_MIN},
  'health.reader_stale_minutes' => ${STALE_MIN},
  'health.gas_stale_minutes' => ${STALE_MIN},
  'health.sensor_stale_minutes' => ${STALE_MIN},
  'health.edge_stale_minutes' => ${STALE_MIN},
] as \$k => \$v) {
  \$s->set(\$k, \$v);
}
\$relay = '${RTSP_BASE}';
\$n = 0;
foreach (App\Models\Camera::all() as \$c) {
  \$c->forceFill([
    'stream_url' => \$relay.'/'.\$c->reference,
    'status' => App\Enums\HardwareStatus::Online,
    'last_frame_at' => now(),
  ])->save();
  \$n++;
}
echo 'cameras_wired='.\$n.' browser='.config('camera_stream.browser_url_template').' hls='.config('camera_stream.mediamtx.hls_url').PHP_EOL;
" || {
  echo "ERROR: artisan bootstrap failed — is MySQL up and .env DB_* correct?" >&2
  exit 1
}

probe_hls() {
  local ref="$1"
  local code
  # Follow redirects — MediaMTX returns 302 cookieCheck before 200.
  code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 12 -L "${HLS_BASE}/${ref}/index.m3u8" 2>/dev/null || echo 000)"
  printf '%s' "$code"
}

echo "==> Probing SCC HLS"
REFS=()
while IFS= read -r ref; do
  [[ -n "$ref" ]] && REFS+=("$ref")
done < <(
  php -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    foreach (App\Models\Camera::orderBy("reference")->pluck("reference") as $r) {
      echo $r, PHP_EOL;
    }
  ' 2>/dev/null
)

if [[ ${#REFS[@]} -eq 0 ]]; then
  echo "ERROR: no cameras in local DB" >&2
  exit 1
fi

ok=0
for ref in "${REFS[@]}"; do
  code="$(probe_hls "$ref")"
  if [[ "$code" == "200" ]]; then
    echo "    $ref HLS $code"
    ok=$((ok + 1))
  else
    echo "    $ref HLS $code (SCC MediaMTX path may be cold)"
  fi
done

if [[ "$ok" -eq 0 ]]; then
  echo "ERROR: no SCC HLS playlists reachable at $HLS_BASE — check Tailscale / MediaMTX on SCC" >&2
  exit 1
fi

echo
echo "==> Keep-alive running (Ctrl+C to stop). Hard-refresh /live once."
echo

heartbeat() {
  php artisan tinker --execute="
\$now = now();
\$cams = App\Models\Camera::query()->update([
  'status' => 'online',
  'last_frame_at' => \$now,
]);
\$devs = App\Models\Device::query()->update([
  'status' => 'online',
  'last_seen_at' => \$now,
]);
echo 'cams='.\$cams.' devices='.\$devs.PHP_EOL;
" 2>/dev/null | tail -1
}

trap 'echo; echo "==> stopped"; exit 0' INT TERM

while true; do
  ts="$(date '+%H:%M:%S')"
  hb="$(heartbeat || echo fail)"
  line="$ts presence=$hb"
  for ref in "${REFS[@]}"; do
    code="$(probe_hls "$ref")"
    line+=" ${ref}:${code}"
  done
  echo "$line"
  sleep "$INTERVAL"
done
