# DOC-16 — Dashboard, Display Mode & Design Language

> **Depends on:** DOC-01 (conventions, hybrid surfaces, frontend stack), DOC-02 (idle timeout; 55″ wall = same session), DOC-03 (role-aware visibility, PM KPI variant), DOC-05/06 (system health, zone occupancy data), DOC-07 (alert panel/banner), DOC-08 (Reverb channels + poll fallback), DOC-09 (headcount/positions/readings), DOC-10/11/12 (PPE/gas/weather cards), DOC-13/14/15 (overdue equipment / open incidents+LSR / last report cards). **Feeds:** the operator's first screen and the 55″ wall.
>
> **Scope:** the **command-centre dashboard** — the single `/api/dashboard/summary` aggregate, the **design language** (this is where the platform's visual identity is defined, since the references call for analytical, beautiful visuals), the **role-aware widget grid**, the **zone occupancy / reading tables**, the **55″ wall as a screen-cast of `/dashboard`**, and the **navigation/sidebar** with permission-based hiding. **Out of scope:** the data each widget shows (owned by its module) — this doc composes and *presents* it. There is **no** `/display` kiosk route.

---

## 1. Purpose & the visual thesis

The dashboard is the operator's home and the 55″ wall's content (via workstation screen-cast) — the at-a-glance safety state of the site. The references establish the target: a **dark, analytical, data-dense but calm** interface — KPI stat cards with sparklines and trend deltas, rich multi-series charts with hover detail and range toggles, and a clean shell with a quiet sidebar. We adopt that *visual language*, grounded in the **safety command-centre** subject (not finance): the hero is not a big number but the **live safety picture** — headcount, open critical alerts, and zone occupancy — because in a command centre "is everyone safe right now?" is the single job of the screen.

---

## 2. Design language (the platform's visual identity)

This section is authoritative for all operator UI, not just the dashboard — DOC-01's `frontend-design` foundation, made concrete here so every module's screens cohere.

### 2.1 Palette — "Control Room" (dark, instrument-panel calm)
Named tokens (CSS variables in `resources/css/app.css`); the platform is **dark-first** (a command centre and a 55″ wall are viewed in low light):
| Token | Hex | Use |
|---|---|---|
| `--bg` | `#0B0F14` | app background (deep slate-black, not pure black) |
| `--surface` | `#131A22` | cards / panels |
| `--surface-2` | `#1B2530` | raised elements, table rows, tooltips |
| `--border` | `#243040` | hairline borders, dividers |
| `--text` | `#E6EDF3` | primary text |
| `--text-dim` | `#8A97A6` | labels, captions, axes |
| `--accent` | `#38BDF8` | primary accent (signal cyan) — links, active nav, focus |
| `--ok` | `#34D399` | healthy / on-track (green) |
| `--warn` | `#F5A524` | warning severity (amber) |
| `--crit` | `#F0506E` | critical severity / alarms (red) |
Severity colors (`--ok`/`--warn`/`--crit`) are **reserved for meaning** — never decorative — so a red element on this UI always means "critical." Accent cyan is the only "brand" color and is used sparingly (active states, the one sparkline stroke, focus rings).

A **light theme** is provided (tokens flipped) for daytime office use / printing, but dark is the default (and what the 55″ wall shows).

### 2.2 Typography
- **Display/number face:** a tight, confident grotesque for the big stat numbers and headings — e.g. **Inter Tight** or **Space Grotesk** (bundled locally, no CDN — DOC-01 on-prem). Numbers are the hero, so a face with good tabular figures matters.
- **Body/UI face:** **Inter** (bundled) for labels, tables, forms — neutral, legible at a distance on the wall.
- **Data/mono:** a monospace (e.g. **JetBrains Mono**) for IDs, timestamps, and tabular readouts (gas ppm, tag UIDs) so columns align.
- **Tabular figures on everywhere numbers change live** (headcount, gas values) so digits don't jitter as they update.
- Type scale: stat numbers 40–56px, section headers 18–20px, body 14px, captions/labels 12px uppercase-tracked for the "eyebrow" labels on cards.

### 2.3 Card & chart system (the signature)
- **Stat cards** (from reference 1 & 3): rounded (`--radius: 14px`), `--surface` background, a 12px uppercase-tracked **label** with a small trailing chevron/deep-link, a large tabular **value**, a **trend delta** chip (`+12 today ↑` in `--ok`, `−6% ↓` in `--crit`) reading against a stated baseline (last shift / last week), and a **sparkline** filling the card bottom. The sparkline uses one accent stroke with a soft gradient fill — the recurring visual motif.
- **Analytical charts** (from reference 2): area/line charts with a subtle grid, **hover tooltip** showing the exact values at a timestamp, a **Day / Week / Month range toggle**, and a moving vertical crosshair. Multi-series where relevant (e.g. two gas channels) with a small legend.
- **Zone occupancy** is the command-centre presence picture (§5) — tables, not a map.
- Motion: restrained — numbers **tween** to new values (200ms), a new critical alert **pulses** its card border once, live cards carry a tiny "live" dot. No gratuitous animation (DOC-01 / frontend-design restraint).
- Charts via **recharts** (DOC-01).

### 2.4 Restraint
One bold thing per screen: on the dashboard it's the **live occupancy table + critical-alert state**; everything else stays quiet. Severity color does the emphasis; the layout stays a calm grid. This keeps the wall readable across a room and avoids the "AI-generated dashboard" busyness.

---

## 3. The aggregate endpoint

- **`GET /api/dashboard/summary`** — one call returning everything the widget grid needs, so the dashboard loads in a single round-trip and the poll fallback (DOC-08 §5.4) is one request. Shape:
```json
{
  "headcount": { "total_on_site": 0, "by_zone": [ { "zone_id", "zone_name", "count" } ] },
  "alerts": { "open_critical": 0, "open_warning": 0, "latest": [ /* AlertResource, identity-stripped */ ] },
  "gas": { "panels": [ { "device_id", "asset", "status": "ok|warn|crit", "channels": { "lel_pct", "h2s_ppm", "o2_pct", "co_ppm", "co2_ppm" }, "stale": false } ] },
  "weather": { "temperature_c", "humidity_pct", "wind_speed_ms", "updated_at", "stale": false },
  "ppe_today": { "total", "by_type": {…}, "trend_delta" },
  "incidents": { "open": 0, "under_review": 0 },
  "lsr": { "open": 0, "by_category": [ … ] },
  "equipment": { "overdue": 0, "due_soon": 0, "checked_out": 0 },
  "system_health": { "assets": [ { "asset", "status": "green|amber|red", "offline_components": [ … ] } ] },
  "last_report": { "report_number", "period", "status", "generated_at" }
}
```
- Assembled by `DashboardController@summary` from each module's read services (cached ~5–10 s). Identity fields (alert payloads, zone names are fine; worker names are not shown here) respect `view-worker-identity`.
- Live deltas ride the Reverb channels (DOC-08 §5): `tracking` (headcount), `gas` (panels), `alerts`, `environment`, `system` (health) patch the already-rendered cards; the 60 s poll of this endpoint reconciles.

---

## 4. The widget grid (role-aware)

Composed in `pages/dashboard/index.tsx`. Widgets, each a card from §2.3:

| Widget | Data | Visual | Permission to see |
|---|---|---|---|
| **Total Manpower** | headcount.total_on_site | big tabular number + sparkline of the day + delta vs last shift | view-dashboard |
| **Zone Headcount** | headcount.by_zone | compact bar/pill list per zone | view-tracking |
| **Open Alerts** | alerts.open_critical/warning + latest | severity-colored counts + a short live feed | view-dashboard |
| **Zone occupancy** | positions + zones (§5) | occupancy table + on-site / in-red counts | view-tracking |
| **Gas Status** | gas.panels | one mini-gauge strip per device, green/amber/red | view-gas |
| **CO₂** | gas co2 | a single tile with trend | view-gas |
| **Weather** | weather | temp/humidity/wind tiles + updated-at | view-dashboard |
| **PPE Today** | ppe_today | count + by-type mini-bars + FP note | view-ppe |
| **Open Incidents** | incidents | count by status, deep-link | view-incidents |
| **Open LSR** | lsr | count by category | view-lsr |
| **Overdue Equipment** | equipment | overdue + due-soon + checked-out | view-equipment |
| **System Health** | system_health | per-asset green/amber/red tiles | view-dashboard |
| **Last Report** | last_report | status chip + download | view-reports |

- **Role-aware rendering:** each widget renders only if the user holds its `view-*` permission (frontend guard + the summary endpoint omits data they can't see — DOC-03). A user sees a grid of exactly what they're entitled to; empty gaps reflow.
- **Project-Manager KPI variant:** a PM (dashboard + published reports only, DOC-03) gets a **cut-down grid** — Total Manpower (count only, no identity), Open Incidents/LSR counts, Overdue Equipment, Last Report — the oversight KPIs, none of the operational occupancy tables or gas detail. Enforced by permission, not a separate page.

---

## 5. Zone occupancy & readings (no GPS)

RFID is **zone-level presence**, not coordinates. There is **no site map, lat/long, or MapLibre**. A person is in a zone because a reader bound to that zone saw their tag.

- **Occupancy table** — one row per active zone: name, type, on-site count, occupancy limit, bound reader count.
- **Presence table** — who is on site now (identity-stripped without `view-worker-identity`).
- **Readings table** — `GET /tracking/api/readings?zone_id=` — all zones or one selected zone; columns time, zone, reader, tag, person, RSSI, antenna.
- Shared components: `components/ir4/zone-tables.tsx` (dashboard, tracking).
- Updates live from the `tracking` channel (`HeadcountUpdated`/`PositionsUpdated`); a rebind changes which zone later reads resolve to.

---

## 6. The 55″ wall

- There is **no `/display` route** and no kiosk layout. The wall is a **screen-cast / screen-mirror of the SCC workstation** showing `/dashboard` (DOC-02 §5.3).
- Auth, RBAC, Reverb, and **normal idle timeout** are the workstation's. An idle operator is signed out on the workstation and therefore on the wall.
- **LIVE / RECONNECTING pill** is always visible on `/dashboard` (DOC-08 §5.4) so a frozen feed is obvious at room distance.
- The page carries the logged-in user's permissions (a PM dashboard is the KPI-limited variant).

---

## 7. Navigation & shell

- **Sidebar** (quiet, `--surface`, active item in `--accent`): grouped, with items hidden unless the user holds the matching `view-*` permission (DOC-03):
  - **Overview** — Dashboard, Alerts, Live View, Environment, Gas, PPE Trends, Permits (live board)
  - **Site** — Live Tracking, Tag readings, Entry/Exit, Evacuation
  - **Safety** — Gas Alarms, PPE Violations, LSR, Vehicle Violations, Incidents
  - **Equipment** — Items, Checkouts
  - **Workforce** — Workers, Permit register, Work orders, Portable Devices
  - **Admin** — Hardware, Access, Reports, Settings (General, Zones, Repositioning, Audit Log, …)
- **Top bar:** global search (workers/equipment/incidents), the **alert bell** with open count (DOC-07), the LIVE/RECONNECTING pill, the user menu (profile, theme toggle, logout), and an idle-timeout indicator (DOC-02).
- **`AppLayout`** hosts `AlertProvider` (toasts/chime), `useIdleLogout`, and the shared permission context.

---

## 8. Frontend (React / Inertia)

- **`pages/dashboard/index.tsx`** — the widget grid; Inertia snapshot then Reverb deltas via `useDashboardLive` (`AlertRaised`/`AlertUpdated`, `HeadcountUpdated`/`PositionsUpdated`, `GasLiveUpdated`, `DeviceStatusChanged`) with a 60 s poll of `/api/dashboard/summary` while the socket is down; LIVE/RECONNECTING pill. `DeviceStatusChanged` includes `asset_id` so system-health tiles patch without refetching the aggregate.
- **Components (`components/ir4/`):** `StatCard` (label + value + delta chip + sparkline), `RangeToggle` (Day/Week/Month), `AnalyticalChart` (area/line + hover tooltip + crosshair, recharts), `ZoneOccupancyTable`, `GasPanelStrip`, `WeatherTiles`, `SystemHealthTiles`, `AlertFeed`, `SeverityBadge`, `LiveDot`.
- **Design tokens** in `resources/css/app.css` (the §2 palette + radius + shadows); a small `useTheme()` for dark/light.
- **Types (`types/dashboard.ts`):** `DashboardSummary` (typed to §3), `StatCardProps`, `ChartRange`.
- Every number that updates live uses tabular figures + a 200ms tween; no layout shift on update.

---

## 9. Real-life scenarios

- **Shift start:** operator opens the dashboard → sees 0 on-site climbing as workers badge in (Total Manpower tweens up, occupancy counts rise), gas panels green, no open alerts → the calm baseline.
- **Critical event:** a gas alarm fires → the Gas Status card border pulses `--crit`, the Open Alerts card jumps, the alert chime fires → operator acts; when resolved, everything settles back to `--ok`.
- **On the wall:** the 55″ shows the same `/dashboard` the workstation has open; a critical alert is visible on the feed and the LIVE pill stays green while the socket is up.
- **PM check-in:** a Project Manager opens the dashboard → sees only KPI cards (manpower count, open incidents/LSR, overdue equipment, last report) — no occupancy tables, no gas detail — enough for oversight.
- **Feed drop:** the socket drops → the LIVE pill flips to RECONNECTING (amber), cards keep last values and poll every 60 s → on reconnect, LIVE (green) and a fresh snapshot.

---

## 10. Tests (this doc's slice of DOC-21)

- **Summary endpoint:** returns all sections; omits data the user lacks permission for; identity-stripped where applicable; cached.
- **Role-aware grid:** a user sees exactly the widgets their permissions allow; PM gets the KPI variant (no occupancy/gas); an operator gets the full grid.
- **Live vs poll:** widgets patch from Reverb events; on socket loss the poll of `/api/dashboard/summary` reconciles and the LIVE/RECONNECTING pill reflects state.
- **Wall:** `GET /display` is gone (404). `/dashboard` is the only command surface; guests redirect to login.
- **Occupancy:** zone tables from reader bindings + live headcount/positions; labels anonymize without `view-worker-identity`; reflects a reader rebind.
- **Design tokens:** severity colors map to meaning (crit/warn/ok) consistently; tabular figures on live numbers (visual/regression check).

---

## 11. Open decisions logged

| # | Decision | Default | Confirm in |
|---|---|---|---|
| 1 | Zone visualisation | occupancy / presence / reading tables (no GPS) | this doc |
| 2 | Display face (Inter Tight vs Space Grotesk) | Inter Tight (bundled) | this doc |
| 3 | 55″ wall | workstation screen-cast of `/dashboard`; no kiosk route | this doc / DOC-02 |
| 4 | Light theme scope | provided but dark is default | this doc |
| 5 | Dashboard cache TTL | 5–10 s | DOC-18 |

---

### Next document
**DOC-17 — Audit Logging:** the append-only `audit_logs`, the `Auditable` trait coverage across config/security-relevant models, the read-only-role `data_access` logging, the event catalogue, sensitive-field masking, and the read-only audit viewer.