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

## Docs

Start with `Docs/Doc 01 base structure.md`. Conventions for agents: `.cursor/rules/ir4-conventions.mdc`.

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
