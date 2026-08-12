# IR4 encoded server release

Built by `php Server/scripts/build-encoded-release.php`.

## What this is

- `server/app/**/*.php` — gzip + base64 + `eval` (weak at-rest obfuscation).
- **Frontend:** only Vite `public/build/` (compiled JS/CSS). No `resources/js` or `resources/css`.
- `resources/views` — Blade only (required by Laravel/Inertia).
- Not strong encryption; compiled JS can still be beautified in a browser.

## Deploy on SCC

```bash
cd Release/server
composer install --no-dev --optimize-autoloader --no-interaction
# link shared .env — never ship secrets in this tree
php artisan optimize:clear
# no npm on the SCC — assets are already in public/build
```

Verify integrity:

```bash
cd Release && sha256sum -c MANIFEST.sha256
```

Rebuild (from repo root; runs `npm run build` if `public/build` is missing):

```bash
cd Server && npm ci && npm run build && cd ..
php Server/scripts/build-encoded-release.php
```