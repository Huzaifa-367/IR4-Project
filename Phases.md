# Phases.md — Build in order

> AI cannot build IR4 in one shot. Complete each phase before starting the next. Check boxes in `Memory.md` as you go. Specs: `Docs/Doc 01`–`Doc 21` (complete set).

---

## Phase 0 — Foundation (DOC-01)

**Goal:** Empty but correct Laravel React app the rest of the system plugs into.

- [x] Install Laravel React Starter Kit (React 19, Inertia 3, Tailwind 4, shadcn, Fortify)
- [x] Add packages: spatie/permission, reverb, dompdf, endroid/qr-code (excel deferred — PHP 8.5); frontend recharts, maplibre-gl, date-fns, zod
- [x] Folder layout per `Architecture.md` (Web / Api/Ingest / Public, Services, Support, …)
- [x] `ApiResponse`, list-query concern, CreatedBy observer stubs
- [x] Settings table + `SettingsService` skeleton
- [x] Enum export command stub + CI scripts (Pint, PHPStan, TS, on-prem host grep)
- [x] Private/public disks + signed URL helper
- [x] Theme tokens from `Design.md` wired into CSS variables

**Done when:** App boots, login page renders with IR4 theme, CI skeleton passes. ✅

---

## Phase 1 — Auth (DOC-02)

**Goal:** Humans can sign in safely on an air-gapped LAN.

- [x] Fortify: login, logout, password update; disable registration / email reset / verification
- [x] Users table fields: `is_active`, `must_change_password`, last login, soft deletes
- [x] Idle timeout (default 15 min) — server middleware + `useIdleLogout`
- [x] `/display` kiosk route with session keep-alive
- [x] `auth.device` middleware (token hash lookup) — contract only
- [x] Admin password reset + `ir4:user:reset` artisan command
- [x] Optional TOTP (offline)

**Done when:** Login works; idle logs out; display stays alive; device middleware rejects bad tokens. ✅

---

## Phase 2 — Roles & permissions (DOC-03)

**Goal:** Authorization catalogue and Super Admin lock.

- [x] Permission seeder (full catalogue from DOC-03)
- [x] Seeded roles: Super Admin, Safety Manager, SCC Operator, PM, Client Rep, Field Staff
- [x] Super Admin invariants (≥1 active, immutable, all perms, no Gate::before)
- [x] `ir4:install` creates first Super Admin
- [x] Policies + `permission:` middleware pattern
- [x] Frontend `usePermissions` / `<RequirePermission>`
- [x] Read-only role server whitelist (`view-*` only)
- [x] `ir4:export-permissions` → `PERMISSIONS.md`

**Done when:** Install works; a restricted user is blocked by policy; Field Staff has no login perms. ✅

---

## Phase 3 — Registries (DOC-04 → 05 → 06)

**Goal:** Site can be commissioned from the UI (empty hardware/zones in production).

### 3a Workers
- [x] Model, migration, factory, policy, CRUD Inertia pages
- [x] Identity stripping without `view-worker-identity`
- [x] CSV import; offboard guards

### 3b Hardware
- [x] Assets, cameras, devices; token issue/rotate (plaintext once)
- [x] Heartbeats; retire vs delete
- [x] Health stale detection (can stub alerts until Phase 4)

### 3c Zones
- [x] Zones CRUD + map fields
- [x] Time-aware `reader_zone_bindings` + `resolveZoneAt`
- [x] Repositioning UI; access lists; gate-rebind warning

**Done when:** Import workers → register devices → issue tokens → create zones → bind readers. ✅

---

## Phase 4 — Alerts hub (DOC-07)

**Goal:** One notification pipeline before domain modules raise events.

- [x] `alerts` table + enums + `AlertService` (raise / dedupe / ack / resolve)
- [x] Alert centre UI + toasts + audible critical loop
- [x] Reverb `alerts` channel + poll `/api/alerts/open`
- [x] `suggested_action` payload shape (no auto domain writes)

**Done when:** Test raise appears live, dedupes, acks, resolves. ✅

---

## Phase 5 — Ingest & realtime backbone (DOC-08)

**Goal:** Shared device contract all telemetry modules reuse.

- [x] Shared ingest helpers: batch, idempotency, forward-only, backfill, skew, rate limit
- [x] Wire five endpoints (handlers may stub-accept until Phase 6)
- [x] Channel auth in `channels.php`
- [x] `useReverbChannel` + LIVE/RECONNECTING + poll fallback hook
- [x] Heartbeats update `last_seen_at`

**Done when:** Device posts a batch → 202 partial outcomes; a page shows LIVE with fallback. ✅

---

## Phase 6 — Live safety modules

**Goal:** Command-centre live picture. Do **6a first**; 6b–6d can proceed in parallel after Phase 5.

### 6a RFID tracking (DOC-09) — priority
- [x] Tags, readings ingest, positions, gate debounce/toggle, entry/exit
- [x] Headcount / positions APIs; zone-rule alerts
- [x] Stationary + worker-down; absence sweep
- [x] Evacuation + PDF; portable devices

### 6b PPE (DOC-10)
- [x] Ingest (no `worker_id`); live wall; review workflow; fall → alert only

### 6c Gas (DOC-11)
- [x] Ingest; global thresholds; hysteresis; live panels; backfill = no live alarms

### 6d Environmental (DOC-12)
- [x] Ingest; weather widget; trends (raw ≤24 h / on-read hourly beyond); no alarms

**Done when:** Headcount + map + gas + PPE update live; evacuation runnable.

---

## Phase 7 — Equipment & public QR (DOC-13)

**Goal:** Mobilization equipment path + Field Staff surface.

- [x] Equipment CRUD; permanent `qr_token`; inspections/maintenance/schedules/docs
- [x] Overdue job + deduped alerts; CSV import; ZPL print
- [x] Checkout/return scan flow
- [x] Public `GET /e/{qr_token}` (Blade default); separate authed by-token API

**Done when:** Import → print → scan public page on LAN; custody works. ✅

---

## Phase 8 — HSE incidents & LSR (DOC-14)

**Goal:** Formal records with mandatory human judgment.

- [x] Incidents lifecycle + personnel + evidence
- [x] LSR + mandatory `action_taken`
- [x] Prefill-from-alert UX (submit only persists)
- [x] PPE-linked LSR keeps `worker_id` null

**Done when:** Create from alert → review → submit with required fields; no service auto-inserts. ✅

---

## Phase 9 — Reporting, dashboard & audit (DOC-15 → 16 → 17)

**Goal:** Deliver the weekly compliance package, the composed command-centre view, and its tamper-resistant audit trail.

### 9a Weekly report (DOC-15)
- [x] Weekly reports + manual vehicle violations; item vii requires `action_taken`
- [x] Assemble and freeze all 10 Section 6.5 items; exclude PPE false positives and include incident/LSR actions
- [x] Compute outage completeness notes and honest monitored-units scope
- [x] Queue scheduled/manual generation; render badged PDF + zipped per-item CSV to private storage
- [x] Publish-lock generated reports; supersede published reports instead of editing; retain both versions
- [x] Enforce generate/publish/log permissions, signed downloads, and published-only PM/read-only visibility

**Done when:** A scheduled or manual run freezes all 9 items, declares qualifying sensor gaps, exports both formats, and a published report can only be amended by a linked superseding report. ✅

### 9b Dashboard, display & design language (DOC-16)
- [x] Apply the dark-first Control Room tokens, local fonts, 14px signature cards, and analytical chart system from `Design.md`
- [x] Build cached, permission-filtered `/api/dashboard/summary` from module read services
- [x] Compose the role-aware widget grid and PM KPI variant
- [x] Share the identity-safe live zone map across tracking, dashboard, and display
- [x] Build authenticated `/display` cycling camera/headcount, gas/CO₂, and map panes with persistent critical banner/ticker
- [x] Patch from Reverb and reconcile from the 60 s summary poll with LIVE/RECONNECTING state

**Done when:** Operators receive the full live safety picture, PM users receive only permitted KPIs, and the authenticated 55″ display remains readable, live, and permission-safe through socket loss. ✅

### 9c Audit logging (DOC-17)
- [x] Create append-only `audit_logs`, `AuditEvent`, and model guards; expose no mutation route
- [x] Add `Auditable` observer coverage for the documented configuration/security models with changed-field diffs
- [x] Mask passwords, tokens, 2FA secrets, and model-declared sensitive values before persistence
- [x] Record authentication and explicit publish/acknowledge/export events with user/system attribution, IP, and user agent
- [x] Add allow-listed per-request `data_access` logging for `is_read_only` roles only
- [x] Build permission-gated read-only viewer, filters, expandable masked diffs, and audited CSV export

**Done when:** Covered changes and meaningful read-only access are attributable, sensitive values never enter the log, and no application or retention path can edit, delete, or prune an audit row. ✅

### 9d Settings & configuration (DOC-18)
- [x] Authoritative `SettingsRegistry` + validated `SettingsService` (whitelist, cache, `config_changed`)
- [x] Legacy key migration to DOC-18 dotted names; printer host/port remain deploy config
- [x] Permission-aware `/settings/general` editor with sensitive confirmations
- [x] Consumers read canonical keys/units live

**Done when:** Settings editor saves audited values; unknown keys rejected; consumers honor registry defaults. ✅

### 9e Retention, backup & decommissioning (DOC-19)
- [x] Gas/env on-read aggregates (no `BuildSensorRollups`; no tag rollup — manpower from entry/exit)
- [x] Daily allow-listed `PruneRawSensorData` + export-file sweep (report PDFs exempt)
- [x] Encrypted daily `BackupDatabase` + rotation + disk/backup gap alerts
- [x] `ir4:restore`, `ir4:export-all`, guarded `ir4:secure-wipe` (crypto_erase default)

**Done when:** Pruning/backup jobs are scheduled; wipe refuses without verified export + confirm phrase. ✅

### 10 Deploy & operations runbook (DOC-20)
- [ ] Documented/deployable Supervisor set: web, reverb, queues (`default`, `ingest`, `reports`), scheduler
- [ ] Nginx TLS + LAN segmentation notes/templates for surfaces A/B/C (device ingest + public QR fences)
- [ ] App DB user INSERT/SELECT-only on `audit_logs`; separate wipe/restore privileged account
- [ ] ZT411 `.env` printer path + commissioning test-label steps
- [ ] Commissioning acceptance checklist usable as Phase-3 sign-off (infra + hardware + smoke + lifecycle)

**Done when:** An engineer can stand up a LAN install from DOC-20 without inventing process model or fence rules.

### 11 Testing strategy & CI gates (DOC-21)
- [ ] Named invariant-guard Pest suite covering DOC-21 §3 hard rules
- [ ] Cross-module scenario catalogue (§4) driven via real ingest where applicable
- [ ] CI gates: Pint, PHPStan, `tsc`, enum-sync, PERMISSIONS.md, on-prem/standalone greps
- [ ] Frontend Vitest coverage for permissions, identity stripping, live+fallback, forms, custody

**Done when:** Invariant guards and CI greps block design regressions; scenario suite covers the DOC-21 catalogue.

---

## MVP cut (first site)

Ship Phases **0–5**, then **6a + 6c + 6b**, then **7**, then **8**, then **9a–9e**, then **10–11** for deploy confidence and CI completeness.  
Defer Phase 10 field commissioning only when the first site is not yet standing hardware.

---

## How to work with the AI

1. Say: “Implement Phase N from `Phases.md`.”
2. Point it at the matching Doc.
3. Require tests before marking the phase done.
4. Update `Memory.md` with what landed, decisions, and blockers.
