# Memory.md — Living progress log

> Specs stay in `PRD.md`, `Architecture.md`, `Rules.md`, `Phases.md`, `Design.md`, and `Docs/`.  
> This file only answers: *what is already true in the codebase right now?*

---

## Status

**Coding started:** Yes

**Current phase:** Phase 10 — Deploy & operations (DOC-20)  
**Next:** Phase 10 deploy/ops artifacts, then Phase 11 (DOC-21) invariant guards + CI gates  
**Blocked on:** Nothing — Docs 01–21 are written and authoritative

---

## Done

### Phase 0–9c
- [x] Foundation through audit logging (DOC-17)

### Phase 9d — Settings (DOC-18)
- [x] `SettingsRegistry` + validated `SettingsService` whitelist/`config_changed`
- [x] Legacy settings key migration; printer host/port in `.env`/`config/ir4.php`
- [x] `/settings/general` grouped editor with per-key permissions + confirm

### Phase 9e — Retention & backup (DOC-19)
- [x] `BuildSensorRollups`, `PruneRawSensorData`, `PruneExportFiles`
- [x] Encrypted `BackupDatabase`, `ir4:restore`, `ir4:export-all`, `ir4:secure-wipe`
- [x] Disk-space / missing-backup `system` alerts

### Docs
- [x] Full set `Docs/Doc 01`–`Doc 21` on disk (including DOC-20 deploy runbook + DOC-21 testing strategy)

### Control Room UI
- [x] Design tokens + local Inter / Inter Tight / JetBrains Mono in `resources/css/app.css`
- [x] Shared `resources/js/components/ir4/*` primitives (StatCard, LiveFeed, AnalyticalChart, gauges/bars, …)
- [x] Operator sidebar/topbar Control Room chrome (grouped nav, LIVE pill, alert bell)
- [x] `/dashboard` rebuilt to mockup archetype on `DashboardService::summary` + Reverb/poll
- [x] `/environment` rebuilt as a Control Room telemetry surface with KPI cards, multi-metric trends (temp/humidity/wind + extras), duration-only range toggle, range statistics, dynamic air-quality metrics, and live sensor fleet health
- [x] `/live` renders browser-safe MediaMTX feeds; raw RTSP URLs and credentials remain server-side

### Demo data
- [x] Non-production `DemoSeeder` — ~4 months of site scenario data

---

## In progress

_None — Control Room UI pass landed; module pages inherit tokens/components next as needed._

---

## Decisions made while coding

- Prior decisions still apply
- DOC-18 registry wins for `retention.exports_days` = **7** (DOC-19 prose corrected)
- Secure wipe default mode = **crypto_erase**; overwrite available via `IR4_WIPE_MODE`
- No tag-reading rollup table — manpower stays entry/exit-derived; tags prune after window unconditionally
- Wipe writes a separate receipt on the exports disk (does not mutate a verified handover archive)
- Dedicated `BACKUP_ENCRYPTION_KEY` preferred; falls back to HKDF from `APP_KEY`
- `dashboard.cache_seconds` is canonical (replaces `dashboard.cache_ttl_seconds`)
- `DemoSeeder` is local/staging only; skips if `Main Gate` zone already exists; never runs in production
- Docs 01–21 are the complete authoritative specification set
- **Frontend chrome + `/dashboard` layout:** `Docs/Ir4 ui styling guide.md` + `Docs/Ir4 dashboard mockup.html` are authoritative (dark-first Control Room tokens, Inter / Inter Tight / JetBrains Mono via `@fontsource`, shared `components/ir4/*`). Module pages inherit; full `/display` kiosk polish is a follow-up.
- Dashboard summary includes mockup analytics: headcount flow/sparklines, PPE compliance + heatmap, multi-series H₂S trend (`gas_range`), safety score, evacuation readiness, open incidents/LSR table. Default shift window **06:00–18:00** `[CONFIRM AT DESIGN]`.

---

## Gotchas / landmines

- App uses `CarbonImmutable` — prefer `DateTimeInterface` then `Carbon::instance()`/`parse()`
- Future `recorded_at` (>skew) clamps — tests must use near-now timestamps
- New Inertia pages need `npm run build` for tests
- `UserFactory` auto-assigns SCC Operator unless `withRole(...)` syncs another role
- `AppliesListQuery` param is `defaultDirection`, not `defaultDir`
- Device-offline outage duration uses alert `created_at`→`resolved_at` (not `raised_at`)
- Dashboard summary cache key includes user permission set; TTL `dashboard.cache_seconds` (default 8)
- Manual report generate is synchronous; scheduled auto-generate stays on `reports` — run `php artisan queue:work --queue=reports,default` for the scheduler path
- Nested `<AppLayout>` on pages doubles the sidebar — pages must rely on `app.tsx` layout resolver only
- Demo logins after seed: `operator@ir4.local` / `safety@ir4.local` / `pm@ir4.local` (password: `password`)
- Sensitive settings require `confirmed` (or UI confirm flow) — report settings page keys do not
- Backup/export workdirs live under `storage/app/tmp/`; disks `backups` + `exports` must exist
- Shared Inertia `settings` drives client idle timeout, display keep-alive, poll fallback, and toast duration

---

## Key paths

| What | Path |
|---|---|
| Settings registry | `app/Support/SettingsRegistry.php` |
| Settings service / UI | `app/Services/SettingsService.php`, `resources/js/pages/settings/general/index.tsx` |
| Rollups / retention | `app/Services/SensorRollupService.php`, `app/Services/RetentionService.php` |
| Backup / export / wipe | `app/Services/Backup/*`, `app/Console/Commands/{Backup,Restore,ExportAll,SecureWipe}*` |
| Doc18 / Doc19 tests | `tests/Feature/Settings/Doc18SettingsTest.php`, `tests/Feature/Retention/Doc19RetentionBackupTest.php` |
| Demo seeder | `database/seeders/DemoSeeder.php` |
| Deploy runbook | `Docs/Doc 20 deployment runbook.md` |
| Testing strategy | `Docs/Doc 21 testing strategy.md` |
| UI styling guide | `Docs/Ir4 ui styling guide.md` |
| Dashboard mockup | `Docs/Ir4 dashboard mockup.html` |
| IR4 UI primitives | `resources/js/components/ir4/` |
| Operator shell | `resources/js/components/app-sidebar.tsx`, `app-sidebar-header.tsx` |

---

## How to update (AI checklist)

After finishing a task:

1. Tick matching items in `Phases.md`.
2. Update **Current phase** / **In progress** / **Blocked on**.
3. Record Doc deviations under **Decisions**.
4. Add traps under **Gotchas**.
5. Keep this file short.
