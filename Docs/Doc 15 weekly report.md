# DOC-15 — Weekly Report (UDPM-GM-0058 §6.5)

> **Depends on:** DOC-01 (conventions, files, queues, scheduler), DOC-03 (`view-reports`, `create-reports`, `update-reports`, vehicle-violation permissions), DOC-05 (asset counts for vehicles-monitored), DOC-09 (manpower), DOC-10 (PPE safety observations), DOC-11 (gas monitoring — five channels incl. CO₂), DOC-12 (weather + environmental), DOC-14 (incidents + LSR, retained + auto-included), DOC-17 (publish/config audited), DOC-18 (schedule settings), DOC-19 (retention; gas/env weekly items from raw on-read aggregates). **Feeds:** DOC-16 (last-report dashboard card).
>
> **Scope:** the **Section 6.5 weekly report** — scheduled auto-generation of all **9 items** from module data plus the one manually-entered item (vehicle violations), the **frozen-data contract**, **PDF/CSV** export with per-item **automation badges**, **publish-lock** + **supersede-don't-edit** amendments, and **data-completeness (outage) honesty** notes. **Out of scope:** the module data itself (owned by DOC-09–14) — this doc assembles it.

---

## 1. Purpose & the honesty principle

The weekly report is the primary deliverable a client and Saudi Aramco reviewers read. It must be: **complete** (every item, every week), **automatic** where the data is sensed, **manual** only where a human must supply it, **immutable once published** (a report is a point-in-time record), and **honest about gaps** (if a sensor was offline, the report says so rather than hiding it). Every design choice below serves those five properties.

**Frozen at generation.** When a report is generated it captures a **frozen JSON snapshot** of all its data. It is not a live view that changes when underlying records change later — a published report reflects exactly what was known at generation time. Amendments create a **new** report that supersedes the old one; nothing is silently edited (§7).

---

## 2. Data origin

- **② system:** assembles the automated items (i–vi, viii–ix) from module data, renders PDF/CSV, runs the schedule, computes completeness notes.
- **③ user:** the one manual item — **vehicle violations** (item vii) — plus generate-now, publish, and schedule settings.
- **① device:** none directly (module data came from devices upstream).

---

## 3. The 9 items (Section 6.5)

Each item, its source module, and its automation classification (the badge shown in the PDF):

| # | Item | Source | Automation badge |
|---|---|---|---|
| i | Daily Safety Observations | PPE violations, excl. false positives (DOC-10) | **Automated** |
| ii | HSE Accidents & Incidents | incidents in period, classified (DOC-14) | **Auto-detect + Manual** (sensor-suggested, human-authored) |
| iii | LSR Violations & Actions Taken | LSR entries in period, incl. action taken (DOC-14) | **Automated + Manual** (mix of alert-suggested + permit-manual) |
| iv | Weather Conditions | environmental weekly stats (DOC-12) | **Automated** |
| v | Site Manpower | entry/exit → daily peak + average headcount (DOC-09) | **Automated** |
| vi | Total Vehicles/Units Monitored | count of active field-unit assets (DOC-05) | **Automated (partial)** — see §4.6 |
| vii | Vehicle Violations & Actions Taken | manual `vehicle_violations` (§5) | **Manual** |
| viii | Environmental Data | environmental weekly stats incl. air-quality (DOC-12) | **Automated** |
| ix | Gas Monitoring (LEL / H₂S / O₂ / CO / CO₂) | all five gas channels weekly stats + alarm events (DOC-11) | **Automated** |

The badge wording mirrors the proposal's §6.5 table (as in the LSR image: "Manual Workflow (Included)", "Actions Taken → All entries"). Items ii and iii explicitly show the **action-taken** content per entry (DOC-14's mandatory field). CO₂ is **not** a separate report item — it is channel five of item ix.

---

## 4. Data model & generation

### 4.1 `weekly_reports` (soft-deleted)
```php
Schema::create('weekly_reports', function (Blueprint $table) {
    $table->id();
    $table->string('report_number')->unique();            // WR-{yyyy}-W{ww} (no location segment — standalone)
    $table->date('period_start');
    $table->date('period_end');
    $table->string('status')->default('draft');           // enum ReportStatus: draft|generated|published
    $table->timestamp('generated_at')->nullable();
    $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('published_at')->nullable();
    $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
    $table->string('pdf_path')->nullable();               // private disk
    $table->string('csv_path')->nullable();               // zipped CSV bundle
    $table->json('data');                                 // the FROZEN 10-item dataset (§4.3)
    $table->foreignId('supersedes_report_id')->nullable()->constrained('weekly_reports')->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();
    $table->index(['period_start', 'period_end']);
    $table->index(['status']);
});
```

### 4.2 `vehicle_violations` (soft-deleted — the manual item vii, ③)
```php
Schema::create('vehicle_violations', function (Blueprint $table) {
    $table->id();
    $table->timestamp('observed_at');
    $table->string('vehicle_description');                // plate/description (free text)
    $table->string('violation_type');                     // free text or seeded list (speeding, seatbelt, …)
    $table->text('description')->nullable();
    $table->text('action_taken');                         // REQUIRED (mandatory action, like LSR)
    $table->foreignId('camera_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('logged_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();
    $table->index(['observed_at']);
});
```

### 4.3 The frozen data contract (`data` JSON — exact keys)
`WeeklyReportService::generate()` assembles this once and stores it:
```json
{
  "period": { "start": "…", "end": "…" },
  "i_daily_safety_observations": {
    "per_day": [ { "date": "…", "by_type": { "missing_helmet": 3, "…": 0 }, "total": 5 } ],
    "by_camera": [ { "camera": "Pole 2 – north", "total": 4 } ],
    "false_positives_excluded": 7
  },
  "ii_hse_incidents": [
    { "incident_number":"INC-2026-014", "occurred_at":"…", "type":"near_miss", "severity":"medium",
      "status":"classified", "nature":"…", "immediate_action":"…", "corrective_action":"…",
      "personnel_count":2, "evidence_counts": { "snapshot":1, "rfid_zone_snapshot":1 } }
  ],
  "iii_lsr_violations": {
    "summary_by_category": [ { "category":"red_zone_intrusion", "count":2 } ],
    "entries": [ { "category":"hot_work_without_fire_watch", "occurred_at":"…", "worker":"Worker #12|—",
                   "zone":"Work Front A", "action_taken":"work stopped, fire watch assigned", "status":"closed" } ]
  },
  "iv_weather": { "per_day": [ { "date":"…", "temp":{"min","avg","max"}, "humidity":{…}, "wind":{…} } ] },
  "v_manpower": { "per_day": [ { "date":"…", "peak":78, "average":54.2, "entries":81, "exits":80 } ] },
  "vi_units_monitored": { "count": 5, "note": "active field units with monitoring devices" },
  "vii_vehicle_violations": [ { "observed_at":"…", "vehicle_description":"…", "violation_type":"speeding",
                               "description":"…", "action_taken":"…", "logged_by":"…" } ],
  "viii_environmental": { "per_day": [ { "date":"…", "…air_quality_params…" } ] },
  "ix_gas": {
    "per_day": [
      { "date":"…",
        "lel": { "min":0.9, "avg":3.2, "max":20.1 },
        "h2s": { "min":0.1, "avg":0.4, "max":3.2 },
        "o2":  { "min":20.4, "avg":20.7, "max":20.9 },
        "co":  { "min":2.0, "avg":8.1, "max":25.0 },
        "co2": { "min":420, "avg":610, "max":880 } }
    ],
    "alarm_events": [ { "triggered_at":"…", "device":"Pole 2 – gas", "gas":"h2s", "level":"alarm",
                        "peak":12.0, "duration_s":95, "acknowledged_by":"…", "during_outage":false } ]
  },
  "completeness": { "notes": [ { "item":"ix_gas", "message":"Gas telemetry offline 22% of the period (Pole 2, 2 outages)." } ] }
}
```
Identity in item iii entries is rendered per the **report author's** `view-worker-identity` at generation `[CONFIRM AT DESIGN]` (default: reports are compliance documents for managers who typically hold identity; a redacted variant can be generated for read-only-role recipients).

### 4.4 `WeeklyReportService::generate(Carbon $start, Carbon $end, bool $auto): WeeklyReport`
- Collects all 9 datasets from the module services (`PpeViolationService::summarize`, `IncidentService`/LSR queries, `EnvironmentalDataService::weeklyStats`, manpower from entry/exit, gas weekly stats for all five channels, `vehicle_violations` query, asset count).
- Freezes them into `data`, computes `completeness` notes (§4.5), renders PDF + CSV (§6), stores paths, sets `status=generated`, `generated_at/by`.
- **Manual generate** (`POST /weekly-reports/generate`) runs **in-request** and redirects to the new report. **Scheduled auto-generate** still uses the `GenerateWeeklyReport` job on the **`reports` queue**.

### 4.5 Completeness (outage honesty)
For each sensor-backed item, compute the fraction of the period its devices were offline (from `device_offline` alert durations, DOC-05/07). If **> 20%** (`report.completeness_threshold_pct`, DOC-18), attach a `completeness.notes` entry naming the item, the percentage, and the device — and the PDF prints it prominently in that section. Gaps are **declared, never hidden**.

### 4.6 Item vi honesty
Item vi is a **count of active field-unit assets with monitoring devices** (DOC-05) — the platform reports what it monitors. It does **not** fabricate vehicle-telematics data (route/speed/idle) the system doesn't have; a fixed note states full fleet telematics is a separate scope extension. (Named "Units Monitored" rather than implying fleet tracking.)

---

## 5. Vehicle violations (item vii, ③, `log-vehicle-violations`)

- **CRUD `/reports/vehicle-violations`** — LogVehicleViolationModal: `observed_at`, `vehicle_description`, `violation_type` (free or seeded list — `[CONFIRM AT DESIGN]` seed: speeding, seatbelt, unauthorized parking, reckless driving), `description`, **`action_taken` (required, min 10 — mandatory action like LSR)**, optional camera link.
- These entries in the period are pulled into item vii at generation.

---

## 6. Rendering (PDF + CSV)

### 6.1 PDF (dompdf)
- Branded template: cover (report_number, period, generated_at, status, supersedes banner if amending); one **section per item i–ix**, each headed with its **automation badge** (matching §3, mirroring the proposal §6.5 wording); charts for iv/v/ix (rendered server-side or as embedded images `[CONFIRM AT DESIGN]`); appendix listing incident + LSR + vehicle-violation entries with their **action-taken** text; completeness notes printed in affected sections.
- Fonts/assets bundled locally (on-prem — DOC-01).
- Manual generation renders synchronously; scheduled auto-generation renders on the `reports` queue.

### 6.2 CSV
- A **zip** with one CSV per item (i–ix) plus a summary sheet — for reviewers who want the raw figures.

### 6.3 Downloads
- **`GET /weekly-reports/{report}/download?format=pdf|csv`** → 15-min signed URL to the stored file (DOC-01 §10). Regenerating a report re-renders both artifacts.

---

## 7. Lifecycle: schedule, publish-lock, supersede

### 7.1 States
`draft → generated → published`. `generated` = artifacts built, reviewable. `published` = **locked** (immutable point-in-time record).

### 7.2 Scheduled generation — `GenerateWeeklyReport` (DOC-01 §A8)
- Runs per settings (`report.generation_day` default Sunday, `report.generation_time` default 06:00, `report.auto_publish` default false — DOC-18) for the **just-completed** reporting week (Sunday–Saturday `[CONFIRM AT DESIGN]`).
- Generates the report, notifies `publish-reports` holders (database notification + bell, DOC-07-style), and auto-publishes if `report.auto_publish`.

### 7.3 Manual generation
- **`POST /weekly-reports/generate {period_start, period_end}`** (`generate-reports`) — generate for an arbitrary period (draft/generated).

### 7.4 Publish
- **`POST /weekly-reports/{report}/publish`** (`publish-reports`) — validates `status=generated`, sets `published_at/by`, **locks** the report. Audited (`report_published`, DOC-17). A published report's `data`/artifacts are never mutated.

### 7.5 Supersede-don't-edit
- To amend a **published** period, generate a **new** report for the same period; it gets `supersedes_report_id` pointing at the prior one, and both are retained (the old shows a "superseded by WR-…" banner, the new shows "supersedes WR-…"). **No edit of a published report ever happens** — the audit trail is the chain of superseding reports.

---

## 8. API / routes summary

| Action | Route | Permission |
|---|---|---|
| List / detail | GET `/reports`, `/reports/{report}` | view-reports |
| Generate now | POST `/weekly-reports/generate` | generate-reports |
| Publish | POST `/weekly-reports/{report}/publish` | publish-reports |
| Download | GET `/weekly-reports/{report}/download?format=` | view-reports |
| Vehicle violations CRUD | `/reports/vehicle-violations…` | log-vehicle-violations |
| Report settings | GET/PUT `/settings/reports` | manage-settings |

**Read-only-role & PM note:** Project Manager and read-only Client Representative roles (DOC-03) get **published reports only** — the list/detail controllers filter to `status=published` for those roles, and drafts/generated reports are invisible to them.

---

## 9. Frontend (React / Inertia)

- **`pages/reports/index.tsx`** — WeeklyReportListPage: history (period, status chips, supersede badges), **Generate Now** (period picker), download buttons.
- **`pages/reports/show.tsx`** — WeeklyReportDetailPage: rendered sections i–ix with automation badges, completeness notes, publish button (when generated), supersede banner, downloads.
- **`pages/reports/vehicle-violations/index.tsx`** — list + LogVehicleViolationModal (with required action-taken).
- **`pages/settings/reports.tsx`** — schedule config (day/time/auto-publish).
- **Components:** `AutomationBadge`, `ReportSectionRenderer` (one per item type), `CompletenessNote`, `SupersedeBanner`.
- **Types (`types/report.ts`):** `WeeklyReport`, `ReportStatus`, `WeeklyReportData` (fully typed to the §4.3 contract), `VehicleViolation`, `ReportSettings`.

---

## 10. Real-life scenarios

- **Automatic Sunday report:** Sunday 06:00, `GenerateWeeklyReport` builds the prior week's report from all module data → Safety Managers get a notification → a manager reviews the rendered sections, confirms the vehicle-violation entries are logged, and publishes it → it locks; the client rep (read-only) can now see it.
- **Outage-honest report:** a gas gateway was down 30% of the week → item ix carries a completeness note "Gas telemetry offline 30% (Pole 2)" printed in the gas section → reviewers see the gap declared rather than a suspiciously clean chart.
- **Amendment:** after publishing, a late-classified incident needs inclusion → a manager regenerates the same week → the new report supersedes the old; both are retained with linked banners.
- **Manual item:** during the week an officer logs two vehicle violations with actions taken → they appear in item vii automatically at generation.

---

## 11. Tests (this doc's slice of DOC-21)

- **Generation:** `generate` produces all 9 `data` item keys (+ period/completeness); frozen `data` doesn't change when underlying records change afterward; manual HTTP generate is synchronous; scheduled auto-generate uses the `reports` queue.
- **Auto-inclusion:** every incident/LSR/vehicle-violation in the period appears in items ii/iii/vii (DOC-14 retention guarantee); false-positive PPE excluded from item i with the excluded count.
- **Completeness:** an item whose devices were offline >20% of the period gets a completeness note; ≤20% does not.
- **Item vi honesty:** reports a count + the scope-extension note; no fabricated telematics.
- **Publish-lock:** publishing requires `status=generated`, locks the report, audits `report_published`; a published report's `data`/artifacts are immutable.
- **Supersede:** regenerating a published period creates a new report with `supersedes_report_id`; the old is retained and banner-linked; no in-place edit.
- **Downloads:** PDF has one badged section per item + action-taken appendix; CSV zip has one file per item; signed-URL expiry.
- **Read-only visibility:** PM/Client-Rep see only published reports.
- Authorization: generate/publish/vehicle-violations gated by their permissions.

---

## 12. Open decisions logged

| # | Decision | Default | Confirm in |
|---|---|---|---|
| 1 | Reporting week boundary | Sunday–Saturday | DOC-18 |
| 2 | Identity in report iii (redacted variant for read-only recipients) | full for managers; redacted variant available | DOC-03/18 |
| 3 | Vehicle-violation `violation_type` seed list | speeding, seatbelt, unauthorized parking, reckless | DOC-18 |
| 4 | Charts in PDF (server-rendered vs embedded images) | server-rendered/embedded | this doc |
| 5 | Completeness threshold | 20% of period | DOC-18 |

---

### Next document
**DOC-16 — Dashboard & Display Mode:** the single `/api/dashboard/summary` aggregate, the role-aware widget grid (PM KPI variant), the 55″ authenticated kiosk `/display`, the shared live map, and the permission-driven navigation/sidebar.