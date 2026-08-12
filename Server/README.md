# IR4 Server (Laravel)

Laravel + Inertia operator UI, device ingest API, and public QR pages.

Run `composer`, `php artisan`, and `npm` from this directory.

Full Hostinger setup (secrets, cron, `.htaccess`, private `/files/private` downloads): this file.  
On-prem SCC: [`SCC-SETUP.md`](../SCC-SETUP.md).

| Topic | Section |
|-------|---------|
| Local development | [§ Local development](#local-development) |
| Hostinger production | [§ Production (Hostinger)](#production-hostinger) |
| Private file downloads | [§ Private file downloads](#private-file-downloads) |
| SCC fresh setup | [`SCC-SETUP.md`](../SCC-SETUP.md) |
| Full ops runbook | [`Docs/Doc 20`](../Docs/Doc%2020%20deployment%20runbook.md) |

---

## Local development

```bash
composer setup
php artisan serve --host=0.0.0.0 --port=8000
# optional: npm run dev  ·  php artisan reverb:start
```

Copy `.env.example` → `.env` if setup did not already.

---

## Production (Hostinger)

Deploy **only** via GitHub Actions (`.github/workflows/server-deploy.yml`).  
**Disable** hPanel Git auto-deploy (disconnect or disable the webhook).

| | |
|--|--|
| Host | `https://ir4.ispc-ai.com` |
| Domain folder | `/home/u373214048/domains/ir4.ispc-ai.com` |
| `DEPLOY_PATH` (Laravel root / `artisan`) | `…/public_html` |
| Document root (hPanel) | `…/public_html` (root `.htaccess` rewrites into `public/`) |
| Front controller | `…/public_html/public` |

CI uploads `Server/*` into `DEPLOY_PATH` (flat layout). It never overwrites the live `.env`.

### One-time setup

1. **hPanel → Git** — turn auto-deploy off.
2. **SSH — create `.env`:**

```bash
cd /home/u373214048/domains/ir4.ispc-ai.com/public_html
cp .env.example .env   # then edit for production
php artisan key:generate
```

```env
APP_URL=https://ir4.ispc-ai.com
SESSION_DOMAIN=ir4.ispc-ai.com
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
```

Confirm `.htaccess` sits next to `artisan`. Do **not** point the domain at `public/` unless you remove that root rewrite.

3. **Cron (required)** — hPanel → Cron Jobs → every minute:

```bash
cd /home/u373214048/domains/ir4.ispc-ai.com/public_html && php artisan schedule:run >> /dev/null 2>&1
```

Without this, scheduled jobs (e.g. `ir4:prune-expired-cache`) never run.

4. **GitHub → Settings → Secrets → Actions**

| Secret | Value |
|--------|--------|
| `SSH_HOST` | Server IP (hPanel → SSH) |
| `SSH_PORT` | `65002` |
| `SSH_USERNAME` | `u373214048` |
| `SSH_PASSWORD` | hPanel SSH password |
| `DEPLOY_PATH` | `/home/u373214048/domains/ir4.ispc-ai.com/public_html` |
| `GH_DEPLOY_TOKEN` | GitHub PAT, **Contents: Read** |

### Deploy cycle

Push to `main` (Server changes) → Actions builds → SCP → storage symlink → migrate → optimize. Check the **Actions** tab for logs.

```bash
# Manual storage link if needed
cd /home/u373214048/domains/ir4.ispc-ai.com/public_html
ln -sfn "$(pwd)/storage/app/public" public/storage
```

### `.htaccess`

| Repo | On server |
|------|-----------|
| `Server/.htaccess` | `public_html/.htaccess` — rewrite into `public/`, deny sensitive trees |
| `Server/public/.htaccess` | `public_html/public/.htaccess` — Laravel front controller |

Until the next deploy lands the root file:

```bash
cd /home/u373214048/domains/ir4.ispc-ai.com/public_html
cat > .htaccess <<'EOF'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTPS} !=on
    RewriteCond %{HTTP:X-Forwarded-Proto} =https
    RewriteRule ^ - [E=HTTPS:on]
    RewriteRule ^public/ - [L]
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
<IfModule mod_authz_core.c>
    RedirectMatch 403 ^/(?:\.env|composer\.(?:json|lock)|artisan|vendor|storage|bootstrap|database|config|routes|app|tests|scripts)(?:/|$)
</IfModule>
EOF
```

---

## Private file downloads

Signed private-disk URLs use **`/files/private/{path}`** (not `/storage/...`).

Root `.htaccess` must keep denying `/storage`. Downloads go through Laravel:

`https://ir4.ispc-ai.com/files/private/reports/19/report.pdf?expires=…&signature=…`

Old `/storage/private/...` links still 403 by design — open a fresh link from the app after deploy.
