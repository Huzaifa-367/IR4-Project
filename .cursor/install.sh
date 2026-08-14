#!/usr/bin/env bash
# IR4 Platform — Cloud Agent install phase.
# Idempotent repository bootstrap: system packages (only if missing), PHP/Node
# dependencies, .env, database + migrations, and baseline seed data.
# Safe to re-run; safe to run from a snapshot that already has the toolchain.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SERVER_DIR="${REPO_ROOT}/Server"

DB_NAME="ir4_project"
DB_USER="ir4_app"
DB_PASS="ir4_app"
TEST_DB="ir4_test"
TEST_USER="ir4_test"

log() { printf '[install] %s\n' "$*"; }

install_system_packages() {
  if command -v php >/dev/null 2>&1 \
    && command -v composer >/dev/null 2>&1 \
    && command -v mysqld >/dev/null 2>&1 \
    && command -v redis-server >/dev/null 2>&1; then
    log "system toolchain already present; skipping apt"
    return
  fi

  log "installing system toolchain (PHP 8.4, MySQL 8, Redis, Composer)"
  export DEBIAN_FRONTEND=noninteractive
  sudo apt-get update -y
  sudo add-apt-repository -y ppa:ondrej/php
  sudo apt-get update -y
  sudo apt-get install -y \
    php8.4-cli php8.4-common php8.4-mysql php8.4-redis php8.4-gd php8.4-zip \
    php8.4-bcmath php8.4-mbstring php8.4-xml php8.4-curl php8.4-intl \
    php8.4-sqlite3 php8.4-gmp \
    mysql-server redis-server unzip zip

  if ! command -v composer >/dev/null 2>&1; then
    log "installing Composer"
    php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
    sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
  fi
}

start_services() {
  sudo service mysql start >/dev/null 2>&1 || true
  sudo service redis-server start >/dev/null 2>&1 || true
  log "waiting for MySQL on 127.0.0.1:3306"
  for _ in $(seq 1 30); do
    if (exec 3<>/dev/tcp/127.0.0.1/3306) 2>/dev/null; then
      exec 3>&- 3<&- || true
      return 0
    fi
    sleep 1
  done
  log "WARNING: MySQL did not become reachable in time"
}

ensure_databases() {
  log "ensuring databases and users exist"
  sudo mysql <<SQL
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS ${TEST_DB} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED WITH mysql_native_password BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED WITH mysql_native_password BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${TEST_USER}'@'127.0.0.1' IDENTIFIED WITH mysql_native_password BY '';
CREATE USER IF NOT EXISTS '${TEST_USER}'@'localhost' IDENTIFIED WITH mysql_native_password BY '';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON ${TEST_DB}.* TO '${TEST_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON ${TEST_DB}.* TO '${TEST_USER}'@'localhost';
GRANT ALL PRIVILEGES ON ${TEST_DB}.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
}

ensure_env() {
  cd "${SERVER_DIR}"
  if [ ! -f .env ]; then
    log "creating Server/.env from .env.example"
    cp .env.example .env
  fi
  # Point the app at the local MySQL/Redis running inside the VM.
  sed -i 's|^APP_URL=.*|APP_URL=http://localhost:8000|' .env
  sed -i 's|^DB_HOST=.*|DB_HOST=127.0.0.1|' .env
  sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" .env
  sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" .env
  sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" .env
  sed -i 's|^REDIS_HOST=.*|REDIS_HOST=127.0.0.1|' .env
  sed -i 's|^VITE_REVERB_HOST=.*|VITE_REVERB_HOST=localhost|' .env
  sed -i 's|^VITE_REVERB_PORT=.*|VITE_REVERB_PORT=8080|' .env
  sed -i 's|^VITE_REVERB_SCHEME=.*|VITE_REVERB_SCHEME=http|' .env
}

install_app_dependencies() {
  cd "${SERVER_DIR}"
  log "composer install"
  composer install --no-interaction --prefer-dist --no-progress

  if ! grep -q '^APP_KEY=base64:' .env; then
    log "generating application key"
    php artisan key:generate --force
  fi

  log "npm install"
  npm install --no-audit --no-fund

  log "building frontend assets"
  npm run build
}

migrate_and_seed() {
  cd "${SERVER_DIR}"
  log "running migrations"
  php artisan migrate --force

  # Idempotent: creates the first Super Admin only if none exists, then seeds
  # the baseline site registry. Re-runs are no-ops.
  log "seeding baseline data (Super Admin + demo site registry)"
  php artisan ir4:install \
    --name="Super Admin" \
    --email="admin@ir4.test" \
    --password="password123" || true
}

install_system_packages
start_services
ensure_databases
ensure_env
install_app_dependencies
migrate_and_seed

log "done"
