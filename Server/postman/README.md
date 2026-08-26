# IR4 Postman collection

Import into Postman:

1. **Collection:** `IR4-API.postman_collection.json`
2. **Environment:** `IR4-Local.postman_environment.json`
3. Select environment **IR4 Local**
4. Set secrets / IDs:
   - `baseUrl` — local Laravel, e.g. `http://127.0.0.1:8000` (or your SCC URL)
   - `deviceToken` — plain token from Hardware → Devices (shown once on create/rotate). For camera headcount / PPE / ROI use an **edge_compute** device (demo: `DEV-CAM-FIXED-01`).
   - `deviceUuid` — device **UUID** (route key for heartbeat), not integer id
   - `readerRef` / `cameraRef` / `deviceRef` — match registered hardware `reference` values (demo cameras: `CAM-FIXED-01` …)
   - `headcountCount` — absolute FOV count for `POST /api/ingest/headcount-readings`
   - `mobileEmail` / `mobilePassword` — operator user for mobile API
   - `qrToken` — equipment permanent QR UUID
   - `equipmentUuid` — equipment **UUID** for checkout/return
   - `workerId` / `zoneId` — integer primary keys

## Folders

| Folder | Auth | Use |
|---|---|---|
| Health | none | `/api/health` |
| Device API | `X-Device-Token` | ingest + heartbeat (incl. headcount-readings) |
| Mobile API | Bearer Sanctum | login → scan → checkout/return |
| Operator JSON helpers | Fortify session cookie | live poll snapshots (headcount + headcount-readings) |
| Public | none | `/e/{qrToken}` |

Ingest requests auto-generate a fresh `event_uid` + timestamp on each send. Mobile **Login** writes `mobileToken` into the environment.

Device and equipment route params use public UUIDs (`HasPublicUuid`).

## Local headcount UI smoke

```bash
cd Server
php artisan migrate   # if camera_headcount_readings / bindings not applied yet
php scripts/mimic_headcount_edge.php
# optional continuous samples:
php scripts/mimic_headcount_edge.php --loop
```

Then open `/tracking` (Latest headcount) and `/tracking/headcount-readings`.
