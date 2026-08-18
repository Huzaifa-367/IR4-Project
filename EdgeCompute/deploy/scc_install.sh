#!/usr/bin/env bash
# SCC → poles over LAN SSH (install or update). Jetsons stay offline.
# Flows: deploy/README.md
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
EDGE_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
DO_PACK=0
SKIP_PACK=0
POLE_FILTER=""

POLE_ROWS=(
  "1 pole1 172.16.3.2"
  "2 pole2 172.16.2.2"
  "3 pole3 172.16.1.50"
  "4 pole4 172.16.4.2"
)

usage() {
  cat <<'EOF'
Method 1 — SCC → pole (run on SCC2). Install and update:

  ~/EdgeCompute/deploy/scc_install.sh
  ~/EdgeCompute/deploy/scc_install.sh --poles 2

All methods: EdgeCompute/deploy/README.md
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --poles) POLE_FILTER="${2:-}"; shift 2 ;;
    --pack) DO_PACK=1; shift ;;
    --no-pack) SKIP_PACK=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown argument: $1" >&2; usage >&2; exit 1 ;;
  esac
done

want_pole() {
  local n="$1"
  [[ -z "${POLE_FILTER}" ]] && return 0
  [[ ",${POLE_FILTER}," == *",${n},"* ]]
}

wheels_ok() {
  [[ -d "${EDGE_ROOT}/.wheels" ]] && ls "${EDGE_ROOT}/.wheels"/* >/dev/null 2>&1
}

if [[ "${SKIP_PACK}" -eq 0 ]]; then
  if [[ "${DO_PACK}" -eq 1 ]] || ! wheels_ok; then
    echo "==> pack_bundle (Jetson aarch64 wheels; internet on SCC)"
    "${SCRIPT_DIR}/pack_bundle.sh" || echo "WARN: pack failed — existing pole venvs can still overlay" >&2
  fi
fi

STAGE="$(mktemp -d /tmp/ir4-edge-online.XXXXXX)"
cleanup() { rm -rf "${STAGE}"; }
trap cleanup EXIT
PAYLOAD="${STAGE}/ir4-edge-offline"
mkdir -p "${PAYLOAD}/EdgeCompute" "${PAYLOAD}/wheels" "${PAYLOAD}/var"
rsync -a \
  --exclude '.git/' --exclude '.venv/' --exclude 'venv/' \
  --exclude '.wheels/' --exclude 'wheels/' --exclude 'dist/' --exclude 'build/' \
  --exclude '__pycache__/' --exclude '*.pyc' --exclude '*.egg-info/' \
  --exclude 'configs/secrets.env' --exclude 'var/' \
  "${EDGE_ROOT}/" "${PAYLOAD}/EdgeCompute/"
install -m 0755 "${SCRIPT_DIR}/install_bundle.sh" "${PAYLOAD}/install.sh"
touch "${PAYLOAD}/var/.keep"
if [[ -d "${EDGE_ROOT}/.wheels" ]] && ls "${EDGE_ROOT}/.wheels"/* >/dev/null 2>&1; then
  rsync -a "${EDGE_ROOT}/.wheels/" "${PAYLOAD}/wheels/"
fi

push_pole() {
  local n="$1" user="$2" ip="$3"
  echo
  echo "############################################"
  echo "# POLE ${n}  ${user}@${ip}"
  echo "############################################"
  if ! ping -c 1 -W 2 "${ip}" >/dev/null 2>&1; then
    echo "SKIP: Jetson down"
    return 0
  fi
  echo "==> rsync"
  rsync -az --delete --exclude 'configs/secrets.env' \
    "${PAYLOAD}/" "${user}@${ip}:/tmp/ir4-edge-offline/"
  ssh -o BatchMode=yes -o ConnectTimeout=15 -o StrictHostKeyChecking=accept-new \
    "${user}@${ip}" "sudo /tmp/ir4-edge-offline/install.sh --pole ${n}"
}

echo "==> Online push from ${EDGE_ROOT}"
ok=0
fail=0
for row in "${POLE_ROWS[@]}"; do
  set -- ${row}
  n="$1" user="$2" ip="$3"
  want_pole "${n}" || continue
  if push_pole "${n}" "${user}" "${ip}"; then
    ok=$((ok + 1))
  else
    fail=$((fail + 1))
    echo "FAIL: pole ${n}" >&2
  fi
done
echo
echo "# SUMMARY ok=${ok} fail=${fail}"
[[ "${fail}" -eq 0 ]]
