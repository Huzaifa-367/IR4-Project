# DOC-12 — Environmental Data

> **Depends on:** DOC-01 (conventions, settings), DOC-03 (`view-dashboard` / `view-gas`-style read perms — see §6), DOC-05 (environmental_sensor devices), DOC-08 (shared `environmental-readings` ingest + `environment` channel + backfill rule), DOC-19 (raw-reading pruning). **Feeds:** DOC-15 (weekly-report weather item iv + environmental item viii), DOC-16 (weather widget on the dashboard + display).
>
> **Scope:** ambient environmental telemetry — temperature, humidity, wind speed, and optional air-quality parameters from RS485/Modbus weather sensors; storage, the live weather widget + trends (on-read aggregates beyond 24 h, like gas), and the weekly-report feed. This is the **smallest module**: pure ①→②, **no user workflow** (no thresholds, no alarms, no manual entry). **Out of scope:** the ingest contract (DOC-08), the sensors (DOC-05), report rendering (DOC-15).

---

## 1. Purpose

Site conditions (heat, humidity, wind, air quality) matter for safety planning and are a required line in the weekly report. Client-supplied environmental sensors feed the platform over RS485/Modbus via an edge unit. The module simply **records and displays** this data and aggregates it on read for reporting — there are no operator actions and no alarms (a heat/wind alarm capability is a deliberate non-goal for v1; `[CONFIRM AT DESIGN]` if the client wants environmental thresholds later, it would reuse DOC-11's threshold/alarm pattern).

---

## 2. Data origin

- **① device:** readings via `/api/ingest/environmental-readings` (DOC-08) from an `environmental_sensor` device (edge RS485).
- **② system:** weekly stats (SQL aggregates over raw), live-widget broadcast.
- **③ user:** **none.** No thresholds, no manual entry, no review. Pure display/report data.

---

## 3. Data model

### 3.1 `environmental_readings`
```php
Schema::create('environmental_readings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('device_id')->constrained()->cascadeOnDelete();
    $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
    $table->timestamp('recorded_at')->index();
    $table->timestamp('received_at');
    $table->decimal('temperature_c', 5, 2)->nullable();
    $table->decimal('humidity_pct', 5, 2)->nullable();
    $table->decimal('wind_speed_ms', 6, 2)->nullable();
    $table->json('extra')->nullable();                 // optional air-quality params (PM2.5, PM10, etc.) — Phase-2 sensor audit
    $table->boolean('is_backfill')->default(false);
    $table->boolean('clock_skew')->default(false);
    $table->string('event_uid');
    $table->timestamps();
    $table->unique(['device_id', 'event_uid']);        // idempotency (DOC-08)
    $table->index(['device_id', 'recorded_at']);
});
```
- Every metric column is **nullable** — a given sensor reports whatever it measures (some send only temp/humidity/wind; air-quality-capable sensors add to `extra`). The `extra` JSON keeps the schema open for parameters confirmed during the Phase-2 sensor audit without a migration.

There is **no** `environmental_rollups` table. Trends beyond 24 h and DOC-15 items **iv** / **viii** aggregate raw rows with SQL `GROUP BY` hour/day (indexed `recorded_at` / `(device_id, recorded_at)`). Raw readings prune after `retention.sensor_readings_days` (default 180) per DOC-19 — environmental has no rollup gate (same pattern as gas, DOC-11).

---

## 4. `EnvironmentalDataService` (②)

- `ingest(array $events, Device $device)` — insert readings (idempotent), populate present metrics + `extra`, denormalize `asset_id`; if **live** (≤10 min), broadcast a throttled `EnvironmentUpdated` (latest values) on the `environment` channel; **backfill** stores only (DOC-08 §5.3). No evaluation/alarms.
- `latest()` — most-recent reading per sensor for the widget.
- `weeklyStats(from, to)` — daily min/avg/max per metric (SQL over raw), for DOC-15 items (iv) weather and (viii) environmental.

---

## 5. Live widget & trends (reads)

- **`GET /api/environment/live`** — latest values per sensor (temp, humidity, wind, any air-quality) + `recorded_at` + a stale badge if old (device likely offline — DOC-05). Cached ~5 s; also pushed via `EnvironmentUpdated`.
- **`GET /api/environment/trends?parameter&range=day|week|custom`** — raw point series for ≤24 h; SQL hourly min/avg/max over raw beyond that.

---

## 6. Permissions

Environmental data is low-sensitivity ambient information. Read access is granted to any authenticated user with `view-dashboard` (the weather widget is a dashboard element), and the trends page uses the same. There is **no** `manage-*` permission for this module — nothing to manage. (If environmental thresholds are added later, a `manage-environmental-thresholds` permission would be introduced then.)

---

## 7. Frontend (React / Inertia)

- **`components/ir4/WeatherWidget.tsx`** — dashboard widget (DOC-16): temperature, humidity, wind, optional air-quality tiles, `updated-at`, stale indicator. Live via the `environment` channel + poll fallback.
- **`pages/environment/index.tsx`** — EnvironmentTrendPage: parameter selector, range selector, trend chart (recharts).
- **Types (`types/environment.ts`):** `EnvironmentalReading`, `EnvironmentLive`, `EnvironmentTrendSeries`.
- Widget updates from `EnvironmentUpdated`.

---

## 8. Real-life scenarios

- **Normal:** the weather sensor reports every minute → the dashboard widget shows live temp/humidity/wind → the weekly report's weather item shows daily min/avg/max from raw aggregates.
- **Hot afternoon:** temperature climbs through the day → visible live on the widget and in the day's trend; no alarm (v1 has none) — it's awareness/record only.
- **Outage:** the sensor's edge link drops → widget shows a stale badge (and DOC-05 health flags the device offline) → on reconnect buffered readings flush as backfill → trend/report complete from raw; no live spike shown for the backfilled period.
- **Air-quality sensor added later:** a PM2.5-capable sensor is registered → its readings populate `extra.pm25` with no migration → the widget and trends pick up the new parameter (frontend reads available keys from `extra`).

---

## 9. Tests (this doc's slice of DOC-21)

- **Ingest:** populates present metrics + `extra`; idempotent on `event_uid`; `asset_id` denormalized; live broadcasts, backfill does not; no alarms/evaluation anywhere.
- **Trends:** ≤24 h reads raw points; beyond reads SQL hourly aggregates over raw; series min/avg/max correct.
- **Live/stale:** `/api/environment/live` returns latest per sensor with a stale badge past threshold.
- **No user writes:** there is no endpoint to create/edit readings or any threshold (module is read-only for users).
- **Extra params:** an air-quality key in `extra` flows to the widget/trends without a schema change.
- Authorization: read gated by `view-dashboard`.

---

## 10. Open decisions logged

| # | Decision | Default | Confirm in |
|---|---|---|---|
| 1 | Environmental thresholds/alarms | none in v1 (reuse DOC-11 pattern if added) | this doc / DOC-18 |
| 2 | Air-quality parameters | open via `extra` JSON; confirmed at Phase-2 sensor audit | this doc / DOC-05 |
| 3 | Which metrics are required vs optional | all nullable (sensor-dependent) | this doc |

---

### Next document
**DOC-13 — QR Equipment Monitoring:** the all-manual equipment registry with permanent QR tokens, inspections/maintenance/schedules/documents, status auto-rules, overdue flagging, ZPL label printing, CSV commissioning import, and the public unauthenticated LAN QR page.