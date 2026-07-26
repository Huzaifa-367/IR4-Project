#!/usr/bin/env bash
# Fail if app code updates/deletes audit_logs rows (DOC-17 / DOC-21).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# Allow SecureWipeService (privileged wipe connection) and model guards that throw.
HITS=$(rg -n --glob '!vendor/**' --glob '!node_modules/**' --glob '!Docs/**' --glob '!tests/**' \
  -e 'audit_logs.*->(update|delete|forceDelete)\(' \
  -e 'AuditLog::query\(\)->(update|delete|forceDelete)\(' \
  -e 'DB::table\([\"'\'']audit_logs[\"'\'']\)->(update|delete)' \
  app || true)

# Filter out SecureWipeService destroyDatabase generic table loop (uses dynamic $table).
FILTERED=$(echo "${HITS}" | rg -v 'SecureWipeService' || true)

if [[ -n "${FILTERED}" ]]; then
  echo "Audit append-only check FAILED:"
  echo "${FILTERED}"
  exit 1
fi

echo "Audit append-only check passed."
