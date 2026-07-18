#!/usr/bin/env bash
# Fail if PPE violations gain a worker_id column (DOC-10 / DOC-21).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

HITS=$(rg -n --glob 'database/migrations/**' -e "ppe_violations" -e "worker_id" database/migrations \
  | rg -n 'ppe_violations' -A2 -B2 || true)

# Narrow: any migration that both mentions ppe_violations and adds worker_id on that table.
BAD=$(rg -n --glob 'database/migrations/**' -U 'ppe_violations[\s\S]{0,400}worker_id|worker_id[\s\S]{0,400}ppe_violations' database/migrations || true)

MODEL=$(rg -n --glob 'app/Models/PpeViolation.php' -e "worker_id" app/Models/PpeViolation.php || true)

if [[ -n "${BAD}" || -n "${MODEL}" ]]; then
  echo "PPE anonymity check FAILED:"
  echo "${BAD}"
  echo "${MODEL}"
  exit 1
fi

echo "PPE no-worker_id check passed."
