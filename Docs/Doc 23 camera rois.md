# DOC-23 — Camera ROI (detection polygons) + Red Zone violations

> **Depends on:** DOC-01 (conventions, enums, surfaces), DOC-03 (`view-camera-rois`, `manage-camera-rois`, `view-roi-violations`, `update-roi-violations`), DOC-05 (cameras, `processed_by_device_id`, stream URLs), DOC-07 (`roi_violation` alert via `AlertService`), DOC-08 (`auth.device` ingest envelope), DOC-10 (PPE review pattern mirrored here). **Feeds:** edge AI inference (pull ROIs; push violations via ingest). **Edge AI guide:** [Doc 23 edge AI ROI integration.md](./Doc%2023%20edge%20AI%20ROI%20integration.md).
>
> **Scope:** named polygon **regions of interest (ROIs)** drawn on a camera’s live feed; device pull of **active** ROIs; SCC **push** of the same payload to the Jetson AI service on publish; anonymous **Red Zone intrusion** events from edge AI with false-positive review. **Out of scope:** RFID DOC-06 zones (logical presence — not image geometry), auto-redraw / CV-assisted polygons, multi-set version history, trends/exports (v1 list+review only).
>
> **Naming:** technical identifiers stay `roi_*` (image polygons ≠ DOC-06 RFID zones). **Operator UI:** editor module **Red Zones**, review module **Red Zone Violations** (distinct from DOC-07 RFID alert `red_zone_intrusion`).

---

## 1. Purpose

Edge AI needs **image-space** regions — e.g. “only flag intrusion in this work bay.” Operators draw polygons on the live HLS feed; the SCC is source of truth. When PTZ or stream URL changes the view, ROIs are marked **stale**. Each camera **device** (UID + token) **pulls** its active ROIs and **pushes** intrusion detections via ingest.

---

## 2. Data origin

| Path | Writer |
|---|---|
| ③ user | Save draft, publish, manual mark-stale; review Red Zone violations |
| ② system | Auto mark-stale after PTZ **move** / stream or reference change; raise `roi_violation` alerts |
| ① device | Pull active ROIs; ingest Red Zone violation events |

---

## 3. Edge pull — `GET /api/devices/{deviceUuid}/camera-rois`

Same auth shape as heartbeat / device APIs: path `{deviceUuid}` = device public UUID + header `X-Device-Token` (must match). Token never returned. **403** if token and UUID disagree.

**Model:** the authenticating **device is the camera unit** (1:1). ROIs are loaded for the camera bound via `processed_by_device_id` = that device. No multi-camera list.

**Response** (`ApiResponse::ok` → `{ data: … }`):

```json
{
  "data": {
    "device": {
      "id": 1,
      "uuid": "…",
      "reference": "DEV-CAM-FIXED-01",
      "name": "Pole 01 Fixed Camera"
    },
    "view_fingerprint": "sha1…",
    "published_at": "2026-08-25T18:00:00+00:00",
    "rois": [
      {
        "name": "Bay floor",
        "reference": "roi_bay_floor",
        "polygon": [{ "x": 0.1, "y": 0.2 }, { "x": 0.8, "y": 0.2 }, { "x": 0.8, "y": 0.9 }],
        "sort_order": 0,
        "meta": null
      }
    ]
  }
}
```

Only **enabled** ROIs with ≥3 points when the set is `active`. Draft/stale → `rois: []` and null fingerprint / published_at.

---

## 3.1 Edge push — SCC → Jetson AI

On **publish**, the SCC POSTs the **same JSON object** as §3 `data` to the camera device’s AI service (does not block operator publish on failure — logged and retried by re-publish / pull).

| | |
|---|---|
| Method | `POST` |
| URL | `device.config.api_url` (full endpoint, e.g. `http://172.16.3.2:8600/rois`) |
| Body | §3 `data` for that camera |
| Content-Type | `application/json` |

`api_url` is nullable JSON on the **edge_compute** device only (Settings → Devices). Empty → skip push.

Camera↔device link: device `config.camera_ref` matches `camera.reference` (seed / device create). Enforced 1:1 via unique `cameras.processed_by_device_id`.

**Site LAN examples** — see `site-network.md`:

| Pole | `api_url` |
|---|---|
| 1 | `http://172.16.3.2:8600/rois` |
| 2 | `http://172.16.2.2:8600/rois` |
| 3 | `http://172.16.1.50:8600/rois` |
| 4 | `http://172.16.4.2:8600/rois` |

Example: Pole 1 → `POST http://172.16.3.2:8600/rois`.

---

## 4. Edge ingest — `POST /api/ingest/roi-violations`

Same DOC-08 / DOC-10 envelope as PPE (mirror methods; ROI-specific fields only where needed):

```json
{
  "events": [
    {
      "event_uid": "uuid",
      "camera_ref": "CAM-FIXED-01",
      "roi_reference": "roi_bay_floor",
      "event_type": "roi_intrusion",
      "detected_at": "2026-08-25T18:01:00Z",
      "confidence": 0.91,
      "snapshot": "<optional raw base64 jpeg — same store as PPE>"
    }
  ]
}
```

**Rules:**
- Auth: `X-Device-Token` (same as PPE).
- Resolve camera by `camera_ref` (DOC-05 / same `ReferenceResolver` as PPE) → unknown → `UNKNOWN_REFERENCE`.
- `roi_reference` must match an enabled ROI on that camera’s **active** set → else `UNKNOWN_ROI`.
- Dedupe on `(camera_id, event_uid)`.
- Snapshot (optional): raw base64 JPEG via shared `IngestSnapshotStore` → `snapshots/{Y/m/d}/{uuid}.jpg` (identical to PPE).
- Non-backfill → create row + `AlertService::raise(AlertType::RoiViolation)` + broadcast `RoiViolationDetected` on private `roi` channel: `{ id, uuid, event_type, camera_ref, roi_reference, snapshot_url, detected_at }`.
- **No `worker_id`** — anonymous site events (same privacy invariant as PPE).

**Response:** `202` `{ accepted, duplicates, rejected: [{ index, code }] }`.

---

## 5. ROI geometry model (operator)

### 5.1 `cameras.ptz_generation`
Bumped on successful PTZ **move** (not stop). Part of view fingerprint.

### 5.2 `camera_roi_sets` / `camera_rois`
One current set per camera; ROI rows hard-replaced on save/publish. Status: `draft` | `active` | `stale`. Stale reasons: `ptz` | `stream_changed` | `manual`.

Fingerprint: `sha1(stream_url | playback_template | camera.reference | ptz_generation)`.

### 5.3 Operator UI
`/hardware/camera-rois` — online cameras only; editor with live feed + canvas; Live wall overlays draft/active/stale.

---

## 6. `roi_violations` (soft-deleted)

Columns: `uuid`, `camera_id`, `device_id` (nullable FK → ingesting device), `camera_roi_id` (nullable FK), `roi_reference`, `event_type`, `detected_at`, `confidence`, `snapshot_path`, `location_label`, `alert_id`, `review_status`, `reviewed_by/at`, `review_note`, `is_backfill`, `event_uid`. Unique `(camera_id, event_uid)`.

**Review** (`update-roi-violations`): confirm / false_positive (+ note). FP resolves linked alert.

**UI:** `/roi-violations` list + show (PPE-parallel; no trends/export in v1).

**Alert:** `roi_violation` — warning, `suggested_action=log_lsr` (AlertPolicy / DOC-07).

---

## 7. Permissions

- `view-camera-rois`, `manage-camera-rois`
- `view-roi-violations`, `update-roi-violations`

---

## 8. Tests

- Polygon validation; publish rules; PTZ move vs stop; stream/reference stale
- Edge pull: `GET /api/devices/{deviceUuid}/camera-rois` + matching token; body = device + rois (1:1)
- Edge push: on publish POST same payload to `device.config.api_url` (Http::fake in tests)
- Ingest: same as PPE (`camera_ref` + token); `roi_reference` on active set; reject unknown camera/ROI; dedupe; RBAC review
- Live wall overlays; online-only ROI index
