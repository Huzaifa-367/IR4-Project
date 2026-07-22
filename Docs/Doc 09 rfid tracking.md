# DOC-09 — RFID Personnel Tracking / SSMS

> **Depends on:** DOC-01 (conventions), DOC-03 (`view-tracking`, `view-worker-identity`, `manage-tags`, `manage-workers`, `manage-zones`, `trigger-evacuation`, `manage-evacuation`, `manage-portable-devices`, `view-entry-exit`), DOC-04 (workers), DOC-05 (reader devices), DOC-06 (zones + `resolveZoneAt` bindings + access lists), DOC-07 (alerts that *suggest* records), DOC-08 (tag-reading ingest + tracking channel). **Feeds:** DOC-14 (zone/stationary alerts suggest LSR/incidents; evacuation roster), DOC-15 (manpower report item), DOC-16 (tracking dashboard, map, display headcount).
>
> **Scope:** the Safety-critical Site Monitoring System — turning RFID tag reads into **live positions**, **entry/exit** history, **headcount** and the **zone map**; the **zone rules** that raise alerts (red-zone, unauthorized, occupancy); **stationary-tag** detection and the **worker-down** correlation; **tag lifecycle** (assign/replace/lost, spare pool); the **portable-device register**; and **evacuation** (freeze → muster auto-accounting → close → PDF). **Out of scope:** incidents/LSR themselves (DOC-14 — this module raises the alerts that *suggest* them), the map rendering shell (DOC-16), and worker identity handling (DOC-04, applied here).

---

## 1. Purpose

Give the command centre a live, trustworthy picture of **who is on site, where, and since when** — plus the safety rules that fire when someone is somewhere they shouldn't be, isn't moving, or an emergency demands everyone be accounted for. RFID is **zone-level** (a tag is "in a zone" because a reader covering that zone saw it — DOC-06), not GPS. The definitive count comes from the **gate reader**; movement between work zones comes from **pole readers**.

---

## 2. Data origin (this module spans all three paths cleanly)

- **① device:** tag reads (`/api/ingest/tag-readings`, DOC-08) from pole + gate readers.
- **② system:** everything derived — `worker_positions`, `entry_exit_logs` from gate logic, headcount, zone-rule alerts, stationary detection, worker-down correlation, evacuation roster freeze, muster auto-accounting, the absence sweep, and the `present`/`last_seen_at` mirror on workers (DOC-04).
- **③ user:** tag assignment/replacement, zone/access-list edits (DOC-06), portable-device register, evacuation trigger + manual accounting + close, and entry/exit **manual corrections**. Alerts raised here **suggest** (never create) LSR/incidents — the user authors those in DOC-14.

---

## 3. Data model

### 3.1 `rfid_tags`
```php
Schema::create('rfid_tags', function (Blueprint $table) {
    $table->id();
    $table->string('tag_uid')->unique();                  // EPC Gen2 hex from the reader
    $table->foreignId('worker_id')->nullable()->constrained()->nullOnDelete();
    $table->string('status')->default('in_stock');        // enum TagStatus (§3.7)
    $table->timestamp('assigned_at')->nullable();
    $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();
    $table->index(['status']);
});
```
- **One `assigned` tag per worker** — enforced in `TagService` inside a transaction (not just a DB constraint, because "at most one *active*" is conditional). `in_stock` tags are the spare pool (the proposal's ~20% spares — a quantity, not a hardcoded rule).

### 3.2 `worker_positions` (current state — one row per assigned tag)
```php
Schema::create('worker_positions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tag_id')->unique()->constrained('rfid_tags')->cascadeOnDelete();
    $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
    $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();  // current zone (may be null if unresolved)
    $table->timestamp('last_seen_at');
    $table->boolean('is_on_site')->default(false);        // authoritative presence
    $table->timestamps();
    $table->index(['zone_id']);
    $table->index(['is_on_site']);
});
```
- This is the **fast current-state table** — headcount and the live map read it, never the high-volume readings table. It advances **forward only** (DOC-08 §3.4): a read updates a row only if its `recorded_at` is newer than `last_seen_at`.

### 3.3 `tag_readings` (high-volume history — DOC-08 volume/retention rules)
```php
Schema::create('tag_readings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tag_id')->constrained('rfid_tags')->cascadeOnDelete();
    $table->foreignId('reader_device_id')->constrained('devices')->cascadeOnDelete();
    $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();  // resolved via resolveZoneAt(recorded_at) — snapshotted (DOC-06)
    $table->timestamp('recorded_at')->index();
    $table->timestamp('received_at');
    $table->integer('rssi')->nullable();
    $table->boolean('is_backfill')->default(false);
    $table->boolean('clock_skew')->default(false);
    $table->string('event_uid');
    $table->timestamps();
    $table->unique(['reader_device_id', 'event_uid']);    // idempotency (DOC-08)
    $table->index(['tag_id', 'recorded_at']);
    $table->index(['zone_id', 'recorded_at']);
});
```
- The **resolved `zone_id` is snapshotted** onto each reading at ingest (DOC-06 §8), so history never shifts if a reader is later rebound. Raw tag readings are pruned by age per DOC-19 (default 90 days); positions/entry-exit are kept.

### 3.4 `entry_exit_logs` (soft-deleted, kept forever)
```php
Schema::create('entry_exit_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
    $table->foreignId('tag_id')->nullable()->constrained('rfid_tags')->nullOnDelete();
    $table->foreignId('gate_zone_id')->nullable()->constrained('zones')->nullOnDelete();
    $table->string('direction');                          // enum Direction: in|out
    $table->timestamp('occurred_at')->index();
    $table->string('source')->default('gate_reader');     // enum EntryExitSource: gate_reader|manual_correction|auto_sweep
    $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
    $table->string('correction_note')->nullable();
    $table->timestamps();
    $table->softDeletes();
    $table->index(['worker_id', 'occurred_at']);
});
```

### 3.5 `portable_devices` (SA Restriction of Portable Devices register)
```php
Schema::create('portable_devices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
    $table->string('device_type');                        // phone, tablet, laptop, camera…
    $table->string('make_model')->nullable();
    $table->string('serial_number')->nullable();
    $table->string('approval_reference')->nullable();
    $table->string('status')->default('approved');        // enum: approved|revoked
    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('approved_at')->nullable();
    $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('revoked_at')->nullable();
    $table->string('revoke_reason')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

### 3.6 `evacuation_reports` + `evacuation_report_entries`
```php
Schema::create('evacuation_reports', function (Blueprint $table) {
    $table->id();
    $table->string('status')->default('open');            // enum: open|closed
    $table->timestamp('triggered_at');
    $table->foreignId('triggered_by')->constrained('users');
    $table->timestamp('closed_at')->nullable();
    $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->boolean('force_closed')->default(false);
    $table->string('close_note')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
Schema::create('evacuation_report_entries', function (Blueprint $table) {
    $table->id();
    $table->foreignId('evacuation_report_id')->constrained()->cascadeOnDelete();
    $table->foreignId('worker_id')->constrained();
    $table->foreignId('last_zone_id')->nullable()->constrained('zones')->nullOnDelete();
    $table->timestamp('last_seen_at')->nullable();
    $table->string('muster_status')->default('unaccounted'); // enum: unaccounted|accounted
    $table->timestamp('accounted_at')->nullable();
    $table->string('accounted_source')->nullable();       // enum: muster_reader|gate_exit|manual
    $table->foreignId('accounted_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->unique(['evacuation_report_id', 'worker_id']);
});
```

### 3.7 Enums (PHP backed + TS mirror)
- **`TagStatus`:** `in_stock`, `assigned`, `lost`, `damaged`, `retired`.
- **`Direction`:** `in`, `out`.
- **`EntryExitSource`:** `gate_reader`, `manual_correction`, `auto_sweep`.
- **`PortableDeviceStatus`:** `approved`, `revoked`.
- **`EvacuationStatus`:** `open`, `closed`. **`MusterStatus`:** `unaccounted`, `accounted`. **`AccountedSource`:** `muster_reader`, `gate_exit`, `manual`.

---

## 4. `TrackingService` — the processing core (②)

Entry point from ingest (DOC-08): `ingestReadings(array $events, Device $reader)`. Per event (after DOC-08's dedupe/skew/backfill classification):

### 4.1 Resolve
- `zone = ReaderBindingService::resolveZoneAt($reader, $recorded_at)` (DOC-06). Snapshot the resolved `zone_id` onto the `tag_reading`.
- `tag = RfidTag::firstWhere('tag_uid', $uid)`. If the tag is unknown, or `lost`/`retired`, handle per §7.4 (security alert) — don't create a position.
- If the reader has **no active binding** (unbound), store the reading with `zone_id = null` and skip position/rule logic (nothing to attribute).

### 4.2 Persist & advance state
- Insert the `tag_reading` (idempotent).
- **Live only** (not backfill): upsert `worker_positions` for the tag **iff `recorded_at > last_seen_at`** — set `zone_id`, `last_seen_at`; broadcast a throttled `PositionsUpdated`/`HeadcountUpdated` (DOC-08 §5.3). Update the worker's `present`/`last_seen_at` mirror (DOC-04).

### 4.3 Gate logic (definitive entry/exit)
If the resolved zone is `gate` type:
- Fetch the worker's last gate event.
- **Debounce:** ignore if the last gate event for this worker is within `gate_debounce_seconds` (default 60 — a worker lingering at the gate produces repeated reads; debounce prevents in/out flapping).
- **Toggle:** if currently `is_on_site=false` → create an `in` log, set `is_on_site=true`; if `true` → create an `out` log, set `is_on_site=false`. (Single-reader gate ⇒ direction is state-toggled.)
- Update presence mirror; broadcast headcount.

### 4.4 Zone rules (raise alerts that *suggest* records — DOC-07/14)
On a **zone change** (position moved to a different zone), evaluate:
- **`checkRestrictedZone`** — zone_type `restricted_red` → raise `red_zone_intrusion` (critical, audible, dedupe `redzone:{worker}:{zone}`), payload carries worker + zone → the alert **suggests** "log LSR from this" (DOC-07 §8). Always alerts regardless of access list (DOC-06 §3.3).
- **`checkUnauthorizedAccess`** — zone `requires_authorization` && worker **not** on its access list → raise `unauthorized_zone_access` (warning, dedupe per worker+zone) → suggests LSR. Access-listed workers raise nothing.
- **`checkOccupancy`** — after the move, if the zone's live count > `occupancy_limit` → raise **one** open `zone_occupancy_exceeded` (dedupe `occupancy:{zone}`, auto-resolves when the count drops back under) → suggests LSR (zone-level).
These raise alerts only; **no LSR row is written** until an operator confirms in DOC-14.

### 4.5 Broadcasts
- Throttled `HeadcountUpdated` (`{ total_on_site, by_zone:[{zone_id, count}] }`) and `PositionsUpdated` (changed positions) on the `tracking` channel (DOC-08 §5).

---

## 5. Headcount, positions & the live map (reads)

- **`GET /api/tracking/headcount`** — `{ total_on_site, by_zone[] }` from `worker_positions` (cached ~5 s). Powers the counter + display.
- **`GET /api/tracking/positions`** — active tags with worker (identity-stripped without `view-worker-identity` — DOC-04 §5), zone, last_seen. Powers the map dots. **Identity is stripped at the resource**, so an un-permitted user sees anonymized, stable `Worker #id` dots they can still follow.
- **`GET /api/tracking/coverage`** — current reader↔zone bindings (DOC-06) for the map legend.
- The map rendering (zones as circles from DOC-06 placement, dots per worker) is DOC-16; this module supplies the data.

---

## 6. Stationary tag & worker-down (②, feeds DOC-14)

### 6.1 `checkStationaryTags()` — scheduled every minute (DOC-01 §A8)
- For each assigned, on-site tag: if the tag's zone hasn't changed and there's been no movement for ≥ `stationary_tag_minutes` (default 15), and the zone is not `gate`/`muster_point` → raise `stationary_tag` (warning, dedupe `stationary:{tag}`) with worker, zone, and the **nearest camera** (the camera on the same asset as the reader that last saw the tag) → the alert **suggests** "create incident from this" (DOC-07/14). The operator visually verifies via that camera before deciding.

### 6.2 Worker-down correlation
- If a `fall` event (from `/api/ingest/ppe-violations` with `event_type=fall`, DOC-10) and a `stationary_tag` condition reference the **same zone within 10 minutes**, raise a combined **`worker_down`** (critical, audible) linking both signals ("two independent signals confirming the same situation") → suggests "create incident from this" (prefilled with both). This is the platform's highest-confidence personnel-safety signal.

---

## 7. Tag lifecycle & hygiene

### 7.1 Assignment (③, `manage-tags`)
- `POST /tracking/tags/{tag}/assign {worker_id}` → `TagService::assign`: 409 if the worker already has an `assigned` tag (offer replace, §7.2) or the tag isn't `in_stock`; else set `worker_id`, `status=assigned`, `assigned_at/by`; create the worker's `worker_positions` row (off-site until first gate-in). Audited.
- `POST /tracking/tags/{tag}/unassign` → back to `in_stock`, clears the position row.

### 7.2 Replace (lost/damaged) (③)
- `POST /tracking/workers/{worker}/replace-tag {new_tag_id, old_tag_status: lost|damaged}` → one transaction: old tag → `lost`/`damaged`, new tag → `assigned`, position row re-pointed. Audited. (Real life: a tag is lost mid-shift; the worker must stay tracked.)

### 7.3 Absence sweep — `sweepOffsiteTags()` hourly (②)
- On-site tags unseen for > `tag_offsite_after_hours` (default 14 — longer than any shift) → set `is_on_site=false`, write a synthetic `out` `entry_exit_log` (`source=auto_sweep`, note "auto: tag unseen"), raise a `system` info alert listing affected workers. Corrects headcount when a gate read was missed (worker left via an unread path).

### 7.4 Lost/retired tag reappears (②)
- A `lost`/`retired` tag read again → raise a `system` warning ("retired/lost tag {uid} seen at {zone}") — security-relevant (a tag thought gone is on site). No position created for it.

### 7.5 Worker offboarding interaction (DOC-04)
- A worker with an `assigned` tag cannot be offboarded until the tag is unassigned (DOC-04's atomic "offboard" does this: tag → spare pool, worker deactivated).

---

## 8. Entry/exit corrections (③, `manage-workers`)

- **`POST /tracking/entry-exit/corrections {worker_id, direction, occurred_at, note}`** — creates a **new** `entry_exit_log` with `source=manual_correction`, `corrected_by`, adjusting presence if needed. **Never edits** a gate-generated row (DOC-01 §9 rule; DOC-04 §6.4). Real life: a worker exits through an unreadable path and headcount must be fixed before evacuation accuracy suffers. Audited.
- **`GET /tracking/entry-exit`** — filterable log (worker, direction, source, date range) + CSV export (`view-entry-exit`).

---

## 9. Evacuation (③ trigger + accounting, ② auto-accounting)

The one-click emergency roster. Permissions: `trigger-evacuation` to start, `manage-evacuation` to account/close.

### 9.1 Trigger — `EvacuationService::trigger(User $by)` 
- `POST /tracking/evacuation` → in a transaction, **freeze** every worker with `is_on_site=true` into `evacuation_report_entries` (worker, `last_zone_id`, `last_seen_at`, `muster_status=unaccounted`) → status `open`.
- Raise `evacuation` (critical, audible) → broadcast `EvacuationTriggered` on the `tracking` channel; **every operator UI hard-navigates** to the evacuation page.

### 9.2 Auto-accounting (② while open)
While a report is open, `TrackingService` marks entries accounted **hands-free**:
- A tag read by a reader bound to a `muster_point` zone → that worker's entry `accounted`, `source=muster_reader`.
- A gate `out` read → `accounted`, `source=gate_exit` (they left site).
- Each broadcasts `EvacuationEntryUpdated`.

### 9.3 Manual accounting & close (③)
- `PATCH /tracking/evacuation/{report}/entries/{entry}` → mark accounted, `source=manual`, `accounted_by`.
- `POST /tracking/evacuation/{report}/close` → requires **zero unaccounted**, OR `{force:true, note}` (audited, `force_closed=true`). Sets `status=closed`.
- `GET /tracking/evacuation/{report}/download` → PDF (branded roster: accounted/unaccounted, times, sources).

### 9.4 UI
Big red **Trigger Evacuation** button (confirm dialog) → live two-column board (Unaccounted | Accounted) updating via websocket, a progress bar (`accounted/total`), tap-to-account, force-close dialog, print/PDF.

---

## 10. API / routes summary

| Action | Route | Permission |
|---|---|---|
| Workers CRUD | `/tracking/workers…` | DOC-04 (`manage-workers`/`view-tracking`) |
| Tags list/assign/unassign/replace | `/tracking/tags…` | `manage-tags` (view: `view-tracking`) |
| Zones/access lists/rebind | `/settings/zones…`, `/settings/repositioning` | DOC-06 (`manage-zones`) |
| Headcount / positions / coverage | `GET /api/tracking/{headcount,positions,coverage}` | `view-tracking` |
| Entry/exit + corrections + CSV | `/tracking/entry-exit…` | `view-entry-exit` (correct: `manage-workers`) |
| Portable devices CRUD + revoke | `/tracking/portable-devices…` | `manage-portable-devices` |
| Evacuation trigger/entries/close/pdf | `/tracking/evacuation…` | `trigger-evacuation` / `manage-evacuation` |

All operator screens are Inertia (surface A); the three `GET /api/tracking/*` snapshots are JSON so the live components + poll fallback (DOC-08 §5.4) can fetch them without a full Inertia visit.

**Project-Manager headcount-only variant:** a PM (`view-tracking` but no `view-worker-identity`, and a narrowed policy) gets **only** `/api/tracking/headcount` (totals + per-zone counts) — not `positions` (no map dots) — enforced at the controller/policy, satisfying DOC-03's "headcount only" note.

---

## 11. Frontend (React / Inertia)

- **`pages/tracking/index.tsx`** — TrackingDashboardPage: total-manpower counter, per-zone headcount cards, live map (DOC-16 `GeoZoneMap` component: zone circles + worker dots, dots anonymized without identity permission, reader badges from coverage), all updating via the `tracking` channel with poll fallback + LIVE/RECONNECTING pill.
- **`pages/tracking/tags/index.tsx`** — TagListPage (status filter, spare-pool count), assign/unassign/ReplaceTagDialog.
- **`pages/tracking/entry-exit/index.tsx`** — EntryExitPage (filters, CSV, ManualCorrectionModal).
- **`pages/tracking/portable-devices/index.tsx`** — register + approve/revoke.
- **`pages/tracking/evacuation/{index,show}.tsx`** — trigger + live accounting board + print.
- Worker list/detail are DOC-04's pages (shared).
- **Components (`components/ir4/`):** `GeoZoneMap` (shared with DOC-16), `HeadcountCards`, `TagStatusBadge`, `ReplaceTagDialog`, `EvacuationBoard`, `WorkerDot` (identity-aware).
- **Types (`types/tracking.ts`):** `RfidTag`, `TagStatus`, `WorkerPosition`, `EntryExitLog`, `Direction`, `PortableDevice`, `EvacuationReport`, `EvacuationEntry`, `HeadcountSnapshot`, `PositionSnapshot`, `CoverageBinding`.

---

## 12. Real-life scenarios

- **Normal day:** worker badges in at the gate (`in` log, on-site) → moves through work zones (dots track on the map, headcount live on the 55″) → badges out (`out` log) → the day's peak/average manpower feed the weekly report (DOC-15).
- **Red zone:** worker enters a restricted_red area → critical audible alert with name (or `Worker #id` for un-permitted viewers) → the alert offers "log LSR" → officer verifies, and if warranted creates the LSR (DOC-14) with an action taken.
- **Stationary → possible fall:** a tag is stationary 16 min → `stationary_tag` alert with the nearest camera → operator checks the feed; if a matching `fall` event lands within 10 min, a `worker_down` critical fires → operator creates an incident from the prefilled alert (DOC-14).
- **Evacuation drill:** trigger → 78 frozen on-site → 71 auto-accounted at muster readers, 5 via gate-out, 2 tapped manually → close → PDF archived.
- **Missed gate read:** a worker leaves via an unread path → 14 h absence sweep marks them off-site with an `auto_sweep` correction and flags it for review.
- **Lost tag:** a tag falls off mid-shift → operator uses ReplaceTag (old→lost, new→assigned) so the worker stays tracked; if the lost tag is later read, a security `system` alert fires.
- **Repositioning during outage:** poles move while offline; buffered reads flush as backfill and resolve to the correct historical zones (DOC-06/08) without rewinding the live map.

---

## 13. Tests (this doc's slice of DOC-21)

- **Ingest→position:** a live read updates `worker_positions` and broadcasts (throttled); a backfilled read stores history but does **not** rewind the position (DOC-08 integration).
- **Gate logic:** first gate read → `in`+on-site; second (after debounce) → `out`; a read within `gate_debounce_seconds` is ignored (no flapping).
- **Zone rules:** entering `restricted_red` raises `red_zone_intrusion` (always, ignoring access list) and **creates no LSR**; entering a `requires_authorization` zone unlisted raises `unauthorized_zone_access`, listed raises nothing; exceeding `occupancy_limit` raises **one** open `occupancy` alert that auto-resolves when the count drops.
- **Stationary/worker-down:** a tag idle ≥ threshold raises `stationary_tag` with nearest camera; a fall + stationary in the same zone within 10 min raises `worker_down`; none of these create incidents (only suggest).
- **Tag lifecycle:** assign 409 when worker already tagged; replace-tag transaction (old→lost, new→assigned) keeps tracking; unassign clears position; lost tag re-read raises a security alert; assigning a non-`in_stock` tag → 409.
- **Absence sweep:** on-site tag unseen > threshold → off-site + `auto_sweep` out log + info alert.
- **Entry/exit correction:** creates a new `manual_correction` row (never edits a gate row); adjusts presence; audited; CSV export works.
- **Evacuation:** trigger freezes exactly the on-site set into entries; muster-reader read auto-accounts; gate-out auto-accounts; manual tap accounts; close blocked while unaccounted unless `force`+note (audited); PDF generates.
- **Identity:** `positions` strips identity without `view-worker-identity` (resource-level); PM gets headcount-only (no positions).
- Authorization: each action gated by its permission (matrix).

---

## 14. Open decisions logged

| # | Decision | Default | Confirm in |
|---|---|---|---|
| 1 | Gate debounce window | 60 s | DOC-18 |
| 2 | Stationary threshold | 15 min | DOC-18 |
| 3 | Absence-sweep threshold | 14 h | DOC-18 |
| 4 | Worker-down correlation window | 10 min | DOC-18 |
| 5 | Multi-reader gates (direction not just toggled) | single-reader toggle model | this doc |
| 6 | Position history beyond raw readings (a positions audit trail) | current-state only + readings history | DOC-19 |

---

### Next document
**DOC-10 — PPE Compliance:** the anonymous PPE-violation stream from the camera AI (shared `ppe-violations` endpoint, DOC-08), the false-positive review workflow, trends/exports, the live wall, and how a violation can be *linked* into a user-created LSR (DOC-14) — with the privacy invariant that PPE violations never carry worker identity.