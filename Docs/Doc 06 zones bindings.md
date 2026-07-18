# DOC-06 — Zones, Reader Bindings & the Repositioning Model

> **Depends on:** DOC-01 (conventions, enums, settings), DOC-03 (`manage-zones`, `view-tracking`), DOC-04 (workers referenced by access lists), DOC-05 (RFID reader devices + `is_mobile` assets). **Feeds:** DOC-09 (all tracking logic resolves a reading's zone through these bindings; zone rules; occupancy), DOC-14 (height-work zones drive the height-harness cross-check; incident RFID zone snapshots), DOC-16 (the live map).
>
> **Scope:** the **logical zone** model, the **time-aware reader↔zone binding** that lets mobile poles move without corrupting history, the **repositioning workflow**, **zone access lists** (who is authorized in restricted zones), and **map-placement** data. **Out of scope:** tag reads and position derivation (DOC-09 consumes bindings), and the physical reader hardware itself (DOC-05).

---

## 1. Purpose & the core problem this solves

RFID gives **zone-level** presence, not GPS coordinates: a tag is "in a zone" because a reader that covers that zone saw it. So the platform needs a mapping from **reader → zone**. The complication: **poles are mobile** (`is_mobile`, DOC-05) and can be relocated as the work front advances, which means the same physical reader covers **different zones at different times**.

If that mapping were a simple `reader.zone_id` column, relocating a pole would silently rewrite history — a tag read recorded last week would suddenly appear to have happened in this week's zone. That is unacceptable for compliance evidence (an incident's "who was in the zone" snapshot must reflect the zone as it was *at that moment*).

**The solution: time-aware bindings.** A reader is bound to a zone over a time interval (`bound_from … bound_until`). Exactly one binding is open (current) per reader at a time. When a pole moves, the old binding is closed and a new one opened. Any tag read is attributed to the zone whose binding was **active at the read's `recorded_at`** — so history stays correct forever, even for readings that arrive late (backfill after a connectivity outage, DOC-08).

**Zones are logical, not physical.** A zone is a named area the safety team defines ("Work Front A", "Laydown", "Muster Point 1", "Gate"). It is not tied to a specific reader — readers are *bound* to zones and can be rebound. This indirection is what makes repositioning safe.

---

## 2. Data origin

- **③ user:** all of it — creating/editing zones, binding/rebinding readers, editing access lists, placing zones on the map. Requires `manage-zones` (map view requires `view-tracking`).
- **② system:** the only system writes are convenience — e.g. resolving the active binding for a read (DOC-09) reads these tables but doesn't mutate them. Occupancy counts, rule evaluations, etc. live in DOC-09.
- **① device:** none directly — readers send tag reads (DOC-08/09); the reader↔zone mapping is operator-defined here.

---

## 3. Data model

### 3.1 `zones`
```php
Schema::create('zones', function (Blueprint $table) {
    $table->id();
    $table->string('name');                               // "Work Front A", "Main Gate", "Muster Point 1"
    $table->string('zone_type');                          // enum ZoneType (§3.4)
    $table->boolean('requires_authorization')->default(false); // if true, only access-listed workers may enter without an alert
    $table->unsignedInteger('occupancy_limit')->nullable();     // null = no limit; else occupancy alert threshold (DOC-09)
    // map placement (DOC-16) — a simple circle/point on a schematic site map (not GPS)
    $table->decimal('map_x', 8, 2)->nullable();
    $table->decimal('map_y', 8, 2)->nullable();
    $table->decimal('map_radius', 8, 2)->nullable();
    $table->string('color')->nullable();                  // display color on the map
    $table->boolean('is_active')->default(true);
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();
    $table->index(['zone_type', 'is_active']);
});
```

### 3.2 `reader_zone_bindings` (the time-aware mapping)
```php
Schema::create('reader_zone_bindings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();  // rfid_reader only
    $table->foreignId('zone_id')->constrained()->restrictOnDelete();
    $table->timestamp('bound_from');                      // interval start
    $table->timestamp('bound_until')->nullable();         // null = currently open/active binding
    $table->foreignId('bound_by')->nullable()->constrained('users')->nullOnDelete();
    $table->string('note')->nullable();                   // e.g. "moved to north edge, work front advanced"
    $table->timestamps();
    $table->index(['device_id', 'bound_until']);          // fast "current binding for reader"
    $table->index(['device_id', 'bound_from', 'bound_until']); // fast "binding active at time T"
});
```
**Invariants (enforced in `ReaderBindingService`, not just DB):**
- **At most one open binding per reader** (`bound_until IS NULL`). Opening a new one first closes the current one.
- Intervals for a given reader are **non-overlapping and contiguous** — the new binding's `bound_from` equals the old one's `bound_until` (the moment of rebind). No gaps, no overlaps, so every point in time maps to exactly one zone per reader.
- A reader (device of type `rfid_reader`) may have **zero** bindings (just registered, not yet placed) — until bound, its reads can't resolve a zone and are handled per DOC-09's "unbound reader" rule (stored, flagged, no zone).

### 3.3 `zone_access_lists` (authorized personnel for restricted zones)
```php
Schema::create('zone_access_lists', function (Blueprint $table) {
    $table->id();
    $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
    $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
    $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('authorized_at')->nullable();
    $table->timestamps();
    $table->unique(['zone_id', 'worker_id']);
});
```
- Meaningful only when the zone's `requires_authorization = true`. A worker **on** the list may enter without triggering an `unauthorized_zone_access` alert (DOC-09); a worker **not** on the list who enters triggers one.
- `restricted_red` zones are a stricter case: entry is alerted regardless of the list (DOC-09 §checkRestrictedZone) — the access list still records who is *permitted* for reporting, but a red-zone entry always alerts. `[CONFIRM AT DESIGN]` whether access-listed workers suppress red-zone alerts or only unauthorized-zone alerts; default: access list governs `requires_authorization` zones; `restricted_red` always alerts.

### 3.4 Enum — `ZoneType`
PHP backed enum + TS mirror:
| case | value | behavior it drives (in DOC-09/14) |
|---|---|---|
| Work | `work` | normal work area; counts toward headcount, occupancy if limited |
| Gate | `gate` | entry/exit boundary; a read here toggles on-site/off-site (DOC-09 gate logic) |
| RestrictedRed | `restricted_red` | red zone — any entry raises a critical alert + automated LSR (DOC-09/14) |
| HeightWork | `height_work` | working-at-height area — drives the height-without-harness cross-check (DOC-14 §G4) |
| MusterPoint | `muster_point` | evacuation assembly point — reads here auto-account workers during an evacuation (DOC-09 §D5) |
| Laydown | `laydown` | material/equipment laydown area |
| Other | `other` | any other logical area |

### 3.5 Relationships
```php
// Zone
public function currentBindings(): HasMany       // reader_zone_bindings where bound_until IS NULL
public function bindings(): HasMany              // all bindings (history)
public function accessList(): HasMany            // zone_access_lists
public function authorizedWorkers(): BelongsToMany // via zone_access_lists
// ReaderZoneBinding
public function reader(): BelongsTo              // devices
public function zone(): BelongsTo
// Device (rfid_reader) — reverse declared in DOC-05
public function zoneBindings(): HasMany
public function currentZoneBinding(): HasOne     // bound_until IS NULL
```

---

## 4. The binding service (authoritative resolution)

`ReaderBindingService` owns all binding mutations and the resolution used by DOC-09.

### 4.1 `bind(Device $reader, Zone $zone, Carbon $effectiveAt, User $by, ?string $note): ReaderZoneBinding`
- Validates the device is an `rfid_reader` (else 422).
- In a transaction: close the reader's current open binding by setting its `bound_until = $effectiveAt`; create a new binding `bound_from = $effectiveAt`, `bound_until = null`, `zone_id`, `bound_by`, `note`.
- `effectiveAt` defaults to `now()`; an operator may set it slightly in the past if they're recording a move that already happened, but never before the current binding's `bound_from` (422) and never in the future beyond a small tolerance `[CONFIRM AT DESIGN]`.
- Writes a `config_changed` audit row.

### 4.2 `resolveZoneAt(Device $reader, Carbon $recordedAt): ?Zone` (used by DOC-09 on every read)
- Returns the zone whose binding for this reader satisfies `bound_from <= recordedAt AND (bound_until IS NULL OR recordedAt < bound_until)`.
- Because intervals are contiguous and non-overlapping, this is a single unambiguous row (or none, if the reader had no binding at that time → DOC-09 treats the read as zone-unresolved).
- This is the crux: a **backfilled** read (arriving hours late after an outage, DOC-08) with an old `recorded_at` resolves to the zone that was active **then**, not the zone the pole is in **now**.

### 4.3 `currentZone(Device $reader): ?Zone`
- Convenience for the live map / UI — the zone of the open binding.

### 4.4 Guards
- Deleting a zone with any binding (open or historical) → **restrict** (409): history must remain resolvable. Zones are deactivated (`is_active=false`), not deleted, once used. Only a never-bound, never-referenced zone may be hard-deleted.
- Deleting a reader device cascades its bindings (DOC-05 retire-don't-delete is preferred; if truly deleted, its historical reads lose their reader FK but keep the stored `zone_id` snapshot DOC-09 records on the reading — see DOC-09).

---

## 5. Repositioning workflow (the real-life driver)

When a mobile pole (or its reader) is physically relocated, the operator records it so tracking stays accurate and history stays intact.

**UI:** `/settings/repositioning` (permission `manage-zones`) — a page listing each `rfid_reader` with its current bound zone, last rebind time, and the parent asset's `current_location_label`.

**Flow:**
1. Operator selects the reader(s) that moved.
2. Chooses the zone now covered — an existing zone, or creates a new zone inline (name, type, optional map placement).
3. Submits `POST /settings/readers/{device}/rebind {zone_id, effective_at, note}` → `ReaderBindingService::bind(...)`.
4. The page prompts to also update the asset's `current_location_label` and the zone's map position (DOC-16) so the live map reflects reality.
5. Everything is audited; the binding-history table shows effective times.

**Gate reader guard:** the reader bound to a `gate` zone is normally fixed (the gate doesn't move). If an operator tries to rebind a reader currently bound to a gate zone, the UI **warns** ("this reader is the gate entry/exit reader — rebinding it will stop entry/exit logging here"). Not blocked, but a deliberate confirmation, because it has outsized consequences for headcount.

**Backfill correctness:** because `resolveZoneAt` uses `recorded_at`, a pole that was offline during and after a move still lands its buffered reads in the historically correct zones once they flush — the move's `effective_at` sits between the outage's reads, and each read resolves against the binding active at its own timestamp.

---

## 6. Zone management (③, `manage-zones`)

Operator UI (Inertia, surface A). **Zones ship empty** — no seeded zones (dynamic, like hardware; a dev/staging demo seeder may add a few for local testing only).

| Action | Route (Inertia) | Controller@method | Permission |
|---|---|---|---|
| List zones | GET `/settings/zones` | `Web\Settings\ZoneController@index` | manage-zones |
| Zone detail | GET `/settings/zones/{zone}` | `@show` | manage-zones |
| Create / update zone | POST/PUT `/settings/zones…` | `@store/@update` | manage-zones |
| Deactivate / delete zone | POST `/{zone}/deactivate`, DELETE `/{zone}` | `@deactivate/@destroy` | manage-zones (delete guarded §4.4) |
| Edit access list | PUT `/settings/zones/{zone}/access-list` | `ZoneAccessListController@update` | manage-zones |
| Set map position | PATCH `/settings/zones/{zone}/map-position` | `@setMapPosition` | manage-zones |
| Repositioning page | GET `/settings/repositioning` | `RepositioningController@index` | manage-zones |
| Rebind reader | POST `/settings/readers/{device}/rebind` | `ReaderBindingController@store` | manage-zones |
| Binding history | GET `/settings/readers/{device}/bindings` | `@history` | manage-zones |
| Coverage (readers↔zones, current) | GET `/api/tracking/coverage` (Inertia prop or JSON) | `CoverageController@index` | view-tracking |

**FormRequest rules:**
- zone: `name` req ≤150, `zone_type` enum, `requires_authorization` boolean, `occupancy_limit` nullable int ≥1, map fields nullable numeric.
- access-list update: `worker_ids` array of existing worker ids.
- rebind: `zone_id` exists, `effective_at` date (within tolerance §4.1), `note` nullable ≤255.

---

## 7. Frontend (React / Inertia)

- **`pages/settings/zones/index.tsx`** — ZoneListPage: table (type badge, requires-auth flag, occupancy limit, current reader count via coverage). ZoneForm modal.
- **`pages/settings/zones/show.tsx`** — ZoneDetailPage: details, **access-list manager** (WorkerPicker from DOC-04, identity-aware), map-placement editor, and the list of readers currently bound here.
- **`pages/settings/repositioning.tsx`** — RepositioningPage: reader cards (current zone, last rebind, asset location), rebind dialog (zone select or create-new), binding-history drawer.
- **`components/ir4/ZoneMapEditor.tsx`** — place/size a zone circle on the schematic map (feeds the live map DOC-16). MapLibre or Leaflet with an uploaded site-plan image as a static overlay (offline tiles — DOC-01 on-prem) `[CONFIRM AT DESIGN]`.
- **Types (`types/zone.ts`):** `Zone`, `ZoneType`, `ReaderZoneBinding`, `ZoneAccessListEntry`, `CoverageBinding`.
- Cache invalidation on every mutation; the tracking map (DOC-16) re-reads coverage after a rebind.

---

## 8. Linkage map

| Related entity | Direction | FK | Owning DOC |
|---|---|---|---|
| Reader (device) ↔ binding | reader 1—* bindings, 1 open | `reader_zone_bindings.device_id` | this doc (device in DOC-05) |
| Binding → Zone | many bindings per zone over time | `reader_zone_bindings.zone_id` | this doc |
| Tag reading → resolved zone | read resolves zone via `resolveZoneAt` | DOC-09 (snapshots `zone_id` onto the reading) | DOC-09 |
| Zone access list ↔ Worker | zone *—* workers | `zone_access_lists.worker_id` | this doc (worker DOC-04) |
| Zone rules (red/unauth/occupancy) | zone drives alerts + LSR | evaluated in `TrackingService` | DOC-09/14 |
| Height-work cross-check | `height_work` zone + missing_harness | height-harness LSR | DOC-14 |
| Muster auto-accounting | `muster_point` zone reads | evacuation entries | DOC-09 |
| Live map | zones + current coverage | map placement fields | DOC-16 |

DOC-09 **snapshots the resolved `zone_id` onto each `tag_reading`** at ingest time (using `resolveZoneAt`), so even if bindings or zones change later, the historical read keeps the zone it was resolved to — belt-and-suspenders on top of the time-aware bindings.

---

## 9. Real-life scenarios

- **Initial setup:** operator creates zones (Gate, Work Front A, Muster Point 1, a restricted_red substation area) → binds the gate reader to Gate, each pole reader to the work zone it covers → sets `requires_authorization` on the substation and adds the two authorized electricians to its access list → places zones on the site map.
- **Work front advances (repositioning):** three poles are relocated north as work progresses → operator opens `/settings/repositioning`, selects those readers, creates "Work Front B", rebinds them with `effective_at = now` → old bindings close, new open → the live map updates; last week's reads still show under Work Front A.
- **Outage across a move:** a pole was offline for 6 h spanning a relocation → when it flushes buffered reads, each read resolves to Work Front A or B depending on whether its `recorded_at` was before or after the rebind's `effective_at` — correct history despite the late arrival.
- **Unauthorized entry:** a worker not on the substation's access list enters → DOC-09 raises `unauthorized_zone_access`; an access-listed electrician entering the same zone raises nothing.
- **Red zone:** anyone entering the restricted_red area triggers a critical alert + automated LSR regardless of the access list (DOC-09/14).
- **Gate reader move warning:** an operator accidentally selects the gate reader to rebind → the UI warns it will stop entry/exit logging → they cancel.

---

## 10. Tests (this doc's slice of DOC-21)

- **Binding invariants:** binding a reader closes its prior open binding and opens a new contiguous one (`old.bound_until == new.bound_from`); at most one open binding per reader ever; a reader can start with none.
- **`resolveZoneAt`:** a timestamp before the current binding resolves to the *previous* zone; a timestamp with no covering binding resolves to null; boundary at the exact `effective_at` resolves to the new zone (half-open interval `[from, until)`).
- **Backfill correctness (integration with DOC-08/09):** a late read with an old `recorded_at` lands in the zone active at that time, not the current zone.
- **Snapshot:** the resolved `zone_id` is written onto the `tag_reading` and does not change when the reader is later rebound.
- **Guards:** deleting a zone with any binding → 409; deleting a never-bound zone → allowed; rebinding a non-reader device → 422; `effective_at` before current `bound_from` → 422; gate-reader rebind returns the warning flag.
- **Access list:** listed worker in a `requires_authorization` zone → no alert; unlisted → `unauthorized_zone_access` (DOC-09 integration); red zone always alerts.
- Authorization: all zone/binding mutations require `manage-zones`; coverage read requires `view-tracking`.

---

## 11. Open decisions logged

| # | Decision | Default | Confirm in |
|---|---|---|---|
| 1 | Do poles actually reposition, or are they fixed after install? | assume repositionable (`is_mobile`); model supports both | this doc / DOC-05 |
| 2 | Access list vs red-zone alerting | access list governs `requires_authorization`; `restricted_red` always alerts | this doc / DOC-09 |
| 3 | `effective_at` future tolerance on rebind | small tolerance only | this doc |
| 4 | Map: uploaded site-plan overlay vs offline tiles | site-plan image overlay (offline) | DOC-16 |

---

### Next document
**DOC-07 — Unified Alerts & Notifications:** the single alert pipeline every module raises into (PPE, gas, zone rules, HSE, device health), the alert state machine, deduplication, audible rules, acknowledgement vs auto-resolve, and the real-time delivery + alert-centre UI.