# DOC-20 — Deployment & Operations Runbook

> **Depends on:** DOC-01 (stack, queues, scheduler, storage), DOC-02/08 (device + display auth on the LAN), DOC-05 (printer as a device), DOC-13 (public QR page + ZT411 printing), DOC-17 (append-only audit → DB grants), DOC-18 (config-vs-`.env`), DOC-19 (backups, restore, wipe). **Feeds:** the field-engineering team standing the system up; the acceptance sign-off.
>
> **Scope:** the **on-prem deployment and operations runbook** — server preparation (Dell R360), the app/queue/Reverb/scheduler process model, reverse-proxy + firewall LAN enforcement (public QR page and device ingest), ZT411 printer setup, database-permission hardening (append-only audit, wipe privileges), backup/restore/wipe drills, monitoring, and the **Phase-3 commissioning acceptance checklist**. **Out of scope:** application behavior (owned by the module DOCs) — this doc gets it running and keeps it running.

---

## 1. Environment & principles

- **Single on-prem server** (Dell R360 or equivalent): the app, **MySQL 8**, Redis, Reverb, queue workers, and scheduler all run on one box on the **site LAN**. No cloud dependency, **no internet egress** (DOC-01) — the platform is fully self-contained. PostgreSQL is not a supported production target.
- **One installation = one site** (standalone, DOC-01 §1). A second location is a separate independent install.
- **Everything the operator/display/devices reach is over the LAN.** External access is not a goal; if remote admin is ever needed it's via the client's own VPN, out of scope here.
- OS baseline: Ubuntu 24.04 LTS. Runtime: **PHP 8.4+**, Node **22** (build-time only), MySQL 8, Redis 7, Nginx. Concrete templates live under `deploy/`.

---

## 2. Server preparation

1. **OS & packages:** Ubuntu 24.04, security updates, then PHP 8.4 (fpm + required extensions: pdo_mysql, redis, gd/imagick for snapshots, zip, bcmath), Nginx, MySQL 8, Redis, `supervisor`, `git`, Composer, Node 22 (build only).
2. **Disk layout:** OS volume; a **data volume** for MySQL + the private storage (snapshots/documents); a **separate backup volume** (DOC-19 backups must not share the live-data disk). Provision per the DOC-19 volume math (hundreds of GB, snapshot-dominated). Enable **encryption at rest** (LUKS) on the data + backup volumes.
3. **Time:** NTP synced (ordering/clock-skew logic depends on a sane server clock; devices are reconciled to it — DOC-08). Server/OS timezone stays UTC; operator display/report timezone is the runtime setting `general.timezone` (DOC-18), not `APP_TIMEZONE`.
4. **Users:** a non-root deploy user; the web/worker processes run unprivileged.

---

## 3. Application deploy

1. Clone the repo, `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build` (Vite build; the built assets ship — no Node at runtime).
2. `.env` from `deploy/env/ir4.production.env.example` (DOC-18 §1): `APP_KEY`, least-privilege DB creds (`ir4_app` / `ir4_backup` / `ir4_restore` / `ir4_wipe`), Redis, Reverb keys, storage paths, `BACKUP_ENCRYPTION_KEY`, `EQUIPMENT_PRINTER_HOST` / `EQUIPMENT_PRINTER_PORT`. **Secrets live here, never in the DB.** Runtime timezone is `general.timezone` after seed — not an `.env` knob.
3. `php artisan migrate --force`; `php artisan db:seed` (permissions, Super Admin role, settings defaults, **no hardware/zone inventory** — those are registered in-app, DOC-05/06).
4. `php artisan ir4:install` — create the first Super Admin user (DOC-03 §7.3).
5. `php artisan config:cache route:cache view:cache`; `php artisan storage:link` (public disk only; snapshots stay private).
6. `php artisan ir4:export-permissions` → commit-checked `PERMISSIONS.md` (DOC-03).

---

## 4. Process model (Supervisor)

Long-running processes under `supervisor` (auto-restart). Templates: `deploy/supervisor/ir4.conf`. PHP-FPM is managed by **systemd** (`php8.4-fpm`); Supervisor owns Reverb, queues, and the scheduler only.

| Process | Command | Notes |
|---|---|---|
| **php-fpm** | systemd `php8.4-fpm` | serves the app + API behind Nginx |
| **reverb** | `php artisan reverb:start` | self-hosted WebSockets (DOC-08); LAN only |
| **queue: default** | `php artisan queue:work --queue=default` | imports, general jobs, backups, pruning |
| **queue: ingest** | `php artisan queue:work --queue=ingest` | reserved for ingest post-processing bursts (DOC-08) |
| **queue: reports** | `php artisan queue:work --queue=reports` | PDF/CSV generation (DOC-15), exports |
| **scheduler** | `php artisan schedule:work` | all timed jobs (DOC-01 §A8): health markStale, rollups, pruning, backups, weekly report, overdue flags, absence sweep |

- Redis backs cache, queues, and Reverb scaling.
- Worker counts tuned to the box; the `ingest` queue gets the most workers (backfill floods, DOC-08). Restart workers on deploy (`queue:restart`).

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

- **App DB user (`ir4_app`):** normal DML on operational tables, but **INSERT/SELECT only on `audit_logs`** (no UPDATE/DELETE) — the append-only guarantee enforced at the DB, not just the model (DOC-17 §6). SQL: `deploy/database/mysql-grants.sql`.
- **Backup user (`ir4_backup`):** SELECT-only on all tables; used by the daily dump connection `ir4_backup`.
- **Restore user (`ir4_restore`):** full DML on the staging database only (never the live DB name).
- **Wipe/maintenance user (`ir4_wipe`):** privileged account used only by `ir4:secure-wipe` (DOC-19), including DELETE on `audit_logs`.
- Encryption key from `.env` (`BACKUP_ENCRYPTION_KEY`). Least-privilege throughout; credentials only in `.env`.

---

## 7. ZT411 label printer setup (DOC-13)

- Connect the Zebra ZT411 to the LAN; assign a static IP; set deploy-only env vars `EQUIPMENT_PRINTER_HOST` / `EQUIPMENT_PRINTER_PORT` (9100). These are **not** runtime settings (DOC-18).
- Register it as a `qr_printer` device (DOC-05) for inventory/health (non-critical).
- The app sends **raw ZPL over TCP :9100** for one-click printing (DOC-13 §5); verify with a test label at commissioning. Media: 50×50 mm labels; calibrate once.
- Fallback: if unreachable, the app offers a `.zpl`/PDF download (DOC-13) so labeling isn't blocked.

---

## 8. Backups, restore & wipe (operational — DOC-19)

- **Daily encrypted backup** to the separate volume (Supervisor scheduler); 30 kept; failure raises a `system` warning. Document the (manual, no-cloud) **off-site copy** procedure in `deploy/offsite-backup.md`.
- **Restore drill at commissioning:** run `ir4:restore` into a staging DB and verify — a backup that hasn't been restored is unproven. Repeat periodically.
- **End-of-project:** `ir4:export-all` → verify → hand over encrypted archive + key → `ir4:secure-wipe --confirm` (refuses without a verified export). The verified `.ir4exp` archive is **immutable**; wipe writes a **separate wipe receipt** beside it on the exports disk (DOC-19 §6). Chain-of-custody: `deploy/operations.md`.

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
- [ ] Server prepped (OS, packages, NTP, LUKS on data+backup volumes, disk sized per DOC-19).
- [ ] App deployed, migrated, seeded (permissions + Super Admin + settings defaults; **no** seeded hardware/zones).
- [ ] Supervisor running web/reverb/queues(default,ingest,reports)/scheduler; all auto-restart.
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
- [ ] Equipment: register + one-click label + mobile scan checkout/return.
- [ ] Incident + LSR: create (incl. from-alert prefill), classify, close with mandatory action.
- [ ] Evacuation: trigger → auto-account at muster/gate → close → PDF.
- [ ] Weekly report: generate → PDF/CSV with automation badges → publish; a completeness note appears when a stream was offline.

**Safety-critical confirmations (client/safety-lead)**
- [ ] Gas threshold seed values confirmed by the safety lead (DOC-11/18).
- [ ] Tracking windows, session/lockout, retention, week boundary confirmed (DOC-18 §6).

**Data lifecycle**
- [ ] Rollups building; pruning dry-run confirms allow-list (no compliance table touched).
- [ ] Daily backup runs; **restore drill passed** on staging.
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
| 2 | Off-site backup copy procedure | manual, no cloud | client IT |
| 3 | Secure-wipe standard | per client policy (DOC-19) | client |
| 4 | Device-network IP allow-list ranges | set at commissioning | client IT |
| 5 | Remote admin (client VPN) | out of scope; client VPN if needed | client IT |

---

### Next document
**DOC-21 — Testing Strategy:** the per-endpoint test matrix (happy/validation/authorization × roles), the scenario test catalogue tying together the cross-module flows (ingest→alert→suggested record, evacuation, backfill, weekly report), factories/seeders, and the CI gates (Pint/PHPStan/TS/enum-sync/append-only/on-prem-grep) that enforce the invariants asserted throughout these docs.