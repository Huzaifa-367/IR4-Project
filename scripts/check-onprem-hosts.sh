#!/usr/bin/env bash
# Fail CI if shipped application code references external hosts (DOC-01/21).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# Scan only application source (not config comments, vendor, storage, Docs).
TARGETS=(app resources/js resources/css routes bootstrap database)

KNOWN='https?://(fonts\.googleapis\.com|fonts\.gstatic\.com|cdn\.jsdelivr\.net|unpkg\.com|cdnjs\.cloudflare\.com|ajax\.googleapis\.com|www\.googletagmanager\.com|www\.google-analytics\.com|js\.pusher\.com|api\.pusherapp\.com|sentry\.io|browser\.sentry-cdn\.com|amazonaws\.com|googleapis\.com|firebaseio\.com|supabase\.co|datadoghq\.com|github\.com|laravel\.com)'

HITS=$(rg -n -e "$KNOWN" "${TARGETS[@]}" || true)

# Any non-local http(s) URL in TS/TSX/JS (excluding comments already caught above for known hosts)
GENERIC=$(rg -n --glob '*.{ts,tsx,js,jsx,css}' -e 'https?://[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}' resources/js resources/css \
  | rg -v 'localhost|127\.0\.0\.1|ir4\.site\.local|example\.com|schema\.org|w3\.org|^\s*//' || true)

if [[ -n "${HITS}" || -n "${GENERIC}" ]]; then
  echo "On-prem external-host check FAILED:"
  [[ -n "${HITS}" ]] && echo "${HITS}"
  [[ -n "${GENERIC}" ]] && echo "${GENERIC}"
  exit 1
fi

echo "On-prem external-host check passed."
