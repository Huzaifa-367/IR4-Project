#!/usr/bin/env bash
# Probe IR4 LAN fences (DOC-20). Run from hosts on each subnet.
# Usage: HOST=https://ir4.site.local EXPECT=operator|device|lan|external ./verify-network-fences.sh
set -euo pipefail

HOST="${HOST:-https://ir4.site.local}"
EXPECT="${EXPECT:-}"

probe() {
  local path="$1"
  curl -k -s -o /dev/null -w "%{http_code}" --connect-timeout 3 "${HOST}${path}" || echo "000"
}

code_up="$(probe /up)"
code_ingest="$(probe /api/ingest/tags)"
code_qr="$(probe /e/probe-token)"
code_app="$(probe /login)"

echo "up=${code_up} ingest=${code_ingest} qr=${code_qr} login=${code_app}"

case "${EXPECT}" in
  operator)
    [[ "${code_up}" != "403" && "${code_app}" != "403" ]] || { echo "operator fence failed"; exit 1; }
    ;;
  device)
    [[ "${code_ingest}" != "403" ]] || { echo "device fence failed"; exit 1; }
    ;;
  lan)
    [[ "${code_qr}" != "403" && "${code_up}" != "403" ]] || { echo "lan fence failed"; exit 1; }
    ;;
  external)
    [[ "${code_up}" == "403" && "${code_ingest}" == "403" && "${code_qr}" == "403" && "${code_app}" == "403" ]] \
      || { echo "external deny failed"; exit 1; }
    ;;
  *)
    echo "Set EXPECT=operator|device|lan|external"
    exit 2
    ;;
esac

echo "Fence probe OK for EXPECT=${EXPECT}"
