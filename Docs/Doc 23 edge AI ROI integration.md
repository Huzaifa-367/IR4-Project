# Edge AI — Camera ROI integration guide (Jetson)

> **Audience:** AI / edge engineers on the pole Jetson.  
> **Authoritative product docs:** [Doc 23 camera rois.md](./Doc%2023%20camera%20rois.md), [Doc 08 ingestion realtime.md](./Doc%2008%20ingestion%20realtime.md), [site-network.md](../site-network.md).  
> **Purpose:** Replace the standalone “Redzone polygon API” with the **IR4 contract**. The SCC is source of truth for polygons; the Jetson applies them and reports intrusions back.

---

## 1. Direction of traffic (read this first)

| Direction | Who calls whom | Purpose |
|---|---|---|
| **SCC → Jetson** | IR4 POSTs to `http://{jetson-ip}:8600/rois` | After an operator **publishes** ROIs on the dashboard |
| **Jetson → SCC** | Your code GETs / POSTs IR4 `/api/...` | Optional pull of ROIs; **required** for intrusion events |
| **Not used** | Browser → Jetson `:8600` | Operators never talk to the Jetson; only IR4 does |

`192.168.x.x` / VLAN SCC addresses (e.g. `172.16.3.40:9100`) are the **IR4 platform**. Port **8600** is **your** process on the Jetson — opposite direction from ingest.

---

## 2. What to stop using (old Redzone API)

Do **not** keep these as the integration surface with IR4:

| Old (yours) | Status |
|---|---|
| `X-Redzone-Token: …` | **Retired** for IR4 — do not use for SCC traffic |
| `POST /redzones/{camera_ref}` with `{ "zones": [ { "zone_id", "label", "enabled", "points" } ] }` | **Replace** with IR4 push body on `POST /rois` |
| `GET /redzones/...` as dashboard source of truth | **No** — IR4 dashboard reads its own DB; Jetson disk is a **local cache** only |
| Naming “zone” / “redzone” in IR4 payloads | **Avoid** — IR4 uses **ROI** (RFID “zones” are a different DOC-06 concept) |

You may keep a local `/health` on `:8600` for your process. IR4 does not call it today.

---

## 3. Naming map (old → IR4)

| Old Redzone field | IR4 field | Notes |
|---|---|---|
| `zone_id` | `rois[].reference` | Stable id; used again on violation ingest as `roi_reference` |
| `label` | `rois[].name` | Display name |
| `enabled` | (filtered server-side) | SCC only pushes **enabled** ROIs with ≥3 points |
| `points[]` | `rois[].polygon[]` | Same shape: `{ "x", "y" }` in **0.0–1.0** |
| path `{camera_ref}` | Body `device` + linked camera | Camera is implied by the AI **device** (1:1). Use `device.config.camera_ref` / stream context locally if you still need `CAM-FIXED-01` |

Camera refs in the field stay the same: `CAM-FIXED-01`, `CAM-PTZ-01`, pole 2 → `…-02`, etc.

Device refs (auth / push identity): `DEV-CAM-FIXED-01`, `DEV-CAM-PTZ-01`, …

---

## 4. Jetson must accept — SCC push

### 4.1 Endpoint

```
POST http://{jetson-lan-ip}:8600/rois
Content-Type: application/json
```

- **Port:** `8600`  
- **Path:** `/rois` (not `/redzones/...`)  
- **Auth (v1):** none required from SCC today (LAN-only). Do **not** require `X-Redzone-Token` for this call or SCC publishes will fail.  
- **Semantics:** **full replace** for this camera/device — whatever arrives is the complete active ROI list. Empty `rois: []` means clear.

IR4 resolves the push URL from **Settings → Devices → API URL** on that camera’s AI device (full endpoint, e.g. pole 1 `http://172.16.3.2:8600/rois` — see `site-network.md`).

### 4.2 Body (exact shape IR4 sends)

Same object as IR4 `GET /api/devices/{deviceUuid}/camera-rois` → `data`:

```json
{
  "device": {
    "id": 3,
    "uuid": "7cd2d9bc-4986-4c07-9cd8-631a2eb66b02",
    "reference": "DEV-CAM-FIXED-01",
    "name": "Pole 01 Fixed Camera"
  },
  "view_fingerprint": "f6882bf2c825c4a0b35e26e5559afeffab1b6829",
  "published_at": "2026-08-25T18:00:00+00:00",
  "rois": [
    {
      "name": "Bay floor",
      "reference": "roi_bay_floor",
      "polygon": [
        { "x": 0.12, "y": 0.30 },
        { "x": 0.45, "y": 0.28 },
        { "x": 0.40, "y": 0.61 }
      ],
      "sort_order": 0,
      "meta": null
    }
  ]
}
```

**Rules for your parser:**

- `polygon[].x` / `y` are normalized **0.0–1.0** (not pixels).  
- Only use entries with ≥3 points (SCC already filters).  
- `rois[].reference` is the id you must echo on violations (`roi_reference`).  
- Persist locally if you want reboot survival — but treat each POST as authoritative overwrite.  
- When `view_fingerprint` changes vs your last apply, discard old geometry and reload (PTZ / stream change).

**Suggested success response** (IR4 only checks HTTP 2xx today):

```json
{ "ok": true }
```

or any 200/204. Prefer not returning 422 unless the body is unusable; log and ignore bad polygons rather than failing the whole publish if possible.

### 4.3 One Jetson, two cameras

Pole typically has **two** AI devices / streams:

| Camera | Device reference (example) | Same Jetson IP? |
|---|---|---|
| `CAM-FIXED-01` | `DEV-CAM-FIXED-01` | Yes (e.g. `172.16.3.2`) |
| `CAM-PTZ-01` | `DEV-CAM-PTZ-01` | Yes |

IR4 POSTs **twice** (once per publish), same host:8600 `/rois`, different `device.uuid` / `device.reference` and different `rois`. Key your in-memory tables by **`device.reference`** or **`device.uuid`**, and map to the correct RTSP/camera pipeline via `camera_ref` you already know for that device.

---

## 5. Jetson may call — SCC pull (optional)

If you prefer pull / reconcile instead of (or in addition to) push:

```
GET {IR4_BASE_URL}/api/devices/{deviceUuid}/camera-rois
Accept: application/json
X-Device-Token: {plain_device_token}
```

- `{deviceUuid}` = that AI device’s public UUID (must match the token).  
- Token = plaintext from IR4 hardware commissioning (shown once). **Not** the old Redzone token.  
- `IR4_BASE_URL` from the pole is the SCC on that VLAN (e.g. pole 1 → `http://172.16.3.40:9100`). See EdgeCompute secrets / `site-network.md`.

### Response

**200** — envelope `{ "data": … }`. Body inside `data` is **identical** to the SCC → Jetson push body (§4.2):

```json
{
  "data": {
    "device": {
      "id": 3,
      "uuid": "7cd2d9bc-4986-4c07-9cd8-631a2eb66b02",
      "reference": "DEV-CAM-FIXED-01",
      "name": "Pole 01 Fixed Camera"
    },
    "view_fingerprint": "f6882bf2c825c4a0b35e26e5559afeffab1b6829",
    "published_at": "2026-08-25T18:00:00+00:00",
    "rois": [
      {
        "name": "Bay floor",
        "reference": "roi_bay_floor",
        "polygon": [
          { "x": 0.12, "y": 0.30 },
          { "x": 0.45, "y": 0.28 },
          { "x": 0.40, "y": 0.61 }
        ],
        "sort_order": 0,
        "meta": null
      }
    ]
  }
}
```

**200 — no active set** (draft / stale / never published):

```json
{
  "data": {
    "device": {
      "id": 3,
      "uuid": "7cd2d9bc-4986-4c07-9cd8-631a2eb66b02",
      "reference": "DEV-CAM-FIXED-01",
      "name": "Pole 01 Fixed Camera"
    },
    "view_fingerprint": null,
    "published_at": null,
    "rois": []
  }
}
```

| Status | When |
|---|---|
| **200** | Token matches `{deviceUuid}`; `data` always present (possibly empty `rois`) |
| **401** | Missing / invalid `X-Device-Token` |
| **403** | Token valid but does not belong to `{deviceUuid}` |
| **404** | Unknown device UUID |

Apply the same rules as §4.2 (`polygon` normalized, key by `device`, honour `view_fingerprint`). Token is never returned in the body.

---

## 6. Jetson must call — intrusion ingest

When detection fires inside an ROI:

```
POST {IR4_BASE_URL}/api/ingest/roi-violations
Content-Type: application/json
X-Device-Token: {plain_device_token}
```

```json
{
  "events": [
    {
      "event_uid": "550e8400-e29b-41d4-a716-446655440000",
      "camera_ref": "CAM-FIXED-01",
      "roi_reference": "roi_bay_floor",
      "event_type": "roi_intrusion",
      "detected_at": "2026-08-25T18:01:00Z",
      "confidence": 0.91,
      "snapshot": null
    }
  ]
}
```

| Field | Rule |
|---|---|
| `event_uid` | UUID; idempotent with retries |
| `camera_ref` | `CAM-FIXED-01` / `CAM-PTZ-01` / … |
| `roi_reference` | Must match a published `rois[].reference` |
| `event_type` | `roi_intrusion` only (v1) |
| `detected_at` | ISO-8601 |
| `confidence` | 0–1 |
| `snapshot` | Optional raw base64 JPEG |

**Response:** `202` `{ "accepted", "duplicates", "rejected": [ { "index", "code" } ] }`.  
Unknown camera → `UNKNOWN_REFERENCE`; unknown ROI → `UNKNOWN_ROI`.

Same pattern as PPE ingest (`/api/ingest/ppe-violations`) — reuse your existing IR4 HTTP client / buffer.

---

## 7. Checklist for your codebase

1. **Add** `POST /rois` accepting the IR4 body in §4.2 (full replace).  
2. **Stop requiring** `X-Redzone-Token` on that path (and stop documenting it for SCC).  
3. **Map** internal models: `zone_id`→`reference`, `label`→`name`, `points`→`polygon`.  
4. **Key** state by `device.uuid` or `device.reference` (two cams per pole).  
5. **On apply**, store `view_fingerprint`; if it changes, drop stale polygons.  
6. **On intrusion**, POST `/api/ingest/roi-violations` with `X-Device-Token` + `camera_ref` + `roi_reference`.  
7. **Optional:** poll `GET /api/devices/{uuid}/camera-rois` as backup if a push was missed.  
8. **Deprecate** `POST/GET /redzones/...` for IR4 integration (keep only if you need a private debug tool).

---

## 8. Quick contrast

| | Old Redzone API | IR4 |
|---|---|---|
| Set polygons | `POST /redzones/{camera_ref}` `{zones:[…]}` | `POST /rois` `{device, view_fingerprint, published_at, rois:[…]}` |
| Auth from SCC | `X-Redzone-Token` | (none on `:8600` v1) |
| Auth to SCC | — | `X-Device-Token` + device UUID |
| Source of truth | Device disk | **SCC** (publish); device caches |
| Report hit | (custom) | `POST /api/ingest/roi-violations` |
| Vocabulary | zone / redzone | **ROI** |

---

## 9. Contacts / config on IR4

- Device UUID + token: IR4 **Settings → Devices** (issue/rotate token).  
- Jetson API URL: same device form → **API URL** (edge_compute only, e.g. `http://172.16.3.2:8600/rois`).
- Postman: collection folder **Camera ROIs** (pull) + **Edge AI (Jetson ROI push)** + ingest **roi-violations**.  
- Product rules: **Doc 23**.
