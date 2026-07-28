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

**Disable Hostinger Git auto-deploy.** Deploy only via GitHub Actions (`.github/workflows/server-deploy.yml`).

### One-time server setup

1. **hPanel → Git** — turn **off** Auto deployment (disconnect or disable webhook).
2. **SSH** — `DEPLOY_PATH` is the Laravel root on the host (`artisan` lives here).

Production host: **ir4.ispc-ai.com**

| Role | Path |
|------|------|
| Domain folder | `/home/u373214048/domains/ir4.ispc-ai.com` |
| Deploy target (`DEPLOY_PATH`, Laravel root with `artisan`) | `/home/u373214048/domains/ir4.ispc-ai.com/public_html` |
| Web document root (hPanel) | `/home/u373214048/domains/ir4.ispc-ai.com/public_html` |
| Laravel front controller | `/home/u373214048/domains/ir4.ispc-ai.com/public_html/public` |

`server-deploy.yml` uploads `Server/*` into `DEPLOY_PATH` (flat Laravel layout on the host). Leave hPanel document root on `public_html`. Root `.htaccess` (shipped as `Server/.htaccess`) rewrites all traffic into `public/`.

```bash
cd /home/u373214048/domains/ir4.ispc-ai.com/public_html
cp .env.example .env   # then edit .env for production
php artisan key:generate
```

Confirm `.htaccess` exists next to `artisan` (rewrites → `public/`). Do **not** point the domain at `public/` unless you remove that root rewrite.

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

| Repo path | On server |
|-----------|-----------|
| `Server/.htaccess` | `public_html/.htaccess` — rewrite all requests into `public/` |
| `Server/public/.htaccess` | `public_html/public/.htaccess` — Laravel front controller |

Until the next deploy lands the root file, you can create it manually on SSH:

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

