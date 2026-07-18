# DOC-07 — Unified Alerts & Notifications

> **Depends on:** DOC-01 (conventions, enums, settings, resources), DOC-03 (`acknowledge-alerts`, `configure-alerts`), DOC-05 (device/camera offline sources), DOC-06 (zone-rule sources). **Feeds:** every module that raises alerts (DOC-05 health, DOC-09 tracking rules, DOC-10 PPE, DOC-11 gas, DOC-14 HSE), DOC-08 (Reverb delivery), DOC-14 (alerts *suggest* — and prefill — user-created incidents/LSR, but never auto-create them), DOC-16 (dashboard alert panel + display banner), DOC-17 (acknowledgements audited).
>
> **Scope:** the **one** alert pipeline the whole platform funnels into — the `alerts` table, the alert-type catalogue, severity/audible rules, the state machine, **deduplication**, the **suggested-action map** (alerts that prompt — and prefill — user-created incidents/LSR, without creating them), acknowledgement vs auto-resolve, real-time delivery, and the alert-centre + notification UI. **Out of scope:** the domain logic that *decides* to raise each alert (owned by the source module) — this doc defines the shared machinery they call.

---

## 1. Purpose & why it's unified

Safety events arrive from many subsystems — a missing helmet (PPE), an H₂S spike (gas), a worker in a red zone (RFID), a fall (camera), a dead gas gateway (health). If each raised, displayed, and tracked notifications its own way, operators would face inconsistent, un-acknowledgeable, un-auditable noise. Instead, **every subsystem raises into one `AlertService`**, which gives the platform: one consistent notification stream, one acknowledgement workflow, one deduplication policy (so a flapping device or a stuck zone doesn't storm the wall), one audible policy, and one retained, auditable history.

Two hard rules (DOC-21 invariants):
- **Alerts are never pruned.** They are compliance-relevant and retained for the life of the deployment (soft-delete only). DOC-19 retention explicitly excludes them.
- **Every alert is raised by system code (path ②)** — services/jobs, never a user endpoint. Users only **acknowledge** (③) or trigger manual **resolve** (③). No "create alert" write endpoint exists.

---

## 2. Data origin

- **② system:** `AlertService::raise(...)` is called only from services/jobs (health monitor, tracking rules, gas evaluation, PPE ingest, HSE correlator, evacuation trigger). Auto-resolve is also ②.
- **③ user:** `acknowledge` (permission `acknowledge-alerts`) and manual `resolve` (permission `configure-alerts`) — the only human writes, both audited.
- **① device:** none directly — devices send readings/detections (DOC-08); the *evaluation* of those into alerts is ②.

---

## 3. Data model

### 3.1 `alerts`
```php
Schema::create('alerts', function (Blueprint $table) {
    $table->id();
    $table->string('alert_type');                         // enum AlertType (§4)
    $table->string('severity');                           // enum AlertSeverity: info|warning|critical
    $table->string('title');                              // human one-liner for the toast/row
    $table->json('payload')->nullable();                  // typed context (ids, values, worker/zone/device refs)
    $table->nullableMorphs('alertable');                  // polymorphic link to the source record (violation, alarm, incident, device…)
    $table->string('status')->default('open');            // enum AlertStatus: open|acknowledged|resolved
    $table->timestamp('raised_at')->index();
    $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('acknowledged_at')->nullable();
    $table->timestamp('resolved_at')->nullable();
    $table->boolean('audible')->default(false);           // play a sound at the SCC
    $table->string('dedupe_key')->nullable()->index();    // groups repeat occurrences (§6)
    $table->unsignedInteger('occurrences')->default(1);   // bumped on dedup hit
    $table->timestamps();
    $table->softDeletes();                                // never hard-deleted except end-of-project wipe
    $table->index(['status', 'severity']);
    $table->index(['alert_type', 'status']);
});
```
- `payload` carries everything the UI needs to render + link without extra queries: e.g. `{ worker_id, worker_label, zone_id, zone_name, device_id, gas_type, value, threshold, camera_ref, snapshot_url }`. Identity fields inside payload are subject to `view-worker-identity` stripping in the resource (§9), same as DOC-04.
- `alertable` (morph) links the alert to its source row so the UI can deep-link ("view the violation / alarm / incident"). Nullable because some alerts (e.g. `system`, `clock_skew`) have no single source row.
- `dedupe_key` + `occurrences` implement storm control (§6).

### 3.2 Enums (PHP backed + TS mirror)
- **`AlertType`** — §4 catalogue.
- **`AlertSeverity`:** `info`, `warning`, `critical`.
- **`AlertStatus`:** `open`, `acknowledged`, `resolved`.

---

## 4. Alert-type catalogue (authoritative)

Every alert type in the platform, its source module, default severity, default audible, whether it auto-resolves, and the **suggested action** it offers (a user-confirmed prefill, never an auto-create — §8). Source modules reference these names; none are invented ad hoc.

| alert_type | Source (DOC) | Default severity | Audible | Auto-resolves? | Suggested action (user confirms) |
|---|---|---|---|---|---|
| `ppe_violation` | PPE (10) | warning | no | no | offer "log LSR from this" (optional; links the violation) |
| `gas_warning` | Gas (11) | warning | no | yes (reading back under, hysteresis) | — |
| `gas_alarm` | Gas (11) | critical | **yes** | yes (hysteresis) | — |
| `red_zone_intrusion` | Tracking (09) | critical | **yes** | no | offer "log LSR from this" (prefills worker + zone) |
| `unauthorized_zone_access` | Tracking (09) | warning | no | no | offer "log LSR from this" (prefills worker + zone) |
| `zone_occupancy_exceeded` | Tracking (09) | warning | no | yes (count drops) | offer "log LSR from this" (prefills zone + count) |
| `height_without_harness` | HSE cross-check (14) | critical | **yes** | no | offer "log LSR from this" |
| `fall_detection` | PPE/camera (10/14) | critical | **yes** | no | offer "create incident from this" (prefills evidence) |
| `stationary_tag` | Tracking (09) | warning | no | no | offer "create incident from this" |
| `worker_down` | Correlator (09→14) | critical | **yes** | no | offer "create incident from this" (two-signal) |
| `evacuation` | Evacuation (09) | critical | **yes** | no (closed with report) | — |
| `device_offline` | Health (05) | warning | no | yes (heartbeat returns) | — |
| `camera_offline` | Health (05) | warning | no | yes (frame returns) | — |
| `equipment_overdue` | Equipment (13) | info | no | yes (inspection logged) | — |
| `clock_skew` | Ingest (08) | info | no | yes (next clean read) | — |
| `system` | any service | info→critical | varies | case-by-case | e.g. gas-telemetry-lost escalation (DOC-05 §6.5) |

Defaults live in a single `AlertPolicy` map (severity + audible + auto-resolvable per type), so the behavior is declared in one place and the `alert.audible_enabled` master switch (DOC-18) can globally mute sound.

---

## 5. State machine

```
        raise()                     acknowledge()                 resolve()/auto
  (none) ───────▶ open ──────────────────────────▶ acknowledged ───────────▶ resolved
                   │                                                            ▲
                   └──────────────── resolve()/auto ───────────────────────────┘
```
- **open** — just raised, unhandled. Drives the bell count, toast, and (if critical) the audible loop and display banner.
- **acknowledged** — a human (with `acknowledge-alerts`) has seen it (stops the audible loop for that alert; records who/when). Acknowledgement does **not** mean the condition cleared — it means "an operator is aware."
- **resolved** — the condition ended. Either **auto** (the source module calls `resolve` / `resolveByDedupeKey` when the reading normalizes, device returns, count drops, inspection is logged) or **manual** (`configure-alerts` holder resolves it — used for alert types that don't auto-resolve, like `red_zone_intrusion` after the situation is handled).
- Valid transitions only: `open→acknowledged`, `open→resolved`, `acknowledged→resolved`. A resolved alert is terminal (its history is retained; a recurrence is a *new* alert, or an occurrences bump if within an open dedupe window).

---

## 6. Deduplication (storm control)

Without dedup, a flapping gas gateway or a zone stuck over its occupancy limit would raise an alert every evaluation cycle — hundreds of rows, a screaming wall, useless history. Dedup collapses repeats.

`AlertService::raise(type, severity, title, payload, ?source, audible, ?dedupeKey)`:
- If `dedupeKey` is provided **and an OPEN or ACKNOWLEDGED alert with the same `dedupe_key` exists**, then instead of inserting a new row: increment `occurrences`, refresh `raised_at`, merge/refresh `payload` (latest values), and re-broadcast an `AlertUpdated` event. **No duplicate row, no new toast storm** (the UI updates the existing one, optionally re-surfacing it).
- If no dedupeKey, or none open, insert a new alert.

**Dedupe key conventions (set by source modules):**
- `device_offline:{device_id}`, `camera_offline:{camera_id}` (one open offline alert per device).
- `gas:{device_id}:{gas_type}:{level}` (one open alarm per detector/gas/level).
- `occupancy:{zone_id}` (one open over-limit alert per zone).
- `redzone:{worker_id}:{zone_id}`, `stationary:{tag_id}` (one per worker/zone or tag).
- `equipment_overdue:{equipment_id}`.

Alerts **without** a dedupe key (e.g. each individual `ppe_violation`, each `fall_detection`) are always distinct rows — they're discrete events, not a continuing condition.

`resolveByDedupeKey(key)` — the source module calls this when the condition clears (device heartbeat returns, gas normalizes, count drops), resolving the single open alert for that key.

---

## 7. `AlertService` API (the shared machinery)

```php
final class AlertService
{
    public function raise(
        AlertType $type,
        AlertSeverity $severity,     // usually from AlertPolicy default; overridable
        string $title,
        array $payload = [],
        ?Model $source = null,        // sets the alertable morph
        ?bool $audible = null,        // null → AlertPolicy default & master switch
        ?string $dedupeKey = null,
    ): Alert;

    public function acknowledge(Alert $alert, User $user): Alert;        // ③, audited
    public function resolve(Alert $alert, ?string $note = null): Alert;  // manual ③ or auto ②
    public function resolveByDedupeKey(string $key): ?Alert;             // ②
}
```
- `raise` applies the `AlertPolicy` defaults (severity/audible/auto-resolvable), honors `alert.audible_enabled`, sets `raised_at = now()`, persists, attaches any `suggested_action` to the payload (§8), and broadcasts `AlertRaised` (or `AlertUpdated` on a dedup hit) on the alerts channel (DOC-08). It never creates incidents/LSR — those are user-authored (DOC-14).
- `acknowledge` — sets `status=acknowledged`, `acknowledged_by/at`; writes an `acknowledged` audit row; broadcasts `AlertUpdated`. Idempotent (acknowledging an already-acknowledged alert is a no-op).
- `resolve` — sets `status=resolved`, `resolved_at`; broadcasts; if manual, requires `configure-alerts` at the controller and is audited.

---

## 8. Suggested-action map (alerts prompt records; users create them)

Incidents and LSR violations are **user-created records** (manual, path ③ — DOC-14). Alerts do **not** auto-create them. Instead, certain alerts carry a **suggested action** so the UI can offer a one-click "create from this alert" flow that **prefills** a manual form with the alert's captured context — but nothing is written until an operator reviews and submits.

`payload.suggested_action` (nullable) tells the UI what to offer:

| alert_type | Suggested action (UI offers; user confirms) | Prefill carried in payload | Owning DOC |
|---|---|---|---|
| `fall_detection` | "Create HSE incident from this alert" | camera_ref, snapshot_url, detected_at, zone, RFID roster snapshot | 14 |
| `stationary_tag` | "Create HSE incident from this alert" | tag/worker, zone, nearest camera, detected_at | 14 |
| `worker_down` | "Create HSE incident from this alert" | both source alerts, zone, worker | 14 |
| `red_zone_intrusion` | "Log LSR violation from this alert" | worker id, zone, detected_at | 14 |
| `unauthorized_zone_access` | "Log LSR violation from this alert" | worker id, zone, detected_at | 14 |
| `zone_occupancy_exceeded` | "Log LSR violation from this alert" | zone, count, limit | 14 |
| `height_without_harness` | "Log LSR violation from this alert" | camera_ref, zone, detected_at | 14 |
| `ppe_violation` | "Log LSR violation from this alert" (optional) | ppe_violation_id, camera_ref, type | 10/14 |

Rules:
- **No alert creates a domain record.** The alert only raises a notification and, via `suggested_action`, makes it one click for an operator to open the prefilled manual form (DOC-14). The operator can ignore it — the alert still stands on its own as a notification and is retained.
- When the operator does create an incident/LSR from an alert, that new record **links back** to the alert (`alert_id`) and/or the PPE violation (`ppe_violation_id`) so the evidence chain is preserved — but the record is authored by the user (path ③, with their user id and audited).
- Dedup bumps (occurrences++) never change or re-issue the suggestion; the suggestion lives on the alert row and is actioned at most once by the operator (creating a record clears the pending-suggestion state on that alert).
- This keeps Incidents and LSR strictly manual (auditable human judgment) while preserving the automatic *evidence capture* the sensors provide.

---

## 9. Delivery & real-time (contract; transport in DOC-08)

- **Channel:** `alerts` (private; authorized to any authenticated user — everyone sees alerts, though identity fields in the payload are stripped per permission). Events: `AlertRaised`, `AlertUpdated` (ack/resolve/dedup bump).
- **Resource:** `AlertResource` serializes the row + strips identity fields from `payload` unless the viewer has `view-worker-identity` (DOC-04 §5) — so an operator without identity permission sees "Worker #42 in Restricted Substation" not the name.
- **Poll fallback:** `GET /api/alerts/open` every 30 s when the socket is down (DOC-08). The bell/badge and display banner reconcile from this.
- **Audible:** the client plays a looping chime while **any unacknowledged `audible` critical alert** exists; acknowledging the last one stops it. Master mute = `alert.audible_enabled` (DOC-18). The 55″ display honors the same.

---

## 10. API & UI

### 10.1 Endpoints
| Action | Route | Controller@method | Permission |
|---|---|---|---|
| List (history) | GET `/alerts` (Inertia) | `Web\AlertController@index` | (any authenticated; filtered by view perms) |
| Open alerts | GET `/api/alerts/open` (JSON, poll) | `AlertController@open` | authenticated |
| Acknowledge | POST `/alerts/{alert}/acknowledge` | `@acknowledge` | acknowledge-alerts |
| Bulk acknowledge | POST `/alerts/acknowledge-bulk` | `@acknowledgeBulk` | acknowledge-alerts |
| Resolve (manual) | POST `/alerts/{alert}/resolve` | `@resolve` | configure-alerts |

List filters: `alert_type`, `severity`, `status`, `date_from/to`, plus standard paging/sort. Newest first.

### 10.2 Frontend
- **`AlertProvider`** (mounted in the authenticated layout, DOC-02): subscribes to the `alerts` channel; maintains the open-alert store; renders the toast stack; plays the audible loop; exposes the bell count.
  - Toast styling by severity: **critical** = red, persistent until acknowledged; **warning** = amber, auto-dismiss ~10 s (stays in the centre); **info** = grey, ~5 s.
  - Dedup bump re-surfaces the toast with an occurrences badge ("×3") rather than stacking new toasts.
- **`pages/alerts/index.tsx`** — AlertCenterPage: filterable history table, per-row acknowledge/resolve actions, bulk acknowledge, deep-link to the source record via `alertable`.
- **Display banner (DOC-16):** the 55″ view shows a full-width red banner listing open critical alerts + the audible loop; warnings scroll in a bottom ticker.
- **Types (`types/alert.ts`):** `Alert`, `AlertType`, `AlertSeverity`, `AlertStatus`, `AlertPayload` (typed per source), `OpenAlertsSnapshot`.
- Cache/store invalidation via websocket events; the poll fallback reconciles on reconnect.

---

## 11. Real-life scenarios

- **Gas spike:** gas service raises `gas_alarm` (critical, audible, dedupe `gas:12:h2s:alarm`) → red toast + wall banner + chime → operator acknowledges (chime stops, "aware") → readings normalize → gas service `resolveByDedupeKey` → alert resolved; the whole episode is one row with the acknowledgement recorded.
- **Flapping gateway:** a gas gateway drops in/out every minute → first drop raises `device_offline` (dedupe `device_offline:33`); subsequent drops bump `occurrences` on the same open alert instead of storming → when it stabilizes online, `resolveByDedupeKey` clears it. One row, `occurrences: 7`.
- **Red zone:** worker enters a red zone → `red_zone_intrusion` (critical, audible) raised → the alert offers "log LSR from this" (prefilled worker + zone) → operator acknowledges, radios the field, and — if warranted — clicks through to create the LSR (a user-authored record linking the alert) → later resolves the alert manually (`configure-alerts`) once handled.
- **Fall:** camera fall detection → `fall_detection` (critical) with captured evidence in the payload → the alert offers "create incident from this" → operator acknowledges, reviews the camera, and (if real) clicks through to the prefilled incident form and submits it (DOC-14) → if it was a false alarm, they simply acknowledge/resolve the alert and create nothing.
- **Anonymized operator:** an operator without `view-worker-identity` sees "Worker #42 entered Restricted Substation" — actionable, but no name — because the alert payload is stripped at the resource.

---

## 12. Tests (this doc's slice of DOC-21)

- **Raise:** `raise` applies AlertPolicy severity/audible defaults; `alert.audible_enabled=false` mutes all audible; sets `alertable` morph from `source`.
- **State machine:** valid transitions only; acknowledge sets who/when + audit row + broadcasts; resolve (manual) requires `configure-alerts`; resolved is terminal.
- **Dedup:** a second `raise` with the same open `dedupe_key` bumps `occurrences` and does **not** insert a row or re-issue the suggested action; `resolveByDedupeKey` resolves the single open one; no-dedupe alerts always insert.
- **Suggested actions, not auto-creation:** raising `fall_detection`/`stationary_tag`/`worker_down`/`red_zone_intrusion`/etc. creates **no** incident or LSR row on its own; the alert carries a `suggested_action` + prefill payload; a domain record appears only when a user submits the prefilled form (DOC-14), and that record links `alert_id`/`ppe_violation_id`.
- **Never pruned:** retention job (DOC-19) leaves alerts untouched; only end-of-project wipe removes them.
- **No create endpoint:** there is no user route to insert an alert (only acknowledge/resolve exist).
- **Delivery:** `AlertRaised`/`AlertUpdated` broadcast on the alerts channel; `AlertResource` strips identity in payload without `view-worker-identity`; poll endpoint returns current open set.
- **Audible loop (component):** chime runs while an unacknowledged audible critical exists; stops on last acknowledge.
- Authorization: acknowledge requires `acknowledge-alerts`; manual resolve requires `configure-alerts`; listing is available to any authenticated user with identity stripping applied.

---

## 13. Open decisions logged

| # | Decision | Default | Confirm in |
|---|---|---|---|
| 1 | Which alert types auto-resolve vs manual | per §4 table | this doc / source DOCs |
| 2 | Dedup window (only-while-open vs a time window) | while an open/ack alert with the key exists | this doc |
| 3 | Warning-toast auto-dismiss time | ~10 s (stays in centre) | DOC-16/18 |
| 4 | Who may manually resolve non-auto alerts | `configure-alerts` | DOC-03 |

---

### Next document
**DOC-08 — Device Ingestion Contract & Real-Time (Reverb):** the `/api/ingest/*` contract (device auth, batching, idempotency, out-of-order/backfill, clock-skew, throttle), the full ingest endpoint catalogue, the Reverb channel/event catalogue that delivers alerts and live data, and the poll-fallback pattern — the machine-data backbone the tracking/PPE/gas/environmental modules ride on.