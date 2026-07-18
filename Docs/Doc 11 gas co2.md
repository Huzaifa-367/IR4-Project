# DOC-11 — Gas & CO₂ Monitoring

> **Depends on:** DOC-01 (conventions, settings), DOC-03 (`view-gas`, `manage-gas-thresholds`, `acknowledge-alerts`), DOC-05 (gas/CO₂ devices + `device_ref`), DOC-07 (`gas_warning`/`gas_alarm` alerts), DOC-08 (shared `gas-readings` ingest endpoint + `gas` channel + backfill rule), DOC-19 (rollups + raw-reading pruning). **Feeds:** DOC-15 (weekly-report gas + CO₂ items), DOC-16 (gas dashboard + display panels).
>
> **Scope:** continuous gas & CO₂ telemetry — the readings stream (one endpoint carrying LEL/H₂S/O₂/CO **and** CO₂), **configurable thresholds**, **alarm evaluation with hysteresis auto-resolve**, the **backfill-creates-no-alarms** rule, **live per-device panels**, **trends** (raw + rollups), acknowledgement, and the weekly-report gas/CO₂ feed. **Out of scope:** the alert machinery (DOC-07), the ingest contract (DOC-08), and the physical detectors (DOC-05).

---

## 1. Purpose & the safety principle

Combustible/toxic-gas and CO₂ exposure is a life-safety hazard on industrial sites. Detectors continuously report LEL (% combustible), H₂S (ppm), O₂ (%), CO (ppm), and CO₂ (ppm). The platform stores every reading, evaluates it against **operator-configurable thresholds**, raises alarms, and trends the data for reporting.

**The critical safety principle (carried from the proposal §6.2):** the **detector alarms locally on site first** — audible/visual/vibrating at the device — regardless of the platform. The dashboard is a *secondary* awareness and record layer. This is why **backfilled readings never raise platform alarms** (§5.3): if a gateway was offline during an excursion, the crew was already warned locally; a dashboard alarm hours later would be dangerous noise. The platform's job is accurate recording, live awareness when connected, and reporting — not being the primary alarm.

---

## 2. Data origin

- **① device:** readings via `/api/ingest/gas-readings` (DOC-08) — gas detectors (LEL/H₂S/O₂/CO, often via a Wi-Fi gateway) and CO₂ sensors (via edge RS485). One endpoint; each reading carries whichever channels the sending device measures.
- **② system:** alarm evaluation, hysteresis auto-resolve, rollups, weekly stats, live-panel broadcasts.
- **③ user:** threshold configuration (`manage-gas-thresholds`, audited) and alarm **acknowledgement** (`acknowledge-alerts`). Reading values are **immutable** — no user endpoint writes readings (DOC-01 §9).

---

## 3. Data model

### 3.1 Storage decision: one readings table with nullable channels
DOC-08 leaves gas-vs-CO₂ table layout to this doc. **Decision: a single `gas_readings` table** with a nullable column per channel. A reading populates whichever channels its device measures; a pure CO₂ sensor fills only `co2_ppm`, a multi-gas detector fills the four gas channels, a combined device fills all five. This keeps ingestion, rollups, trends, and retention uniform (one code path), and matches the single ingest endpoint. (Rationale over two tables: the streams share identical bookkeeping — recorded_at/received_at/is_backfill/event_uid, rollups, pruning — so one table halves the machinery. `[CONFIRM AT DESIGN]` if a client insists on physical separation.)

```php
Schema::create('gas_readings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('device_id')->constrained()->cascadeOnDelete();  // the detector/sensor
    $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete(); // the pole/unit it's on (denormalized for panels/trends)
    $table->timestamp('recorded_at')->index();
    $table->timestamp('received_at');
    $table->decimal('lel_pct', 6, 2)->nullable();
    $table->decimal('h2s_ppm', 8, 2)->nullable();
    $table->decimal('o2_pct', 5, 2)->nullable();
    $table->decimal('co_ppm', 8, 2)->nullable();
    $table->decimal('co2_ppm', 10, 2)->nullable();
    $table->boolean('is_backfill')->default(false);
    $table->boolean('clock_skew')->default(false);
    $table->string('event_uid');
    $table->timestamps();
    $table->unique(['device_id', 'event_uid']);       // idempotency (DOC-08)
    $table->index(['device_id', 'recorded_at']);
});
```

### 3.2 `gas_reading_rollups` (hourly, for trends beyond 24 h — DOC-19)
```php
Schema::create('gas_reading_rollups', function (Blueprint $table) {
    $table->id();
    $table->foreignId('device_id')->constrained()->cascadeOnDelete();
    $table->timestamp('bucket_start');                // hour bucket
    // per-channel min/avg/max + sample count
    $table->decimal('lel_min',6,2)->nullable(); $table->decimal('lel_avg',6,2)->nullable(); $table->decimal('lel_max',6,2)->nullable();
    $table->decimal('h2s_min',8,2)->nullable(); $table->decimal('h2s_avg',8,2)->nullable(); $table->decimal('h2s_max',8,2)->nullable();
    $table->decimal('o2_min',5,2)->nullable();  $table->decimal('o2_avg',5,2)->nullable();  $table->decimal('o2_max',5,2)->nullable();
    $table->decimal('co_min',8,2)->nullable();  $table->decimal('co_avg',8,2)->nullable();  $table->decimal('co_max',8,2)->nullable();
    $table->decimal('co2_min',10,2)->nullable();$table->decimal('co2_avg',10,2)->nullable();$table->decimal('co2_max',10,2)->nullable();
    $table->unsignedInteger('sample_count')->default(0);
    $table->timestamps();
    $table->unique(['device_id', 'bucket_start']);
});
```
Built by the hourly `BuildSensorRollups` job (DOC-19); raw readings pruned after `retention.sensor_readings_days` (default 180) once rolled up.

### 3.3 `gas_thresholds` (③ configurable, audited)
```php
Schema::create('gas_thresholds', function (Blueprint $table) {
    $table->id();
    $table->string('gas_type');                       // enum GasType (§3.5)
    $table->decimal('warning_level', 10, 2);
    $table->decimal('alarm_level', 10, 2);
    $table->string('unit');                           // %LEL, ppm, %vol
    $table->string('direction')->default('above');    // enum: above|below  (O₂-low is 'below')
    $table->boolean('is_active')->default(true);
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->unique(['gas_type']);
});
```
Thresholds are **global** to the installation (not per-device) in v1 — one LEL threshold applies to all LEL-measuring devices `[CONFIRM AT DESIGN]` (per-device overrides if needed later).

### 3.4 `gas_alarms` (soft-deleted, kept forever)
```php
Schema::create('gas_alarms', function (Blueprint $table) {
    $table->id();
    $table->foreignId('device_id')->constrained()->cascadeOnDelete();
    $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
    $table->string('gas_type');                       // GasType
    $table->string('level');                          // enum: warning|alarm
    $table->decimal('reading_value', 10, 2);
    $table->decimal('threshold_value', 10, 2);
    $table->timestamp('triggered_at');
    $table->timestamp('resolved_at')->nullable();
    $table->foreignId('alert_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('acknowledged_at')->nullable();
    $table->boolean('during_outage')->default(false); // true if the exceedance was in backfilled data
    $table->timestamps();
    $table->softDeletes();
    $table->index(['device_id', 'gas_type', 'level']);
});
```

### 3.5 Enums
- **`GasType`:** `lel`, `h2s`, `o2_low`, `o2_high`, `co`, `co2`. (O₂ has two threshold rows — low-oxygen `below` and enriched-oxygen `above`.)
- **`GasAlarmLevel`:** `warning`, `alarm`.
- **`ThresholdDirection`:** `above`, `below`.

**Seed defaults `[CONFIRM AT DESIGN — safety-critical, confirm with safety lead]`:** LEL warn 10 / alarm 20 (%LEL) · H₂S warn 5 / alarm 10 (ppm) · O₂-low warn 19.5 / alarm 19.0 (%) · O₂-high warn 23.0 / alarm 23.5 (%) · CO warn 25 / alarm 50 (ppm) · CO₂ warn 5000 / alarm 30000 (ppm).

---

## 4. `GasMonitoringService` (②)

Entry from DOC-08: `ingest(array $events, Device $device)`. Per event (after dedupe/skew/backfill classification):
- Insert the `gas_reading` (idempotent), populating only the channels present, with `asset_id` denormalized from the device.
- If **live** (≤10 min): `evaluate()` each present channel; broadcast a throttled `GasLiveUpdated` (per-device latest panel) on the `gas` channel.
- If **backfill**: store + roll up only; **no evaluate, no broadcast** (§5.3).

### 4.1 `evaluate(reading)`
For each present channel, look up its active threshold(s):
- O₂ is dual-bounded: below `o2_low` **or** above `o2_high`.
- Others compare per `direction` (all `above` except o2_low).
- Determine the crossing level: none / warning / alarm.
- **If crossing into warning or alarm and no open alarm exists for this device+gas+level:** create a `gas_alarm` row and raise the alert (`gas_warning` = warning; `gas_alarm` = critical + audible; dedupe key `gas:{device}:{gas}:{level}` — DOC-07). Warning→alarm escalation closes the warning-level alarm and opens an alarm-level one.

### 4.2 Hysteresis auto-resolve
Threshold chatter (a value hovering right at the line) would otherwise flap alarms on/off. So resolution requires **two consecutive live readings** back under `(warning_level − 5% of the warn→alarm span)` before the alarm auto-resolves (sets `resolved_at`, `AlertService::resolveByDedupeKey`). Tunable via `gas.hysteresis_margin_pct` (DOC-18).

### 4.3 Weekly stats
`weeklyStats(from, to)` → per-gas per-day min/avg/max (from rollups) + the list of alarm events in the period (time, device/asset, gas, level, peak value, duration, acknowledged_by, `during_outage`). Feeds DOC-15 items (ix) gas and (x) CO₂.

---

## 5. Alarm behavior details

### 5.1 Acknowledgement (③)
`POST /gas/alarms/{alarm}/acknowledge` (`acknowledge-alerts`) sets `acknowledged_by/at` on the alarm and acknowledges the linked alert (DOC-07). Acknowledgement ≠ resolution — the alarm resolves only when readings normalize (hysteresis) or, if it never does live, is handled operationally.

### 5.2 Escalation to health (cross-ref DOC-05)
If a gas detector/gateway goes **offline > 30 min**, DOC-05's health monitor raises the additional critical `system` alert ("gas telemetry lost… detector local alarms still active"). That's a *health* alert, not a gas alarm — the two are complementary.

### 5.3 Backfill creates no alarms (the key rule)
Backfilled readings (>10 min old, DOC-08) are **stored and rolled up** but **do not** call `evaluate()` — no `gas_alarm`, no alert. The detector already alarmed locally on site during the actual excursion (§1). However, backfilled **exceedances still appear** in trends and in the weekly report's alarm section, flagged `during_outage=true` ("recorded during telemetry outage") — so the record is complete and honest without generating dangerous late alarms. (Reconstructing the backfilled exceedance into the report is done by `weeklyStats` scanning readings, not by creating alarm rows — or by creating a `gas_alarm` with `during_outage=true` purely for reporting, never linked to a live alert. `[CONFIRM AT DESIGN]` which; default: derive from readings for the report, no alarm row.)

---

## 6. Thresholds management (③, `manage-gas-thresholds`)

- **`GET /gas/thresholds`** (`view-gas`) / **`PUT /gas/thresholds`** (`manage-gas-thresholds`) — edit warning/alarm levels per gas. Every change writes a `config_changed` audit row with before/after (DOC-17) and shows "last changed by/at" in the UI. Only Safety-Manager-level roles hold this by default (DOC-03).
- Changing a threshold does **not** retroactively re-evaluate historical readings; it applies to subsequent live evaluations.

---

## 7. Live panels & trends (reads, `view-gas`)

- **`GET /api/gas/live`** — latest reading per device (one panel each) + any open-alarm flags + a **stale badge** if the device's last reading is older than ~5 min (device likely offline — cross-ref DOC-05). Cached ~5 s; also pushed via `GasLiveUpdated`.
- **`GET /api/gas/trends?gas_type&device_id&range=shift|day|week|custom`** — raw readings for ≤24 h, rollups beyond (DOC-19). Returns downsampled series (min/avg/max per bucket).
- **`GET /gas/alarms`** — filterable alarm history (gas, level, device, resolved, date range).

---

## 8. Frontend (React / Inertia)

- **`pages/gas/index.tsx`** — GasDashboardPage: one **panel per device** — LEL/H₂S/O₂/CO gauges + a CO₂ tile — colored green/amber/red vs thresholds, a stale badge if telemetry is old, and an alarm banner strip. Live via the `gas` channel + poll fallback (DOC-08 §5.4).
- **`pages/gas/trends/index.tsx`** — line charts (recharts), gas + device + range selectors.
- **`pages/gas/alarms/index.tsx`** — alarm history with acknowledge action, resolved/`during_outage` badges.
- **`pages/gas/thresholds/index.tsx`** — editable threshold table (role-gated), "last changed by/at".
- **Components:** `GasGauge`, `GasDevicePanel`, `Co2Tile`, `ThresholdEditor`, `GasTrendChart`.
- **Types (`types/gas.ts`):** `GasReading`, `GasType`, `GasThreshold`, `GasAlarm`, `GasAlarmLevel`, `GasLivePanel`, `GasTrendSeries`.
- Panels update from `GasLiveUpdated`; alarm strip from `GasAlarmRaised`/`GasAlarmResolved`.

---

## 9. Real-life scenarios

- **H₂S excursion (live):** a detector reports 12 ppm → `evaluate` crosses the alarm level → `gas_alarm` row + critical audible alert + the device panel turns red → operator acknowledges (chime stops), radios the field → readings fall → two consecutive clean reads under the hysteresis margin → auto-resolve → the episode is one alarm row with the acknowledgement recorded, and appears in the weekly report.
- **Gateway outage (backfill):** a gas gateway drops for 90 min while a brief LEL spike occurs on site (detector alarmed locally) → on reconnect the buffered readings flush as backfill → **no platform alarm fires** → the spike still shows in the trend and the weekly report flagged "during telemetry outage" → operators see it happened without a dangerous late siren.
- **Bump test:** detector removed for calibration → operator sets the device to `maintenance` (DOC-05) → no offline/health noise → readings resume after.
- **Threshold change:** safety manager lowers the CO alarm level → audited with before/after → applies to subsequent readings; history unchanged.

---

## 10. Tests (this doc's slice of DOC-21)

- **Ingest:** a reading populates only its present channels; idempotent on `event_uid`; `asset_id` denormalized; live evaluates, backfill does not.
- **Evaluate:** crossing warning/alarm creates a `gas_alarm` + correct-severity alert (dedup one per device+gas+level); O₂ dual-bound (low and high) works; warning→alarm escalation closes the warning alarm.
- **Hysteresis:** an alarm does **not** resolve on a single dip; resolves after two consecutive reads under the margin; no flapping at the threshold.
- **Backfill-no-alarm:** backfilled exceedances create **no** alarm/alert but appear in trends/weekly stats flagged `during_outage`.
- **Acknowledge:** sets fields + acknowledges the alert; ack ≠ resolve.
- **Thresholds:** update requires `manage-gas-thresholds`, audited before/after; does not retro-evaluate history; O₂ has two rows.
- **Trends:** ≤24 h reads raw, beyond reads rollups; series min/avg/max correct.
- **Immutability:** no route updates reading values.
- **Live/stale:** `/api/gas/live` returns latest per device with a stale badge past ~5 min.
- Authorization: view/thresholds/acknowledge gated by permission.

---

## 11. Open decisions logged

| # | Decision | Default | Confirm in |
|---|---|---|---|
| 1 | One `gas_readings` table (nullable channels) vs separate gas/CO₂ tables | **one table** | this doc |
| 2 | Global thresholds vs per-device overrides | global (v1) | this doc |
| 3 | Seed threshold values | listed defaults — **confirm with safety lead** | DOC-18 |
| 4 | Backfilled exceedance in reports: derive from readings vs `during_outage` alarm row | derive from readings | this doc / DOC-15 |
| 5 | Hysteresis margin | 5% of warn→alarm span | DOC-18 |

---

### Next document
**DOC-12 — Environmental Data:** the RS485 weather stream (temperature/humidity/wind + optional air-quality), rollups, the live weather widget + trends, and the weekly-report weather/environmental items — the smallest module (pure ①→② with no user workflow).