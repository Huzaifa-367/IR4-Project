# IR4 Server (Laravel)

Laravel + Inertia operator UI, device ingest API, and public QR pages. Run all `composer`, `php artisan`, and `npm` commands from this directory.

## Local development

```bash
composer setup
php artisan serve --host=0.0.0.0 --port=8000
# optional: npm run dev  /  php artisan reverb:start
```

Copy `.env.example` → `.env` if setup did not already.

## Production deploy (Hostinger)

**Disable Hostinger Git auto-deploy.** Deploy only via GitHub Actions SSH (`.github/workflows/deploy.yml` at the repo root).

### One-time server setup

1. **hPanel → Git** — turn **off** Auto deployment (disconnect or disable webhook).
2. **SSH** into the server and clone (or use an existing folder). `DEPLOY_PATH` is the **repo root** (the folder that contains `.git`); this `Server/` directory is where `artisan` lives.

Production host: **ir4.ispc-ai.com**

| Role | Path |
|------|------|
| Domain folder | `/home/u373214048/domains/ir4.ispc-ai.com` |
| Repo root (`DEPLOY_PATH`, folder with `.git`) | `/home/u373214048/domains/ir4.ispc-ai.com/public_html` |
| Laravel app (`artisan`) | `/home/u373214048/domains/ir4.ispc-ai.com/public_html/Server` |
| Document root | `/home/u373214048/domains/ir4.ispc-ai.com/public_html/Server/public` |

```bash
cd /home/u373214048/domains/ir4.ispc-ai.com
# If public_html is empty or you are re-cloning as the monorepo:
#   rm -rf public_html && git clone https://github.com/Huzaifa-367/IR4-Project.git public_html
cd public_html/Server
cp .env.example .env   # then edit .env for production
php artisan key:generate
```

Point the domain document root at `public_html/Server/public` (hPanel → Domains → ir4.ispc-ai.com → Document root).

3. **GitHub → Settings → Secrets → Actions** — add:

| Secret | Value |
|--------|--------|
| `SSH_HOST` | Server IP (hPanel → SSH) |
| `SSH_PORT` | `65002` |
| `SSH_USERNAME` | `u373214048` |
| `SSH_PASSWORD` | hPanel SSH password |
| `DEPLOY_PATH` | `/home/u373214048/domains/ir4.ispc-ai.com/public_html` |
| `GH_DEPLOY_TOKEN` | GitHub PAT, **Contents: Read** — [create token](https://github.com/settings/tokens) |

### Every push to `main`

GitHub Actions SSHs in → `git pull` (uploads in `storage/app/public` are kept) → symlink `public/storage` → `composer install` → `npm run build` → `migrate` → config/route/view caches.

Check the **Actions** tab for logs.

### Manual storage link (SSH)

```bash
cd /home/u373214048/domains/ir4.ispc-ai.com/public_html/Server
ln -sfn "$(pwd)/storage/app/public" public/storage
```

