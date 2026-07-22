# Architecture.md — App Architecture

> How IR4 is structured. Follow this when scaffolding or placing new code. Detail lives in `Docs/Doc 01` and module docs.

---

## System flow

```
Field hardware                    Operators / kiosk              Field staff
      │                                 │                            │
      │  X-Device-Token                 │  Fortify session           │  no auth
      ▼                                 ▼                            ▼
 /api/ingest/*  ──► Services ──► DB ◄── Inertia controllers    GET /e/{qr_token}
 /api/.../heartbeat     │         ▲         │                        │
                        │         │         ▼                        ▼
                        └──► AlertService   React pages          Public read page
                        └──► Reverb ──────────────────────────► live deltas
                        └──► Redis queues (ingest, default, reports)
```

**One site, one install.** Nothing leaves the LAN. No `site_id` column anywhere.

---

## Three entry surfaces (never mix)

| Surface | Who | How | Routes | Auth |
|---|---|---|---|---|
| **A. Operator UI** | Staff | Inertia pages + forms | `routes/web.php` | Session + RBAC |
| **B. Device API** | Hardware | REST JSON | `routes/api.php` | `auth.device` |
| **C. Public page** | Anyone on LAN | Read-only page | `web.php` public group | None, rate-limited |

Live pattern: Inertia snapshot → Reverb subscribe → patch state → LIVE/RECONNECTING → poll if socket down.

---

## Three-path data origin

| Path | Writer | `created_by` |
|---|---|---|
| ① Device | Ingest → services | `NULL` |
| ② System | Jobs / services / scheduler | `NULL` |
| ③ User | Inertia + FormRequest + policy | user id |

Machine fields are immutable. Human judgment is always path ③. Alerts are path ② only (users only ack/resolve). Incidents/LSR are path ③ only (alerts may prefill, never insert).

---

## Technical stack

| Layer | Choice |
|---|---|
| Backend | PHP 8.4+, Laravel 13.9+ |
| Operator UI | React 19, TypeScript, Inertia 3, Tailwind 4, shadcn/ui, Vite |
| Auth | Fortify (humans), `auth.device` (machines) |
| Realtime | Laravel Reverb (self-hosted — no cloud Pusher) |
| Queue/cache | Redis (`ingest`, `default`, `reports`) |
| Database | MySQL 8+ (default) or PostgreSQL 15+ |
| RBAC | `spatie/laravel-permission` |
| PDF / QR | `barryvdh/laravel-dompdf`, `endroid/qr-code` |
| Charts / map | recharts, maplibre-gl |
| Dates / forms | date-fns, zod |
| Quality | Larastan 6+, Pest, Pint; TS strict |

---

## File & folder structure

```
app/
├── Enums/                 # PHP backed enums → export to TS
├── Models/
├── Http/
│   ├── Controllers/
│   │   ├── Web/           # Surface A — Inertia
│   │   ├── Api/Ingest/    # Surface B — device ingest
│   │   └── Public/        # Surface C — QR pages
│   ├── Requests/          # One FormRequest per write
│   ├── Resources/         # JsonResource for B/C
│   └── Middleware/        # auth.device, idle timeout, audit…
├── Policies/
├── Services/              # Business logic (thin controllers)
├── Actions/ Jobs/ Events/ Listeners/ Observers/
└── Support/               # ApiResponse, list-query helpers

database/
├── migrations/            # Additive only — never edit a run migration
├── factories/
└── seeders/

routes/
├── web.php                # Operator + public
├── api.php                # Ingest + heartbeats + authed JSON helpers
├── channels.php           # Reverb auth
└── console.php            # Scheduler

resources/js/
├── pages/                 # Mirror route/controller paths
├── layouts/               # AppLayout, DisplayLayout, AuthLayout
├── components/
│   ├── ui/                # shadcn
│   └── ir4/               # Domain UI
├── hooks/                 # useReverbChannel, useIdleLogout, usePermissions…
├── lib/
└── types/                 # enums.ts is GENERATED — never hand-edit

tests/Feature/  tests/Unit/
Docs/                      # Specs DOC-01…21 (authoritative, complete set)
```

**Mapping rule:** page `resources/js/pages/tracking/workers/index.tsx` ↔ `App\Http\Controllers\Web\Tracking\WorkerController@index`.

---

## Module map

| DOC | Module | Surfaces |
|---|---|---|
| 02–03 | Auth + RBAC | A (+ display), B token contract |
| 04 | Workers | A |
| 05 | Hardware | A, B heartbeats |
| 06 | Zones / bindings | A |
| 07 | Alerts hub | A + Reverb |
| 08 | Ingest + Reverb | B backbone |
| 09 | RFID tracking | A, B |
| 10 | PPE | A, B |
| 11 | Gas | A, B |
| 12 | Environmental | A, B |
| 13 | Equipment + public QR | A, C |
| 14 | HSE + LSR | A |
| 15 | Weekly reports + vehicle violations | A + reports queue |
| 16 | Aggregate dashboard + authenticated display | A + Reverb |
| 17 | Append-only audit trail + viewer | A + middleware/observers |
| 18 | Runtime settings registry + general editor | A |
| 19 | Rollups, pruning, backup, export, wipe | Scheduler + privileged CLI |
| 20 | Deploy / LAN ops runbook + commissioning | Ops (Nginx, Supervisor, DB grants) |
| 21 | Testing strategy + CI invariant gates | Pest / Vitest / CI |

---

## Shared reporting, composition & audit

- **Weekly reports:** a queued `WeeklyReportService` reads module summaries, freezes all 9 items into one JSON snapshot, and writes PDF/zipped-CSV artifacts to private storage. Publication locks the snapshot; amendments create a linked superseding report.
- **Dashboard/display:** one cached, permission-filtered `GET /api/dashboard/summary` composes module read services. Inertia supplies the screen, Reverb patches live deltas, and a 60 s summary poll reconciles both the role-aware dashboard and authenticated display.
- **Audit:** observers record masked diffs for configured security/configuration models; middleware records allow-listed meaningful access by read-only roles; domain actions emit explicit publish/acknowledge/export events. `audit_logs` is append-only and excluded from pruning, with only a permission-gated read/export surface.
- **Settings (DOC-18):** `SettingsRegistry` + `SettingsService` whitelist every runtime key; deploy-fixed values stay in `.env`/`config`.
- **Lifecycle (DOC-19):** daily allow-listed `PruneRawSensorData`, encrypted `BackupDatabase`, and console-only `ir4:export-all` / `ir4:restore` / `ir4:secure-wipe`. Gas/env trends use on-read SQL aggregates (no rollup job/table).
- **Deploy (DOC-20):** single-box LAN install; Nginx fences surfaces A/B/C; Supervisor runs web/Reverb/queues/scheduler; app DB user is INSERT/SELECT-only on `audit_logs`.
- **Quality (DOC-21):** endpoint matrix + named invariant guards + cross-module scenarios; CI fails on enum drift, on-prem greps, and append-only violations.

---

## Key API shapes (surfaces B & C)

- Success: `{ data: … }` or collection with `meta` / `links`
- Non-resource: `ApiResponse::ok/accepted/error(...)`
- Errors: `{ error: { code, message, details } }`
- Ingest: `{ events: [...] }` → **202** `{ accepted, duplicates, rejected[] }`

---

## Reverb channels

`alerts` · `ppe` · `tracking` · `gas` · `environment` · `system`  
Private, no location prefix. High-frequency ~5 s throttle; discrete events immediate.
