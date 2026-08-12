# DOC-20 — Deployment & Operations Runbook

> **Depends on:** DOC-01 (stack, queues, scheduler, storage), DOC-02/08 (device + display auth on the LAN), DOC-05 (printer as a device), DOC-13 (public QR page + ZT411 printing), DOC-17 (append-only audit → DB grants), DOC-18 (config-vs-`.env`), DOC-19 (backups, restore, wipe). **Feeds:** the field-engineering team standing the system up; the acceptance sign-off.
>
> **Scope:** the **on-prem deployment and operations runbook** — server preparation (Dell R360), the app/queue/Reverb/scheduler process model, reverse-proxy + firewall LAN enforcement (public QR page and device ingest), ZT411 printer setup, database-permission hardening (append-only audit, wipe privileges), backup/restore/wipe drills, monitoring, and the **Phase-3 commissioning acceptance checklist**. **Out of scope:** application behavior (owned by the module DOCs) — this doc gets it running and keeps it running.
>
> **Fresh box (ordered field steps):** [`SCC-SETUP.md`](../SCC-SETUP.md) (repo root) — OS → Lerd → `Server/scripts/01-setup.sh` → `.env` → migrate → **`ir4.target`** → MediaMTX → backups → reboot proof. This DOC-20 file holds the deeper rationale and acceptance checklist.

---

## 1. Environment & principles

- **Single on-prem server** (Dell R360 or equivalent): the app, **MySQL 8**, Redis, Reverb, queue workers, and scheduler all run on one box on the **site LAN**. No cloud dependency, **no internet egress** (DOC-01) — the platform is fully self-contained. PostgreSQL is not a supported production target.
- **One installation = one site** (standalone, DOC-01 §1). A second location is a separate independent install.
- **Everything the operator/display/devices reach is over the LAN.** External access is not a goal; if remote admin is ever needed it's via the client's own VPN, out of scope here.
- OS baseline: Ubuntu 24.04 LTS. Runtime: **PHP 8.4+**, Node **22** (build-time only), MySQL 8, Redis 7, Nginx. Concrete templates live under `deploy/`.

---

## 2. Server preparation

1. **OS & packages:** Ubuntu 24.04, security updates, then Lerd PHP 8.4 (required extensions: pdo_mysql, redis, gd/imagick for snapshots, zip, bcmath), Nginx, Lerd MySQL 8, `7zip`, Redis, `git`, Composer, Node 22 (build only). Add `mariadb-connector-c` to Lerd PHP with `lerd php:pkg add mariadb-connector-c --php 8.4` when the bundled `mysqldump` needs MySQL 8 `caching_sha2_password`.
2. **Disk layout:** OS volume; a **data volume** for MySQL + the private storage (snapshots/documents); a **separate backup volume** (DOC-19 backups must not share the live-data disk). Provision per the DOC-19 volume math (hundreds of GB, snapshot-dominated). Enable **encryption at rest** (LUKS) on the data + backup volumes.
3. **Time:** NTP synced (ordering/clock-skew logic depends on a sane server clock; devices are reconciled to it — DOC-08). Server/OS timezone stays UTC. Bootstrap default is `APP_TIMEZONE` in `.env` (default `Asia/Riyadh`); after seed the runtime setting `general.timezone` (DOC-18) overrides it for display/reports/scheduler.
4. **Users:** a non-root deploy user; the web/worker processes run unprivileged.

---

## 3. Application deploy

The Laravel/Inertia app lives under **`Server/`** in the monorepo (Flutter under `Mobile/`, docs under `Docs/`).

1. Clone the repo, then from `Server/`: `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build` (Vite build; the built assets ship — no Node at runtime).
2. `Server/.env` from the production template: `APP_KEY`, `APP_TIMEZONE`, the fixed MySQL connection credentials, the maintenance-only `ir4_wipe` credentials, Redis, Reverb keys, storage paths, `BACKUP_DISK_ROOT=/data/ir4-backups`, `BACKUP_ARCHIVE_PASSWORD`, `MYSQL_DUMP_BINARY_PATH`, and `EQUIPMENT_PRINTER_HOST` / `EQUIPMENT_PRINTER_PORT`. **Secrets live here, never in the DB.** After seed, `general.timezone` overrides `APP_TIMEZONE` at runtime (DOC-18).
3. From `Server/`: `php artisan migrate --force`; `php artisan db:seed` (permissions, Super Admin role, settings defaults, **no hardware/zone inventory** — those are registered in-app, DOC-05/06).
4. `php artisan ir4:install` — create the first Super Admin user (DOC-03 §7.3).
5. `php artisan config:cache route:cache view:cache`; `php artisan storage:link` (public disk only; snapshots stay private).
6. `php artisan ir4:export-permissions` → commit-checked `Server/PERMISSIONS.md` (DOC-03).

Nginx/php-fpm document root is `Server/public`.

---

## 4. Process model

Long-running processes are brought up by **`ir4.target`** (system systemd — templates in `Server/scripts/systemd/`, installer `Server/scripts/02-install-systemd-units.sh`). Inside Lerd, workers run as the deploy user’s units; linger is required on a headless SCC so `/run/user/<uid>` exists at boot.

| Process | How it starts | Notes |
|---|---|---|
| **Lerd stack** | `ir4-lerd.service` → `lerd start` | nginx, PHP-FPM, MySQL, Redis |
| **scheduler** | `ir4-workers.service` → `lerd schedule:start` | Spatie backups + prune; `DB_HOST=lerd-mysql` |
| **queue / reverb** | same workers unit | default / ingest / reports queues; WebSockets |
| **MediaMTX** | `ir4-mediamtx.service` | live-wall HLS gateway |
| **camera sync** | `ir4-sync-camera-streams.service` | after MediaMTX + Lerd |

- Redis backs cache, queues, and Reverb scaling.
- Restart queue workers on deploy (`php artisan queue:restart` / `lerd` worker restart).
- Verify after reboot: `systemctl status ir4.target && lerd worker list`.

---

## 5. Nginx, TLS & LAN enforcement

### 5.1 Reverse proxy
- Nginx terminates TLS (a self-signed or client-CA cert for the LAN hostname; the on-prem box has no public domain) and proxies to php-fpm + Reverb (WS upgrade for the Reverb path).
- Large body allowance on `/api/ingest/*` for batched snapshots; sane timeouts.

### 5.2 LAN segmentation (the security spine)
Three surfaces (DOC-01 §3) with different exposure, enforced at Nginx + host firewall (ufw/nftables):
- **Operator app + display (surface A):** the SCC workstation + 55″ display subnet. Session-authenticated (DOC-02).
- **Device ingest (surface B, `/api/ingest/*`, `/api/devices/*`):** restricted to the **device network** (poles/gate/edge units) by IP allow-list at the proxy — a device token is necessary but the proxy also fences the path to device IPs. Not reachable from the general LAN.
- **Public QR page (surface C, `/e/{qr_token}`):** unauthenticated but **LAN-only** — the proxy restricts it to internal ranges and refuses external/unknown sources; rate-limited (DOC-13). No other public route exists.
- Everything else (settings, reports, admin) is behind auth and the operator subnet.
- **No route is exposed to the internet.** Egress is blocked outbound too (no telemetry/CDN calls — assets are bundled, DOC-01).

---

## 6. Database hardening

- **App DB user (`ir4_app`):** normal DML on operational tables, but **INSERT/SELECT only on `audit_logs`** (no UPDATE/DELETE) — the append-only guarantee enforced at the DB, not just the model (DOC-17 §6). SQL: `deploy/database/mysql-grants.sql`. Needs `mysqldump` read privileges (`SELECT`, `SHOW VIEW`, `TRIGGER`, `EVENT`) for Spatie backups.
- **Wipe/maintenance user (`ir4_wipe`):** privileged account used only by `ir4:secure-wipe` (DOC-19), including DELETE on `audit_logs`.
- Archive password from `.env` (`BACKUP_ARCHIVE_PASSWORD`). Least-privilege throughout; credentials only in `.env`.

---

## 7. ZT411 label printer setup (DOC-13)

- Connect the Zebra ZT411 to the LAN; assign a static IP; set deploy-only env vars `EQUIPMENT_PRINTER_HOST` / `EQUIPMENT_PRINTER_PORT` (9100). These are **not** runtime settings (DOC-18).
- Register it as a `qr_printer` device (DOC-05) for inventory/health (non-critical).
- The app sends **raw ZPL over TCP :9100** for one-click printing (DOC-13 §5); verify with a test label at commissioning. Media: 50×50 mm labels; calibrate once.
- Fallback: if unreachable, the app offers a `.zpl`/PDF download (DOC-13) so labeling isn't blocked.

---

## 7a. Android operator app (equipment scan checkout/return)

The Android APK under `Mobile/` is **surface A** over Sanctum bearer tokens (DOC-02 §8a, DOC-13 §4.5) — not a fourth surface and not device auth.

1. **Build:** on a Flutter-capable machine, `cd Mobile && flutter pub get && flutter build apk --release` (debug: `flutter build apk --debug`). Artifact: `Mobile/build/app/outputs/flutter-apk/app-release.apk`.
2. **Sideload:** install on operator handsets over USB / MDM / shared LAN drop — no Play Store, no public internet.
3. **Base URL:** at first login the operator enters the on-prem host (e.g. `https://10.0.0.10` or the LAN hostname). The app stores the URL + Sanctum token in secure storage.
4. **TLS / self-signed:** Nginx uses a self-signed or client-CA cert (§5.1). The app's network security config permits cleartext only for private LAN ranges, trusts user-added CAs, and accepts the configured host's certificate (so a commissioning `auto.crt`-style cert works without shipping a public CA). Prefer installing the site CA on the handset when available.
5. **Commissioning check:** log in as a user with `view-equipment` + `update-equipment`, scan a printed `/e/{qr_token}` label, confirm detail → checkout → rescan → return.

---

## 8. Backups, restore & wipe (operational — DOC-19)

Ordered field path: **[`SCC-SETUP.md`](../SCC-SETUP.md)** steps **02–04** (systemd boot → MediaMTX → backups). This section is the commissioning detail.

### 8.1 One-shot SCC commissioning

Proven on SCC2 (`/data2/laravel/IR4-Project`, flattened deploy — **not** a git checkout):

```bash
cd /data2/laravel/IR4-Project
BACKUP_ARCHIVE_PASSWORD_OVERRIDE='<strong-site-secret>' \
  bash scripts/04-setup-backup.sh
# ends with scripts/02-install-systemd-units.sh when present (ir4.target)
```

`scripts/04-setup-backup.sh`:

1. `.env`: `APP_TIMEZONE`, `BACKUP_DISK_ROOT=/data/ir4-backups`, `BACKUP_ARCHIVE_PASSWORD`, `MYSQL_DUMP_*`, `DISK_SPACE_WARN_PCT`
2. If `backup:run` missing → `composer install --no-dev` + package discover / cache clear
3. Creates/chowns `/data/ir4-backups`
4. Requires `/data` in `~/.config/lerd/config.yaml` mounts; installs `mariadb-connector-c` when needed
5. Bind-mount: `lerd restart`; on missing `/data` or **inode mismatch** → rescue PHP-only zips → `lerd unlink` + `lerd link`
6. Same-inode proof for `BACKUP_DISK_ROOT`
7. `backup:run` — host `ls` must show `ir4-*.zip` under `/data/ir4-backups/{APP_NAME}`
8. `scripts/02-install-systemd-units.sh` — installs **root** units into `/etc/systemd/system/` (`ir4.target`) and enables linger for rootless Podman

Manual equivalent:

```bash
grep laravel-backup composer.json
composer install --no-dev --optimize-autoloader
php artisan package:discover
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php
php artisan optimize:clear
php artisan list backup

sudo mkdir -p /data/ir4-backups && sudo chown -R "$(whoami):$(whoami)" /data/ir4-backups
# APP_TIMEZONE=Asia/Riyadh  BACKUP_DISK_ROOT=/data/ir4-backups  BACKUP_ARCHIVE_PASSWORD=…
# MYSQL_DUMP_BINARY_PATH=/usr/bin  MYSQL_DUMP_TIMEOUT=3600

grep -A5 '^mounts:' ~/.config/lerd/config.yaml   # must include: - /data
lerd restart
# If PHP /data missing or inode mismatch:
lerd unlink && lerd link    # optional "Run lerd setup?" → n
sudo mkdir -p /data/ir4-backups && sudo chown -R "$(whoami):$(whoami)" /data/ir4-backups

stat -c '%i %n' /data/ir4-backups
php -r 'echo fileinode("/data/ir4-backups"), PHP_EOL;'   # MUST match

php artisan backup:run
sudo ls -lah /data/ir4-backups/IR4
lerd schedule:start && lerd worker list
php artisan schedule:list | grep backup
bash scripts/02-install-systemd-units.sh
```

If `backup:run` succeeds but host `ls` fails, PHP wrote into a container-private `/data`. Rescue into `storage/app/`, then fix mounts before trusting backups.

### 8.2 Daily schedule & ops

- Schedule (Spatie order; times use `APP_TIMEZONE` / `general.timezone`, not raw Linux wall clock alone): `backup:clean` 01:00 → `backup:run` 01:30 → `backup:monitor` 03:00 → prune 03:15. Archives are AES-256 ZIPs under `/data/ir4-backups/{APP_NAME}`; 30 daily retention. Failures raise in-app `system` alerts via `AlertService` (no mail). Pruning refuses without the current day's success marker.
- `scripts/01-setup.sh` also starts the schedule worker and fails if `/data` or `mysqldump` is missing.
- Operational commands: `php artisan backup:run`, `backup:list`, `backup:clean`, `backup:monitor`.
- After deploy/`optimize`, if artisan fails on `CleanupStrategy`, delete `bootstrap/cache/config.php` then `php artisan optimize:clear`.
- **Restore drill (staging only):** decrypt/extract with `7z` + `BACKUP_ARCHIVE_PASSWORD`, import SQL into a new staging schema, validate, destroy staging. Never import into live.
- **End-of-project:** `ir4:export-all` → verify → hand over → `ir4:secure-wipe --confirm` (DOC-19 §6).

---

## 9. Monitoring & operations

- **System health** is in-app (DOC-05/16): device/camera offline, gas-telemetry-lost escalation, `disk_space_low`, backup-failure — all as `system` alerts on the dashboard, so operators see infra problems in the same place as safety ones.
- **Logs:** Laravel logs to disk (rotated); Nginx/php-fpm/Supervisor logs standard. No external log shipping (on-prem).
- **Health endpoint:** `GET /up` (Laravel health) for a local uptime check.
- **Runbook basics:** how to restart a stuck queue (`supervisorctl restart`), re-run a failed scheduled job, rotate a leaked device token (DOC-05 §5), re-cache config after an `.env` change, and read the audit log after an incident.

---

## 10. Phase-3 commissioning acceptance checklist

Sign-off that the deployment is production-ready:

**Infrastructure**
- [ ] Server prepped (OS, packages, NTP, LUKS on data volume, disk sized per DOC-19).
- [ ] App deployed, migrated, seeded (permissions + Super Admin + settings defaults; **no** seeded hardware/zones).
- [ ] Lerd PHP-FPM, Reverb, queues, and default scheduler worker are healthy.
- [ ] Boot persistence: `systemctl enable --now ir4.target` (`scripts/02-install-systemd-units.sh`); recovers after cold reboot.
- [ ] Nginx TLS up; LAN segmentation verified (device path device-only, public QR LAN-only, no internet egress in/out).
- [ ] DB grants: app user INSERT/SELECT-only on `audit_logs`; separate wipe account.

**Hardware registration (dynamic — DOC-05/06)**
- [ ] All poles/gate/SCC assets registered; all cameras + readers + gas/CO₂/env devices registered with references + tokens.
- [ ] Zones created; every reader bound to its zone; gate reader bound; map placements set.
- [ ] Heartbeats green for every device; system-health widget all-green.
- [ ] ZT411 prints a test label (one-click) and a bulk run.

**Functional smoke (per module)**
- [ ] Tag read → position on the map + headcount; gate in/out toggles presence.
- [ ] PPE violation ingests → wall toast + record; fall event → alert suggests an incident.
- [ ] Gas reading → live panel; a test excursion → alarm (audible) → acknowledge → hysteresis resolve; **backfill raises no alarm**.
- [ ] Environmental reading → weather widget.
- [x] Equipment: register + one-click label + mobile scan checkout/return (`Mobile/` APK, DOC-13 §4.5 / §7a).
- [ ] Incident + LSR: create (incl. from-alert prefill), classify, close with mandatory action.
- [ ] Evacuation: trigger → auto-account at muster/gate → close → PDF.
- [ ] Weekly report: generate → PDF/CSV with automation badges → publish; a completeness note appears when a stream was offline.

**Safety-critical confirmations (client/safety-lead)**
- [ ] Gas threshold seed values confirmed by the safety lead (DOC-11/18).
- [ ] Tracking windows, session/lockout, retention, week boundary confirmed (DOC-18 §6).

**Data lifecycle**
- [ ] Pruning dry-run confirms allow-list (no compliance table touched).
- [ ] `backup:run`, `backup:list`, `backup:monitor`, and 30-day cleanup pass; encrypted ZIP exists on `/data`; staging restore drill passed.
- [ ] `ir4:export-all` produces a verifiable archive (dry run).

**Access & audit**
- [ ] Roles configured; Super Admin present; a read-only client role writes `data_access` rows.
- [ ] Audit log records logins, config changes (masked), publishes; append-only verified.

---

## 11. Tests (this doc's slice of DOC-21)

Deployment is validated operationally (the checklist) plus a few automated guards:
- **DB-grant guard:** a test/CI check (or a startup self-check) asserts the app connection cannot UPDATE/DELETE `audit_logs`.
- **Route-exposure guard:** an automated check that device (`/api/ingest/*`, `/api/devices/*` heartbeats) and public QR (`/e/{token}`) plus health (`/up`, `/api/health`) and Fortify auth routes are the only unauthenticated surfaces; operator CRUD stays session-gated (DOC-21 on-prem grep).
- **Health/liveness:** `GET /up` returns healthy; scheduler registered all DOC-01 §A8 jobs.
- **Printer:** a `print-label` call with no printer configured falls back to download (no crash).
- **Egress:** (manual/commissioning) outbound blocked; app functions with no internet.

---

## 12. Open decisions logged

| # | Decision | Default | Confirm in |
|---|---|---|---|
| 1 | TLS cert (self-signed vs client CA) | client CA if provided, else self-signed | client IT |
| 2 | Secure-wipe standard | per client policy (DOC-19) | client |
| 3 | Device-network IP allow-list ranges | set at commissioning | client IT |
| 4 | Remote admin (client VPN) | out of scope; client VPN if needed | client IT |

---

### Next document
**DOC-21 — Testing Strategy:** the per-endpoint test matrix (happy/validation/authorization × roles), the scenario test catalogue tying together the cross-module flows (ingest→alert→suggested record, evacuation, backfill, weekly report), factories/seeders, and the CI gates (Pint/PHPStan/TS/enum-sync/append-only/on-prem-grep) that enforce the invariants asserted throughout these docs.