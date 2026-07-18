#!/usr/bin/env bash
# Fail if Super Admin bypass via Gate::before appears (DOC-03 / DOC-21).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

HITS=$(rg -n --glob '!vendor/**' --glob '!node_modules/**' --glob '!Docs/**' -e 'Gate::before\s*\(' app bootstrap || true)

if [[ -n "${HITS}" ]]; then
  echo "Gate::before check FAILED:"
  echo "${HITS}"
  exit 1
fi

echo "No Gate::before check passed."
