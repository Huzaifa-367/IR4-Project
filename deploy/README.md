# IR4 on-prem deployment (DOC-20)

Concrete templates for standing up a single-site LAN install on Ubuntu 24.04 + PHP 8.4 + MySQL 8 + Redis 7 + Nginx + Supervisor.

## Layout

| Path | Purpose |
|---|---|
| `env/ir4.production.env.example` | Production `.env` template (Redis queue/cache, least-privilege DB users) |
| `nginx/ir4.conf` | TLS vhost, Reverb upgrade, LAN fences for surfaces A/B/C |
| `supervisor/ir4.conf` | Reverb, queues (`default`, `ingest`, `reports`), scheduler |
| `php-fpm/ir4.conf` | Pool overrides (optional include) |
| `firewall/nftables.conf` | Ingress allow-lists + egress deny |
| `database/mysql-grants.sql` | `ir4_app` / `ir4_backup` / `ir4_restore` / `ir4_wipe` |
| `logrotate/ir4` | Laravel + Nginx + Supervisor log rotation |
| `scripts/preflight.sh` | Pre-deploy checks |
| `scripts/deploy.sh` | Idempotent pull/build/migrate/restart |
| `scripts/verify-network-fences.sh` | Probe surface fences from each subnet |
| `operations.md` | Day-2 ops: restart, rollback, token rotate, restore drill |
| `offsite-backup.md` | Manual off-site copy + chain of custody |
| `commissioning-signoff.md` | Phase-3 acceptance record |

Authoritative narrative: [`Docs/Doc 20 deployment runbook.md`](../Docs/Doc%2020%20deployment%20runbook.md).

## Quick start

1. Prep server per DOC-20 §2 (LUKS volumes, NTP, packages).
2. Apply `database/mysql-grants.sql` as a MySQL admin.
3. Copy `env/ir4.production.env.example` → `/var/www/ir4/.env` and fill secrets.
4. Install Nginx/Supervisor/logrotate snippets; replace `CHANGE_ME_*` CIDRs.
5. Run `scripts/preflight.sh` then `scripts/deploy.sh`.
6. `php artisan ir4:install` for the first Super Admin.
7. Walk `commissioning-signoff.md`.
