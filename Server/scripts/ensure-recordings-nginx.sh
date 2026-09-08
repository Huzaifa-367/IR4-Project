#!/usr/bin/env bash
# Ensure Lerd nginx X-Accel + /data2/video mounts for DOC-24 recordings archive.
# Idempotent. Safe after `lerd secure` / vhost regen (uses custom.d include).
#
#   export PATH="$HOME/.local/share/lerd/bin:$HOME/.local/bin:$PATH"
#   cd /data2/laravel/IR4-Project && bash scripts/ensure-recordings-nginx.sh
set -euo pipefail

export PATH="${HOME}/.local/share/lerd/bin:${HOME}/.local/bin:${PATH}"

ROOT="${RECORDINGS_ROOT:-/data2/video}"
CUSTOM_DIR="${HOME}/.local/share/lerd/nginx/custom.d"
SITE="${LERD_SITE_NAME:-ir4-project.test}"
SNIPPET="${CUSTOM_DIR}/${SITE}.conf"
APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"

if [[ ! -d "${ROOT}" ]]; then
  echo "ERROR: ${ROOT} does not exist on the host." >&2
  exit 1
fi

# Global Lerd mounts (PHP + nginx) — applied on next full start / EnsurePathMounted.
CONFIG="${HOME}/.config/lerd/config.yaml"
if [[ -f "${CONFIG}" ]] && ! grep -qE "^[[:space:]]*-[[:space:]]*${ROOT}[[:space:]]*$" "${CONFIG}"; then
  echo "Add to ${CONFIG} under mounts:"
  echo "  - ${ROOT}"
  echo "Then: cd ${APP_ROOT} && lerd stop && lerd start"
  exit 1
fi

mkdir -p "${CUSTOM_DIR}"
cat > "${SNIPPET}" <<EOF
# DOC-24 recordings archive — X-Accel-Redirect target (internal only).
# Included by the site vhost via: include /etc/nginx/custom.d/${SITE}.conf*;
location /internal-recordings/ {
    internal;
    alias ${ROOT}/;
}
EOF
echo "Wrote ${SNIPPET}"

# EnsurePathMounted: running php from the recordings root updates PHP + nginx quadlets.
cd "${ROOT}"
lerd php -r 'exit(is_dir("'"${ROOT}"'") ? 0 : 1);' >/dev/null
cd "${APP_ROOT}"

if ! podman exec lerd-nginx test -d "${ROOT}"; then
  echo "nginx still missing ${ROOT} — run: cd ${APP_ROOT} && lerd stop && lerd start" >&2
  exit 1
fi

podman exec lerd-nginx nginx -t
podman exec lerd-nginx nginx -s reload
echo "OK: X-Accel location active; ${ROOT} visible to nginx."
