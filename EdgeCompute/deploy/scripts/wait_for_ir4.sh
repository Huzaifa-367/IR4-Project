#!/usr/bin/env bash
# ExecStartPre for IR4 agents: wait until IR4_BASE_URL is reachable.
# Covers the post-reboot VLAN race where network-online fires before .40 routes.
set -euo pipefail

url="${IR4_BASE_URL:-}"
if [[ -z "${url}" ]]; then
  echo "wait_for_ir4: IR4_BASE_URL unset — skipping" >&2
  exit 0
fi

max_seconds="${IR4_WAIT_SCC_SECONDS:-180}"
interval=2

host_port="$(python3 - "${url}" <<'PY'
import sys
from urllib.parse import urlparse
u = urlparse(sys.argv[1].strip())
host = u.hostname or ""
port = u.port or (443 if u.scheme == "https" else 80)
if not host:
    sys.exit(2)
print("{} {}".format(host, port))
PY
)" || {
  echo "wait_for_ir4: cannot parse IR4_BASE_URL=${url}" >&2
  exit 0
}
# shellcheck disable=SC2086
set -- ${host_port}
host="$1"
port="$2"

echo "wait_for_ir4: waiting up to ${max_seconds}s for ${host}:${port}"
deadline=$((SECONDS + max_seconds))
while (( SECONDS < deadline )); do
  if python3 - "${host}" "${port}" <<'PY'
import socket, sys
host, port = sys.argv[1], int(sys.argv[2])
s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
s.settimeout(2.0)
try:
    s.connect((host, port))
except OSError:
    sys.exit(1)
finally:
    s.close()
sys.exit(0)
PY
  then
    echo "wait_for_ir4: ${host}:${port} reachable"
    exit 0
  fi
  sleep "${interval}"
done

echo "wait_for_ir4: timed out waiting for ${host}:${port} — starting anyway (agent will buffer)" >&2
exit 0
