#!/usr/bin/env bash
# IR4 deploy preflight (DOC-20). Run from the app root as the deploy user.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

fail() { echo "FAIL: $*" >&2; exit 1; }
ok() { echo "OK: $*"; }

[[ -f .env ]] || fail ".env missing — copy deploy/env/ir4.production.env.example"
grep -q '^APP_KEY=base64:' .env || fail "APP_KEY not set"
grep -q '^APP_ENV=production' .env || fail "APP_ENV must be production"
grep -q '^APP_DEBUG=false' .env || fail "APP_DEBUG must be false"
grep -q '^DB_CONNECTION=mysql' .env || fail "DB_CONNECTION must be mysql"
grep -q '^QUEUE_CONNECTION=redis' .env || fail "QUEUE_CONNECTION must be redis"
grep -q '^CACHE_STORE=redis' .env || fail "CACHE_STORE must be redis"
grep -q '^BACKUP_ENCRYPTION_KEY=.' .env || fail "BACKUP_ENCRYPTION_KEY missing"
grep -q '^IR4_WIPE_DB_USERNAME=.' .env || fail "IR4_WIPE_DB_USERNAME missing"
grep -q '^IR4_BACKUP_DB_USERNAME=.' .env || fail "IR4_BACKUP_DB_USERNAME missing"

command -v php >/dev/null || fail "php missing"
php -r 'exit(version_compare(PHP_VERSION, "8.4.0", ">=") ? 0 : 1);' || fail "PHP 8.4+ required"
php -m | grep -qi pdo_mysql || fail "pdo_mysql extension missing"
php -m | grep -qi redis || fail "redis extension missing"
command -v mysql >/dev/null || fail "mysql client missing"
command -v nginx >/dev/null || fail "nginx missing"
command -v supervisorctl >/dev/null || fail "supervisor missing"

ok "preflight passed"
