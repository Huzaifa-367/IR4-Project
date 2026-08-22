#!/usr/bin/env bash
# Build the SanDisk / SCC offline package (needs internet).
# Output: dist/ir4-edge-offline.tar.gz
#
#   ./deploy/pack_bundle.sh
#   ./deploy/pack_bundle.sh --out /Volumes/SanDisk/ir4-edge-offline.tar.gz
#
# Layout inside the archive (same as live /opt/ir4-edge after install.sh):
#   ir4-edge-offline/install.sh
#   ir4-edge-offline/EdgeCompute/   code + configs + systemd
#   ir4-edge-offline/wheels/        Jetson aarch64 pip wheels
#   ir4-edge-offline/var/           empty runtime dir
#
# venv is NOT in the tarball: SCC/laptop are x86, Orin is aarch64.
# install.sh creates /opt/ir4-edge/venv from wheels on the Jetson.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
EDGE_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
OUT=""

usage() {
  cat <<'EOF'
Usage: ./deploy/pack_bundle.sh [--out PATH.tar.gz]
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --out)
      OUT="${2:-}"
      shift 2
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown argument: $1" >&2
      usage >&2
      exit 1
      ;;
  esac
done

[[ -n "${OUT}" ]] || OUT="${EDGE_ROOT}/dist/ir4-edge-offline.tar.gz"
mkdir -p "$(dirname "${OUT}")"

STAGE="$(mktemp -d /tmp/ir4-edge-pack.XXXXXX)"
cleanup() { rm -rf "${STAGE}"; }
trap cleanup EXIT

BUNDLE="${STAGE}/ir4-edge-offline"
mkdir -p "${BUNDLE}/EdgeCompute" "${BUNDLE}/wheels" "${BUNDLE}/var"

echo "==> Staging EdgeCompute code"
rsync -a \
  --exclude '.git/' \
  --exclude '.venv/' --exclude 'venv/' \
  --exclude '.wheels/' --exclude 'wheels/' \
  --exclude 'dist/' --exclude 'build/' \
  --exclude '__pycache__/' --exclude '*.pyc' --exclude '*.egg-info/' \
  --exclude 'configs/secrets.env' \
  --exclude 'var/' \
  "${EDGE_ROOT}/" "${BUNDLE}/EdgeCompute/"
touch "${BUNDLE}/var/.keep"
install -m 0755 "${SCRIPT_DIR}/install.sh" "${BUNDLE}/install.sh"

echo "==> Fetching Jetson aarch64 wheels (not this machine’s arch)"
PIP=(python3 -m pip)
if ! python3 -m pip --version >/dev/null 2>&1; then
  echo "ERROR: python3 pip required to pack wheels" >&2
  exit 1
fi
PKGS=(pip setuptools wheel 'httpx>=0.27,<0.29' 'paho-mqtt>=2.0,<3' 'pyserial>=3.5,<4' 'PyYAML>=6.0,<7')
download_abi() {
  local pyver="$1" abi="$2"
  echo "    ${abi} manylinux aarch64"
  "${PIP[@]}" download -q -d "${BUNDLE}/wheels" \
    --python-version "${pyver}" \
    --implementation cp \
    --abi "${abi}" \
    --platform manylinux_2_17_aarch64 \
    --only-binary=:all: \
    "${PKGS[@]}" || true
}
download_abi 310 cp310
download_abi 38 cp38
echo "    ir4-edge (pure python)"
"${PIP[@]}" wheel -q -w "${BUNDLE}/wheels" "${EDGE_ROOT}"
count="$(find "${BUNDLE}/wheels" -type f | wc -l | tr -d ' ')"
if [[ "${count}" -lt 3 ]]; then
  echo "ERROR: wheelhouse almost empty (${count} files)" >&2
  exit 1
fi
echo "    ${count} wheel files"

cat > "${BUNDLE}/README.txt" <<EOF
IR4 Edge — offline bundle (SanDisk / SCC copy)

On the Orin:
  tar -xzf ir4-edge-offline.tar.gz
  cd ir4-edge-offline
  sudo ./install.sh --pole 2

Installs to /opt/ir4-edge/{EdgeCompute,venv,var,wheels} and starts agents.
Keeps an existing configs/secrets.env. Stamps IR4_BASE_URL for that pole.
EOF

mkdir -p "${EDGE_ROOT}/.wheels"
rsync -a "${BUNDLE}/wheels/" "${EDGE_ROOT}/.wheels/"
echo "==> Writing ${OUT}"
tar -C "${STAGE}" -czf "${OUT}" ir4-edge-offline
ls -lh "${OUT}"
echo "==> Done. USB: copy the tarball. Online: ./deploy/scc_push.sh"
