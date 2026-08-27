#!/usr/bin/env bash
# ZT411 on SCC (Ubuntu) — CUPS queue + connectivity check.
#
# Zebra's Windows "Printer Driver v10" / Setup Utilities from zebra.com do not
# install on Linux. Ubuntu uses CUPS with the built-in Zebra ZPL PPD (same path
# IR4 uses: raw ZPL over TCP :9100 — DOC-13/20).
#
# Usage (on SCC, as deploy user with sudo):
#   EQUIPMENT_PRINTER_HOST=192.168.2.210 bash scripts/06-setup-zt411-cups.sh
#
# Reads EQUIPMENT_PRINTER_* from Server/.env when env vars are unset.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="${ROOT}/.env"
QUEUE_NAME="${ZT411_CUPS_QUEUE:-IR4-ZT411}"

read_env() {
  local key="$1"
  local default="${2:-}"
  if [[ -f "$ENV_FILE" ]] && grep -qE "^${key}=" "$ENV_FILE"; then
    grep -E "^${key}=" "$ENV_FILE" | tail -1 | cut -d= -f2-
  else
    echo "$default"
  fi
}

PRINTER_HOST="${EQUIPMENT_PRINTER_HOST:-$(read_env EQUIPMENT_PRINTER_HOST "")}"
PRINTER_PORT="${EQUIPMENT_PRINTER_PORT:-$(read_env EQUIPMENT_PRINTER_PORT "9100")}"

if [[ -z "$PRINTER_HOST" ]]; then
  echo "ERROR: set EQUIPMENT_PRINTER_HOST in .env or environment" >&2
  exit 1
fi

echo "==> ZT411 CUPS setup"
echo "    Host:  ${PRINTER_HOST}:${PRINTER_PORT}"
echo "    Queue: ${QUEUE_NAME}"
echo

sudo apt-get update -qq
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y \
  cups cups-client cups-bsd netcat-openbsd

sudo systemctl enable --now cups

sudo lpadmin -x "$QUEUE_NAME" 2>/dev/null || true

sudo lpadmin -p "$QUEUE_NAME" -E \
  -v "socket://${PRINTER_HOST}:${PRINTER_PORT}" \
  -m "drv:///sample.drv/zebra.ppd" \
  -L "IR4 Equipment Labels (ZT411 ZPL)" \
  -o printer-is-shared=false

sudo cupsenable "$QUEUE_NAME"
sudo cupsaccept "$QUEUE_NAME"
sudo lpoptions -d "$QUEUE_NAME" 2>/dev/null || true

echo
echo "==> CUPS queue"
lpstat -p "$QUEUE_NAME" -l 2>/dev/null || lpstat -p "$QUEUE_NAME"
echo
echo "    Admin UI: http://127.0.0.1:631 (Set Default Options → media 3.15x1.85 @ 203dpi after first label)"
echo

TEST_ZPL=$'^XA^PW639^LL376^FO120,80^ADN,36,20^FDIR4 ZT411 Test^FS^XZ'

if nc -z -w3 "$PRINTER_HOST" "$PRINTER_PORT" 2>/dev/null; then
  echo "==> Printer reachable — sending test label (TCP :9100, same as IR4)"
  printf '%b\n' "$TEST_ZPL" | nc -w5 "$PRINTER_HOST" "$PRINTER_PORT"
  echo "    Test ZPL sent."
else
  echo "WARN: ${PRINTER_HOST}:${PRINTER_PORT} not reachable."
  echo "      Queue is configured; power on the printer, assign static IP, then re-run."
  exit 2
fi
