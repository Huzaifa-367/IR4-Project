# IR4 Postman collection

Import into Postman:

1. **Collection:** `IR4-API.postman_collection.json`
2. **Environment:** `IR4-Local.postman_environment.json`
3. Select environment **IR4 Local**
4. Set secrets / IDs:
   - `deviceToken` — plain token from Hardware → Devices (shown once on create/rotate)
   - `deviceUuid` — device **UUID** (route key for heartbeat), not integer id
   - `readerRef` / `cameraRef` / `deviceRef` — match registered hardware `reference` values
   - `mobileEmail` / `mobilePassword` — operator user for mobile API
   - `qrToken` — equipment permanent QR UUID
   - `equipmentUuid` — equipment **UUID** for checkout/return
   - `workerId` / `zoneId` — integer primary keys

## Folders

| Folder | Auth | Use |
|---|---|---|
| Health | none | `/api/health` |
| Device API | `X-Device-Token` | ingest + heartbeat |
| Mobile API | Bearer Sanctum | login → scan → checkout/return |
| Operator JSON helpers | Fortify session cookie | live poll snapshots |
| Public | none | `/e/{qrToken}` |

Ingest requests auto-generate a fresh `event_uid` + timestamp on each send. Mobile **Login** writes `mobileToken` into the environment.

Device and equipment route params use public UUIDs (`HasPublicUuid`).
