#!/usr/bin/env bash

set -euo pipefail

APP_ROOT="${APP_ROOT:-/data2/laravel/IR4-Project}"
PHP_BINARY="${PHP_BINARY:-/usr/bin/php8.4}"
MYSQL_DUMP_BINARY_PATH="${MYSQL_DUMP_BINARY_PATH:-/usr/bin}"
SERVICE_USER="${SERVICE_USER:-$USER}"
SYSTEMD_DIRECTORY="/etc/systemd/system"

if [ ! -x "$PHP_BINARY" ]; then
  echo "ERROR: PHP 8.4 CLI not found at $PHP_BINARY." >&2
  exit 1
fi

if [ ! -x "$MYSQL_DUMP_BINARY_PATH/mysqldump" ]; then
  echo "ERROR: mysqldump not found at $MYSQL_DUMP_BINARY_PATH/mysqldump." >&2
  exit 1
fi

MYSQL_DUMP_VERSION="$("$MYSQL_DUMP_BINARY_PATH/mysqldump" --version)"
case "${MYSQL_DUMP_VERSION,,}" in
  *mariadb*)
    echo "ERROR: MariaDB mysqldump is incompatible with the MySQL caching_sha2_password account." >&2
    echo "Install the official MySQL 8 client and set MYSQL_DUMP_BINARY_PATH." >&2
    exit 1
    ;;
esac

if [ ! -d "$APP_ROOT" ]; then
  echo "ERROR: Application root does not exist: $APP_ROOT" >&2
  exit 1
fi

sudo install -m 0644 "$APP_ROOT/deploy/systemd/ir4-scheduler@.service" "$SYSTEMD_DIRECTORY/"
sudo install -m 0644 "$APP_ROOT/deploy/systemd/ir4-scheduler@.timer" "$SYSTEMD_DIRECTORY/"
sudo systemctl daemon-reload
sudo systemctl enable --now "ir4-scheduler@${SERVICE_USER}.timer"
sudo systemctl status "ir4-scheduler@${SERVICE_USER}.timer" --no-pager
