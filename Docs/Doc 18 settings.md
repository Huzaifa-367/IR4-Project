# DOC-18 — Settings & Configuration

> **Depends on:** DOC-01 (settings mechanism, conventions), DOC-03 (`manage-settings`; per-key gating), DOC-17 (`config_changed` audit). **Feeds:** every module that reads a tunable value — this is the single registry of all runtime configuration.
>
> **Scope:** the **consolidated settings registry** — every runtime-tunable key introduced across the docs, its default, unit, who may edit it, and where it's used; the `SettingsService`; the **config-vs-`.env` boundary**; and the general settings editor with per-key permission gating. This doc is the **single home** for the `[CONFIRM AT DESIGN]` tunables accumulated in DOC-02–17. **Out of scope:** the *behavior* each value drives (owned by its module) — this doc catalogues and governs the values.

---

## 1. Purpose & the config-vs-settings boundary

Two kinds of configuration, kept strictly separate:

- **Runtime settings** (this doc) — values an authorized user may change while the system runs, without a deploy: thresholds, timeouts, schedules, throttles. Stored in the `settings` table, editable in the UI, audited.
- **Deploy config** (`.env` / `config/*`) — values fixed at install by an administrator with server access: DB credentials, Reverb host, queue driver, disk paths, printer IP, reverse-proxy rules. **Not** in the settings table; changing them is a deploy/ops action (DOC-20).

**Rule of thumb:** if an operator or safety manager should be able to tune it during operations → `settings`. If it needs server access to change → `.env`/`config`.

Defaults for every setting live in `config/ir4.php`; the `settings` table overrides them at runtime. On first boot, `SettingsSeeder` writes the defaults (idempotent).

---

## 2. Data model & service

### 2.1 `settings`
```php
Schema::create('settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();                     // dotted key, e.g. 'tracking.gate_debounce_seconds'
    $table->json('value');                               // typed value (bool/int/float/string/array)
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

### 2.2 `SettingsService`
- `get(string $key, mixed $default = null): mixed` — reads the row, falling back to `config('ir4.…')` then `$default`; cached (flushed on write).
- `set(string $key, mixed $value): void` — validates against the key's schema (§4 — type/range/enum), writes the row, flushes cache, and records a **`config_changed`** audit row with old→new (DOC-17). Permission-checked at the controller by the key's required permission (§3).
- `all(): array` / `group(string $prefix): array` — for the editor UI.
- Keys are **whitelisted** — `set` on an unknown key is rejected (no arbitrary key injection).

---

## 3. Permission gating (per-key)

Not all settings are equally sensitive. Each key declares the permission required to edit it:
- **`manage-settings`** — general operational tunables (timeouts, throttles, schedules, thresholds-config that aren't safety-critical).
- **`manage-gas-thresholds`** — gas/CO₂ threshold values (safety-critical; DOC-11).
- **`manage-settings` + confirmation** — retention/wipe-adjacent and security keys (session timeout, lockout) require an extra confirm step in the UI.
Reads of settings are available to the modules that need them (server-side); the editor UI requires the relevant edit permission to show a key as editable (otherwise read-only/hidden).

---

## 4. The settings registry (authoritative — every tunable key)

Consolidated from DOC-02–17. Each row: key · default · unit/type · edit permission · used by.

### 4.1 General
| key | default | type | edit perm | used by |
|---|---|---|---|---|
| `general.timezone` | `Asia/Riyadh` | tz string | manage-settings | display/reports/scheduler (DOC-01) |
| `general.locale` | `en` | string | manage-settings | UI |
| `general.theme_default` | `dark` | enum(dark,light) | manage-settings | DOC-16 |

### 4.2 Authentication & session (DOC-02)
| key | default | type | edit perm | used by |
|---|---|---|---|---|
| `auth.session_timeout_minutes` | `15` | int | manage-settings + confirm | idle timeout |
| `auth.login_max_per_min` | `5` | int | manage-settings | login throttle |
| `auth.lockout_threshold` | `10` | int | manage-settings | lockout |
| `auth.lockout_minutes` | `15` | int | manage-settings | lockout |
| `auth.password_min_length` | `12` | int | manage-settings + confirm | password policy |
| `auth.require_2fa_for_admins` | `false` | bool | manage-settings + confirm | 2FA gate |

### 4.3 Alerts (DOC-07)
| key | default | type | edit perm | used by |
|---|---|---|---|---|
| `alert.audible_enabled` | `true` | bool | configure-alerts | audible master switch |
| `alert.warning_toast_seconds` | `10` | int | manage-settings | toast dismiss |

### 4.4 Ingestion & real-time (DOC-08)
| key | default | type | edit perm | used by |
|---|---|---|---|---|
| `ingest.max_batch` | `1000` | int | manage-settings | batch cap |
| `ingest.rate_per_minute` | `120` | int | manage-settings | per-device throttle |
| `ingest.future_skew_seconds` | `300` | int | manage-settings | clock-skew clamp |
| `ingest.backfill_after_seconds` | `600` | int | manage-settings | backfill classification |
| `realtime.headcount_throttle_seconds` | `5` | int | manage-settings | broadcast coalescing |
| `realtime.positions_throttle_seconds` | `5` | int | manage-settings | broadcast coalescing |
| `realtime.gas_throttle_seconds` | `5` | int | manage-settings | broadcast coalescing |
| `realtime.poll_fallback_seconds` | `30` | int | manage-settings | poll fallback |

### 4.5 Hardware health (DOC-05)
| key | default | type | edit perm | used by |
|---|---|---|---|---|
| `health.reader_stale_minutes` | `5` | int | manage-settings | markStale |
| `health.gas_stale_minutes` | `5` | int | manage-settings | markStale |
| `health.sensor_stale_minutes` | `5` | int | manage-settings | markStale (co2/env/gateway) |
| `health.edge_stale_minutes` | `3` | int | manage-settings | markStale |
| `health.camera_stale_minutes` | `3` | int | manage-settings | markStale (last_frame_at) |
| `health.gas_offline_escalate_minutes` | `30` | int | manage-settings | gas-telemetry-lost escalation |

### 4.6 Tracking / RFID (DOC-09)
| key | default | type | edit perm | used by |
|---|---|---|---|---|
| `tracking.gate_debounce_seconds` | `60` | int | manage-settings | gate flap prevention |
| `tracking.stationary_tag_minutes` | `15` | int | manage-settings | stationary detection |
| `tracking.tag_offsite_after_hours` | `14` | int | manage-settings | absence sweep |
| `tracking.worker_down_window_minutes` | `10` | int | manage-settings | fall+stationary correlation |
| `tracking.headcount_cache_seconds` | `5` | int | manage-settings | headcount read cache |

### 4.7 Gas (DOC-11) — safety-critical
| key | default | type | edit perm | used by |
|---|---|---|---|---|
| `gas.hysteresis_margin_pct` | `5` | float | manage-gas-thresholds | alarm auto-resolve |
| gas thresholds | see below | rows in `gas_thresholds` | manage-gas-thresholds | evaluation |

Gas threshold **seed values** (in `gas_thresholds`, DOC-11 — **confirm with safety lead**, not just an admin): LEL 10/20 %LEL · H₂S 5/10 ppm · O₂-low 19.5/19.0 % · O₂-high 23.0/23.5 % · CO 25/50 ppm · CO₂ 5000/30000 ppm.

### 4.8 Environmental (DOC-12)
| key | default | type | edit perm | used by |
|---|---|---|---|---|
| `env.thresholds_enabled` | `false` | bool | manage-settings | (v1 has no env alarms) |

### 4.9 Equipment (DOC-13)
| key | default | type | edit perm | used by |
|---|---|---|---|---|
| `equipment.public_rate_limit_per_min` | `30` | int | manage-settings | public QR page |
| `equipment.default_is_checkoutable` | `false` | bool | manage-settings | new equipment default |
| `equipment.overdue_return_alerts` | `false` | bool | manage-settings | overdue-return flag vs alert |
| `equipment.printer_host` | *(env)* | — | **`.env`** | ZT411 IP (deploy config) |
| `equipment.printer_port` | `9100` | int | **`.env`** | ZT411 raw TCP (deploy config) |

### 4.10 Reports (DOC-15)
| key | default | type | edit perm | used by |
|---|---|---|---|---|
| `report.generation_day` | `sunday` | enum(mon…sun) | manage-settings | scheduled generation |
| `report.generation_time` | `06:00` | time | manage-settings | scheduled generation |
| `report.auto_publish` | `false` | bool | manage-settings | auto-publish |
| `report.week_start` | `sunday` | enum(mon…sun) | manage-settings | reporting week boundary |
| `report.completeness_threshold_pct` | `20` | int | manage-settings | outage-note threshold |

### 4.11 Retention & backup (DOC-19)
| key | default | type | edit perm | used by |
|---|---|---|---|---|
| `retention.tag_readings_days` | `90` | int | manage-settings + confirm | raw tag-read pruning |
| `retention.sensor_readings_days` | `180` | int | manage-settings + confirm | raw gas/env pruning |
| `retention.exports_days` | `7` | int | manage-settings | export-file cleanup |
| `backup.keep_count` | `30` | int | manage-settings | DB backup rotation |
| *(compliance tables — never pruned)* | — | — | — | alerts/incidents/LSR/reports/audit (DOC-19) |

### 4.12 Display (DOC-16)
| key | default | type | edit perm | used by |
|---|---|---|---|---|
| `display.cycle_seconds` | `20` | int | manage-settings | kiosk pane rotation |
| `display.keep_session_alive` | `true` | bool | manage-settings | wall session (DOC-02) |
| `dashboard.cache_seconds` | `8` | int | manage-settings | summary cache |

**Note — no `ppe.min_confidence`:** removed (edge handles thresholding, DOC-10). Any doc referencing it is superseded by this registry.

---

## 5. Editor UI (`manage-settings`)

- **`GET /settings/general`** (Inertia) — SettingsPage: keys grouped by the §4 sections, each rendered by type (toggle, number, time, enum select). A key shows as editable only if the user holds its edit permission; safety-critical and security keys (gas thresholds, session timeout, retention) require a **confirm dialog** with the old→new shown.
- Every save calls `SettingsService::set` → validates → audits (`config_changed`, DOC-17). The UI shows "last changed by/at" per key.
- Gas thresholds have their own editor (DOC-11 `/gas/thresholds`) since they're safety-critical rows, not simple key/values; the general editor links to it.
- Invalid values (out of range/type) are rejected with a clear inline error (DOC-01 error contract).

---

## 6. Frontend (React / Inertia)

- **`pages/settings/general/index.tsx`** — grouped editor; per-type field components; confirm dialogs for sensitive keys; "last changed" metadata.
- **Components:** `SettingField` (renders by type), `SensitiveSettingConfirm`, `SettingGroup`.
- **Types (`types/settings.ts`):** `AppSettings` (typed keys), `SettingSchema`, `SettingGroupKey`.

---

## 7. Real-life scenarios

- **Tuning debounce:** operators report occasional gate in/out flapping in a crowded shift → a manager raises `tracking.gate_debounce_seconds` 60→90 → audited → applies live; no deploy.
- **Safety-critical change:** a manager updates the CO alarm threshold → goes through the gas-thresholds editor (not the general one), requires confirm, audited with old→new (DOC-11/17).
- **Schedule shift:** the client wants the weekly report on Saturday morning → change `report.generation_day`/`week_start` → next run follows the new schedule.
- **Retention adjustment:** storage is tight → raise pruning aggressiveness by lowering `retention.sensor_readings_days` (with confirm) — compliance tables remain untouched regardless (DOC-19).
- **Blocked injection:** a crafted request tries to `set('arbitrary.key', …)` → rejected (not whitelisted).

---

## 8. Tests (this doc's slice of DOC-21)

- **Get/set:** `get` falls back config→default; `set` validates type/range/enum, writes, flushes cache, audits `config_changed` with old→new.
- **Whitelist:** `set` on an unknown key → rejected.
- **Permission gating:** editing a `manage-gas-thresholds` key without that permission → 403; general keys require `manage-settings`; sensitive keys require confirm.
- **Seed:** `SettingsSeeder` writes all §4 defaults idempotently; re-run doesn't clobber operator changes (only fills missing keys).
- **Applied live:** changing `tracking.gate_debounce_seconds` affects subsequent gate evaluation without a restart (integration).
- **Audit:** every `set` produces a `config_changed` row (DOC-17 integration).

---

## 9. Open decisions logged (consolidated — the ones deferred across docs)

| # | Key/decision | Default applied | Owner to confirm |
|---|---|---|---|
| 1 | Gas threshold seed values | listed (§4.7) | **safety lead** (not just admin) |
| 2 | Reporting week boundary | Sunday–Saturday | client/safety lead |
| 3 | Session timeout | 15 min | client |
| 4 | Lockout thresholds | 5/min, 10 fails, 15 min | client |
| 5 | Mandatory 2FA for admins | off | client |
| 6 | `equipment.default_is_checkoutable` | off | client |
| 7 | Overdue-return: flag vs alert | flag only | client |
| 8 | Retention windows | 90 d tags / 180 d sensors | client (storage) |
| 9 | Printer host/port | `.env` (deploy) | ops (DOC-20) |
| 10 | Display cycle interval | 20 s | client |

These are the settings the client/safety lead should review at commissioning; all have working defaults so nothing blocks the build.

---

### Next document
**DOC-19 — Data Retention, Rollups, Backup & End-of-Project:** the sensor rollup jobs, raw-data pruning (never touching compliance tables), the data-volume math, encrypted daily backups, and the `ir4:export-all` / `ir4:secure-wipe` commands for end-of-project handover.