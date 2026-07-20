# DOC-14 — HSE Incidents & Life Saving Rules

> **Depends on:** DOC-01 (conventions), DOC-03 (`view-incidents`, `log-incidents`, `classify-incidents`, `view-lsr`, `log-lsr`, `close-lsr`), DOC-04 (workers as incident personnel / LSR subjects, identity-permissioned), DOC-06 (zones), DOC-07 (alerts that *suggest* + prefill these records), DOC-09 (RFID roster/zone evidence), DOC-10 (PPE violations as linkable evidence). **Feeds:** DOC-15 (weekly-report incidents item ii + LSR item iii), DOC-16 (open-incidents / open-LSR dashboard widgets).
>
> **Scope:** the two safety-record types — **HSE Incidents** and **Life Saving Rule (LSR) violations** — both **fully user-created (manual)**. Covers creation, the incident **classification form**, **optional linking** of alerts, PPE violations, and RFID zone/identity evidence, the **prefill-from-alert** convenience, the **LSR categories**, mandatory **action-taken** to close, and the weekly-report feed. **Out of scope:** the sensor/alert machinery that *suggests* these records (DOC-07/09/10) — this module owns the human-authored records those suggestions lead to.

---

## 1. Purpose & the manual-first principle

Incidents and LSR violations are the platform's formal safety records — the artifacts a client and regulators read. Per the confirmed model (DOC-07 §8): **these records are always created by a person.** Sensors and alerts do the *evidence capture* (a fall detected, a red-zone entry, a PPE miss, the RFID roster at a moment) and *suggest* a record with everything prefilled — but **nothing is written until an authorized user reviews and submits.** This keeps every incident and LSR an auditable act of human judgment, with a named author, while still capturing the automatic evidence the sensors provide.

**Hard rules (DOC-21 invariants):**
- **No auto-created incidents or LSR rows.** There is no service that inserts these from an alert; the only creators are user-submitted forms (path ③).
- Every incident/LSR has a **user author** (`created_by`/`logged_by`) and, once actioned, a **classifier/closer** (`classified_by`/`closed_by`).
- **Every LSR violation and HSE incident carries a mandatory "action taken" field** (proposal §6.5 *Actions Taken* — "every LSR violation and HSE incident entry includes a mandatory actions-taken field"). For LSR this is `action_taken` (required to close); for incidents it is the classification's `immediate_action` + `corrective_action` (required to classify). No record is considered resolved/report-ready without a documented action. These are human decisions, never auto-filled.
- **All records are retained long-term** (soft-delete only) and **automatically included in the Section 6.5 weekly report** (DOC-15) — incidents in item (ii), LSR in item (iii). Retention/inclusion is guaranteed, not optional.
- Linked evidence (`alert_id`, `ppe_violation_id`, RFID snapshot) is **optional context** attached by the user, not a trigger.

---

## 2. Data origin

- **③ user:** all incident + LSR creation, classification, closure, and evidence attachment.
- **② system:** only *supporting* — it surfaces the alert's `suggested_action` + prefill payload (DOC-07), and, when a user creates a record from an alert, it can **snapshot** the RFID zone roster at the incident time (DOC-09) into the record as attached evidence. It never creates the record.
- **① device:** none directly — devices produce the readings/detections that became the suggesting alert.

---

## 3. Data model

### 3.1 `hse_incidents` (soft-deleted, kept forever)
```php
Schema::create('hse_incidents', function (Blueprint $table) {
    $table->id();
    $table->string('incident_number')->unique();          // INC-{yyyy}-{seq} (no location segment — standalone)
    $table->string('source')->default('manual');          // enum IncidentSource: manual | from_alert (still user-submitted)
    $table->foreignId('alert_id')->nullable()->constrained()->nullOnDelete();   // optional linked alert
    $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();    // where it occurred
    $table->foreignId('camera_id')->nullable()->constrained()->nullOnDelete();  // optional
    $table->timestamp('occurred_at');                     // when it happened (user-entered; prefilled from alert if linked)
    $table->string('status')->default('open');            // enum IncidentStatus (§3.5)
    // classification (③ — required to reach 'classified')
    $table->string('incident_type')->nullable();          // enum IncidentType
    $table->string('severity')->nullable();               // enum Severity
    $table->text('nature_of_incident')->nullable();       // what happened
    $table->text('immediate_action')->nullable();         // action taken at the time
    $table->text('corrective_action')->nullable();        // action to prevent recurrence (required to classify)
    $table->foreignId('classified_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('classified_at')->nullable();
    // closure
    $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('closed_at')->nullable();
    $table->string('close_note')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();
    $table->index(['status']);
    $table->index(['incident_type', 'severity']);
    $table->index(['occurred_at']);
});
```

### 3.2 `incident_personnel` (workers involved — DOC-04)
```php
Schema::create('incident_personnel', function (Blueprint $table) {
    $table->id();
    $table->foreignId('hse_incident_id')->constrained()->cascadeOnDelete();
    $table->foreignId('worker_id')->constrained();
    $table->string('involvement');                        // enum Involvement: involved | witness | present_in_zone
    $table->timestamps();
    $table->unique(['hse_incident_id', 'worker_id']);
});
```
- `present_in_zone` rows come from an **RFID roster snapshot** (§5) when the user creates the incident from a zone/fall alert — they represent "who the RFID said was in the zone at that time," attached as evidence. `involved`/`witness` are added/edited by the user in the classification form. The snapshot rows are informational and clearly labeled as auto-captured evidence.

### 3.3 `incident_evidence`
```php
Schema::create('incident_evidence', function (Blueprint $table) {
    $table->id();
    $table->foreignId('hse_incident_id')->constrained()->cascadeOnDelete();
    $table->string('evidence_type');                      // enum: snapshot | rfid_zone_snapshot | ppe_violation | document | note
    $table->string('file_path')->nullable();              // for snapshot/document (private disk)
    $table->json('payload')->nullable();                  // for rfid_zone_snapshot (frozen roster json) / note
    $table->foreignId('ppe_violation_id')->nullable()->constrained()->nullOnDelete(); // linked PPE evidence (DOC-10)
    $table->foreignId('camera_id')->nullable()->constrained()->nullOnDelete();
    $table->timestamp('captured_at')->nullable();
    $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete(); // null = auto-captured at creation
    $table->timestamps();
});
```

### 3.4 `lsr_violations` (soft-deleted, kept forever)
```php
Schema::create('lsr_violations', function (Blueprint $table) {
    $table->id();
    $table->string('category');                           // enum LsrCategory (§3.5)
    $table->timestamp('occurred_at');
    $table->foreignId('worker_id')->nullable()->constrained()->nullOnDelete();   // optional subject (identity-permissioned)
    $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('camera_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('alert_id')->nullable()->constrained()->nullOnDelete();     // optional linked alert
    $table->foreignId('ppe_violation_id')->nullable()->constrained()->nullOnDelete(); // optional linked PPE evidence
    $table->text('description')->nullable();
    $table->text('action_taken')->nullable();             // required to close (§6)
    $table->string('status')->default('open');            // enum: open | closed
    $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('closed_at')->nullable();
    $table->foreignId('logged_by')->nullable()->constrained('users')->nullOnDelete(); // the human author
    $table->timestamps();
    $table->softDeletes();
    $table->index(['category', 'status']);
    $table->index(['occurred_at']);
});
```
- `worker_id` is **optional** and identity-permissioned: an LSR sourced from an RFID event may name the worker; one linked to an anonymous PPE violation stays worker-less (DOC-10's privacy invariant — a PPE-linked LSR does not resurrect identity the camera never had).

### 3.5 Enums
- **`IncidentSource`:** `manual`, `from_alert` (both user-submitted; `from_alert` just records that it originated from a suggestion).
- **`IncidentStatus`:** `open`, `under_review`, `classified`, `closed`.
- **`IncidentType`:** `injury`, `near_miss`, `property_damage`, `environmental`, `other`.
- **`Severity`:** `low`, `medium`, `high`, `critical`.
- **`Involvement`:** `involved`, `witness`, `present_in_zone`.
- **`EvidenceType`:** `snapshot`, `rfid_zone_snapshot`, `ppe_violation`, `document`, `note`.
- **`LsrCategory`:** `missing_ppe`, `red_zone_intrusion`, `unauthorized_zone_access`, `height_without_harness`, `worker_down`, `zone_occupancy_exceeded`, `working_without_permit`, `hot_work_without_fire_watch`, `simops_violation`. (These correspond to both alert-suggested categories and manually-logged permit categories; all are user-created here.)

---

## 4. Incident lifecycle & state machine

```
  create ─▶ open ─▶ under_review ─▶ classified ─▶ closed
                         │                            ▲
                         └──────── (unclassified) ────┘  (open→closed only with a mandatory close_note)
```
- **open** — just created (manually or from an alert suggestion), minimal info.
- **under_review** — an operator is working it (optional intermediate; a `from_alert` incident may start here).
- **classified** — the formal classification form is complete (all required fields, §5.2); this is the report-ready state.
- **closed** — resolved. A genuine incident reaches `closed` **only via `classified`** (so its mandatory `immediate_action` + `corrective_action` are recorded — proposal §6.5 Actions Taken). A `open/under_review → closed` shortcut exists **only** for a verified false alarm and **requires a `close_note`** (min length) explaining why it wasn't a real incident needing classification. This keeps the "every incident has an action taken" guarantee: anything real is classified with actions; only non-incidents are closed with a note.
- Reopen: `classified → under_review` is allowed (audited) to amend.
- Transitions validated in the service (invalid → 422); each is audited.

---

## 5. Creating an incident

### 5.1 Two entry points, one record type
- **Fully manual:** `POST /incidents` with `{ occurred_at, zone_id?, nature?, … }` — an operator logs an incident they observed. Starts `open`/`under_review`.
- **From an alert (prefilled):** the operator clicks an alert's "create incident from this" (DOC-07 §8). The form opens **prefilled** from the alert payload — `occurred_at` (= detected_at), zone, camera, the alert link, the camera **snapshot** as evidence, and, for zone/fall alerts, an **RFID zone-roster snapshot**. The operator reviews, adjusts, and submits. The saved incident has `source=from_alert`, `alert_id` set, and the prefilled evidence attached. **It is still a user-authored record** — no incident existed until submit.

### 5.2 RFID zone-roster snapshot (② evidence capture, on user creation)
When creating from a zone/fall alert, the service captures a **frozen** snapshot of who the RFID said was in that zone at `occurred_at` (querying DOC-09 positions/history): each such worker becomes an `incident_personnel(present_in_zone)` row **and** a single `incident_evidence(rfid_zone_snapshot, payload=frozen roster json)`. Frozen = later movement never alters the evidence. This is the automatic evidence the sensors provide, attached to the human-authored record.

### 5.3 Classification form (③, `classify-incidents`)
`PUT /incidents/{incident}/classify` — the formal fields, all required to reach `classified`:
- `incident_type` (enum), `severity` (enum), `occurred_at` (≤ now), `nature_of_incident` (min 10), `immediate_action` (min 10), `corrective_action` (min 10), and the **personnel** list (`involved`/`witness` — the user curates these; the `present_in_zone` snapshot rows remain as evidence).
- Sets `classified_by/at`, status → `classified`. Audited.

### 5.4 Evidence management (③)
`POST /incidents/{incident}/evidence` — attach further documents, notes, snapshots, or **link a PPE violation** (DOC-10) as `ppe_violation` evidence. Auto-captured evidence (`added_by=null`) is distinguished in the UI from user-added.

### 5.5 Numbering
`incident_number = INC-{yyyy}-{seq}` via a dedicated yearly sequence (no location segment — standalone). Generated on create.

---

## 6. LSR violations

### 6.1 Creation (③) — from a suggestion or fully manual
- **From an alert (prefilled):** clicking an alert's "log LSR from this" (red-zone, unauthorized, occupancy, height-harness, or a PPE violation — DOC-07 §8) opens the LSR form **prefilled**: `category` (mapped from the alert), `occurred_at`, `zone`, `worker` (only if the alert carried an identity — never for PPE/camera-anonymous), the `alert_id`, and `ppe_violation_id` if applicable. The user reviews and submits.
- **Fully manual (categorised form):** `POST /lsr-violations` — the structured manual LSR logging workflow for violations that **cannot be detected automatically** and require a safety officer's observation. This explicitly covers the three permit-dependent categories from the proposal, each of which needs information the platform can't sense:
  - `working_without_permit` — cannot be detected without access to the Digital Work Permit system; the officer logs the observed violation.
  - `hot_work_without_fire_watch` — cannot be detected without permit verification; officer-logged.
  - `simops_violation` — cannot be detected without simultaneous-operations permit cross-referencing; officer-logged.
  The officer selects the category, `occurred_at`, zone?, worker?, and a description on the categorised form. (Any other category may also be logged manually — decision #2.) Each is included in the weekly report with its action taken recorded.
- Either way the row is user-authored (`logged_by`), status `open`.

### 6.2 Closure (③, `close-lsr`) — action-taken mandatory
`POST /lsr-violations/{lsr}/close {action_taken}` — validates `action_taken` (min 10); sets `status=closed`, `closed_by/at`. **An LSR cannot be closed without a documented action taken** (the core compliance requirement). Bulk close (shared action) is available for grouped occupancy episodes.

### 6.3 Identity rule
- `worker_id` is set only when the source genuinely knows the worker (RFID-sourced alerts, or an officer naming them). A PPE-linked LSR keeps `worker_id` null — the camera never identified anyone (DOC-10 §1). Identity display is `view-worker-identity`-gated (DOC-04).

### 6.4 Summary & weekly-report inclusion
`GET /api/lsr-violations/summary?from&to` → counts by category + open count → dashboard widget + weekly report item (iii). **All LSR entries in the reporting period are automatically included** in the Section 6.5 weekly report with their category and action taken (proposal §6.5) — inclusion is guaranteed by the report generator (DOC-15) reading the retained records, not a per-entry opt-in.

---

## 7. API / routes (operator surface A)

| Action | Route | Permission |
|---|---|---|
| Incidents list/detail | GET `/incidents`, `/incidents/{incident}` | view-incidents |
| Create incident (manual or from-alert) | POST `/incidents` | log-incidents |
| Classify | PUT `/incidents/{incident}/classify` | classify-incidents |
| Reopen | POST `/incidents/{incident}/reopen` | classify-incidents |
| Close | POST `/incidents/{incident}/close` | classify-incidents (or log-incidents for false-alarm close-note) `[CONFIRM]` |
| Add/attach evidence | POST `/incidents/{incident}/evidence` | log-incidents |
| LSR list/detail | GET `/lsr-violations`, `/{lsr}` | view-lsr |
| Create LSR (manual or from-alert) | POST `/lsr-violations` | log-lsr |
| Close LSR (action-taken) | POST `/lsr-violations/{lsr}/close` | close-lsr |
| LSR bulk close | POST `/lsr-violations/close-bulk` | close-lsr |
| LSR summary | GET `/api/lsr-violations/summary` | view-lsr |

FormRequests enforce the min-length decision fields (DOC-01 §8): classification narratives ≥10, `action_taken` ≥10, `close_note` ≥10. All writes audited (DOC-17).

---

## 8. Frontend (React / Inertia)

- **`pages/hse/incidents/index.tsx`** — IncidentListPage: filters (type, severity, source, status); **Log incident** opens a create dialog (manual or prefilled via `?alert_id=`).
- **`pages/hse/incidents/show.tsx`** — IncidentDetailPage: status stepper; **evidence gallery** (snapshot lightbox, RFID-roster snapshot table, linked PPE violation, documents, notes — auto-captured vs user-added clearly marked); personnel sections (auto `present_in_zone` vs curated `involved`/`witness`); **ClassifyForm**; reopen/close.
- **`pages/hse/lsr/index.tsx`** — LsrListPage: category filters, open/closed; **Log LSR** create dialog + close-with-action dialog; from-alert prefill via `?alert_id=`.
- **`pages/hse/lsr/summary.tsx`** — LsrSummaryPage: category chart, range picker.
- **Components:** `IncidentStatusStepper`, `EvidenceGallery`, `RfidRosterTable`, `WorkerPicker` (DOC-04, identity-aware), `ClassifyForm`, `LsrForm`, `CloseLsrDialog`, `SourceBadge`.
- **Types (`types/hse.ts`):** `HseIncident`, `IncidentSource`, `IncidentStatus`, `IncidentType`, `Severity`, `IncidentPersonnel`, `Involvement`, `IncidentEvidence`, `EvidenceType`, `LsrViolation`, `LsrCategory`.

---

## 9. Real-life scenarios

- **Fall → incident (from alert):** camera fall detection raises `fall_detection` (DOC-10) → operator acknowledges, checks the feed, and clicks "create incident from this" → the form opens prefilled with the snapshot, camera, zone, and the frozen RFID roster of who was in the zone → operator confirms the involved worker, submits → later a safety officer classifies it (injury, high, corrective action) → it appears in the weekly report item (ii). If it was a false alarm, the operator simply resolves the alert and creates nothing.
- **Red zone → LSR (from alert):** `red_zone_intrusion` alert → operator clicks "log LSR from this" → form prefilled (category, worker, zone) → operator adds the action taken and closes it.
- **Permit LSR (fully manual):** an officer observes hot work without a fire watch → logs an LSR (`hot_work_without_fire_watch`, description) → closes it with "work stopped, fire watch assigned."
- **PPE-linked LSR:** a repeated no-harness-at-height PPE violation → operator logs an LSR linking the violation (`ppe_violation_id`), worker stays anonymous (camera-sourced), action taken recorded.
- **Amendment:** a classified incident needs a correction → reopened to `under_review`, edited, re-classified (audited).

---

## 10. Tests (this doc's slice of DOC-21)

- **No auto-creation:** raising any alert (fall/red-zone/stationary/worker-down/PPE) creates **no** incident or LSR row; a record appears only via the user-submitted endpoint; the created record links `alert_id`/`ppe_violation_id`.
- **Incident lifecycle:** create (manual + from-alert) → classify requires all narrative fields (≥10) + type + severity (422 otherwise) → `classified`; `open→closed` requires a `close_note`; reopen works; invalid transitions 422; numbering `INC-{yyyy}-{seq}` sequential.
- **RFID snapshot:** creating from a zone/fall alert freezes the roster into `present_in_zone` personnel + an `rfid_zone_snapshot` evidence row; later worker movement doesn't change it.
- **LSR:** create (manual + from-alert); close **blocked without `action_taken`** (≥10); PPE-linked LSR keeps `worker_id` null; RFID-sourced LSR may name the worker (identity-permissioned); bulk close works; summary counts by category; the three permit categories are loggable via the categorised manual form.
- **Mandatory action taken (proposal §6.5):** an LSR cannot be closed and a real incident cannot be classified/closed without its action field(s); a false-alarm incident close requires a `close_note`.
- **Retention & auto-inclusion:** incidents and LSR are soft-deleted only (never hard-pruned) and every entry in a period is automatically pulled into the weekly report (DOC-15) — verified by a report-generation test over seeded records.
- **Identity:** worker fields on incidents/LSR are stripped without `view-worker-identity` (resource-level, DOC-04).
- **Evidence provenance:** auto-captured evidence has `added_by=null`; user-added has the user id; both shown distinctly.
- Authorization: log/classify/close gated by their permissions; view gated by view perms.

---

## 11. Open decisions logged

| # | Decision | Default | Confirm in |
|---|---|---|---|
| 1 | Who may close an incident with a false-alarm `close_note` | `classify-incidents` (or `log-incidents`?) | DOC-03 |
| 2 | Manual LSR categories restricted to permit types, or any category | any category allowed manually | this doc |
| 3 | RFID roster snapshot on manual (non-alert) incidents | only when a zone + time is given | DOC-09 |
| 4 | Incident numbering reset per year | yes (`INC-{yyyy}-{seq}`) | DOC-15 |

---

### Next document
**DOC-15 — Weekly Report (UDPM-GM-0058 §6.5):** scheduled generation of the 10-item report from module data (PPE, incidents, LSR, weather, manpower, gas, CO₂, environmental) + manual vehicle violations, the frozen-data contract, PDF/CSV export with automation badges, publish-locks and supersede-don't-edit amendments, and outage-completeness notes.