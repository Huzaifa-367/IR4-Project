#!/usr/bin/env bash
# Fail if standalone/no-tenant invariant is violated (DOC-01 / DOC-21).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

HITS=$(rg -n --glob '!vendor/**' --glob '!node_modules/**' --glob '!Docs/**' --glob '!tests/**' --glob '!*.md' --glob '!public/build/**' --glob '!deploy/**' \
  -e '\bsite_id\b' -e "Schema::create\('sites'" -e 'create_sites_table' app database routes config || true)

if [[ -n "${HITS}" ]]; then
  echo "Standalone check FAILED (site_id / sites table):"
  echo "${HITS}"
  exit 1
fi

echo "Standalone check passed."
