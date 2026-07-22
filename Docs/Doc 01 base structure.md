# DOC-01 — Base Structure & Platform Conventions

> **Status:** Authoritative foundation document. Every other DOC (02–21) inherits the conventions defined here and does not restate them. Source of truth for behavior = `IR4_MASTER_SPEC_v3.md`; source of truth for *how we build it* = this document.
>
> **Scope of this doc:** the project skeleton — stack, versions, repository layout, the hybrid Inertia/API architecture, global data conventions, the enum pattern, the API response + error contract, validation baseline, file storage, the settings registry, and the base classes/traits that every module reuses.
>
> **Out of scope:** authentication (DOC-02), RBAC (DOC-03), and any domain logic. Those consume the primitives defined here.

---

## 1. Product context in one paragraph

IR4 is a **standalone, on-premise safety command-centre platform**. A single installation serves a single deployment — there is no multi-tenancy, no scoping partition, and no location concept in the schema. Field hardware (RFID readers, AI cameras, gas/CO₂/environmental sensors) streams data in over a token-authenticated REST API; the server derives live state, alarms, and reports from it; and operators work in a rich real-time web UI (dashboard, live tracking map, gas panels, HSE workflows, reporting). One always-on 55″ display runs a kiosk view. Everything runs behind the local network; nothing calls the public internet.

---

## 2. Stack & versions (authoritative)

| Layer | Choice | Notes |
|---|---|---|
| PHP | 8.4+ | required by Laravel 13 |
| Framework | **Laravel 13.9+** | API + Inertia server |
| Starter kit | **Laravel React Starter Kit** | React 19, TypeScript, Inertia 3, Tailwind 4, shadcn/ui, Vite |
| Auth scaffolding | **Laravel Fortify** (ships with the kit) | extended in DOC-02; RBAC in DOC-03 |
| Frontend | **React 19 + TypeScript** | Inertia pages; NOT a separate SPA |
| UI kit | **shadcn/ui + Radix + Tailwind 4** | components published into `resources/js/components/ui` |
| Realtime | **Laravel Reverb** (self-hosted WebSockets) | cloud Pusher is forbidden (on-prem rule) |
| Queue/cache | **Redis** (preferred) or database driver | queues: `ingest`, `default`, `reports` |
| DB | **MySQL 8+** or **PostgreSQL 15+** | choose one; migrations are portable |
| PDF | `barryvdh/laravel-dompdf` | weekly report + evacuation PDF |
| QR | `simplesoftwareio/simple-qrcode` (or endroid) | equipment labels |
| Excel/CSV | `maatwebsite/excel` or native fputcsv | exports + imports |
| RBAC | `spatie/laravel-permission` | DOC-03 |
| Audit | custom `Auditable` trait/observer | DOC-17 |

**On-premise hard rule (applies to every DOC):** no outbound internet HTTP anywhere — no CDN scripts or fonts, no analytics, no cloud SDKs, no external mail relays except a local SMTP host if configured. All fonts and assets are bundled and served by Vite. CI includes a grep check that fails the build on `https://` references to known CDN/analytics/cloud hosts in shipped code.

**Packages to add on top of the fresh starter kit:**
```bash
composer require spatie/laravel-permission laravel/reverb barryvdh/laravel-dompdf \
  simplesoftwareio/simple-qrcode maatwebsite/excel
# dev
composer require --dev larastan/larastan pestphp/pest laravel/pint --with-all-dependencies
```
```bash
# frontend (recharts for charts, maplibre for the zone map, date-fns, zod for form/runtime typing)
npm i recharts maplibre-gl date-fns zod
```

---

## 3. Architecture — the hybrid model (read carefully, it governs every module doc)

The React starter kit is **Inertia-based**: Laravel controllers return `Inertia::render('Page', $props)` and React renders them as SPA pages. There is **no REST client for the operator UI** — props flow server → page directly. But IR4 also needs machine and public surfaces that cannot speak Inertia. So the app has **three distinct entry surfaces**, and every module doc's "API" section is written against this split:

| Surface | Who | Transport | Routes file | Auth |
|---|---|---|---|---|
| **A. Operator UI** | logged-in staff (operators, managers) | **Inertia pages** (typed props + Inertia forms) | `routes/web.php` | Fortify session + RBAC |
| **B. Device API** | field hardware (readers, cameras, gateways, edge units) | **REST JSON** (`/api/ingest/*`, heartbeats) | `routes/api.php` | `auth.device` token (DOC-08) |
| **C. Public page** | anyone on the LAN scanning a QR | **read-only page** (Inertia or Blade), no login | `routes/web.php` (public group) | none, rate-limited (DOC-13) |

**Consequences carried into every later DOC:**
- Operator reads/writes = **Inertia controller actions** returning `Inertia::render(...)` or `back()`/redirects, using **Inertia form submissions** (not fetch/axios). Validation errors flow back through Inertia's error bag automatically.
- Live data that must update without a full Inertia visit (gas gauges, moving RFID dots, alert toasts, headcount) rides **Reverb WebSocket events**, layered on top of the Inertia page. Inertia provides the initial props; Reverb pushes deltas.
- Device ingestion and the public QR page are the **only** true REST/standalone routes. They use `JsonResource` envelopes (§7) and the error contract (§8).
- Where a module doc says "endpoint," it means an Inertia action unless it lives under `/api/` (device) or the public group.

**Realtime pattern (standard for all live screens):** page loads via Inertia with a snapshot prop → React subscribes to the relevant Reverb channel on mount → incoming events patch local React state → a `LIVE / RECONNECTING` indicator reflects socket status → a polling fallback (`router.reload({ only: [...] })` on an interval) covers socket downtime. Full channel catalog in DOC-08.

---

## 4. Repository layout

The starter kit ships a working tree; we extend it. Final structure:

```
app/
├── Enums/                     # PHP 8 backed enums (mirrored to TS — §6)
├── Models/                    # Eloquent models (+ BaseModel conventions §5)
├── Http/
│   ├── Controllers/
│   │   ├── Web/               # Inertia operator controllers (surface A)
│   │   ├── Api/Ingest/        # device ingestion controllers (surface B)
│   │   └── Public/            # public QR page controller (surface C)
│   ├── Requests/              # FormRequest per write action (§8)
│   ├── Resources/             # JsonResource for API/public payloads (§7)
│   ├── Middleware/            # auth.device, AuditDataAccess, etc.
│   └── Kernel middleware aliases
├── Policies/                  # one per model (DOC-03)
├── Services/                  # business logic (AlertService, TrackingService, …)
├── Actions/                   # optional single-purpose actions for complex writes
├── Jobs/                      # queued work (ingest processing, report gen, exports)
├── Events/                    # broadcast events (Reverb) + domain events
├── Listeners/
├── Observers/                 # created_by, Auditable, side-effects
└── Support/                   # ApiResponse, Concerns, helpers

bootstrap/app.php              # middleware, scheduler (Laravel 11+ style)
config/                        # ir4.php (our settings defaults), permission.php, reverb.php
database/
├── migrations/                # additive, ordered
├── factories/                 # one per model
└── seeders/                   # Role/Permission, Settings, GasThreshold, Asset, Zone, Demo

routes/
├── web.php                    # Inertia operator routes + public group
├── api.php                    # device ingestion + heartbeats
├── channels.php               # Reverb channel authorization
└── console.php                # scheduler entries

resources/
├── js/
│   ├── pages/                 # Inertia page components (mirror route structure)
│   ├── layouts/               # AppSidebar layout, DisplayLayout (kiosk), AuthLayout
│   ├── components/
│   │   ├── ui/                # shadcn/ui primitives
│   │   └── ir4/               # domain components (GaugePanel, GeoZoneMap, AlertToast…)
│   ├── hooks/                 # useReverbChannel, useIdleLogout, usePermissions…
│   ├── lib/                   # api client (for public/device-facing only), utils, formatters
│   └── types/                 # generated + hand-written TS types, enums.ts (§6)
└── css/

tests/
├── Feature/                   # endpoint + scenario tests (DOC-21)
└── Unit/
```

Mapping rule: an Inertia page `resources/js/pages/tracking/workers/index.tsx` is rendered by `App\Http\Controllers\Web\Tracking\WorkerController@index` via `Inertia::render('tracking/workers/index', ...)`. Keep page paths and controller namespaces parallel.

---

## 5. Global data conventions

Applied to **every** domain table and model unless a DOC explicitly overrides.

### 5.1 Table & column conventions
- Primary key: `id` (big integer, auto-increment).
- Timestamps: `created_at`, `updated_at` on every table. **Stored in UTC**, displayed in the configured timezone (default `Asia/Riyadh`, from settings). Never store local time.
- **Soft deletes** (`deleted_at`) on every compliance / evidence / log table (violations, incidents, LSR, alarms, alerts, entry/exit logs, evacuation, reports, equipment + children, workers, portable devices, vehicle violations). Reference tables and pure telemetry may hard-delete only via retention jobs (DOC-19). Hard delete of compliance data happens **only** through the end-of-project wipe command (DOC-19).
- **`created_by`** (`foreignId` → `users.id`, nullable) on every table that a human can write to. Populated automatically by an observer (§5.4). `NULL` = system-generated (path ② — see §9). Machine-decision user columns (`classified_by`, `acknowledged_by`, `logged_by`, `reviewed_by`, `closed_by`) follow the same nullable-FK-to-users pattern.
- Foreign keys use `->constrained()` with an explicit `->cascadeOnDelete()` or `->restrictOnDelete()`. Children of compliance parents use **restrict**; join/telemetry children of hardware use **cascade** only where the parent is itself undeletable-while-referenced.
- Indexes: every FK column is indexed; every column used in a documented list filter or a hot query (`recorded_at`, `detected_at`, `raised_at`, `occurred_at`, status enums) is indexed. High-volume telemetry uses composite indexes specified in DOC-08/09/11.
- **No location/tenant column exists** on any table (standalone — §1). If you ever see `site_id` in a copied snippet, remove it.

### 5.2 Naming
- Tables: plural snake_case (`ppe_violations`, `worker_positions`).
- Columns: snake_case; booleans read as predicates (`is_active`, `is_backfill`, `requires_authorization`, `present`).
- Enums: PHP backed enum class in `App\Enums`, singular PascalCase (`ViolationType`, `AlertSeverity`); values are lower_snake strings.
- Models: singular PascalCase. Services: `<Domain>Service`. Jobs/Events: verb-first PascalCase (`ProcessTagReadings`, `AlertRaised`).
- Inertia routes: kebab/lowercase resource paths (`/tracking/workers`, `/gas/thresholds`).

### 5.3 BaseModel conventions
There is no forced `BaseModel` class, but every model applies these consistently:
```php
final class Worker extends Model
{
    use SoftDeletes;           // if a compliance/log/personnel table
    use HasFactory;

    protected $guarded = ['id'];          // mass-assignment guarded; writes go through FormRequests
    protected function casts(): array
    {
        return [
            'is_active'   => 'boolean',
            'worker_type' => WorkerType::class,   // enum cast
            // dates cast automatically; add explicit datetime casts where needed
        ];
    }
}
```
Rules: models are `final`; use the `casts()` method (Laravel 11+ style); cast every enum column to its enum class and every boolean to `boolean`; declare **both sides** of every relationship (a `hasMany` always has its `belongsTo` counterpart). Strict models are enabled globally (see §11) so missing attributes and lazy loading throw in non-production.

### 5.4 Observers (cross-cutting writes)
- `CreatedByObserver` (bound to all human-writable models): on `creating`, set `created_by = auth()->id()` when a user session exists.
- `AuditableObserver` (DOC-17): records created/updated/deleted diffs for configured models.
Machine/system writes (path ②) run inside services/jobs where no `auth()` user exists, so `created_by` stays NULL — which is exactly the provenance signal we want (§9).

### 5.5 List/query conventions (operator screens & API)
Every index supports: `page`, `per_page` (default 25, max 100), `sort`, `direction` (`asc|desc`), `search`, plus the module-specific filters named in each DOC. Implement via a shared `AppliesListQuery` concern (whitelisted sortable/searchable columns per model — never raw user input into `orderBy`). Inertia index actions pass `filters` back as props so the React table can reflect current state; API indexes return `meta`/`links` (§7).

---

## 6. The enum pattern (PHP ↔ TypeScript mirror)

Every enum is defined **once** in PHP and **mirrored** in TS. They must never drift. This is a hard invariant re-checked in the final sweep (DOC-21).

**PHP (`app/Enums/ViolationType.php`):**
```php
enum ViolationType: string
{
    case MissingHelmet  = 'missing_helmet';
    case MissingVest    = 'missing_vest';
    case MissingHarness = 'missing_harness';
    case MissingMask    = 'missing_mask';

    public function label(): string
    {
        return match ($this) {
            self::MissingHelmet  => 'Missing Helmet',
            self::MissingVest    => 'Missing Vest',
            self::MissingHarness => 'Missing Harness',
            self::MissingMask    => 'Missing Mask',
        };
    }
}
```

**TS mirror (`resources/js/types/enums.ts`):**
```ts
export const ViolationType = {
  MissingHelmet: 'missing_helmet',
  MissingVest: 'missing_vest',
  MissingHarness: 'missing_harness',
  MissingMask: 'missing_mask',
} as const;
export type ViolationType = (typeof ViolationType)[keyof typeof ViolationType];

export const ViolationTypeLabels: Record<ViolationType, string> = {
  missing_helmet: 'Missing Helmet',
  missing_vest: 'Missing Vest',
  missing_harness: 'Missing Harness',
  missing_mask: 'Missing Mask',
};
```

**Keeping them in sync:** provide an Artisan command `php artisan ir4:export-enums` that writes `resources/js/types/enums.ts` from the `App\Enums` namespace (reflection over cases + optional `label()`). Run it in CI; the build fails if the generated file differs from the committed one (`git diff --exit-code`). Hand-editing `enums.ts` is forbidden.

---

## 7. API response envelope & resources (surfaces B & C only)

Operator UI uses Inertia props and needs no envelope. Device API and public page responses use `JsonResource` with a consistent shape.

**Success (single):** the resource object under `data`.
**Success (collection):** `ResourceCollection` → `{ data: [...], meta: { current_page, per_page, total, last_page }, links: {...} }`.
**Helper** (`app/Support/ApiResponse.php`) for non-resource JSON (e.g. ingest results):
```php
ApiResponse::ok(array $data = [], int $status = 200): JsonResponse;
ApiResponse::accepted(array $data): JsonResponse;      // 202 for ingest
ApiResponse::error(string $code, string $message, ?array $details = null, int $status = 400): JsonResponse;
```
Every model that crosses surface B or C has a `JsonResource`. Resources **include relationships explicitly** (`whenLoaded`) and **strip fields by permission where required** (e.g. worker identity — DOC-03/09). Timestamps are serialized ISO-8601 UTC; the client formats to local.

---

## 8. Error contract & validation baseline

### 8.1 Error shape (surfaces B & C)
```json
{ "error": { "code": "VALIDATION_FAILED", "message": "…", "details": { "field": ["…"] } } }
```
Codes & HTTP status: `VALIDATION_FAILED` 422 · `UNAUTHENTICATED` 401 · `FORBIDDEN` 403 · `NOT_FOUND` 404 · `CONFLICT` 409 (blocked delete, duplicate assignment, state-machine violation) · `RATE_LIMITED` 429 · `INGEST_PARTIAL` (inside a 202 body, per-event outcomes — DOC-08). Register a handler in `bootstrap/app.php` that renders these for JSON/api requests; Inertia requests get the framework's standard validation-error redirect behavior instead.

### 8.2 Validation (every write, all surfaces)
- **Every write goes through a dedicated `FormRequest`.** No controller reads `$request->all()` unvalidated. `authorize()` returns the policy check (DOC-03).
- **FormRequests whitelist only human-writable columns.** For mixed machine/human tables, the request may touch *only* the decision/annotation fields — never the measurement fields (this enforces §9 rule 1). Example: a PPE review request accepts `review_status` + `note`, and nothing else.
- Shared rule baseline (used everywhere; a `BaseFormRequest` exposes helpers):
  - names / short labels: `required|string|max:150`
  - free text: `nullable|string|max:5000`
  - **decision fields** (`action_taken`, `corrective_action`, `immediate_action`, `nature_of_incident`, closure/review notes): `required|string|min:10|max:5000`
  - dates: `date` and not more than 24h in the future
  - enums: `Rule::enum(SomeEnum::class)`
  - image uploads: `mimes:jpg,jpeg,png|max:10240` (10 MB)
  - document uploads: `mimes:pdf|max:51200` (50 MB)
- Uploaded files are stored on the **private** disk and served only via short-lived signed URLs (§10).

---

## 9. The three-path data origin model (architectural spine)

Every row is written by exactly one path. This is defined in full in the master spec (Part A0) and enforced by the primitives above; restated here because DOC-01 is where it becomes concrete.

| Path | Written by | Mechanism in this codebase | `created_by` |
|---|---|---|---|
| **① Device (machine)** | field hardware | `/api/ingest/*` controllers → services; `auth.device` token; batch + idempotent (DOC-08) | NULL |
| **② System (derived)** | services, jobs, correlators, scheduler | plain service/job code, no `auth()` user | NULL |
| **③ User (manual)** | operators via Inertia | Web controller + FormRequest + policy + CreatedBy/Audit observers | user id |

**Enforced invariants (checked in DOC-21 sweep):**
1. **Machine truth is immutable.** No FormRequest and no Inertia/REST route exposes an update to a measurement/detection field (reading values, `detected_at`, `snapshot_path`, `confidence`, frozen report data). Users annotate via separate columns only.
2. **Human judgment is always manual.** `action_taken`, `corrective_action`, classification, acknowledgement, closure, evacuation accounting, inspection outcomes are written only by path ③ with a user id (single documented exception: the PPE false-positive auto-close note, DOC-10).
3. **Provenance is explicit.** Mixed tables expose `source`/`detection_method` enums + nullable user FKs; the UI labels "Detected automatically" vs "Entered by {user}".
4. **No user endpoint writes raw telemetry tables** (readings, ppe_violations). The one human-adjacent exception is the entry/exit manual correction (a *new* row with `source=manual_correction`, never an edit — DOC-09).

---

## 10. File storage

- Two disks in `config/filesystems.php`: `private` (default for all evidence/snapshots/videos/documents/exports) and `public` (bundled static assets only — never user/evidence content).
- Directory conventions: `snapshots/{Y/m/d}/{uuid}.jpg`, `evidence/{incident}/{uuid}.mp4`, `equipment-docs/{equipment}/{uuid}.pdf`, `exports/{type}/{uuid}.{pdf|zip}`, `exports/final/…` (end-of-project).
- Access: never expose a raw path. A `SnapshotStorageService` (and equivalents) issues **15-minute signed URLs**. The public QR page proxies document downloads through server-generated signed URLs it creates per view (DOC-13); it never links a private path directly.
- Retention/pruning of files is handled by DOC-19 jobs, not ad-hoc deletes.

---

## 11. Coding standards & tooling

- **Strict models everywhere:** in a service provider `boot()`, `Model::shouldBeStrict(! app()->isProduction())` — lazy-loading, silently-discarded attributes, and missing attributes throw in dev/test.
- **Static analysis:** Larastan/PHPStan at a high level (target level 6+); Pint for formatting (Laravel preset). Frontend: the kit's TypeScript strict mode + lint; `any` is disallowed in module code (types come from `types/` and generated `enums.ts`).
- **Tests:** Pest. Every write endpoint ships happy + validation + authorization tests; scenario tests per DOC-21. Factories for every model.
- **CI gates (build fails on any):** Pint diff, PHPStan, TS typecheck + lint, `ir4:export-enums` diff, the on-prem external-host grep, and the full test suite.
- **Migrations are additive and ordered**; never edit a run migration. (Relevant even on a fresh build once the first environment exists.)
- Controllers stay thin: they validate (FormRequest), authorize (policy), delegate to a service/action, and return an Inertia render / redirect (A) or `JsonResource`/`ApiResponse` (B/C). Business rules live in services, never in controllers or models.

---

## 12. Settings registry (foundation; full catalog in DOC-18)

Runtime-tunable values live in a `settings` table (`key` unique, `value` json, `updated_by`) with defaults seeded from `config/ir4.php`. A `SettingsService` exposes `get(string $key, mixed $default = null)` and `set(string $key, mixed $value)`; every `set` writes a `config_changed` audit row (DOC-17) and is permission-gated (`manage-settings`). Keys are documented where they are used and consolidated in DOC-18. DOC-01 only establishes the mechanism; representative keys: `timezone`, `session_timeout_minutes`, `stationary_tag_minutes`, `gate_debounce_seconds`, `alert.audible_enabled`, `ppe.min_confidence`, `ingest.rate_per_minute`, `retention.*`, `report.generation_day/time/auto_publish`.

Config vs settings rule: things an operator/manager may change at runtime → `settings` table. Things fixed at deploy (DB creds, Reverb host, queue driver, disk paths) → `.env`/`config`.

---

## 13. Base migration & model scaffolding (copy-paste starting points)

**Migration skeleton** (compliance table shape — adapt columns per module DOC):
```php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('example_records', function (Blueprint $table) {
            $table->id();
            // domain columns here …
            $table->string('status')->index();          // enum-backed
            $table->timestamp('occurred_at')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('example_records'); }
};
```

**Inertia controller skeleton (surface A):**
```php
final class WorkerController
{
    public function index(IndexWorkerRequest $request): Response
    {
        $this->authorize('viewAny', Worker::class);
        $workers = Worker::query()
            ->applyListQuery($request)        // shared concern (§5.5)
            ->with(['activeTag'])
            ->paginate($request->perPage());
        return Inertia::render('tracking/workers/index', [
            'workers' => WorkerResource::collection($workers),
            'filters' => $request->listFilters(),
        ]);
    }

    public function store(StoreWorkerRequest $request, WorkerService $service): RedirectResponse
    {
        $worker = $service->create($request->validated());  // created_by set by observer
        return back()->with('success', "Worker {$worker->name} created.");
    }
}
```

**Device ingest controller skeleton (surface B):**
```php
final class TagReadingController
{
    public function store(IngestTagReadingRequest $request, TrackingService $service): JsonResponse
    {
        $device = $request->device();                        // resolved by auth.device
        $result = $service->ingestReadings($request->validated()['events'], $device);
        return ApiResponse::accepted($result->toArray());    // 202 + per-event outcomes
    }
}
```

---

## 14. What DOC-01 guarantees to later docs

Every subsequent DOC may assume, without restating:
- The stack, versions, repo layout, and the three-surface hybrid architecture (§2–§4).
- Timestamps/soft-deletes/`created_by`/indexing/FK conventions and the strict-model + list-query behavior (§5).
- The single-definition enum pattern with the CI sync check (§6).
- The API envelope + error contract + FormRequest-per-write baseline with decision-field rules (§7–§8).
- The three-path origin model and its four invariants (§9).
- Private-disk + signed-URL file handling (§10).
- The tooling/CI gates and additive-migration rule (§11).
- The settings mechanism (§12) and the base scaffolds (§13).

Each module DOC therefore starts at its own §1 (Purpose) and goes straight to its data model, using these primitives.

---

## 15. Open decisions logged (to confirm; defaults applied)

| # | Decision | Default applied | Confirm in |
|---|---|---|---|
| 1 | DB engine | MySQL 8 (Postgres fully supported) | DOC-20 |
| 2 | Timezone | `Asia/Riyadh` | DOC-18 |
| 3 | Reporting week boundary | Sunday–Saturday | DOC-15 |
| 4 | Public QR page renderer | Inertia (standalone layout) vs Blade | DOC-13 |
| 5 | Map library | MapLibre GL (offline tiles) vs Leaflet | DOC-09/16 |

These do not block DOC-02. Confirm at the referenced DOC.

---

### Next document
**DOC-02 — Authentication:** Fortify configuration, login/logout/session lifecycle, password & lockout policy, idle-timeout, the display-mode non-expiring token, and where the device-auth path (`auth.device`) plugs in — all built on the primitives above.