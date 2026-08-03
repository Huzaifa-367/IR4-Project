# IR4 Platform

On-premise safety command-centre. Monorepo layout:

| Path | Contents |
|---|---|
| `Server/` | Laravel + Inertia operator UI, device API, public QR pages |
| `Mobile/` | Android Flutter app (equipment QR scan / checkout / return) |
| `Docs/` | Authoritative design docs (DOC-01 … DOC-22) |

## Server (Laravel)

```bash
cd Server
composer setup
php artisan serve --host=0.0.0.0 --port=8000
# optional: npm run dev  /  php artisan reverb:start
```

Copy `Server/.env.example` → `Server/.env` if setup did not already.

## Mobile (Flutter)

```bash
cd Mobile
flutter pub get
flutter run
# or: flutter build apk --debug
```

On the login screen, base URL is the LAN address of the Server (e.g. `http://10.0.2.2:8000` for the Android emulator, or `http://<mac-lan-ip>:8000` for a physical device).

## Live wall cameras (MediaMTX) — permanent multi-feed setup

**Operator UX:** only set RTSP (+ `reference`) on Hardware → Cameras. IR4 pushes each URL into MediaMTX; `/live` iframes `http://<scc>:8888/{reference}`.

Config and units live under **`Server/scripts/`** (on SCC flattened root: `scripts/`).

### Architecture

```text
DB cameras.stream_url  --save/sync-->  MediaMTX paths  --HLS-->  /live tiles
```

1. **MediaMTX** always running (Docker + systemd).
2. **`MEDIAMTX_API_URL`** set so create/update camera calls MediaMTX API.
3. **`ir4:sync-camera-streams`** on boot (and after adding cameras offline).
4. **`CAMERA_BROWSER_STREAM_URL_TEMPLATE=http://<SCC-LAN-IP>:8888/{reference}`** for browsers on other PCs.

### One-time SCC install

```bash
cd /data2/laravel/IR4-Project   # flattened Server/ (has artisan + scripts/)

sudo cp scripts/ir4-mediamtx.service /etc/systemd/system/
sudo cp scripts/ir4-sync-camera-streams.service /etc/systemd/system/
# Edit WorkingDirectory / User / volume path in those units if your path/user differ

sudo systemctl daemon-reload
sudo systemctl enable --now ir4-mediamtx.service
sudo systemctl enable --now ir4-sync-camera-streams.service
```

`.env` (critical under Lerd / Docker PHP):

```env
CAMERA_BROWSER_STREAM_URL_TEMPLATE=http://192.168.x.x:8888/{reference}
# Prefer explicit SCC LAN IP under Lerd/Podman (pasta gateway ≠ host):
MEDIAMTX_API_URL=http://192.168.x.x:9997
# Or: MEDIAMTX_API_URL=gateway with MEDIAMTX_HOST_IP=192.168.x.x
MEDIAMTX_HOST_IP=
MEDIAMTX_API_USER=
MEDIAMTX_API_PASS=
```

Start MediaMTX on the host network (opens anonymous API for sync):

```bash
sudo bash scripts/ensure-mediamtx.sh
# Host curl must list paths, not "authentication error"
lerd artisan config:clear
lerd artisan ir4:sync-camera-streams --probe
lerd artisan ir4:sync-camera-streams
```

RTSP passwords with `@` are encoded automatically when syncing (`@` → `%40`).

### Day-to-day

| Action | Result |
|--------|--------|
| Add/update camera RTSP in UI | Path created/updated in MediaMTX automatically |
| Open `/live` | One tile per camera via `{reference}` |
| Reboot SCC | MediaMTX restarts; sync unit re-pushes all DB cameras |

Files: `scripts/mediamtx.yml`, `scripts/ir4-mediamtx.service`, `scripts/ir4-sync-camera-streams.service`.

## On-prem SCC backups (DOC-19 / DOC-20)

Dell R360 / Lerd hosts (e.g. SCC2 at `/data2/laravel/IR4-Project`). Deploy is a **flattened** `Server/` tree (often not a git repo). Archives land on a separate volume: `/data/ir4-backups/{APP_NAME}/ir4-*.zip`.

### One-shot setup

```bash
cd /data2/laravel/IR4-Project          # directory with artisan + .env
# copy Scripts/setup-backup.sh onto the host if needed, e.g. ~/Desktop/Scripts/
BACKUP_ARCHIVE_PASSWORD_OVERRIDE='<strong-site-secret>' \
  bash ~/Desktop/Scripts/setup-backup.sh
```

The script:

1. Writes `.env` keys: `APP_TIMEZONE`, `BACKUP_DISK_ROOT=/data/ir4-backups`, dump paths, password  
2. Runs `composer install --no-dev` if `backup:*` artisan commands are missing (Spatie not in vendor yet)  
3. Creates/chowns `/data/ir4-backups`  
4. Requires `/data` under `mounts:` in `~/.config/lerd/config.yaml`  
5. Applies the bind-mount (`lerd restart`; if PHP still lacks `/data` or **inodes differ**, `lerd unlink` + `lerd link`)  
6. Proves host and Lerd PHP share the **same inode** for `BACKUP_DISK_ROOT`  
7. Runs `backup:run` and requires the zip to be visible with host `ls`  
8. Starts `lerd schedule:start` (clean 01:00 → run 01:30 → monitor 03:00 in `APP_TIMEZONE`)

### Manual checklist (if not using the script)

```bash
# 1) Package (flattened deploys often need this once)
grep laravel-backup composer.json
composer install --no-dev --optimize-autoloader
php artisan package:discover
rm -f bootstrap/cache/{packages,services,config}.php
php artisan optimize:clear
php artisan list backup

# 2) Volume + .env
sudo mkdir -p /data/ir4-backups && sudo chown -R "$(whoami):$(whoami)" /data/ir4-backups
# APP_TIMEZONE=Asia/Riyadh
# BACKUP_DISK_ROOT=/data/ir4-backups
# BACKUP_ARCHIVE_PASSWORD=...
# MYSQL_DUMP_BINARY_PATH=/usr/bin

# 3) Lerd must mount host /data into PHP (listing it in config is not enough)
grep -A5 '^mounts:' ~/.config/lerd/config.yaml   # must include: - /data
lerd restart
# If PHP still has no /data, or host/PHP inodes differ:
lerd unlink && lerd link     # answer n to optional full "lerd setup" if prompted
sudo mkdir -p /data/ir4-backups && sudo chown -R "$(whoami):$(whoami)" /data/ir4-backups

# 4) Same disk proof (numbers MUST match)
stat -c '%i %n' /data/ir4-backups
php -r 'echo fileinode("/data/ir4-backups"), PHP_EOL;'

# 5) First backup — zip must show on the HOST
php artisan backup:run
sudo ls -lah /data/ir4-backups/IR4
lerd schedule:start && lerd worker list
```

**Failure mode to avoid:** `backup:run` succeeds but `ls /data/ir4-backups/IR4` fails — PHP wrote into a container-only `/data` (different inode). Fix mounts with `unlink`/`link` before trusting backups. Rescue a PHP-only zip into the app tree first if needed (`storage/app/…`), then remount.

Failures raise in-app `system` alerts (no mail). Full ops/restore drill: `Docs/Doc 20 deployment runbook.md` §8.

## Production deploy (Hostinger)

**Disable Hostinger Git auto-deploy.** Deploy only via GitHub Actions (`.github/workflows/server-deploy.yml`).

### One-time server setup

1. **hPanel → Git** — turn **off** Auto deployment (disconnect or disable webhook).
2. **SSH** — `DEPLOY_PATH` is the Laravel root on the host (`artisan` lives here). CI uploads `Server/*` into this folder (flat layout).

Production host: **ir4.ispc-ai.com**

| Role | Path |
|------|------|
| Domain folder | `/home/u373214048/domains/ir4.ispc-ai.com` |
| Deploy target (`DEPLOY_PATH`) | `/home/u373214048/domains/ir4.ispc-ai.com/public_html` |
| Web document root (hPanel) | `/home/u373214048/domains/ir4.ispc-ai.com/public_html` |
| Laravel front controller | `/home/u373214048/domains/ir4.ispc-ai.com/public_html/public` |

Leave document root on `public_html`. Root `.htaccess` (from `Server/.htaccess`) rewrites all traffic into `public/`.

```bash
cd /home/u373214048/domains/ir4.ispc-ai.com/public_html
cp .env.example .env   # then edit .env for production
php artisan key:generate
# ensure root .htaccess is present (next to artisan)
```

3. **GitHub → Settings → Secrets → Actions** — add:

| Secret | Value |
|--------|--------|
| `SSH_HOST` | Server IP (hPanel → SSH) |
| `SSH_PORT` | `65002` |
| `SSH_USERNAME` | `u373214048` |
| `SSH_PASSWORD` | hPanel SSH password |
| `DEPLOY_PATH` | `/home/u373214048/domains/ir4.ispc-ai.com/public_html` |
| `GH_DEPLOY_TOKEN` | GitHub PAT, **Contents: Read** — [create token](https://github.com/settings/tokens) |

### Every push to `main` (Server changes)

GitHub Actions builds in CI → SCP `Server/*` into `DEPLOY_PATH` → storage symlink → migrate → optimize.

Check the **Actions** tab for logs.

### Manual storage link (SSH)

```bash
cd /home/u373214048/domains/ir4.ispc-ai.com/public_html
ln -sfn "$(pwd)/storage/app/public" public/storage
```

### Hostinger `.htaccess`

- `Server/.htaccess` → deployed as `public_html/.htaccess` (rewrite → `public/`)
- `Server/public/.htaccess` → Laravel front controller (unchanged)

See `Server/README.md` for Laravel-specific setup and deploy detail.
