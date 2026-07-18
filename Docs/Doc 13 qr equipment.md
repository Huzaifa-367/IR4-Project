# DOC-13 — QR Equipment Monitoring

> **Depends on:** DOC-01 (conventions, files/signed URLs, settings), DOC-03 (`view-equipment`, `manage-equipment`; Field Staff = public page only), DOC-05 (optional `qr_printer` device), DOC-07 (`equipment_overdue` alert). **Feeds:** DOC-15 (equipment status can appear in reports if configured), DOC-16 (overdue-equipment dashboard widget). **Special surface:** the **public unauthenticated LAN QR page** (DOC-01 §3 surface C).
>
> **Scope:** the equipment registry and its lifecycle — records with **permanent QR tokens**, **inspections**, **maintenance**, **preventive-maintenance schedules**, **document attachments**, **status auto-rules**, **overdue flagging**, **ZPL label printing**, **CSV commissioning import**, **checkout/return custody tracking** (workers taking equipment for work and returning it), and the **public no-login QR page** any phone on the LAN can scan. **Out of scope:** sensor data (this module has none).

---

## 1. Purpose & what makes it different

Every safety-relevant asset on site — fire extinguishers, lifting gear, harnesses, generators, gas cylinders — needs periodic inspection and maintenance, and a field worker must be able to check an item's status by scanning it with any phone, no app and no login. Authorized staff also **take items out for work and return them**, tracked by **scanning the QR from the mobile app**. And commissioning must be painless — **one-click QR label printing** straight to the ZT411. This is the **only all-manual module** (path ③ for every record; no device telemetry) plus **one public read surface**. Two properties define it:

1. **The QR token is permanent.** Each equipment record has an immutable `qr_token` (UUID) embedded in a printed label. Updating the record (new inspection, status change) **never** regenerates the token — the physical label stays valid for the item's whole life. Cursor must ensure no code path regenerates `qr_token`.
2. **The public page needs no login.** Scanning the QR opens a mobile page over the LAN showing the item's status, history, schedule, and manuals — read-only, unauthenticated, rate-limited (this is the entire surface the Field Staff role maps to, DOC-03).

---

## 2. Data origin

- **③ user:** everything — registration, import, inspections, maintenance, schedules, documents, status changes, and **checkout/return custody** (`manage-equipment`).
- **② system:** `qr_token` generation (once, at create), due-date recompute, status auto-rules, daily overdue flagging → `equipment_overdue` alert.
- **① device:** none. (The `qr_printer` is a device only for health/inventory, DOC-05; it doesn't ingest data.)
- **Public (surface C):** read-only, unauthenticated.

---

## 3. Data model

### 3.1 `equipment` (soft-deleted)
```php
Schema::create('equipment', function (Blueprint $table) {
    $table->id();
    $table->string('equipment_code')->unique();          // human/printed id
    $table->uuid('qr_token')->unique();                  // PERMANENT — never regenerated (§1)
    $table->string('name');
    $table->string('equipment_type');                    // free text/category: extinguisher, sling, generator…
    $table->string('status')->default('in_service');     // enum EquipmentStatus (§3.7)
    $table->boolean('is_checkoutable')->default(false);  // participates in checkout/return custody (§3.6)
    $table->string('location_label')->nullable();
    $table->text('description')->nullable();
    $table->date('next_inspection_due')->nullable();     // derived (§4.2)
    $table->date('next_service_due')->nullable();        // derived
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();
    $table->index(['status']);
    $table->index(['next_inspection_due']);
    $table->index(['next_service_due']);
});
```

### 3.2 `equipment_inspections` (soft-deleted)
```php
Schema::create('equipment_inspections', function (Blueprint $table) {
    $table->id();
    $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
    $table->date('inspected_at');
    $table->string('outcome');                           // enum InspectionOutcome: pass|fail|pass_with_notes
    $table->text('notes')->nullable();
    $table->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
    $table->date('next_due')->nullable();                // computed from schedule or set manually
    $table->timestamps();
    $table->softDeletes();
    $table->index(['equipment_id', 'inspected_at']);
});
```

### 3.3 `equipment_maintenances` (soft-deleted)
```php
Schema::create('equipment_maintenances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
    $table->date('performed_at');
    $table->string('maintenance_type');                  // enum: preventive|corrective
    $table->text('description');
    $table->string('performed_by_name')->nullable();     // free text (may be an external technician)
    $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
    $table->date('next_due')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

### 3.4 `maintenance_schedules`
```php
Schema::create('maintenance_schedules', function (Blueprint $table) {
    $table->id();
    $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
    $table->string('schedule_type');                     // enum: inspection|service
    $table->unsignedInteger('interval_days');            // e.g. 30 = monthly
    $table->string('notes')->nullable();
    $table->timestamps();
    $table->unique(['equipment_id', 'schedule_type']);
});
```

### 3.5 `equipment_documents`
```php
Schema::create('equipment_documents', function (Blueprint $table) {
    $table->id();
    $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
    $table->string('title');
    $table->string('file_path');                         // private disk (DOC-01 §10)
    $table->string('mime');
    $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

### 3.6 `equipment_checkouts` (custody — who has what, and history)
Tracks a worker taking an item for work and returning it. One **open** checkout per item at a time (an item can't be in two hands at once); the full history is retained.
```php
Schema::create('equipment_checkouts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
    $table->foreignId('worker_id')->constrained()->cascadeOnDelete();     // who holds it (DOC-04)
    $table->timestamp('checked_out_at');
    $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete(); // operator who scanned/issued it
    $table->string('reason')->nullable();                // why it's being taken (task/purpose)
    $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete(); // where it's going (DOC-06)
    $table->timestamp('expected_return_at')->nullable(); // optional due-back time
    $table->timestamp('returned_at')->nullable();        // null = still out
    $table->foreignId('returned_to')->nullable()->constrained('users')->nullOnDelete();    // operator who scanned it back
    $table->string('condition_out')->nullable();         // condition note at checkout
    $table->string('condition_in')->nullable();          // condition note at return
    $table->string('return_status')->nullable();         // optional outcome at return (e.g. ok|damaged|needs_service)
    $table->string('return_reason')->nullable();         // optional note at return
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();
    $table->index(['equipment_id', 'returned_at']);      // fast "current holder" / open checkout
    $table->index(['worker_id', 'returned_at']);         // fast "what does this worker hold"
});
```
- **At most one open checkout** (`returned_at IS NULL`) per `equipment_id` — enforced in `EquipmentCheckoutService` (transaction), so an item can't be double-issued.
- The item's `equipment_code`/`qr_token` are unchanged by checkout — custody is a separate record, not a mutation of the equipment row.
- Not every item is checkout-tracked (a wall-mounted extinguisher isn't taken anywhere). A boolean `equipment.is_checkoutable` (default false) marks which items participate; checkout endpoints 422 on non-checkoutable items. `[CONFIRM AT DESIGN]` default on/off.

### 3.7 Enums
- **`EquipmentStatus`:** `in_service`, `out_of_service`, `under_maintenance`, `retired`.
- **`InspectionOutcome`:** `pass`, `fail`, `pass_with_notes`.
- **`MaintenanceType`:** `preventive`, `corrective`.
- **`ScheduleType`:** `inspection`, `service`.
- **`CheckoutState`** (derived, not stored): `available` (no open checkout), `checked_out` (open checkout exists), `overdue_return` (open + past `expected_return_at`).

---

## 4. `EquipmentService` (② derivations + ③ orchestration)

### 4.1 Create
`create(data)` — generates `equipment_code` (if not supplied) and a fresh `qr_token` (uuid4). This is the **only** place a token is ever set. Update/other operations must not touch it.

### 4.2 Due-date recompute
`recomputeDueDates(Equipment $e)` — after any inspection, maintenance, or schedule change: `next_inspection_due = last inspection date + inspection schedule interval` (and similarly `next_service_due` from the service schedule). If no schedule exists, dues are whatever the operator set manually on the last record, or null.

### 4.3 Status auto-rules (②)
- An inspection with `outcome = fail` → set status `out_of_service` + raise a `system` (info→warning) alert "Equipment {code} failed inspection".
- Recording a `corrective` maintenance offers a **return-to-service** toggle → status back to `in_service`.
- `retired` is **terminal**: no further inspections/maintenance/status changes (documents may still be added for record-keeping); the public page shows a RETIRED banner.
- Status transitions validated in the service (invalid → 422).

### 4.4 Overdue flagging — `FlagOverdueEquipment` daily job (DOC-01 §A8)
- For each item past `next_inspection_due` or `next_service_due`, raise **one deduplicated** `equipment_overdue` alert (dedupe `equipment_overdue:{id}` — DOC-07) and surface it on the dashboard widget (overdue + due-in-7-days). Resolved when the overdue inspection/service is logged.

### 4.5 Checkout / return custody (`EquipmentCheckoutService`, ③) — QR-scan driven
Checkout and return are performed **in the field from the mobile app** (DOC-01 §3 surface A on mobile) by an authorized user who **scans the item's QR code**. The QR resolves the equipment by its permanent `qr_token`; the app then drives a short form. Both actions are also available from the desktop UI (manual pick instead of scan) as a fallback.

- **Resolve-by-scan:** `GET /api/equipment/by-token/{qr_token}` (auth’d, `view-equipment`) returns the equipment + its current custody state so the mobile app knows whether to show a **checkout** or **return** flow after a scan. (This is the *authenticated* lookup for operators — distinct from the public page §7, which is unauthenticated and read-only.)
- **`checkout(...)`** — from the scan flow the user selects the **worker** (WorkerPicker), a **reason/task**, an optional **zone** (where it's going, DOC-06), optional `expected_return_at`, and an optional `condition_out`. In a transaction: verify the item is `is_checkoutable`, `in_service`, and has **no open checkout** (else 409/422); create the `equipment_checkouts` row (`checked_out_by` = the scanning user). Audited. Item status unchanged; "who holds it" is derived from the open checkout.
- **`return(...)`** — the user scans the same QR; because an open checkout exists, the app shows the **return** flow: optional `return_status` (ok / damaged / needs_service), optional `return_reason`, optional `condition_in`. Sets `returned_at`, `returned_to` = the scanning user. Audited. Item becomes `available`.
- **Scan-decides-the-action:** one scan entry point — if the item has an open checkout the app offers **return**, otherwise **checkout**. No mode toggle for the user to get wrong.
- **Condition-on-return → maintenance:** if `return_status` is `damaged`/`needs_service` (or `condition_in` notes damage), the return flow offers "log corrective maintenance / set out-of-service" (reusing §4.3) — a convenience, not automatic.
- **Overdue return** (folded into the daily `FlagOverdueEquipment`, or a sibling): open checkouts past `expected_return_at` are flagged `overdue_return` on the equipment list + dashboard; `[CONFIRM AT DESIGN]` whether it raises an alert (default: flag only, not a safety alert).
- **Worker offboarding interaction (DOC-04):** a worker with any **open checkout** cannot be offboarded until items are returned/reassigned → 409; the offboard flow surfaces "N items still checked out."
- The **public QR page** (§7) shows current custody (available / checked out to {worker}, identity-permitting) so even an unauthenticated field scan reveals who holds it.

Permissions: checkout/return require a role holding `manage-equipment` (the "authorized role" that operates the mobile custody flow). `[CONFIRM AT DESIGN]` whether to split out a lighter `checkout-equipment` permission so field supervisors can issue/return gear without full equipment management — default: reuse `manage-equipment`.

---

## 5. QR labels — one-click printing (Zebra ZT411)

Printing must be effortless — a single click on a record, or one action for a whole batch, sends the label straight to the ZT411 with no format-juggling.

- **One-click print (single item):** a **Print** button on the equipment row/detail sends the label to the configured printer in one action — no format picker, no download step in the common case. The button calls `POST /equipment/{equipment}/print-label` which generates the ZPL (50×50 mm QR encoding `https://{host}/e/{qr_token}` + the `equipment_code` text line) and dispatches it to the printer via the configured method (§5.1).
- **One-click bulk print:** on the list (and immediately after a CSV import) a **Print all / Print selected** action → `POST /equipment/print-labels {ids[]}` streams the concatenated ZPL for the whole batch to the printer in one run — the commissioning workflow (register 120 items → one click → all labels print).
- **Reprint** is the same one click; the token is permanent so a reprinted label is identical (§1).

### 5.1 Printing method (configured once, then invisible)
The printer is registered as a `qr_printer` device/asset (DOC-05) with its connection in `config`/settings. Supported dispatch methods, in order of "easiest for the operator":
1. **Direct network print (default target):** the server sends raw ZPL to the ZT411 over the LAN (raw TCP port 9100) at the printer's IP. This makes Print truly one-click — nothing downloads, the label just comes out. Printer IP/port in settings (`equipment.printer_host`, `equipment.printer_port=9100`). `[CONFIRM AT DESIGN]` confirmed as the primary method.
2. **Browser download fallback:** if no printer is configured/reachable, Print returns the `.zpl` (or a rendered PDF) for the operator to send manually — so the feature still works before the printer is wired up.

### 5.2 Raw formats (still available)
`GET /equipment/{equipment}/qr?format=png|svg|zpl&size=50` remains for previews/embeds and manual workflows; the one-click buttons above are the primary path and hide this from everyday use.

---

## 6. CSV commissioning import (③, `manage-equipment`)

Real life: at mobilization the team registers dozens/hundreds of items from client spreadsheets (cranes, generators, extinguishers).
- **`POST /equipment/import`** (CSV) → queued `ImportEquipmentJob` → row-level result report (created / updated / skipped / errored-with-reason).
- **Columns:** `equipment_code?`, `name` (req), `equipment_type` (req), `location_label`, `description`, `inspection_interval_days`, `service_interval_days`, `last_inspection_date?`, `notes`. Header row required; template downloadable (`GET /equipment/import/template`).
- Each row validated as through the store request; unique `equipment_code` enforced within the file and against the DB. **Re-import matches on `equipment_code` and updates** rather than duplicating. Schedules are created from the interval columns; `recomputeDueDates` runs per row. Every import writes an audit summary.
- Import **generates a `qr_token` per new item** (once) so labels can be bulk-printed immediately after.

---

## 7. The public QR page (surface C — unauthenticated, LAN-only)

- **`GET /e/{qr_token}`** — `Public\EquipmentPublicController@show`. **No auth, no session, no cookies.** Renders a mobile-first read-only page: equipment id + name + type, current status (with RETIRED banner if retired), description, inspection history (dates + outcomes), maintenance history, PM schedule, next-due dates, and downloadable manuals.
- **Hardening:**
  - Rate-limited (30/min/IP, `equipment.public_rate_limit`).
  - **LAN-only** — enforced at the reverse proxy (DOC-20); the app additionally refuses if the request isn't from the local network `[CONFIRM AT DESIGN]`.
  - No internal ids exposed (only the `qr_token` in the URL); no edit affordances; `<meta name="robots" content="noindex">`.
  - Document links are **per-view 15-min signed URLs** generated server-side (DOC-01 §10) — the page never links a raw private path.
  - Renders as a standalone Inertia page (or Blade) outside the authenticated app shell (`[CONFIRM AT DESIGN]` Inertia vs Blade — Blade is lighter for a public read-only page).
- This page is the **entire surface** of the Field Staff role (DOC-03) — they never log in.

---

## 8. API / routes (operator surface A)

| Action | Route | Permission |
|---|---|---|
| List / detail | GET `/equipment`, `/equipment/{equipment}` | view-equipment |
| Create / update | POST/PUT `/equipment…` | manage-equipment |
| Retire / delete | POST `/{equipment}/retire`, DELETE `/{equipment}` | manage-equipment (delete guarded — retire preferred) |
| Add inspection | POST `/equipment/{equipment}/inspections` | manage-equipment |
| Add maintenance | POST `/equipment/{equipment}/maintenances` | manage-equipment |
| Set schedule | PUT `/equipment/{equipment}/schedules` | manage-equipment |
| Documents CRUD | `/equipment/{equipment}/documents…` | manage-equipment |
| Resolve by QR scan | GET `/api/equipment/by-token/{qr_token}` | view-equipment (authed scan lookup) |
| Check out (scan → worker/reason/zone) | POST `/equipment/{equipment}/checkout` | manage-equipment |
| Return (scan → status/reason/condition) | POST `/equipment/checkouts/{checkout}/return` | manage-equipment |
| Checkout history (item) | GET `/equipment/{equipment}/checkouts` | view-equipment |
| Currently checked-out list | GET `/equipment/checkouts?open=1` | view-equipment |
| One-click print (single) | POST `/equipment/{equipment}/print-label` | view-equipment |
| One-click print (bulk/selected) | POST `/equipment/print-labels {ids[]}` | manage-equipment |
| QR raw (png/svg/zpl) | GET `/equipment/{equipment}/qr` | view-equipment |
| Bulk labels | POST `/equipment/labels` | manage-equipment |
| Import + template | POST `/equipment/import`, GET `/equipment/import/template` | manage-equipment |
| **Public page** | GET `/e/{qr_token}` | **none (public)** |

FormRequest highlights: `equipment_code` unique (ignore self), `equipment_type`/`name` required, `status` transitions validated in the service, inspection `outcome`/maintenance `maintenance_type` enums, documents pdf ≤50 MB (DOC-01 §8). Delete guarded — `retire` (terminal status) is preferred over hard delete to preserve history.

---

## 9. Frontend (React / Inertia)

- **`pages/equipment/index.tsx`** — EquipmentListPage: table with status + **overdue** badges (and due-in-7-days), type/status filters, "Add Equipment" + "Import" buttons.
- **`pages/equipment/show.tsx`** — EquipmentDetailPage: header card (status, **custody badge** — available / checked out to {worker}, next-due dates, **Print QR Label** button), tabs — Inspections (AddInspectionModal), Maintenance (AddMaintenanceModal), Documents (upload/list), Schedule (interval editor), **Custody** (CheckoutDialog / ReturnDialog + checkout history). Retire / return-to-service actions.
- **`pages/equipment/checkouts/index.tsx`** — CheckoutsPage: all currently checked-out items (worker, since, reason, zone, expected-back, overdue-return badge), with a quick "return" action.
- **Mobile custody flow (Claude for mobile / responsive Inertia):** a **Scan** entry point opens the device camera → scans the QR → calls `GET /api/equipment/by-token/{qr_token}` → if the item is available, shows the **Checkout** sheet (WorkerPicker, reason, zone, optional due-back, condition); if it has an open checkout, shows the **Return** sheet (return status, reason, condition). One scan, the app picks the right action. Components: `EquipmentScanner` (camera + QR decode), `CheckoutSheet`, `ReturnSheet`.
- **One-click print:** every equipment row/detail has a **Print** button → `POST /equipment/{id}/print-label` (fire-and-forget toast "Sent to printer"); the list + post-import screen have **Print selected / Print all** → `POST /equipment/print-labels`. No format dialog in the common path.
- **`pages/equipment/import.tsx`** — ImportPage: template download, file picker, result table, **Print all labels** button on completion.
- **`pages/public/equipment.tsx`** (or Blade `public/equipment.blade.php`) — the standalone public page, outside the auth shell (shows current custody state).
- **Components:** `EquipmentStatusBadge`, `CustodyBadge`, `OverdueBadge`, `QrLabelButton` (format menu + ZPL download), `InspectionForm`, `MaintenanceForm`, `ScheduleEditor`, `CheckoutDialog` (WorkerPicker from DOC-04, identity-aware), `ReturnDialog`.
- **Types (`types/equipment.ts`):** `Equipment` (incl. `is_checkoutable`, derived `checkout_state`), `EquipmentStatus`, `EquipmentInspection`, `InspectionOutcome`, `EquipmentMaintenance`, `MaintenanceType`, `MaintenanceSchedule`, `ScheduleType`, `EquipmentDocument`, `EquipmentCheckout`, `CheckoutState`, `PublicEquipmentRecord`, `EquipmentImportResult`.

---

## 10. Real-life scenarios (the flagship flow)

- **Commissioning:** team imports 120 items from a CSV → each gets a `qr_token` → bulk ZPL print run on the ZT411 → labels affixed to extinguishers, slings, generators.
- **Monthly inspection:** an officer scans an extinguisher's QR with their phone → the public page shows "last inspected 3 weeks ago, next due in 6 days" → back in the SCC they log the new inspection in the SPA → `recomputeDueDates` rolls the due date forward.
- **Missed service:** an item passes its `next_service_due` → the daily job raises a dedup'd `equipment_overdue` alert and it appears on the dashboard widget → once serviced, the alert resolves.
- **Failed inspection:** an extinguisher fails (corroded) → status auto-set `out_of_service` + a system alert → a `corrective` maintenance is recorded with a return-to-service toggle → back `in_service`. The QR label was never reprinted.
- **Damaged label:** a label peels off → operator reprints from the detail page → same `qr_token`, same code — the physical item's identity is unchanged.
- **Checkout for work (mobile scan):** a rigger needs a harness → a supervisor opens the mobile app, taps **Scan**, scans the harness QR → the app shows it's available and opens the Checkout sheet → picks the worker, reason "tower work", zone "Work Front A", condition-out "good" → confirms → the item shows "checked out to {worker}" on the detail page and the public QR page.
- **Return (mobile scan):** after the shift the supervisor scans the same QR → the app detects the open checkout and opens the Return sheet → return status "ok", condition-in "fine" → confirms → item available again; full custody history retained.
- **Damaged on return:** a returned drill is cracked → return status "damaged" → the return sheet offers "log corrective maintenance / set out-of-service" → item pulled from service until repaired.
- **Not returned at offboarding:** a demobilizing worker still holds two items → the offboard flow (DOC-04) blocks with "2 items still checked out" → the returns are scanned in first.
- **One-click commissioning print:** import 120 items → tap **Print all labels** → the whole batch streams to the ZT411 and prints; affix and go.
- **Retirement:** an item is retired → terminal status, RETIRED banner on the public page, no further inspections.

---

## 11. Tests (this doc's slice of DOC-21)

- **Permanent token:** creating sets `qr_token` once; no update/inspection/maintenance/status change alters it (schema/behavior test — build fails if a regenerate path exists); reprinting yields the same token.
- **Due dates:** logging an inspection/maintenance recomputes `next_*_due` from the schedule; changing the schedule recomputes.
- **Status auto-rules:** `fail` inspection → `out_of_service` + alert; corrective maintenance offers/returns to service; `retired` is terminal (further edits 422 except documents); invalid transition → 422.
- **Overdue:** the daily job raises one dedup'd `equipment_overdue` per overdue item; resolves when the inspection/service is logged; widget lists overdue + due-in-7-days.
- **QR/labels:** `zpl` output is well-formed ZPL encoding the `qr_token` URL; bulk labels concatenate; png/svg render.
- **Import:** valid rows import while invalid are reported (partial success); re-import updates via `equipment_code` (no duplicates); schedules created; each new row gets a `qr_token`; audit summary written.
- **Checkout/return (scan-driven):** `GET /api/equipment/by-token/{qr_token}` (authed) resolves the item + custody state so the app picks checkout vs return; checking out a checkoutable, in-service item with no open checkout creates a row with reason/zone and sets state `checked_out`; a second checkout → 409; checkout of a non-`is_checkoutable` item → 422; return records status/reason/condition and the item becomes `available`; custody history retained; open checkout past `expected_return_at` → `overdue_return`; a worker with an open checkout can't be offboarded (DOC-04 → 409); public page shows custody (identity-stripped per `view-worker-identity`).
- **One-click print:** `POST /equipment/{id}/print-label` generates valid ZPL encoding the `qr_token` URL and dispatches to the configured printer (or returns a download when none configured); `POST /equipment/print-labels {ids[]}` streams the batch; reprint yields the same token.
- **Public page:** `GET /e/{qr_token}` works unauthenticated; write attempts (any method other than GET) rejected; document links are 15-min signed URLs that expire; no internal ids leaked; retired item shows the banner; rate-limited.
- Authorization: operator actions require `view/manage-equipment`; the authed scan lookup requires `view-equipment`; the public page requires none.

---

## 12. Open decisions logged

| # | Decision | Default | Confirm in |
|---|---|---|---|
| 1 | Public page renderer | Blade (lighter) vs standalone Inertia | this doc / DOC-16 |
| 2 | Printing method | **direct network print to ZT411 (raw TCP :9100)** as primary; browser download fallback | DOC-20 |
| 3 | App-level LAN check on the public page | reverse-proxy enforced + optional app check | DOC-20 |
| 4 | Equipment status in the weekly report | not by default; available if configured | DOC-15 |
| 5 | `is_checkoutable` default | off (opt items in) | this doc |
| 6 | Overdue *return* raises an alert? | no — dashboard/list flag only | this doc / DOC-07 |
| 7 | Separate `checkout-equipment` permission | no — reuse `manage-equipment` | this doc / DOC-03 |
| 8 | Mobile QR scanning | in-app camera + QR decode (Claude mobile / responsive Inertia) | DOC-16/20 |

---

### Next document
**DOC-14 — HSE Incidents & Life Saving Rules:** the fully manual (user-created) incidents and LSR records — the classification form, optional linking of alerts/PPE violations and RFID evidence, the prefill-from-alert convenience, the LSR categories, and mandatory action-taken to close — with **no** auto-created records anywhere.