# DOC-08 — Device Ingestion Contract & Real-Time (Reverb)

> **Depends on:** DOC-01 (conventions, envelope, error contract, settings, queues), DOC-02 (`auth.device` credential), DOC-05 (devices/cameras + `reference` resolution + heartbeats/health), DOC-06 (`resolveZoneAt` for tag reads), DOC-07 (alert delivery channel). **Feeds:** DOC-09 (tag reads), DOC-10 (PPE/fall detections), DOC-11 (gas/CO₂ readings), DOC-12 (environmental readings), DOC-16 (live views subscribe to these channels).
>
> **Scope:** the **machine-data backbone** — the shared `/api/ingest/*` contract every device endpoint obeys (device auth, batching, idempotency, out-of-order/backfill handling, clock-skew, throttling), the **five** ingest endpoints (tag-readings, ppe-violations [incl. fall], gas-readings [incl. CO₂], environmental-readings, heartbeat), and the **Reverb** real-time layer (channel/event catalogue, authorization, throttling, poll fallback, LIVE/RECONNECTING). **Out of scope:** what each payload *means* and how it's processed into domain state (owned by DOC-09/10/11/12) — this doc defines how data *gets in* and how live updates *go out*.

---

## 1. Purpose & the reality this must survive

Every reading, detection, and heartbeat enters through `/api/ingest/*`. The network reality (DOC-05) is unforgiving: field units connect over Wi-Fi/4G/LAN that **drops**; edge units **buffer during outages and flush hours of backlog at once**; device clocks **drift**; and a batch may be **retried** after a timeout. A naïve ingest endpoint would double-count retries, rewrite live state with stale backlog, or fire hours-late alarms. This contract makes ingestion **idempotent, order-tolerant, and backfill-aware** so data is never lost or duplicated and live state is never corrupted by late arrivals.

The complementary half is **real-time delivery**: operators must see live headcount, gas gauges, moving positions, and alerts without refreshing. Reverb (self-hosted WebSockets, DOC-01 — no cloud Pusher) pushes deltas on top of the initial Inertia page props, with a polling fallback so a dropped socket degrades gracefully rather than freezing the wall.

---

## 2. Data origin

- **① device:** all `/api/ingest/*` and `/api/devices/{id}/heartbeat` traffic — the only writers here. Authenticated by `auth.device` (DOC-02).
- **② system:** the ingest controllers delegate to domain services (DOC-09/10/11/12) which derive state; ingest itself also raises `clock_skew`/backfill `system` alerts (DOC-07) and broadcasts live events.
- **③ user:** none — there is no human path into ingestion (DOC-01 §9 rule 4). Operators consume the *results* via the UI.

---

## 3. The shared ingest contract (every `/api/ingest/*` obeys this)

### 3.1 Authentication (DOC-02 §9)
- Header `X-Device-Token: <token>` → `auth.device` middleware → resolves the **Device** (and its asset) by hashed token match against `devices.api_token_hash`.
- Unknown/absent token → `401 UNAUTHENTICATED`; device `status = retired` → `403 FORBIDDEN`.
- The resolved device is attached: `$request->device()`. A device can only write data attributable to **itself** — payloads reference cameras/readers by `reference` (DOC-05), and the server validates those references belong to a plausible source; a device cannot post data "as" another device's id.
- Stateless: no session, no cookies, no CSRF. LAN-only in practice (reverse-proxy/firewall — DOC-20).

### 3.2 Batching (backlog-friendly)
- Body shape: `{ "events": [ {…}, {…} ] }`, **max 1000 events** per request (`ingest.max_batch`, DOC-18). Larger backlogs are sent as multiple batches by the edge unit.
- Response: **`202 Accepted`** with per-event outcomes — never all-or-nothing:
```json
{ "accepted": 187, "duplicates": 12, "rejected": [ { "index": 44, "code": "LOW_CONFIDENCE" }, { "index": 90, "code": "UNKNOWN_REFERENCE" } ] }
```
- Partial success is normal: valid events persist even if some siblings are rejected. This is the `INGEST_PARTIAL` case from DOC-01 §8 (carried inside the 202 body, not an error status).

### 3.3 Idempotency (retry-safe)
- Every event carries a client-generated **`event_uid`** (uuid). Each raw table has a unique index `(device_id, event_uid)` (or a shared `ingest_events` dedupe table `[CONFIRM AT DESIGN]`).
- A replayed batch (same `event_uid`s) counts each repeat as a **duplicate** (in the response), inserts nothing new, and is **not** an error. So an edge unit that resends after a timeout never double-counts.
- Idempotency is per raw stream; the same `event_uid` space is scoped per device.

### 3.4 Ordering, clocks & backfill (the crux)
Each event carries **`recorded_at`** (device clock). The server also stamps **`received_at = now()`** on every row. Two timestamps, two jobs:
- **Live state advances only forward.** State tables (`worker_positions`, gas "latest" panels, `present`/`last_seen_at` mirrors) update **only if `recorded_at` is newer** than the current state's timestamp. A backfilled read with an old `recorded_at` is stored in history but **never rewinds** live state.
- **Historical tables accept any order.** Readings/violations are inserted regardless of order; queries sort by `recorded_at`.
- **Clock-skew guard:** if `recorded_at` is more than **5 minutes in the future** (`ingest.future_skew_seconds`, DOC-18), clamp the stored `recorded_at` to `received_at`, flag the row `clock_skew = true`, and raise a **once-per-day** `clock_skew` alert per device (DOC-07). Prevents a mis-set device clock from poisoning ordering.
- **Backfill classification:** an event whose `recorded_at` is older than **10 minutes** (`ingest.backfill_after_seconds`, DOC-18) is marked **`is_backfill = true`**. Backfill events are: stored, rolled up (DOC-11/19), and included in reports (DOC-15) — but **excluded from live broadcasts** and from **live alert evaluation** (most importantly, gas backfill raises **no** alarms because the detector already alarmed locally on site, DOC-11). Events ≤ 10 min old run the full live pipeline (broadcast + alert evaluation).
- Zone resolution for tag reads uses `resolveZoneAt(reader, recorded_at)` (DOC-06) so backfilled reads land in the historically correct zone.

### 3.5 Throttling & size limits
- Per-device rate limit `ingest.rate_per_minute` (default 120, DOC-18) → `429 RATE_LIMITED` with `Retry-After`. Tuned high enough for legitimate backlog flushes (which come as few large batches, not many small requests).
- Request body size capped (reject oversized with 413) and `events` length capped at `ingest.max_batch`.

### 3.6 Liveness side-effect
- Every ingest call (and every heartbeat) updates the device's `last_seen_at` and bubbles `last_heartbeat_at` to its asset (DOC-05 §6), which is how the health monitor knows the device is alive.

### 3.7 Processing pipeline (per batch)
1. `auth.device` resolves the device.
2. `IngestBatchRequest` validates the envelope (`events` array, size) and each event's shape (per-endpoint FormRequest rules).
3. Controller delegates to the domain service (e.g. `TrackingService::ingestReadings`), passing the device.
4. Service, per event: dedupe check → clock-skew clamp → backfill classify → resolve references (camera/reader/zone) → persist raw row → if live (≤10 min), update state + evaluate alerts + queue a broadcast.
5. Heavy work (alert evaluation for large live batches) is pushed to the **`ingest` queue** (DOC-01 §A8) so the HTTP response returns fast; the raw insert + dedupe is synchronous so the 202 outcome is accurate.
6. Controller returns `202` with `{accepted, duplicates, rejected[]}`.

---

## 4. Ingest endpoint catalogue

All under `/api/ingest/*` (or `/api/devices/*` for heartbeats), `auth.device`, batch envelope, 202 response. There are **five** ingest endpoints. Payload *meaning* is in the owning DOC; here is the wire contract.

| Endpoint | Sender (device_type) | Per-event payload | Owning DOC |
|---|---|---|---|
| `POST /api/ingest/tag-readings` | `rfid_reader` (pole + gate; pole reads may relay via edge) | `{ event_uid, reader_ref, tag_uid, recorded_at, rssi? }` | DOC-09 |
| `POST /api/ingest/ppe-violations` | `edge_compute` (camera AI) | `{ event_uid, camera_ref, event_type, detected_at, worker_count?, confidence, snapshot(base64 or multipart) }` — `event_type` covers PPE violations **and** fall detection (`missing_helmet`\|`missing_vest`\|`missing_harness`\|`missing_mask`\|`fall`) | DOC-10/14 |
| `POST /api/ingest/gas-readings` | `gas_detector` / `wifi_gateway` | `{ event_uid, device_ref?, recorded_at, lel_pct?, h2s_ppm?, o2_pct?, co_ppm?, co2_ppm? }` — one endpoint for all five gas channels; a reading includes whichever fields the sending device measures | DOC-11 |
| `POST /api/ingest/environmental-readings` | `environmental_sensor` (edge RS485) | `{ event_uid, device_ref?, recorded_at, temperature_c?, humidity_pct?, wind_speed_ms?, extra? }` | DOC-12 |
| `POST /api/devices/{id}/heartbeat` | any device/edge agent | `{ status?, meta? }` (not batched; simple liveness ping) | DOC-05 |

Notes:
- **PPE + fall are one endpoint.** The camera AI reports both kinds of computer-vision event through `/api/ingest/ppe-violations`, discriminated by `event_type`. A `fall` event carries no `worker_count`/PPE type; a PPE event carries its violation type. DOC-10 routes each `event_type` to the right handling (PPE violation record vs the `fall_detection` alert that suggests an incident — DOC-14).
- **Five gas channels, one endpoint.** `/api/ingest/gas-readings` accepts a reading with any subset of `lel_pct`, `h2s_ppm`, `o2_pct`, `co_ppm`, and `co2_ppm`. Devices register as `gas_detector` regardless of which channels they measure; DOC-11 stores and evaluates each present channel against its threshold.
- `*_ref` fields resolve to the exact camera/device/reader by `reference` (DOC-05), **not** by the authenticating device's id — this is what lets a single edge unit post on behalf of multiple cameras/readers it processes (e.g. relayed pole cameras). Unknown reference → per-event `UNKNOWN_REFERENCE` rejection.
- `snapshot` may be sent inline (base64 in JSON) for small images or as multipart for larger; stored to the private disk (DOC-01 §10) and referenced by path — the raw bytes are never echoed back.
- Camera frame-liveness: an optional lightweight `frame-liveness` ping may piggy-back on the ppe-violations stream (or a heartbeat) to update `cameras.last_frame_at` for health (DOC-05) `[CONFIRM AT DESIGN]`.

---

## 5. Reverb real-time layer

Self-hosted Reverb provides WebSockets over the LAN. Initial page data always arrives via Inertia props; Reverb pushes **deltas** on top. Every live screen follows the same pattern (DOC-01 §3).

### 5.1 Channel & event catalogue
Private channels (authorized in `routes/channels.php`); since the platform is a single standalone instance, channel names carry **no location prefix** (the master spec's `site.{id}.*` collapses to bare names).

| Channel | Events | Payload (delta) | Consumers (DOC) |
|---|---|---|---|
| `alerts` | `AlertRaised`, `AlertUpdated` | `AlertResource` (identity-stripped per viewer) | AlertProvider, display banner (07/16) |
| `ppe` | `PpeViolationDetected` | id, type, camera_ref, snapshot_url, detected_at | live wall, PPE card (10/16) |
| `tracking` | `HeadcountUpdated` (throttled 5 s), `PositionsUpdated` (throttled 5 s), `EvacuationTriggered`, `EvacuationEntryUpdated` | headcount totals / changed positions / evac state | tracking page, map, display (09/16) |
| `gas` | `GasLiveUpdated` (throttled 5 s), `GasAlarmRaised`, `GasAlarmResolved` | per-device latest panel / alarm | gas dashboard, display (11/16) |
| `environment` | `EnvironmentUpdated` (throttled) | latest weather values | weather widget (12/16) |
| `system` | `DeviceStatusChanged` | device/camera id + status | settings/devices, health widget (05/16) |

### 5.2 Channel authorization
- All channels are **private**; `routes/channels.php` authorizes a subscription only for an authenticated user (session guard). Membership is not per-user data-scoped (everyone in the SCC sees the same live picture) — **but payloads are permission-filtered at the resource** (identity stripping in `alerts`/`tracking` per `view-worker-identity`, DOC-04/07). So authorization gates *connection*; resources gate *content*.
- The device/display identities: devices never subscribe (they only POST); the display view subscribes as the logged-in user (DOC-02 §5.3).

### 5.3 Throttling & batching of broadcasts
- High-frequency streams (headcount, positions, gas panels) are **throttled to one broadcast per 5 s** per channel (coalesce intra-window changes into a single delta) so a backlog flush or a busy shift doesn't flood the socket. Throttle windows are settings (`realtime.*_throttle_seconds`).
- Discrete events (an alert, a PPE violation, an evacuation trigger) broadcast immediately — they're rare and important.
- Backfill events (§3.4) do **not** broadcast at all.

### 5.4 Poll fallback & LIVE/RECONNECTING
- Every live screen shows a **LIVE / RECONNECTING** pill reflecting socket state (critical on the 55″ display, DOC-16).
- When the socket is down, screens fall back to polling: `GET /api/alerts/open` every 30 s (DOC-07) and the relevant snapshot endpoints (`/api/tracking/headcount`, `/api/gas/live`, `/api/dashboard/summary`) every 30–60 s. On reconnect, the client re-fetches a fresh snapshot to reconcile any missed deltas, then resumes streaming.
- This guarantees the wall never silently freezes on a dropped connection — it visibly degrades and self-heals.

### 5.5 Frontend hook
- **`useReverbChannel(channel, handlers)`** — subscribes on mount, unsubscribes on unmount, exposes connection status, and wires the poll fallback + reconnect-reconcile. Every live page uses it; individual DOCs specify which channel/events.

---

## 6. Security & robustness

- **Device tokens** are per-device, hashed, rotatable (DOC-05 §5); a leaked token is contained to one device and revoked by rotation.
- **Reference validation** prevents a device spoofing another's data (§3.1).
- **Idempotency + dedupe** prevent replay/duplication.
- **Backfill/clock-skew guards** prevent stale or mis-clocked data from corrupting live state or firing phantom alarms.
- **Rate limiting + body caps** prevent a malfunctioning device from overwhelming the server.
- **No internet egress** (DOC-01) — ingestion and Reverb are LAN-only; the reverse proxy restricts `/api/ingest/*` to the device network (DOC-20).
- All ingest anomalies (skew, unknown reference bursts, rejected-event spikes) surface as `system` alerts so operators notice a misbehaving device.

---

## 7. Real-life scenarios

- **Normal live read:** a reader posts a batch of 30 tag reads (all `recorded_at` within seconds) → validated, deduped, zones resolved, positions updated, a throttled `HeadcountUpdated`/`PositionsUpdated` broadcast fires → the map moves within 5 s. 202 `{accepted:30}`.
- **Outage flush:** a pole is offline 2 h, buffers ~4 000 reads → on reconnect the edge unit sends 4 batches of 1000 → each event is `is_backfill=true` (recorded_at >10 min old) → stored, zones resolved via time-aware bindings, positions **not** rewound, **no** broadcasts, **no** alarms → weekly report data is complete; the live picture reflects only the newest read. Health flips the device back online.
- **Retry after timeout:** a batch's HTTP response is lost, the edge resends the same `event_uid`s → response `{accepted:0, duplicates:1000}`, nothing double-counted.
- **Bad clock:** a device's clock is +2 h → its events' `recorded_at` clamp to `received_at`, rows flagged `clock_skew`, one `clock_skew` alert/day raised → ordering stays sane.
- **Spoof attempt:** a device posts a reading with a `reader_ref` that isn't a registered reader → per-event `UNKNOWN_REFERENCE` rejection; the valid siblings still persist.
- **Socket drop on the wall:** the 55″ display loses its WebSocket → pill flips to RECONNECTING, it polls snapshots every 30 s → on reconnect it re-fetches and resumes LIVE, no gap visible to the operator.

---

## 8. Tests (this doc's slice of DOC-21)

- **Auth:** valid `X-Device-Token` resolves the device; unknown → 401; retired → 403; a device token rejected on any non-ingest route (DOC-02 integration).
- **Batching:** `{events:[…]}` returns 202 with `{accepted, duplicates, rejected}`; a mix of valid + invalid events persists the valid ones (partial success); >`max_batch` rejected; oversized body → 413.
- **Idempotency:** resending identical `event_uid`s inserts nothing and reports them as duplicates; unique `(device_id, event_uid)` enforced.
- **Ordering/backfill:** an event with `recorded_at` older than the current live state does **not** rewind `worker_positions`/live panels but **is** stored; `is_backfill` set >10 min; backfill events don't broadcast and (for gas) raise no alarm.
- **Clock-skew:** `recorded_at` >5 min future clamps to `received_at`, flags the row, raises one `clock_skew` alert/day/device.
- **Reference resolution:** a `*_ref` resolves to the correct camera/reader/device; unknown ref → `UNKNOWN_REFERENCE` rejection; a device cannot write data under another device's reference beyond its plausible set.
- **Throttle:** exceeding `rate_per_minute` → 429 + Retry-After.
- **Liveness:** ingest/heartbeat updates `last_seen_at`/`last_heartbeat_at`.
- **Reverb:** private channels authorize only authenticated users; high-frequency events throttle to ~5 s and coalesce; discrete events broadcast immediately; backfill never broadcasts; `AlertResource`/tracking payloads are identity-stripped without `view-worker-identity`.
- **Poll fallback (component):** on socket down, the screen polls the snapshot endpoint and shows RECONNECTING; on reconnect it reconciles and shows LIVE.

---

## 9. Open decisions logged

| # | Decision | Default | Confirm in |
|---|---|---|---|
| 1 | Dedupe storage: per-table unique vs shared `ingest_events` | per-table unique `(device_id, event_uid)` | this doc |
| 2 | Snapshot transport: base64 vs multipart | support both; multipart for larger | DOC-10 |
| 3 | Frame-liveness mechanism | piggy-back on the ppe-violations stream + optional ping | DOC-05/10 |
| 4 | Backfill threshold / skew threshold | 10 min / 5 min | DOC-18 |
| 5 | Realtime throttle windows | 5 s for headcount/positions/gas/env | DOC-18 |

---

### Next document
**DOC-09 — RFID Personnel Tracking / SSMS:** the biggest module — tag reads → live positions + entry/exit (gate logic, debounce, corrections), headcount + zone map, zone rules that raise alerts (which now *suggest* LSR, DOC-07/14), stationary-tag & evacuation, tag lifecycle and portable-device register — all riding the ingestion + real-time backbone defined here.