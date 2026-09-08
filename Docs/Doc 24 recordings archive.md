# DOC-24 — Recordings archive browser

> **Depends on:** DOC-01 (Surface A, config-vs-`.env`), DOC-03 (`view-recordings`), DOC-05 (cameras exist upstream; this module does not rename them), DOC-16 (Control Room styling), DOC-18 (disk paths stay in `.env`), DOC-20 (SCC mounts / nginx). **Feeds:** operator investigation of historical footage from workstations.

> **Scope:** authenticated browse + inline playback of hour-long camera recordings already stored on the SCC under `RECORDINGS_ROOT` (default `/data2/video`). **Out of scope:** live HLS (DOC-10/16), renaming `cam1` → `CAM-FIXED-01`, delete/upload/trim, transcoding, multi-SCC federation, wrapping `rec-check-scc.sh`.

---

## 1. Purpose

Field poles record continuously to disk. Operators need to **navigate those folders as they exist** and **play files in the browser** from a workstation — without SSH, gnome-remote-desktop, or copying files locally.

Honesty: the UI shows **raw directory names** (`pole1`, `cam1`, dates, hour files). No mapping layer in v1.

---

## 2. Data origin & storage

| Concern | Choice |
|---|---|
| Writer | Edge `ir4-record` / MediaMTX on poles → files land under SCC `RECORDINGS_ROOT` (ops path; not app-owned writes) |
| Reader | Surface A only — Fortify session + `view-recordings` |
| Layout | `{pole}/{camera}/{YYYY-MM-DD}/{hour-file}` e.g. `/data2/video/pole1/cam1/2026-09-09/…` |
| Config | `.env` `RECORDINGS_ROOT` — deploy config (DOC-18), not SettingsRegistry |

There is **no** `recordings` table in v1. The filesystem is the source of truth.

---

## 3. Streaming (large files)

```
Workstation ──session──► Laravel /recordings/stream/{path}
                              │ authorize + path jail
                              ▼
                     X-Accel-Redirect: /internal-recordings/…
                              │
                              ▼
                     nginx (internal) serves Range bytes from disk
```

- Production: `RECORDINGS_X_ACCEL=true` + nginx `location /internal-recordings/ { internal; alias …; }`
- Local/dev without nginx snippet: `RECORDINGS_X_ACCEL=false` → `BinaryFileResponse` (Range-capable fallback)
- Stream route strips idle / Inertia share middleware (same rationale as `/hls`) so seeking does not thrash the session

**Never** expose `/data2/video` as a public alias.

---

## 4. Path jail

`RecordingArchiveService` is the only resolver:

- Reject `..`, absolute paths, NUL bytes
- `realpath` must stay under `RECORDINGS_ROOT`
- Symlink escape outside root → 404
- Only allow-listed extensions for play: `mp4`, `m4v`, `webm`, `mkv`, `mov`, `ts`
- Browser props carry **relative** paths only

---

## 5. RBAC

| Permission | Who (seed) | Use |
|---|---|---|
| `view-recordings` | Super Admin (all), Safety Manager, SCC Operator, Project Manager | Browse + play |

Four layers: route middleware → controller `abort_unless` → (no model policy in v1) → sidebar UX guard.

Re-seed / assign `view-recordings` on existing installs after deploy (`RolePermissionSeeder` creates the permission; Super Admin is re-synced to the full catalogue).

---

## 6. Operator UI

- Route: `GET /recordings/{path?}` — Inertia `recordings/index`
- Control Room styling (DOC-16 / Design.md): dark panels, breadcrumb, dense list, inline `<video controls>`
- Click folder → navigate; click playable file → player panel loads same-origin stream URL

---

## 7. SCC commissioning checklist

1. `/data2/video` exists and is readable by the deploy user / nginx.
2. Mount the same path **read-only** into the Lerd PHP container so `realpath` matches nginx.
3. Add nginx internal location (see SCC-SETUP).
4. `.env`: `RECORDINGS_ROOT=/data2/video`, `RECORDINGS_X_ACCEL=true`.
5. `lerd artisan db:seed --class=RolePermissionSeeder` (or grant `view-recordings` in Roles UI).
6. Workstation: open `/recordings`, drill to an hour file, seek in the player.
7. Ops health (optional): keep using host `rec-check-scc.sh` — not wrapped by the app in v1.

---

## 8. Tests

- Path traversal / absolute / symlink escape rejected
- List only children under fixture root; playable flag by extension
- Unauthenticated / missing permission → 403
- Stream sets `X-Accel-Redirect` when enabled; file response when disabled

---

## 9. Non-goals (v1)

- Camera registry rename / linkage
- Download button permission (`download-recordings`) — play is enough
- Delete, upload, clip export
- HLS VOD remux via MediaMTX
