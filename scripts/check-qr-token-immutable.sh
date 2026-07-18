#!/usr/bin/env bash
# Fail if qr_token is accepted as writable input outside equipment create (DOC-13 / DOC-21).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# FormRequests must not whitelist qr_token as an input field.
HITS=$(rg -n --glob 'app/Http/Requests/**/*.php' -e "['\"]qr_token['\"]" app/Http/Requests || true)

if [[ -n "${HITS}" ]]; then
  echo "qr_token immutability check FAILED (FormRequest accepts qr_token):"
  echo "${HITS}"
  exit 1
fi

# Controllers must not mass-assign qr_token from request input.
BAD=$(rg -n --glob 'app/Http/Controllers/**/*.php' \
  -e "\$request->(input|validated|only|all)\([^)]*qr_token" \
  -e "['\"]qr_token['\"]\s*=>\s*\$request" \
  app/Http/Controllers || true)

if [[ -n "${BAD}" ]]; then
  echo "qr_token immutability check FAILED (controller writes qr_token from request):"
  echo "${BAD}"
  exit 1
fi

echo "qr_token immutability check passed."
