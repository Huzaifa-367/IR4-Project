# DOC-10 — PPE Compliance

> **Depends on:** DOC-01 (conventions, files/signed URLs), DOC-03 (`view-ppe`, `review-ppe`, `export-ppe-reports`, `view-live-cameras`), DOC-05 (cameras + `camera_ref`), DOC-07 (raises `ppe_violation`/`fall_detection` alerts that *suggest* records), DOC-08 (shared `ppe-violations` ingest endpoint + `ppe` channel). **Feeds:** DOC-14 (a violation is *linkable* into a user-created LSR/incident; fall alert suggests an incident), DOC-15 (PPE feeds the weekly report's safety-observations item), DOC-16 (live wall + PPE dashboard card).
>
> **Scope:** the PPE computer-vision pipeline — the **anonymous** violation stream from the camera AI (helmet/vest/harness/mask), **fall** routing (same endpoint), the **false-positive review** workflow, **trends + exports**, the **live wall**, and how a violation is **linked** (never auto-attached) into a user-created LSR. **Out of scope:** LSR/incident records themselves (DOC-14 — this module supplies linkable evidence), camera hardware (DOC-05), and detection thresholds (applied on the edge compute, not the server).

---

## 1. Purpose & the privacy invariant

The AI cameras flag PPE non-compliance on the active work front — a worker without a helmet, hi-vis vest, harness (at height), or face mask — and separately detect **falls**. This module records those computer-vision events, lets operators triage false positives (unavoidable during AI calibration and in dusty conditions), and surfaces trends for the weekly report.

**The privacy invariant (hard, DOC-21-checked): PPE violations are anonymous site events, never linked to a worker identity.** There is **no `worker_id` column** on `ppe_violations`, and no code path may add one. A camera sees "a person without a helmet at camera X" — it does not (and must not) resolve *who*. This is deliberate: PPE compliance is measured and reported at the **site/camera** level, not used to single out individuals. (Contrast RFID tracking, DOC-09, which is identity-aware and permission-gated.) Cursor must fail the build if a `worker_id` appears on this table.

---

## 2. Data origin

- **① device:** violation + fall events from the camera AI via `/api/ingest/ppe-violations` (DOC-08). Includes the snapshot image and confidence.
- **② system:** raises the `ppe_violation` / `fall_detection` alerts (DOC-07), computes summaries/trends, generates exports.
- **③ user:** the **review** decision (confirm / false-positive + note), export requests, and — in DOC-14 — creating an LSR/incident that *links* a violation. The machine fields (type, detected_at, confidence, snapshot) are **immutable** — no update endpoint touches them (DOC-01 §9).

---

## 3. Data model

### 3.1 `ppe_violations` (soft-deleted, retained forever)
```php
Schema::create('ppe_violations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('camera_id')->constrained()->restrictOnDelete();
    $table->string('violation_type');                     // enum ViolationType (§3.3) — PPE types only
    $table->timestamp('detected_at')->index();
    $table->unsignedInteger('worker_count')->default(1);  // people in frame affected (a count, NOT an identity)
    $table->string('snapshot_path');                      // private disk (DOC-01 §10)
    $table->decimal('confidence', 5, 2)->nullable();      // AI confidence %
    $table->string('location_label')->nullable();         // asset current_location_label at detection (DOC-05)
    $table->foreignId('alert_id')->nullable()->constrained()->nullOnDelete();
    // review (③ — the ONLY human-writable fields)
    $table->string('review_status')->default('unreviewed'); // enum ReviewStatus (§3.3)
    $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('reviewed_at')->nullable();
    $table->string('review_note')->nullable();
    // ingest bookkeeping (DOC-08)
    $table->boolean('is_backfill')->default(false);
    $table->string('event_uid');
    $table->timestamps();
    $table->softDeletes();
    $table->unique(['camera_id', 'event_uid']);           // idempotency
    $table->index(['violation_type', 'detected_at']);
    $table->index(['review_status']);
});
```
**NO `worker_id`.** Ever. (§1.)

### 3.2 Falls are not stored here
A `fall` event (same ingest endpoint, `event_type=fall`) is **not** a PPE violation row — it has no PPE type and no compliance meaning. It is routed to raise a `fall_detection` alert (DOC-07) carrying its snapshot, which **suggests** a user-created incident (DOC-14). The snapshot is stored on the private disk and referenced from the alert payload. (If a lightweight audit trail of fall events is wanted independent of whether an incident is created, a minimal `detection_events` table is a `[CONFIRM AT DESIGN]` option; default: the alert is the record.)

### 3.3 Enums (PHP backed + TS mirror)
- **`ViolationType`:** `missing_helmet`, `missing_vest`, `missing_harness`, `missing_mask`. (No `fall` — falls aren't PPE violations.)
- **`ReviewStatus`:** `unreviewed`, `confirmed`, `false_positive`.

---

## 4. Ingest processing (`PpeViolationService`, ②)

Entry from DOC-08: the shared `ppe-violations` endpoint hands each event to this service after dedupe/backfill classification. Per event, branch on `event_type`:

### 4.1 `event_type = fall`
- Store the snapshot (private disk). Raise `fall_detection` (critical, audible, DOC-07) with `{ camera_ref, snapshot_url, detected_at, zone? }` in the payload; the alert's `suggested_action` = "create incident from this" (DOC-14). Feed the worker-down correlator (DOC-09 §6.2). **No PPE row created.**

### 4.2 `event_type` = a PPE violation type
- **Resolve camera** by `camera_ref` (DOC-05); unknown → `UNKNOWN_REFERENCE` rejection.
- **Store snapshot** → `snapshots/{Y/m/d}/{uuid}.jpg` (private).
- **Create the `ppe_violation`** (`review_status=unreviewed`, `location_label` from the camera's asset). `confidence` is stored as reported by the edge (for display/analytics), not used as a server-side filter — the edge compute has already applied its detection threshold before sending, so every event that arrives is a real detection to record.
- **Raise `ppe_violation` alert** (warning, not audible, DOC-07); link `alert_id`. The alert's `suggested_action` = "log LSR from this" (optional) carrying `ppe_violation_id` — so an operator can, if warranted, create an LSR in DOC-14 that **links** this violation. Nothing is auto-created.
- **Broadcast** `PpeViolationDetected` on the `ppe` channel (unless backfill — DOC-08 §5.3): `{ id, violation_type, camera_ref, snapshot_url, detected_at }` for the live wall.

### 4.3 Backfill
Backfilled PPE events (>10 min old, DOC-08) are stored and counted in trends/reports but **don't broadcast** and **don't raise a live toast** (the event is historical).

---

## 5. False-positive review (③, `review-ppe`)

Real life: the first days of on-site AI calibration, plus dust/glare/PPE-color edge cases, produce false positives. Operators triage them, and confirmed false positives are **excluded from all metrics**.

- **`POST /ppe/violations/{violation}/review {status: confirmed|false_positive, note}`** — sets `review_status`, `reviewed_by/at`, `review_note`. Audited.
- **`POST /ppe/violations/bulk-review {ids[], status, note}`** — for clearing a burst during calibration.
- **Effect of `false_positive`:** the violation is excluded from every summary/trend/weekly-report figure (with the excluded count shown as a footnote). If an LSR was ever linked to it (DOC-14), that link is surfaced for the operator to reconsider — but marking FP does **not** silently alter a user-authored LSR (that's the user's record). The linked `ppe_violation` alert is resolved.
- **`confirmed`** keeps it in metrics and marks it triaged.
- Machine fields remain immutable — review only writes the review columns (DOC-01 §8 whitelist rule).

---

## 6. Trends, summaries & exports

### 6.1 Summaries (`view-ppe`)
- **`GET /api/ppe/violations/summary?range=daily|weekly|custom&from&to&group_by=type|camera|hour`** → aggregates for the trends page and the weekly report item (i). **Excludes `false_positive`** rows; returns the excluded count for the footnote.
- Metrics: counts by type, by camera, by hour-of-day (heatmap), and a **false-positive rate** stat (evidences calibration progress).

### 6.2 List & detail (`view-ppe`)
- **`GET /ppe`** — PPE trends dashboard (range filters, by-type / by-camera / heatmap).
- **`GET /ppe/violations`** — filterable table (type, camera, review_status, date range, backfill), newest first; snapshot thumbnails via signed URL.
- **`GET /ppe/violations/{violation}`** — full snapshot (signed URL), camera, confidence, linked alert, and any linked LSR (DOC-14).

### 6.3 Exports (`export-ppe-reports`)
- **`POST /ppe/violations/export {format: pdf|csv, from, to}`** → queued `GeneratePpeTrendExport` on the `reports` queue → notification with a signed download link. Excludes false positives; states the exclusion.

---

## 7. Live wall (`view-live-cameras`)

- **`GET /live`** — LiveWallPage: a grid of camera feeds (stream descriptors from DOC-05/16) with an AI-status chip per camera (ai_enabled + online), and **real-time violation toasts** as `PpeViolationDetected` events arrive on the `ppe` channel.
- **`/live?display=1`** — the kiosk variant for the 55″ wall (DOC-16), auto-rotating feeds, no chrome.
- Pole cameras processed on a different edge unit still show under their own camera (events resolve by `camera_ref`, DOC-05/08).

---

## 8. Linkage into LSR/incidents (DOC-14) — *link, not auto-create*

Per the confirmed model (DOC-07 §8): a PPE violation is **evidence a user can attach**, not a record that spawns another.
- The `ppe_violation` alert offers "log LSR from this" → opens DOC-14's manual LSR form **prefilled** with the violation reference (and camera/type/time). The operator reviews and submits; the resulting LSR carries `ppe_violation_id`. If they don't act, the violation stands on its own (counted in trends, retained).
- When creating an incident or LSR manually in DOC-14, an operator can also **search/attach** a PPE violation as linked evidence.
- The violation itself never gains identity or an action field — those live on the user-authored LSR/incident.

---

## 9. Frontend (React / Inertia)

- **`pages/live/index.tsx`** — LiveWallPage (feeds grid, AI-status chips, violation toasts; `?display=1` kiosk).
- **`pages/ppe/index.tsx`** — PpeTrendPage (`GET /ppe`): stacked bars by type/day, hour heatmap, per-camera breakdown, **FP-rate** stat, Export PDF/CSV buttons.
- **`pages/ppe/violations/index.tsx`** — PpeViolationListPage (`GET /ppe/violations`): filter chips, thumbnail column, review-status badges, bulk-select review, row → detail.
- **`pages/ppe/violations/show.tsx`** — detail: snapshot viewer, camera/time/confidence, Confirm / Mark-false-positive actions (with note), linked alert + any linked LSR.
- **Components:** `PpeSnapshot` (signed-URL image with expiry handling), `ViolationTypeBadge`, `ReviewStatusBadge`, `PpeToast` (wall).
- **Types (`types/ppe.ts`):** `PpeViolation`, `ViolationType`, `ReviewStatus`, `PpeSummaryRow`, `PpeExportResult`. Note: no worker field exists on any PPE type.
- Real-time via the `ppe` channel (DOC-08); the wall + PPE card subscribe.

---

## 10. Real-life scenarios

- **Normal detection:** camera flags a missing helmet (confidence 0.82) → stored, `ppe_violation` alert + wall toast → operator confirms in the log → appears in the weekly report's safety-observations item.
- **Calibration week:** many dust false positives → operator bulk-marks false-positive → excluded from trends/report → the **FP-rate** stat trends down as calibration improves, evidencing progress.
- **Escalating to LSR:** a repeated no-harness-at-height violation → operator uses the alert's "log LSR from this" → the DOC-14 LSR form opens prefilled with the violation → operator adds the action taken and submits; the LSR links the violation.
- **Fall (not a PPE row):** camera fall detection → `fall_detection` alert (no PPE row) → if RFID stationary corroborates within 10 min, `worker_down` (DOC-09) → operator creates an incident from the prefilled alert (DOC-14).
- **Pole camera:** a pole camera's feed is AI-processed on a different edge unit → the violation still appears under the pole camera (resolved by `camera_ref`).

---

## 11. Tests (this doc's slice of DOC-21)

- **Privacy invariant:** `ppe_violations` has **no** `worker_id` (schema test that fails the build if present); no endpoint or resource exposes a worker on a PPE violation.
- **Ingest routing:** a PPE `event_type` creates a violation + alert + broadcast; a `fall` `event_type` creates **no** PPE row but raises `fall_detection`; unknown `camera_ref` → `UNKNOWN_REFERENCE`. (No server-side confidence filtering — the edge already thresholds; `confidence` is stored for display only.)
- **Immutability:** no route updates `violation_type`/`detected_at`/`confidence`/`snapshot`; only the review columns are writable (403/422 on attempts).
- **Review:** confirm/false-positive set the review fields + audit; bulk review works; false positives are excluded from summary/trend/report and counted in the footnote; the linked alert resolves on FP.
- **Backfill:** backfilled violations are stored and counted but don't broadcast/toast.
- **Trends/export:** summary groups by type/camera/hour and excludes FPs; export queues and returns a signed link; FP-rate computed.
- **Linkage:** the alert offers a prefilled LSR (DOC-14) carrying `ppe_violation_id`; no LSR is created without the user submitting; a created LSR links the violation.
- **Snapshots:** served only via 15-min signed URLs; raw path never exposed.
- Authorization: view/review/export gated by their permissions; live wall by `view-live-cameras`.

---

## 12. Open decisions logged

| # | Decision | Default | Confirm in |
|---|---|---|---|
| 1 | Store fall events in a `detection_events` table? | no — the alert is the record | this doc / DOC-14 |
| 2 | Detection thresholding | on the edge compute, not the server (`confidence` stored for display/analytics only) | this doc / DOC-08 |
| 3 | Snapshot retention (vs raw-reading pruning) | violations + snapshots kept forever (soft delete) | DOC-19 |
| 4 | Live-wall stream transport | signed stream descriptor (DOC-05 §1) | DOC-16 |

---

### Next document
**DOC-11 — Gas & CO₂ Monitoring:** the readings stream (shared `gas-readings` endpoint carrying gas channels + CO₂), configurable thresholds, alarm evaluation with hysteresis auto-resolve, the backfill-creates-no-alarms rule, live per-device panels, trends, and the weekly-report gas/CO₂ items.