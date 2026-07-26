#!/usr/bin/env bash
# Fail if generated enums.ts is out of date vs ir4:export-enums.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

php artisan ir4:export-enums -q

TMP="$(mktemp)"
cp resources/js/types/enums.ts "$TMP"
php artisan ir4:export-enums -q

if ! cmp -s resources/js/types/enums.ts "$TMP"; then
  echo "enums.ts drifted — run: php artisan ir4:export-enums"
  diff -u "$TMP" resources/js/types/enums.ts || true
  rm -f "$TMP"
  exit 1
fi

rm -f "$TMP"
echo "Enum export check passed."
