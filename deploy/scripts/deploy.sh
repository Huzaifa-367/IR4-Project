#!/usr/bin/env bash
# Idempotent IR4 deploy (DOC-20). Run from the app root as the deploy user with sudo for service restarts.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

bash "$ROOT/deploy/scripts/preflight.sh"

composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

php artisan down --retry=60 || true
php artisan migrate --force
php artisan db:seed --force --class=Database\\Seeders\\SettingsSeeder
php artisan db:seed --force --class=Database\\Seeders\\RolePermissionSeeder
php artisan db:seed --force --class=Database\\Seeders\\GasThresholdSeeder
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link || true
php artisan queue:restart
php artisan up

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart ir4:*
sudo systemctl reload php8.4-fpm
sudo systemctl reload nginx

echo "Deploy complete. Run commissioning smoke next."
