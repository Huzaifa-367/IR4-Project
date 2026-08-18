#!/usr/bin/env bash
# Pole has internet — overlay latest EdgeCompute, keep secrets.env, reinstall
# venv + systemd. Does not uninstall or wipe /opt/ir4-edge.
#
#   sudo ir4-edge update
#   sudo ./deploy/orin_update.sh
#   sudo ./deploy/orin_update.sh --from /path/to/EdgeCompute
#
# No internet on the Orin: use scc_install.sh or USB (deploy/README.md).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SELF_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
DEST="/opt/ir4-edge/EdgeCompute"
REPO_URL="${IR4_EDGE_REPO:-https://github.com/Huzaifa-367/IR4-Project.git}"
BRANCH="${IR4_EDGE_BRANCH:-main}"
FROM=""
WORKDIR=""
SECRETS_BAK=""

usage() {
  cat <<'EOF'
Usage: sudo ./deploy/orin_update.sh [--from DIR] [--branch NAME]

Pole with internet: overlay EdgeCompute onto /opt/ir4-edge/EdgeCompute.
Keeps configs/secrets.env. Re-runs pip + systemd (no uninstall).
No internet: use scc_install.sh or USB install.sh (deploy/README.md).
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --from)
      FROM="${2:-}"
      shift 2
      ;;
    --branch)
      BRANCH="${2:-}"
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

[[ "$(id -u)" -eq 0 ]] || { echo "Use sudo" >&2; exit 1; }

if [[ ! -d "${DEST}" ]]; then
  echo "ERROR: no install at ${DEST}" >&2
  echo "       First install: copy EdgeCompute there, then sudo ./deploy/orin_bootstrap.sh" >&2
  exit 1
fi

cleanup() {
  [[ -n "${WORKDIR}" && -d "${WORKDIR}" ]] && rm -rf "${WORKDIR}"
  [[ -n "${SECRETS_BAK}" && -f "${SECRETS_BAK}" ]] && rm -f "${SECRETS_BAK}"
}
trap cleanup EXIT

resolve_src() {
  if [[ -n "${FROM}" ]]; then
    local from_real
    from_real="$(realpath "${FROM}")"
    if [[ -d "${from_real}/EdgeCompute" ]]; then
      echo "${from_real}/EdgeCompute"
    elif [[ -d "${from_real}/ir4_edge" ]]; then
      echo "${from_real}"
    else
      echo "ERROR: --from must be the EdgeCompute folder or the monorepo root" >&2
      exit 1
    fi
    return
  fi

  local self_real dest_real
  self_real="$(realpath "${SELF_ROOT}")"
  dest_real="$(realpath "${DEST}")"
  if [[ "${self_real}" != "${dest_real}" ]]; then
    echo "${self_real}"
    return
  fi

  echo "==> Fetching ${REPO_URL} (${BRANCH})" >&2
  WORKDIR="$(mktemp -d /tmp/ir4-edge-update.XXXXXX)"
  if ! git clone --depth 1 --branch "${BRANCH}" "${REPO_URL}" "${WORKDIR}/repo" >&2; then
    echo "ERROR: git clone failed. Copy the tree and run: sudo $0 --from /path/to/EdgeCompute" >&2
    exit 1
  fi
  echo "${WORKDIR}/repo/EdgeCompute"
}

SRC="$(resolve_src)"
SRC="$(realpath "${SRC}")"
DEST="$(realpath "${DEST}")"

if [[ "${SRC}" == "${DEST}" ]]; then
  echo "ERROR: source and install path are the same (${DEST})" >&2
  echo "       Fetch a copy first, or pass --from /path/to/new/EdgeCompute" >&2
  exit 1
fi

if [[ ! -f "${SRC}/pyproject.toml" || ! -d "${SRC}/ir4_edge" ]]; then
  echo "ERROR: ${SRC} does not look like an EdgeCompute tree" >&2
  exit 1
fi

echo "==> ir4-edge update"
echo "    from ${SRC}"
echo "    onto ${DEST}"

if [[ -f "${DEST}/configs/secrets.env" ]]; then
  SECRETS_BAK="$(mktemp /tmp/ir4-secrets.env.XXXXXX)"
  cp -a "${DEST}/configs/secrets.env" "${SECRETS_BAK}"
  echo "    keeping configs/secrets.env"
fi

echo "==> Overlaying code (secrets.env preserved)"
if command -v rsync >/dev/null 2>&1; then
  rsync -a --delete \
    --exclude 'configs/secrets.env' \
    --exclude '.git/' \
    --exclude '__pycache__/' \
    --exclude '*.pyc' \
    "${SRC}/" "${DEST}/"
else
  (cd "${SRC}" && tar \
    --exclude='./configs/secrets.env' \
    --exclude='./.git' \
    --exclude='./__pycache__' \
    -cf - .) | (cd "${DEST}" && tar xf -)
fi

if [[ -n "${SECRETS_BAK}" && -f "${SECRETS_BAK}" ]]; then
  cp -a "${SECRETS_BAK}" "${DEST}/configs/secrets.env"
  chmod 640 "${DEST}/configs/secrets.env"
fi

echo "==> Reinstalling package + units (no uninstall)"
"${DEST}/deploy/orin_bootstrap.sh"

echo "==> Update complete. ir4-edge doctor && ir4-edge status"
