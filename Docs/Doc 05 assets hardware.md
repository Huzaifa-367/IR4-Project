# DOC-05 — Assets & Hardware Registry

> **Depends on:** DOC-01 (conventions, enums, resources, settings), DOC-02 (`auth.device` credential — issued here), DOC-03 (`manage-devices` permission). **Feeds:** DOC-06 (zones bind to RFID readers), DOC-07 (device/camera offline alerts), DOC-08 (ingestion authenticates as these devices; heartbeats), and every telemetry module (DOC-09/10/11/12 readings reference the device + asset that produced them).
>
> **Scope:** the physical hardware registry — **assets** (poles, gate, SCC units), **cameras**, and **devices** (sensors, readers, gateways, edge units) — plus device API-token issuance/rotation, heartbeats, and the health-monitoring service that marks hardware offline and raises/clears alerts. The registry is **fully dynamic**: no hardcoded counts or fixed arrangement of hardware (§1.1). All global to the single standalone instance (no location concept — DOC-01 §1). **Out of scope:** what the devices *send* (DOC-08 ingestion) and reader↔zone bindings (DOC-06).

---

## 1. Purpose & the physical topology it models

Every reading, detection, snapshot, and live feed originates from a piece of hardware. This registry is the source of truth for "what hardware exists, what it's mounted on, is it healthy, and what token authenticates it." Nothing can ingest, appear on the live wall, or be health-monitored unless it's registered here.

### 1.1 Fully dynamic — nothing about the inventory is hardcoded
**This is the governing rule of the whole registry.** The platform makes **no assumptions about how many poles, cameras, readers, or sensors exist, or how they're arranged.** There is no fixed count baked into code, no seeded hardware inventory, no "for each of the N poles" logic anywhere. An operator registers whatever hardware is actually deployed, in whatever quantity and arrangement, through the UI at commissioning — and can add, move, retire, or reconfigure it at any time. Counts and layouts below are **illustrative of a typical deployment, not constraints**; the same codebase must work with a different mix without any change.

Concretely, the system must never encode: a number of poles/cameras/devices; a fixed cameras-per-pole ratio; which device sits on which asset; or which asset is "the gateway" or "the edge." All of that is data in the tables below, entered by operators.

### 1.2 A typical deployment (example only)
A representative install looks like:
- **~4 poles** — solar-powered field units that carry cameras and readers and may **reposition every 5–7 days** as the work front advances. Poles are the mobile field units of the system.
- **~7 cameras** — distributed across poles **unevenly** (e.g. one pole has a single camera, others carry more). There is **no one-camera-per-asset assumption**; an asset has zero, one, or many cameras.
- **~5 RFID readers** — e.g. 4 mounted on poles for zone tracking + 1 fixed at the gate for the definitive entry/exit record.
- **~4 gas readers (detectors)** — mounted where hazardous-gas monitoring is needed.
- Plus edge-compute unit(s), a connectivity path per field unit (Wi-Fi / 4G / LAN — whatever is configured), CO₂ / environmental sensors as fitted, and the **SCC** (server + workstation + 55″ display + Zebra ZT411 label printer).

Change any of these numbers and the platform behaves identically — the registry, health monitoring, ingestion, and live views all iterate over *registered* hardware, never over hardcoded expectations.

The registry expresses this as three tables: `assets` (the mountable/physical units — poles, gate, SCC), `cameras`, and `devices` (readers, gas/CO₂/environmental sensors, gateways, edge units, printer), with cameras and devices belonging to an asset. Every relationship (how many cameras on a pole, which reader covers which zone, which edge unit processes which camera) is data, not code.

---

## 2. Data origin

- **③ user:** all registration and configuration — creating assets/cameras/devices, issuing/rotating tokens, setting status, editing config. Requires `manage-devices`.
- **① device:** heartbeats (`/api/devices/{id}/heartbeat`) and every ingest call touch `last_seen_at`/`last_heartbeat_at`.
- **② system:** the health service flips `status` to offline and raises/resolves alerts; recovery flips it back.

No telemetry values live in this registry — only the hardware and its liveness.

---

## 3. Data model

### 3.1 `assets`
The physical, mountable units. Cameras and devices attach to an asset.
```php
Schema::create('assets', function (Blueprint $table) {
    $table->id();
    $table->string('asset_type');                         // enum AssetType (§3.4)
    $table->string('name');                               // "Pole 3", "Main Gate", "SCC Server" — operator-chosen
    $table->string('identifier')->unique();               // serial / hostname / any unique label
    $table->string('status')->default('active');          // enum AssetStatus (§3.4)
    $table->boolean('is_mobile')->default(false);         // true for repositionable field units (poles); drives repositioning UI (DOC-06)
    $table->string('current_location_label')->nullable(); // free-text current position, updated on repositioning (DOC-06)
    $table->timestamp('last_heartbeat_at')->nullable();   // most recent heartbeat from any child device
    $table->json('meta')->nullable();                     // model, specs, connectivity (wifi/4g/lan), install notes
    $table->timestamps();
    $table->index(['asset_type', 'status']);
});
```
- `is_mobile` marks repositionable field units (typically poles); it drives whether the repositioning workflow (DOC-06) applies. Fixed assets (gate, SCC) are `is_mobile=false`. This is a per-asset flag set by the operator — not a hardcoded assumption about which asset types move.
- `current_location_label` is a human label ("Work Front A – north edge"); the authoritative zone coverage of a pole reader is the reader↔zone binding in DOC-06. This field is convenience/display, updated during the repositioning workflow (DOC-06).
- SCC units (server/workstation) are modeled as assets too so cameras/devices/printers can hang off them and so health covers them, even though they don't move.
- `meta.connectivity` records how the asset reaches the SCC (Wi-Fi / 4G / LAN) — informational, not hardcoded per asset type; each field unit connects however it's configured.

### 3.2 `cameras`
```php
Schema::create('cameras', function (Blueprint $table) {
    $table->id();
    $table->foreignId('asset_id')->constrained()->restrictOnDelete();
    $table->string('name');                               // "Pole 3 – north", operator-chosen
    $table->string('reference')->unique();                // camera_ref used by edge AI ingestion (DOC-08/10)
    $table->string('camera_type');                        // enum CameraType (§3.4)
    $table->foreignId('processed_by_device_id')->nullable()->constrained('devices')->nullOnDelete(); // which edge unit runs AI for this camera (optional, dynamic)
    $table->string('stream_url');                         // local RTSP/HLS on the LAN
    $table->boolean('ai_enabled')->default(true);         // PPE/fall inference on/off
    $table->string('status')->default('offline');         // enum HardwareStatus
    $table->timestamp('last_frame_at')->nullable();       // liveness for the wall + health
    $table->json('meta')->nullable();
    $table->timestamps();
    $table->index(['status']);
    $table->index(['asset_id']);
});
```
- `reference` is the stable id the edge units put on ingested PPE/fall events (`camera_ref`). A camera's AI may be processed by an edge unit that is a **different** asset than the camera's mount (recorded in the optional `processed_by_device_id`), so events can arrive tagged with the **camera's** reference even though a *different* device's token sent them — ingestion resolves the camera by `reference`, not by the sending device (DOC-10 §C-S3). This mapping is data, not a hardcoded "pole relays through vehicle" rule; whatever edge/camera arrangement the operator configures works.
- `stream_url` is a LAN address; the live wall consumes it via a signed stream descriptor (DOC-16), never exposing the raw URL to the browser directly `[CONFIRM AT DESIGN]`.

### 3.3 `devices`
Everything that authenticates with a token, produces telemetry, or has liveness — sensors, readers, gateways, edge units, the QR printer.
```php
Schema::create('devices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('asset_id')->constrained()->restrictOnDelete();
    $table->string('device_type');                        // enum DeviceType (§3.4)
    $table->string('name');                               // "Pole 2 – gas detector", operator-chosen
    $table->string('reference')->unique();                // reader_ref etc. used in ingestion payloads (DOC-08/09)
    $table->string('serial_number')->nullable()->unique();
    $table->string('status')->default('offline');         // enum HardwareStatus
    $table->string('api_token_hash')->nullable();         // hashed device token (auth.device) — §5
    $table->timestamp('token_issued_at')->nullable();
    $table->json('config')->nullable();                   // modbus addr, gateway binding, thresholds ref, etc.
    $table->timestamp('last_seen_at')->nullable();        // updated on heartbeat/ingest (§6)
    $table->timestamps();
    $table->index(['device_type', 'status']);
    $table->index(['asset_id']);
});
```
- `reference` is what ingestion payloads carry (e.g. `reader_ref` on tag reads — DOC-09) so the server maps an event to the exact device without trusting the caller's device id.
- `api_token_hash` — only the hash is stored; the plaintext is shown once at issuance (§5). A device with no token cannot authenticate to ingest.
- `config` holds device-specific wiring: an RS485 device's modbus address, a gas detector's gateway pairing, a reader's default parameters. Threshold *values* live in DOC-11's `gas_thresholds`, not here — `config` may hold a reference only.

### 3.4 Enums (PHP backed + TS mirror — DOC-01 §6)
These enums are **type categories**, not inventory — the code needs to know *what kind* of thing a device is to route its data, but never how many exist.
- **`AssetType`:** `pole`, `gate`, `scc_server`, `scc_workstation`, `other`. (`other` keeps the registry open to hardware arrangements not anticipated here — dynamic by design. No `vehicle` type: field units are poles.)
- **`AssetStatus`:** `active`, `maintenance`, `offline`. (`maintenance` suppresses health alerts — §6.4.)
- **`CameraType`:** `fixed`, `ptz`, `dome`, `other`. (Describes the camera itself; the mount is the asset it's attached to, so a camera type is independent of where it hangs.)
- **`DeviceType`:** `gas_detector`, `environmental_sensor`, `rfid_reader`, `wifi_gateway`, `rs485_interface`, `qr_printer`, `edge_compute`, `other`. (Any number of each may be registered on any asset. Gas detectors may report any subset of LEL / H₂S / O₂ / CO / CO₂.)
- **`HardwareStatus`** (cameras & devices): `online`, `offline`, `degraded`, `fault`, `maintenance`, `retired`.

### 3.5 Relationships
```php
// Asset
public function cameras(): HasMany
public function devices(): HasMany
// Camera / Device
public function asset(): BelongsTo
// Device (from DOC-06/09/11 — reverse sides declared there)
public function zoneBindings(): HasMany   // reader_zone_bindings (rfid_reader only) — DOC-06
```
Delete rules: an asset with attached cameras/devices → `restrictOnDelete` (409 in the service before it reaches the DB, with a clear message). Retire hardware (status `retired`) rather than deleting it, so historical telemetry keeps a valid device reference.

---

## 4. Registration & configuration (③, `manage-devices`)

All hardware management is operator UI (Inertia, surface A). **The registry ships empty** — there is no seeded hardware inventory (no default poles/cameras/devices). Everything is created by an operator at commissioning, matching the fully-dynamic rule (§1.1). (A dev/staging-only demo seeder may create sample hardware for local testing, guarded to non-production — DOC-01 §M; it is never run in production.)

Typical order at commissioning: create an asset (pole/gate/SCC) → attach its cameras and devices (any number) → issue device tokens → configure edge units with those tokens → confirm heartbeats turn everything green.

| Action | Route (Inertia) | Controller@method | Permission |
|---|---|---|---|
| List assets | GET `/settings/assets` | `Web\Settings\AssetController@index` | manage-devices |
| Asset detail | GET `/settings/assets/{asset}` | `@show` | manage-devices |
| Create / update / retire asset | POST/PUT/DELETE `/settings/assets…` | `@store/@update/@destroy` | manage-devices |
| List cameras | GET `/settings/cameras` | `CameraController@index` | manage-devices |
| Create / update camera | POST/PUT `/settings/cameras…` | `@store/@update` | manage-devices |
| Toggle camera AI | PATCH `/settings/cameras/{camera}/ai` | `@toggleAi` | manage-devices |
| List devices | GET `/settings/devices` | `DeviceController@index` | manage-devices |
| Create / update device | POST/PUT `/settings/devices…` | `@store/@update` | manage-devices |
| Set device/asset status | PATCH `/settings/devices/{device}/status` | `@setStatus` | manage-devices |
| Issue / rotate device token | POST `/settings/devices/{device}/token` | `@regenerateToken` | manage-devices |
| Health overview | GET `/settings/health` (or dashboard widget) | `SystemHealthController@index` | view-dashboard |

**FormRequest rules (representative):**
- asset: `asset_type` enum, `name` req ≤150, `identifier` req unique ≤150.
- camera: `asset_id` exists, `reference` req unique, `camera_type` enum, `stream_url` req url-ish, `ai_enabled` boolean.
- device: `asset_id` exists, `device_type` enum, `reference` req unique, `serial_number` nullable unique, `config` nullable array.

Delete guards (409): asset with children; a device currently bound to a zone (DOC-06) must be unbound first; a camera referenced by open incidents keeps its row (retire, don't delete).

---

## 5. Device token issuance & rotation (the `auth.device` credential)

DOC-02 defined how `auth.device` *verifies* a token; DOC-05 defines how it's *issued*.

- **Issuance:** `POST /settings/devices/{device}/token` generates a cryptographically random token (e.g. 40+ chars), stores **only its hash** in `api_token_hash`, sets `token_issued_at`, and returns the **plaintext exactly once** in the response for the operator to copy into the edge unit's config. It is never retrievable again.
- **Rotation:** calling the endpoint again generates a new token and overwrites the hash — the old token stops working immediately. Used when a token is suspected exposed or an edge unit is re-imaged.
- **Scope:** the token authorizes only `/api/ingest/*` and `/api/devices/{id}/heartbeat` for **that device** (DOC-02 §9, DOC-08). It cannot reach operator/admin routes.
- **Audit:** issuance/rotation writes a `config_changed` audit row (device id, `token_issued_at`; never the token value).
- **UI:** the device detail page shows a "Generate token" action with a one-time reveal modal ("copy now — you won't see this again") and a warning that regenerating breaks the currently-configured device until reconfigured.
- **Bulk commissioning:** an optional "issue tokens for all devices on this asset" action returns a printable one-time sheet of device→token pairs for field setup `[CONFIRM AT DESIGN]`.

Retired devices: setting status `retired` invalidates the token (treated as no valid token) and blocks ingestion (DOC-02 → 403).

---

## 6. Heartbeats & health monitoring

### 6.1 Heartbeats (①)
- Every edge unit/gateway/reader periodically calls `POST /api/devices/{id}/heartbeat` (device-authed) with optional `{status, meta}`. This, and any ingest call, updates the device's `last_seen_at` and bubbles `last_heartbeat_at` to the parent asset.
- Cameras don't heartbeat via this route; their liveness is `last_frame_at`, updated whenever the edge unit reports a frame/inference or via a lightweight frame-liveness ping (DOC-08/10).

### 6.2 `AssetHealthService::markStale()` (②, scheduled every minute — DOC-01 §A8)
- For each device, if `last_seen_at` is older than its **per-type threshold**, set `status = offline` and raise a deduplicated alert (DOC-07) `device_offline` with `dedupeKey = "device_offline:{id}"` (warning severity).
- For each camera, if `last_frame_at` is older than the camera threshold, set `status = offline` and raise `camera_offline`.
- Per-type staleness thresholds (settings, DOC-18): `rfid_reader` 5 min, `gas_detector` 5 min, `environmental_sensor` 5 min, `wifi_gateway` 5 min, `edge_compute` 3 min, camera `last_frame_at` 3 min. `qr_printer` is not health-critical (informational only).

### 6.3 Recovery (②)
- The next heartbeat/ingest/frame from an offline device/camera flips `status` back to `online` and resolves the open offline alert (`resolveByDedupeKey`).

### 6.4 Maintenance suppression
- A device or asset set to `maintenance` (③, e.g. gas detector removed for a bump test — DOC-11 §E-S3) is **skipped by `markStale`** — no offline alert while under maintenance. Restoring it to `active` re-enables monitoring.

### 6.5 Escalation (gas telemetry)
- If a `gas_detector` (or its `wifi_gateway`) stays offline **> 30 minutes**, raise an additional **critical** `system` alert ("Gas telemetry lost on {asset} — detector local alarms still active; dispatch a check"), because on-detector alarms still protect the crew even when the dashboard is blind (proposal §6.2). This is the one health case escalated to critical.

### 6.6 System health overview
- `GET /api/dashboard/summary` (DOC-16) and a dedicated `SystemHealthController` expose a per-asset rollup: for each asset, counts of online/offline/degraded cameras + devices, the newest `last_heartbeat_at`, and any open offline/system alerts. Drives the `SystemHealthWidget` (green/amber/red per asset with a tooltip listing offline components) and the acceptance check "all readers bound + health green" (DOC-20).

---

## 7. Frontend (React / Inertia)

- **`pages/settings/assets/{index,show}.tsx`** — AssetListPage (type/status filters, health badge) → AssetDetailPage (tabs: Cameras, Devices; current location; retire action). AssetForm modal.
- **`pages/settings/cameras/index.tsx`** — CameraListPage (online/offline indicator, AI-enabled toggle, stream test). CameraForm modal.
- **`pages/settings/devices/index.tsx`** — DeviceListPage grouped by device_type (status badge, last-seen relative time, token-status indicator, "Generate token" action, status/maintenance toggle). DeviceForm modal; TokenRevealModal (one-time).
- **`components/ir4/SystemHealthWidget.tsx`** — per-asset health tiles; reused on the dashboard (DOC-16).
- **Types (`types/hardware.ts`):** `Asset` (incl. `is_mobile`), `AssetType`, `AssetStatus`, `Camera` (incl. `processed_by_device_id`), `CameraType`, `Device`, `DeviceType`, `HardwareStatus`, `SystemHealth`, `DeviceTokenResult`.
- Cache invalidation on every mutation; health tiles update via the `site.system` → `DeviceStatusChanged` websocket (channel name simplified to `system` in the standalone instance — DOC-08) plus the 60 s dashboard poll.

---

## 8. Linkage map

| Related entity | Direction | FK | Owning DOC |
|---|---|---|---|
| Camera → Asset | many cameras per asset | `cameras.asset_id` | this doc |
| Device → Asset | many devices per asset | `devices.asset_id` | this doc |
| Reader ↔ Zone binding | rfid_reader device 1—* bindings | `reader_zone_bindings.device_id` | DOC-06 |
| Tag readings → device + asset | reading references device (`reader_ref`→`devices.reference`) | DOC-09 |
| PPE/fall events → camera | event references camera (`camera_ref`→`cameras.reference`) | DOC-10/14 |
| Gas/CO₂/env readings → device + asset | reading references device | DOC-11/12 |
| Offline/system alerts → device/camera | alertable morph | DOC-07 |
| Incident evidence → camera | snapshot/video from a camera | DOC-14 |

Ingestion always resolves the producing hardware by its stored `reference`/token, never by an id the caller supplies — so a device can only ever write data attributed to itself.

---

## 9. Real-life scenarios

- **Commissioning a pole:** operator creates Asset "Pole 2" (`is_mobile=true`) → adds its cameras (references `pole2-cam-n`, `pole2-cam-s` — this pole happens to have two) and its RFID reader (`pole2-reader`), plus any gas detector and edge unit fitted → issues a token per device → edge unit is configured with the tokens → heartbeats arrive → all tiles green on the health widget. A different pole with a single camera and no gas detector is registered exactly the same way, just with fewer children.
- **Connectivity drop:** a pole loses its link for 2 h → within 5 min its reader/gas/CO₂ devices go `offline` with dedup'd warning alerts; if a gas detector crosses 30 min a critical system alert fires → link restores → heartbeats/ingest flip everything back to `online` and resolve the alerts (backfill handling in DOC-08).
- **Repositioning:** a pole moves to a new work front → operator updates its `current_location_label` and rebinds its reader to the now-covered zone (DOC-06) → cameras/devices keep their references and tokens; nothing is re-issued.
- **Bump test:** a gas detector is removed for calibration → operator sets that device to `maintenance` → no offline alert for the duration → restored to `active` afterward.
- **Adding hardware later:** mid-project the team adds a 5th pole and two more cameras → operator registers them; the live wall, tracking map, health widget, and reports all pick them up automatically because everything iterates over registered hardware — no code change, no config constant to bump.
- **Token compromise:** an edge unit is decommissioned/re-imaged → operator rotates its device token → old token immediately rejected on ingest (403), new token configured.

---

## 10. Tests (this doc's slice of DOC-21)

- CRUD for assets/cameras/devices; unique `identifier`/`reference`/`serial_number`; delete guards (asset with children → 409; bound reader → 409; retire vs delete).
- **Token:** issuing returns plaintext once and stores only the hash; the same token then authenticates that device on an ingest route (DOC-08 integration); rotating invalidates the old token immediately; retired device's token rejected (403); token value never appears in any GET or audit row.
- **Heartbeat/health:** heartbeat updates `last_seen_at` + asset `last_heartbeat_at`; `markStale` flips a stale device to `offline` and raises a dedup'd `device_offline` alert (one alert despite repeated runs); recovery resolves it; camera staleness uses `last_frame_at`.
- **Maintenance suppression:** a `maintenance` device is not marked offline and raises no alert.
- **Gas escalation:** a gas detector offline > 30 min raises the additional critical `system` alert.
- **Reference resolution:** an ingest event carrying `reader_ref`/`camera_ref` resolves to the correct device/camera; an unknown reference is rejected (DOC-08).
- Authorization: all hardware mutations require `manage-devices`; the health overview requires `view-dashboard`.

---

## 11. Open decisions logged

| # | Decision | Default | Confirm in |
|---|---|---|---|
| 1 | Stream URL exposure to browser | via signed stream descriptor, not raw URL | DOC-16 |
| 2 | Bulk token issuance (per-asset printable sheet) | provided | DOC-20 |
| 3 | Per-type staleness thresholds | reader/gas/co2/env/gateway 5 min, edge 3 min, camera 3 min | DOC-18 |
| 4 | QR printer as a device vs. a settings-only endpoint | modeled as a `qr_printer` device (non-health-critical) | DOC-13 |
| 5 | Hardware inventory | **fully dynamic, operator-registered, nothing seeded/hardcoded** (§1.1) | this doc |
| 6 | `camera.processed_by_device_id` (which edge unit runs a camera's AI) | optional/dynamic mapping | DOC-10 |

---

### Next document
**DOC-06 — Zones, Reader Bindings & the Repositioning Model:** logical zones, the time-aware reader↔zone bindings that let mobile poles reposition every 5–7 days without corrupting history, zone access lists, and the map-placement data the tracking view and height-harness cross-check rely on.