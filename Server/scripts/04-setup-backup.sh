#!/usr/bin/env bash
# IR4 — Spatie backup module commissioning (DOC-19 / DOC-20)
#
# Proven on SCC2 (PowerEdge R360 / Lerd). Run from the deployed Laravel root
# (flattened Server/ — often not a git checkout):
#
#   cd /data2/laravel/IR4-Project
#   BACKUP_ARCHIVE_PASSWORD_OVERRIDE='<secret>' bash scripts/04-setup-backup.sh
#
# Prerequisites: app tree present, .env present, Lerd installed.

set -euo pipefail

APP_ROOT="$(pwd)"
BACKUP_ROOT="${BACKUP_DISK_ROOT_OVERRIDE:-}"
ARCHIVE_PASSWORD="${BACKUP_ARCHIVE_PASSWORD_OVERRIDE:-}"

if [[ ! -f "$APP_ROOT/artisan" ]]; then
  echo "ERROR: run this from the Laravel app root (directory that contains artisan)." >&2
  exit 1
fi

if [[ ! -f "$APP_ROOT/.env" ]]; then
  echo "ERROR: $APP_ROOT/.env is missing." >&2
  exit 1
fi

echo "==> IR4 backup setup on $(hostname)"
echo "    App root: $APP_ROOT"

#########################################
# 1. .env keys
#########################################

ensure_env() {
  local key="$1"
  local value="$2"
  if grep -qE "^${key}=" "$APP_ROOT/.env"; then
    if [[ -n "$value" ]]; then
      sed -i.bak "s|^${key}=.*|${key}=${value}|" "$APP_ROOT/.env"
      rm -f "$APP_ROOT/.env.bak"
      echo "    updated $key"
    else
      echo "    kept existing $key"
    fi
  else
    printf '\n%s=%s\n' "$key" "$value" >> "$APP_ROOT/.env"
    echo "    added $key"
  fi
}

echo "==> Ensuring backup-related .env keys"
ensure_env "APP_TIMEZONE" "Asia/Riyadh"
ensure_env "BACKUP_DISK_ROOT" "${BACKUP_ROOT:-/data/ir4-backups}"
ensure_env "MYSQL_DUMP_BINARY_PATH" "/usr/bin"
ensure_env "MYSQL_DUMP_TIMEOUT" "3600"
ensure_env "DISK_SPACE_WARN_PCT" "15"

if [[ -n "$ARCHIVE_PASSWORD" ]]; then
  ensure_env "BACKUP_ARCHIVE_PASSWORD" "$ARCHIVE_PASSWORD"
elif ! grep -qE '^BACKUP_ARCHIVE_PASSWORD=.+' "$APP_ROOT/.env"; then
  echo "ERROR: BACKUP_ARCHIVE_PASSWORD is empty." >&2
  echo "Set it in .env or pass BACKUP_ARCHIVE_PASSWORD_OVERRIDE='...' $0" >&2
  exit 1
fi

BACKUP_DISK_ROOT="$(grep -E '^BACKUP_DISK_ROOT=' "$APP_ROOT/.env" | head -1 | cut -d= -f2-)"
BACKUP_DISK_ROOT="${BACKUP_DISK_ROOT:-/data/ir4-backups}"
APP_NAME="$(grep -E '^APP_NAME=' "$APP_ROOT/.env" | head -1 | cut -d= -f2-)"
APP_NAME="${APP_NAME:-IR4}"

#########################################
# 2. Install Spatie if artisan has no backup:* 
#########################################

echo "==> Checking spatie/laravel-backup is installed"
if ! grep -q 'spatie/laravel-backup' "$APP_ROOT/composer.json"; then
  echo "ERROR: spatie/laravel-backup missing from composer.json — deploy a build that includes DOC-19 backups." >&2
  exit 1
fi

if ! php artisan list backup 2>/dev/null | grep -q 'backup:run'; then
  echo "    backup:* commands missing — running composer install --no-dev"
  composer install --no-dev --optimize-autoloader --no-interaction
  php artisan package:discover --ansi
  rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php
  php artisan optimize:clear
fi

if ! php artisan list backup 2>/dev/null | grep -q 'backup:run'; then
  echo "ERROR: backup:* still missing after composer install." >&2
  exit 1
fi
echo "    backup:run available"

#########################################
# 3. Host backup volume
#########################################

echo "==> Preparing host backup volume: $BACKUP_DISK_ROOT"
sudo mkdir -p "$BACKUP_DISK_ROOT"
sudo chown -R "$(whoami):$(whoami)" "$BACKUP_DISK_ROOT"
chmod 750 "$BACKUP_DISK_ROOT" || true

#########################################
# 4. Lerd: /data mount + MySQL dump tooling
#########################################

echo "==> Checking Lerd global mounts for /data"
LERD_CFG="${HOME}/.config/lerd/config.yaml"
if [[ ! -f "$LERD_CFG" ]]; then
  echo "ERROR: $LERD_CFG not found. Install/configure Lerd first." >&2
  exit 1
fi

if ! grep -qE '^\s*-\s*/data\s*$' "$LERD_CFG" && ! grep -qE '^\s*-\s*"/data"\s*$' "$LERD_CFG"; then
  echo "ERROR: add '/data' under mounts: in $LERD_CFG then re-run." >&2
  echo "Example:" >&2
  echo "  mounts:" >&2
  echo "    - /data" >&2
  exit 1
fi
grep -A5 '^mounts:' "$LERD_CFG" || true

echo "==> Ensuring mariadb-connector-c (MySQL 8 dump auth plugin)"
lerd php:pkg add mariadb-connector-c 2>/dev/null \
  || echo "    (pkg add skipped — confirm mysqldump works below)"

php_has_data() {
  php -r 'exit(is_dir("/data") ? 0 : 1);'
}

inodes_match() {
  local host_inode php_inode
  host_inode="$(stat -c '%i' "$BACKUP_DISK_ROOT" 2>/dev/null || echo '')"
  php_inode="$(php -r 'echo @fileinode("'"$BACKUP_DISK_ROOT"'") ?: "";' 2>/dev/null || true)"
  [[ -n "$host_inode" && -n "$php_inode" && "$host_inode" == "$php_inode" ]]
}

rescue_php_only_backups() {
  # If PHP has zips the host cannot see, copy them into the shared app tree first.
  local rescue_dir="$APP_ROOT/storage/app/backup-rescue"
  mkdir -p "$rescue_dir"
  php -r '
$srcRoot = "'"$BACKUP_DISK_ROOT"'/'"$APP_NAME"'";
$dstRoot = "'"$rescue_dir"'";
if (!is_dir($srcRoot)) { exit(0); }
foreach (glob($srcRoot."/*.zip") ?: [] as $src) {
  $dst = $dstRoot."/".basename($src);
  if (!is_file($dst)) {
    if (!@copy($src, $dst)) {
      fwrite(STDERR, "WARN: could not rescue ".basename($src)."\n");
      continue;
    }
    echo "rescued ".basename($src)." -> ".$dst.PHP_EOL;
  }
}
' || true
}

apply_lerd_data_mount() {
  echo "==> Applying Lerd /data bind-mount (restart, then unlink+link if needed)"
  lerd restart || true

  if php_has_data && inodes_match; then
    echo "    /data already visible and inodes match"
    return 0
  fi

  echo "    /data missing or inode mismatch — re-linking site so mounts apply"
  rescue_php_only_backups
  # SCC2: config listed /data but restart alone was not enough; unlink+link fixed it.
  # Answer "n" to optional "Run lerd setup?" — we already handle composer/schedule here.
  lerd unlink || true
  printf 'n\n' | lerd link || lerd link

  sudo mkdir -p "$BACKUP_DISK_ROOT"
  sudo chown -R "$(whoami):$(whoami)" "$BACKUP_DISK_ROOT"
}

apply_lerd_data_mount

#########################################
# 5. Prove PHP and host share the same disk
#########################################

echo "==> Verifying PHP and host share $BACKUP_DISK_ROOT"
if ! php_has_data; then
  echo "ERROR: /data still missing inside Lerd PHP after unlink+link." >&2
  echo "Confirm mounts: in $LERD_CFG includes '- /data', then: lerd unlink && lerd link" >&2
  exit 1
fi

php -r '
$root = "'"$BACKUP_DISK_ROOT"'";
if (!is_dir($root) && !@mkdir($root, 0750, true)) {
  fwrite(STDERR, "ERROR: cannot create $root inside PHP runtime\n");
  exit(1);
}
$probe = $root."/.ir4-backup-mount-probe";
if (file_put_contents($probe, "ok\n") === false) {
  fwrite(STDERR, "ERROR: cannot write probe under $root\n");
  exit(1);
}
echo "php inode: ".fileinode($root).PHP_EOL;
'

HOST_INODE="$(stat -c '%i' "$BACKUP_DISK_ROOT")"
PHP_INODE="$(php -r 'echo fileinode("'"$BACKUP_DISK_ROOT"'");')"
echo "    host inode: $HOST_INODE"
echo "    php  inode: $PHP_INODE"

if [[ "$HOST_INODE" != "$PHP_INODE" ]]; then
  echo "ERROR: host and PHP see different filesystems for $BACKUP_DISK_ROOT" >&2
  echo "Backup would succeed in PHP but ls on the host would show nothing." >&2
  echo "Fix: ensure mounts includes /data, then: lerd unlink && lerd link" >&2
  exit 1
fi

if [[ ! -f "$BACKUP_DISK_ROOT/.ir4-backup-mount-probe" ]]; then
  echo "ERROR: probe file written by PHP is not visible on the host." >&2
  exit 1
fi
rm -f "$BACKUP_DISK_ROOT/.ir4-backup-mount-probe"

# Restore any rescued zips onto the real volume
if [[ -d "$APP_ROOT/storage/app/backup-rescue" ]]; then
  sudo mkdir -p "$BACKUP_DISK_ROOT/$APP_NAME"
  sudo chown -R "$(whoami):$(whoami)" "$BACKUP_DISK_ROOT"
  shopt -s nullglob
  for z in "$APP_ROOT"/storage/app/backup-rescue/*.zip; do
    dest="$BACKUP_DISK_ROOT/$APP_NAME/$(basename "$z")"
    if [[ ! -f "$dest" ]]; then
      mv "$z" "$dest"
      echo "    restored rescued archive -> $dest"
    fi
  done
  shopt -u nullglob
fi

#########################################
# 6. mysqldump available to Lerd PHP
#########################################

echo "==> Checking mysqldump inside Lerd PHP"
if ! php -r 'passthru("/usr/bin/mysqldump --version", $status); exit($status);'; then
  echo "ERROR: mysqldump missing in Lerd PHP runtime." >&2
  exit 1
fi

#########################################
# 7. Clear caches + first encrypted backup
#########################################

echo "==> Clearing caches"
rm -f bootstrap/cache/config.php bootstrap/cache/packages.php bootstrap/cache/services.php \
  bootstrap/cache/routes-v7.php bootstrap/cache/events.php
php artisan optimize:clear
php artisan config:clear

echo "==> Running first backup:run (must appear on the HOST under $BACKUP_DISK_ROOT)"
php artisan backup:run
php artisan backup:list

sudo mkdir -p "$BACKUP_DISK_ROOT/$APP_NAME"
echo "==> Host archive listing (must show ir4-*.zip):"
ls -lah "$BACKUP_DISK_ROOT/$APP_NAME"

if ! ls "$BACKUP_DISK_ROOT/$APP_NAME"/ir4-*.zip >/dev/null 2>&1; then
  echo "ERROR: backup reported success but no zip on the host." >&2
  echo "Re-check inode match; do not trust PHP-only /data." >&2
  exit 1
fi

#########################################
# 8. Automatic schedule worker
#########################################

echo "==> Starting Lerd schedule worker (daily clean/run/monitor)"
lerd schedule:start
if ! lerd worker list 2>/dev/null | grep -qi 'schedule'; then
  echo "ERROR: schedule worker not running." >&2
  exit 1
fi

php artisan schedule:list | grep -E 'backup:(clean|run|monitor)' || true

#########################################
# 9. Survive system reboot
#########################################

INSTALLER="$APP_ROOT/scripts/02-install-systemd-units.sh"
if [ -f "$INSTALLER" ]; then
  echo "==> Installing system units (ir4.target → /etc/systemd/system/)"
  APP_ROOT="$APP_ROOT" bash "$INSTALLER"
else
  echo "WARN: missing $INSTALLER — run after deploy:" >&2
  echo "  bash $APP_ROOT/scripts/02-install-systemd-units.sh" >&2
fi

echo
echo "=================================="
echo "Backup module ready on $(hostname)"
echo "  Disk:      $BACKUP_DISK_ROOT/$APP_NAME/"
echo "  Schedule:  clean 01:00 → run 01:30 → monitor 03:00 (APP_TIMEZONE)"
echo "  Alerts:    system alerts via AlertService (no mail)"
echo "  Boot:      systemctl status ir4.target"
echo "  Next:      SCC-SETUP.md (monorepo) · DOC-20 §8 restore drill"
echo "=================================="
