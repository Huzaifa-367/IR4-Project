# DOC-04 — Workers / Employees

> **Depends on:** DOC-01 (conventions, enum pattern, resources, list queries), DOC-02 (Users are a separate concept), DOC-03 (`view-worker-identity`, `manage-workers`, field-stripping). **Feeds:** DOC-09 (RFID tracking hangs off workers), DOC-14 (incident personnel + RFID-sourced LSR), DOC-09 evacuation, and the weekly report manpower item (DOC-15).
>
> **Scope:** the **Worker** domain — the master registry of tracked site personnel, its schema, enums, identity privacy handling, lifecycle/state rules, the CRUD + bulk-import surfaces, the relationships every downstream module attaches to, and the operator UI. **Out of scope:** RFID tags/positions themselves (DOC-09 owns `rfid_tags`, `worker_positions`, `tag_readings`, zones, entry/exit, portable devices, evacuation) — this doc defines the Worker they all reference and the tag *relationship*, not the tag mechanics.

---

## 1. Purpose & the Worker↔User distinction (non-negotiable)

A **Worker** is a person tracked on the ground — someone wearing an RFID tag whose presence, zone, and safety events the platform records. A **User** (DOC-02/03) is a login account operating the software. They are different tables, different lifecycles, and never the same row. This separation is a hard invariant checked in DOC-21.

| | **Worker** (this doc) | **User** (DOC-02/03) |
|---|---|---|
| Table | `workers` | `users` |
| Represents | tracked personnel on site | software operator |
| Authenticates? | **never** — holds no credentials | yes (email + password) |
| Identified by | RFID tag UID + badge number | email |
| Has roles/permissions? | no | yes |
| Created by | an operator with `manage-workers`, or bulk import | an admin with `manage-users` |
| Referenced by | tags, positions, entry/exit, incidents, LSR, evacuation, portable devices | `created_by`/`classified_by`/`logged_by` on records |
| Deleted when | offboarded from the workforce | leaves the SCC team |

**Foreign-key rule (applies everywhere):** `worker_id` always points to `workers`; `created_by` / `classified_by` / `logged_by` / `reviewed_by` / `acknowledged_by` always point to `users`. No table ever conflates them. An incident, for example, has `worker`-typed personnel *and* a `classified_by` user — two distinct FKs.

---

## 2. Data origin

Workers are a **path ③ (user input)** domain — the registry is maintained by operators (create/edit/import). Downstream, path ① (device) tag reads and path ② (system) derivations *reference* workers but never create them. Nothing about a worker is machine-written except denormalized convenience state that mirrors DOC-09 (e.g. a cached `present` flag, §4.4), which is derived by services, not by users.

---

## 3. Data model

### 3.1 `workers` table
```php
Schema::create('workers', function (Blueprint $table) {
    $table->id();
    $table->string('name');                                  // identity — permissioned (§5)
    $table->string('employee_code')->nullable()->unique();   // internal/company id if any
    $table->string('badge_number')->nullable()->unique();    // physical badge id
    $table->string('contractor');                            // employing company / contractor name
    $table->string('role_title')->nullable();                // job title on site (e.g. "Rigger")
    $table->string('worker_type');                           // enum WorkerType (§3.3)
    $table->string('phone')->nullable();
    $table->string('photo_path')->nullable();                // private disk; identity — permissioned
    $table->text('notes')->nullable();
    $table->boolean('is_active')->default(true);             // active in the workforce
    // denormalized presence mirror (authoritative source = worker_positions, DOC-09)
    $table->boolean('present')->default(false);              // currently on site (derived)
    $table->timestamp('last_seen_at')->nullable();           // derived from tracking
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();
    $table->index(['contractor']);
    $table->index(['worker_type']);
    $table->index(['is_active', 'present']);
});
```

Notes:
- **Identity fields** (`name`, `photo_path`, and arguably `badge_number`/`phone`) are subject to `view-worker-identity` stripping (§5).
- `present` and `last_seen_at` are **denormalized mirrors** of the authoritative state in `worker_positions` (DOC-09). They exist so worker lists/filters and the manpower report don't join the high-volume positions table on every query. DOC-09's `TrackingService` updates them; nothing else writes them. If they ever disagree with `worker_positions`, positions win and a reconcile job corrects the mirror.
- Soft deletes: offboarding is a soft delete (or deactivation, §6) so historical incident/LSR/entry-exit references stay intact.
- `contractor` is a plain string in v1. Whether it becomes its own `contractors` reference table is `[CONFIRM AT DESIGN]` (§10) — if the client wants per-contractor reporting/rollups, we normalize it.

### 3.2 Relationships (declared both directions — DOC-01 §5.3)
```php
// Worker
public function activeTag(): HasOne          // rfid_tags where status = assigned  (DOC-09)
public function tags(): HasMany              // all tags ever assigned (history)
public function position(): HasOne           // worker_positions (current)          (DOC-09)
public function entryExitLogs(): HasMany     //                                     (DOC-09)
public function portableDevices(): HasMany   //                                     (DOC-09)
public function incidentInvolvements(): HasMany   // incident_personnel pivot rows  (DOC-14)
public function lsrViolations(): HasMany      // RFID-sourced LSR rows (worker_id)  (DOC-14)
public function evacuationEntries(): HasMany  //                                     (DOC-09)
public function equipmentCheckouts(): HasMany // equipment currently/previously held (DOC-13)
public function creator(): BelongsTo          // users
```
Downstream tables own the FK and its reverse `belongsTo Worker`; this doc guarantees the Worker side is declared so eager-loading works from either direction.

### 3.3 Enum — `WorkerType`
PHP backed enum + TS mirror (DOC-01 §6):
| case | value | meaning |
|---|---|---|
| Employee | `employee` | direct company employee |
| Contractor | `contractor` | third-party contractor personnel (default) |
| Visitor | `visitor` | temporary visitor (may have shorter tag lifecycle / stricter zone rules) |

`worker_type` drives some downstream behavior (e.g. visitors may be excluded from certain reports or flagged in restricted zones — those rules live in the consuming DOCs, referenced by this enum).

---

## 4. Presence, identity, and derived state

### 4.1 Presence (`present`, `last_seen_at`)
Authoritative presence lives in `worker_positions.is_on_site` (DOC-09). This doc's mirror is updated by `TrackingService` whenever a worker's on-site state flips (gate in/out, absence sweep). Consumers that only need "is this worker on site / where were they last seen" read the mirror; anything needing live zone/position reads DOC-09 directly.

### 4.2 The worker↔tag relationship (owned by DOC-09, summarized here)
- A worker has **at most one `assigned` tag** at a time (`activeTag`). The assignment/lifecycle (assign, unassign, replace, lost/damaged, 20% spare pool) is DOC-09.
- The worker detail page surfaces the active tag and tag history but delegates all tag mutations to DOC-09 endpoints.
- A worker with no assigned tag is valid (just-onboarded, or between tags) — they simply won't be tracked until a tag is assigned.

### 4.3 Identity is the sensitive axis
`name` and `photo_path` (and optionally `badge_number`, `phone`) are personal identity. Per DOC-03, viewing them requires `view-worker-identity`. This matters because tracking data + identity together is privacy-sensitive; the platform can run in an anonymized mode for roles that should see headcount/positions but not *who* (§5).

### 4.4 Who writes what (provenance)
- Operators (③): every editable field.
- `TrackingService` (②): `present`, `last_seen_at` only.
- Nothing else writes `workers`.

---

## 5. Identity privacy & `view-worker-identity` (field-stripping, authoritative here)

DOC-03 introduced the permission; DOC-04 defines exactly how the Worker payload is stripped, because Worker is the identity source.

### 5.1 What is identity vs non-identity
- **Identity (stripped without `view-worker-identity`):** `name`, `photo_path`, `phone`, `badge_number`, `employee_code`.
- **Non-identity (always visible with `view-tracking`):** `id`, `contractor`, `role_title`, `worker_type`, `present`, `last_seen_at`, zone/position (DOC-09).

Rationale: contractor + role + presence supports safety operations (how many riggers from ACME are in Zone 3) without exposing individuals.

### 5.2 Enforcement at the resource level (not just UI)
`WorkerResource` (and any resource/prop embedding worker data — positions, incident personnel, evacuation entries) applies stripping in the **resource**, keyed off the current user's permission:
```php
public function toArray($request): array
{
    $canSeeIdentity = $request->user()?->can('view-worker-identity') ?? false;
    return [
        'id'          => $this->id,
        'contractor'  => $this->contractor,
        'role_title'  => $this->role_title,
        'worker_type' => $this->worker_type,
        'present'     => $this->present,
        'last_seen_at'=> $this->last_seen_at,
        // identity — replaced with a stable anonymized label when not permitted
        'name'         => $canSeeIdentity ? $this->name : "Worker #{$this->id}",
        'badge_number' => $canSeeIdentity ? $this->badge_number : null,
        'photo_url'    => $canSeeIdentity && $this->photo_path
                            ? SnapshotStorage::signedUrl($this->photo_path) : null,
        'phone'        => $canSeeIdentity ? $this->phone : null,
        'employee_code'=> $canSeeIdentity ? $this->employee_code : null,
    ];
}
```
- The anonymized label is **stable** (`Worker #<id>`) so an un-permitted user can still follow a specific dot on the map across updates without learning who it is.
- Because stripping is in the resource, a crafted request, an export, or a websocket payload all get the same treatment — there is no endpoint that leaks identity to a role lacking the permission.
- Photo is served only as a 15-min signed URL and only when permitted (DOC-01 §10).

### 5.3 Search & filter under anonymization
A user without `view-worker-identity` cannot search by name (the field isn't returned and name-search is disabled for them server-side); they can still filter by contractor, worker_type, and presence. Prevents name-enumeration via the search box.

---

## 6. Lifecycle & state rules

### 6.1 States
A worker is `is_active` (in the workforce) or not; orthogonally `present` (on site) or not. Combined with soft-delete, the meaningful states:
- **Active, off site** — normal between shifts.
- **Active, on site** — currently tracked.
- **Inactive** — offboarded but retained for history (soft-deleted or `is_active=false`, §6.4).

### 6.2 Onboarding
`manage-workers` operator creates the worker (name, contractor, role, type, optional badge/photo). No tag yet is fine. Then DOC-09 assigns a tag to begin tracking.

### 6.3 Editing
All fields editable by `manage-workers`. Editing identity fields is audited (DOC-17) since it's personal data. `present`/`last_seen_at` are not user-editable (derived).

### 6.4 Offboarding (deactivate vs delete)
Prefer **deactivation** over deletion to preserve audit/history integrity:
- **Guard:** a worker who is currently `present` (on site) **cannot** be deactivated or deleted → 409 `CONFLICT` ("worker is on site; ensure they've exited / correct their entry-exit first"). This protects headcount and evacuation accuracy.
- **Guard:** a worker with an `assigned` tag must have the tag unassigned first (DOC-09) → 409 otherwise. The UI offers a combined "offboard" action that unassigns the tag (marking it back to the spare pool) and then deactivates, in one audited transaction.
- **Guard:** a worker with any **open equipment checkout** must return (or reassign) those items first (DOC-13) → 409 otherwise. The offboard flow surfaces "N items still checked out."
- **Deactivation** (`is_active=false`) keeps the row and all references intact; the worker vanishes from active lists but their historical incidents/LSR/entry-exit remain linked and reportable.
- **Soft delete** is reserved for genuine data-entry mistakes / GDPR-style removal; it hides the worker but keeps FK integrity via `nullOnDelete` on downstream references where configured. Actual hard removal only via the end-of-project wipe (DOC-19).

### 6.5 Reactivation
A deactivated worker can be reactivated by `manage-workers` (audited) and re-issued a tag.

---

## 7. API / actions

Worker management is **operator UI = Inertia** (surface A, DOC-01 §3). All writes require `manage-workers`; reads require `view-tracking` (identity gated by `view-worker-identity`).

| Action | Route (Inertia unless noted) | Controller@method | Permission | Notes |
|---|---|---|---|---|
| List | GET `/tracking/workers` | `Web\Tracking\WorkerController@index` | view-tracking | filters §7.1; identity-stripped resource |
| Detail | GET `/tracking/workers/{worker}` | `@show` | view-tracking | tabs: profile, active tag + history (DOC-09), portable devices (DOC-09), entry/exit history (DOC-09), incident involvements (DOC-14) |
| Create | POST `/tracking/workers` | `@store` | manage-workers | `StoreWorkerRequest` |
| Update | PUT `/tracking/workers/{worker}` | `@update` | manage-workers | `UpdateWorkerRequest`; identity edits audited |
| Deactivate | POST `/tracking/workers/{worker}/deactivate` | `@deactivate` | manage-workers | 409 guards §6.4 |
| Reactivate | POST `/tracking/workers/{worker}/reactivate` | `@reactivate` | manage-workers | |
| Offboard (unassign tag + deactivate) | POST `/tracking/workers/{worker}/offboard` | `@offboard` | manage-workers | one transaction |
| Delete (soft) | DELETE `/tracking/workers/{worker}` | `@destroy` | manage-workers | 409 guards; discouraged vs deactivate |
| Bulk import | POST `/tracking/workers/import` | `@import` | manage-workers | §8 |
| Import template | GET `/tracking/workers/import/template` | `@template` | manage-workers | CSV template download |

**FormRequest rules (`StoreWorkerRequest` / `UpdateWorkerRequest`):**
- `name` required|string|max:150
- `contractor` required|string|max:150
- `worker_type` required, `Rule::enum(WorkerType::class)`
- `role_title` nullable|string|max:150
- `badge_number` nullable|string|max:100|unique (ignore self on update)
- `employee_code` nullable|string|max:100|unique (ignore self)
- `phone` nullable|string|max:40
- `photo` nullable|image|mimes:jpg,jpeg,png|max:10240 (stored private)
- `notes` nullable|string|max:5000
- Requests whitelist **only** these human fields — never `present`/`last_seen_at` (derived; DOC-01 §8 rule).

### 7.1 List filters
`contractor`, `worker_type`, `is_active` (default true), `present` (on-site toggle), `has_tag` (assigned/none), plus standard `search` (name — only for identity-permitted users; otherwise contractor/role only), `sort`, `direction`, `page`, `per_page`.

---

## 8. Bulk import (commissioning & ongoing intake)

Real life: at mobilization the safety team receives worker rosters as spreadsheets (often per contractor). Manual entry of ~100 workers is error-prone, so `manage-workers` gets a CSV import.

- **Endpoint:** POST `/tracking/workers/import` (multipart CSV) → queued `ImportWorkersJob` on the `default` queue → row-level result report (created, skipped-duplicate, errored-with-reason) surfaced back on the import page.
- **CSV columns:** `name` (req), `contractor` (req), `worker_type` (req: employee|contractor|visitor), `role_title`, `badge_number`, `employee_code`, `phone`, `notes`. Header row required; template downloadable.
- **Validation:** each row validated as if through `StoreWorkerRequest`; unique `badge_number`/`employee_code` enforced within the file and against existing rows. Invalid rows are reported, valid rows still import (partial success — never all-or-nothing).
- **Idempotency:** re-importing the same roster matches on `badge_number` or `employee_code` (if present) and **updates** rather than duplicating; rows with no stable key and a matching name+contractor are flagged for the operator to confirm rather than silently duplicated `[CONFIRM AT DESIGN]`.
- **No tag assignment in import** — import creates the registry; tags are assigned separately (DOC-09), matching real workflow (badges/tags issued at the gate).
- Every import writes an audit summary row (count created/updated, file name, user).

---

## 9. Frontend (React / Inertia)

- **`pages/tracking/workers/index.tsx`** — WorkerListPage: filterable/sortable table (contractor, type, present badge, has-tag badge, active toggle), search box (name-search hidden for non-identity users), row actions (view, edit, offboard), "Add Worker" and "Import" buttons. Identity columns render `Worker #id` placeholders when the user lacks `view-worker-identity`.
- **`pages/tracking/workers/show.tsx`** — WorkerDetailPage: profile card (photo when permitted), tabs — Active Tag & history (DOC-09 component), Portable Devices (DOC-09), Entry/Exit history (DOC-09), Incident involvements (DOC-14), Notes. Offboard / deactivate / reactivate actions with confirm dialogs and the 409-guard messaging surfaced inline.
- **`pages/tracking/workers/import.tsx`** — ImportPage: template download, file picker, result table (created/updated/skipped/errored with reasons).
- **Components (`components/ir4/`):** `WorkerForm`, `WorkerIdentityCell` (handles the anonymized-label logic once, reused in tables and pickers), `WorkerPicker` (used by incident classification DOC-14 and zone access lists DOC-09 — also identity-aware).
- **Types (`types/worker.ts`):** `Worker`, `WorkerType` (from `enums.ts`), `WorkerListFilters`, `WorkerImportResult`. `Worker.name` is typed `string` but may contain the anonymized label — components must not assume it's a real name for anything except display.
- Cache invalidation: creating/updating/offboarding invalidates the workers list + the specific worker query (DOC-01 §5.5 / Inertia partial reloads).

---

## 10. Linkage map (how Worker connects to everything)

| Related entity | Direction | FK location | Owning DOC | Notes |
|---|---|---|---|---|
| RFID tag (active + history) | worker 1—* tags, 1—1 active | `rfid_tags.worker_id` | DOC-09 | one assigned tag at a time |
| Worker position (live) | worker 1—1 | `worker_positions.worker_id` | DOC-09 | authoritative presence/zone |
| Entry/exit logs | worker 1—* | `entry_exit_logs.worker_id` | DOC-09 | gate history |
| Portable devices | worker 1—* | `portable_devices.worker_id` | DOC-09 | approval register |
| Zone access authorizations | worker *—* zones | `zone_access_lists.worker_id` | DOC-09 | permitted restricted zones |
| Incident personnel | worker *—* incidents | `incident_personnel.worker_id` | DOC-14 | involved/witness/present_in_zone |
| LSR violations (RFID-sourced) | worker 1—* | `lsr_violations.worker_id` (nullable) | DOC-14 | NULL for camera/PPE-sourced (anonymous) |
| Evacuation entries | worker 1—* | `evacuation_report_entries.worker_id` | DOC-09 | one per open evac |
| Equipment checkouts | worker 1—* | `equipment_checkouts.worker_id` | DOC-13 | items the worker holds/held; open checkout blocks offboarding |
| Manpower report | aggregate | via entry/exit + presence | DOC-15 | peak/average headcount |

Every one of these FKs targets `workers.id`, never `users.id`. This table is the contract each downstream DOC honors.

---

## 11. Real-life scenarios

- **Mobilization:** safety team imports three contractor rosters (~90 workers) via CSV → registry populated with contractor/type/badge → over the next day, as workers arrive, DOC-09 assigns tags at the gate → tracking begins.
- **Anonymized oversight:** a Project Manager (no `view-worker-identity`) opens tracking → sees "18 on site, 6 ACME riggers in Work Front A" and dots on the map labeled `Worker #…` → cannot see names or search by name → sufficient for oversight, private by design.
- **Incident involves a worker:** a fall auto-opens an incident (DOC-14) capturing the RFID zone roster → those workers appear as `present_in_zone` personnel (identity shown only to permitted classifiers) → the worker's detail page later shows the involvement under its Incidents tab.
- **Offboarding done right:** a contractor demobilizes → operator hits "Offboard" on each worker → system refuses any worker still on site (409) until their exit is recorded → for the rest, the tag returns to the spare pool and the worker is deactivated in one audited step → historical incidents/LSR remain intact and reportable.
- **Badge reuse:** re-importing an updated roster matches on `badge_number` and updates role_title without creating duplicates.

---

## 12. Tests (this doc's slice of DOC-21)

- CRUD: create/update/deactivate/reactivate happy paths; unique `badge_number`/`employee_code` enforced (create + update ignoring self).
- **Identity stripping:** `WorkerResource` returns real `name`/photo for a user with `view-worker-identity`; returns stable `Worker #id` + null photo/badge/phone without it — asserted on the index, show, **and** an embedded context (e.g. a position payload) to prove resource-level (not UI) enforcement. Name-search disabled for non-identity users.
- **Provenance:** `present`/`last_seen_at` cannot be set via the store/update request (ignored/blocked); only `TrackingService` writes them (unit test).
- **Lifecycle guards:** deactivating/deleting a worker who is `present` → 409; a worker with an assigned tag → 409 until unassigned; `offboard` performs unassign+deactivate atomically and is audited.
- **Import:** valid rows import while invalid rows are reported (partial success); duplicate badge within file and against DB handled; re-import updates rather than duplicates; import writes an audit summary; import never assigns tags.
- **Worker≠User invariant:** no code path lets a Worker authenticate; `worker_id` FKs resolve to `workers`, `created_by`/`classified_by` resolve to `users` (schema test).
- Authorization: every write requires `manage-workers`; reads require `view-tracking`; identity requires `view-worker-identity` (403/stripping as appropriate).

---

## 13. Open decisions logged

| # | Decision | Default | Confirm in |
|---|---|---|---|
| 1 | `contractor` as string vs its own reference table | string (v1) | this doc / DOC-15 if per-contractor rollups needed |
| 2 | Which fields count as "identity" (include phone/badge?) | name, photo, phone, badge, employee_code | this doc / DOC-03 |
| 3 | Import dedupe when no stable key | flag name+contractor matches for confirmation | this doc |
| 4 | Deactivate vs soft-delete as the standard offboard | deactivate (soft-delete reserved for corrections/removal) | this doc |

---

### Next document
**DOC-05 — Assets & Hardware Registry:** the vehicles, poles, gate, cameras, and devices that every ingest source and live view depends on — including device token issuance (the `auth.device` credential from DOC-02) and heartbeats, all global to the single standalone instance.