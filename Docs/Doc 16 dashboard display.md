# DOC-16 — Dashboard, Display Mode & Design Language

> **Depends on:** DOC-01 (conventions, hybrid surfaces, frontend stack), DOC-02 (display = authenticated extension view), DOC-03 (role-aware visibility, PM KPI variant), DOC-05/06 (system health, zone map data), DOC-07 (alert panel/banner), DOC-08 (Reverb channels + poll fallback), DOC-09 (headcount/positions/map), DOC-10/11/12 (PPE/gas/weather cards), DOC-13/14/15 (overdue equipment / open incidents+LSR / last report cards). **Feeds:** the operator's first screen and the 55″ wall.
>
> **Scope:** the **command-centre dashboard** — the single `/api/dashboard/summary` aggregate, the **design language** (this is where the platform's visual identity is defined, since the references call for analytical, beautiful visuals), the **role-aware widget grid**, the **live zone map**, the **55″ kiosk display**, and the **navigation/sidebar** with permission-based hiding. **Out of scope:** the data each widget shows (owned by its module) — this doc composes and *presents* it.

---

## 1. Purpose & the visual thesis

The dashboard is the operator's home and the 55″ wall's content — the at-a-glance safety state of the site. The references establish the target: a **dark, analytical, data-dense but calm** interface — KPI stat cards with sparklines and trend deltas, rich multi-series charts with hover detail and range toggles, and a clean shell with a quiet sidebar. We adopt that *visual language*, grounded in the **safety command-centre** subject (not finance): the hero is not a big number but the **live safety picture** — headcount, open critical alerts, and the zone map — because in a command centre "is everyone safe right now?" is the single job of the screen.

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

A **light theme** is provided (tokens flipped) for daytime office use / printing, but dark is the default and the display is always dark.

### 2.2 Typography
- **Display/number face:** a tight, confident grotesque for the big stat numbers and headings — e.g. **Inter Tight** or **Space Grotesk** (bundled locally, no CDN — DOC-01 on-prem). Numbers are the hero, so a face with good tabular figures matters.
- **Body/UI face:** **Inter** (bundled) for labels, tables, forms — neutral, legible at a distance on the wall.
- **Data/mono:** a monospace (e.g. **JetBrains Mono**) for IDs, timestamps, and tabular readouts (gas ppm, tag UIDs) so columns align.
- **Tabular figures on everywhere numbers change live** (headcount, gas values) so digits don't jitter as they update.
- Type scale: stat numbers 40–56px, section headers 18–20px, body 14px, captions/labels 12px uppercase-tracked for the "eyebrow" labels on cards.

### 2.3 Card & chart system (the signature)
- **Stat cards** (from reference 1 & 3): rounded (`--radius: 14px`), `--surface` background, a 12px uppercase-tracked **label** with a small trailing chevron/deep-link, a large tabular **value**, a **trend delta** chip (`+12 today ↑` in `--ok`, `−6% ↓` in `--crit`) reading against a stated baseline (last shift / last week), and a **sparkline** filling the card bottom. The sparkline uses one accent stroke with a soft gradient fill — the recurring visual motif.
- **Analytical charts** (from reference 2): area/line charts with a subtle grid, **hover tooltip** showing the exact values at a timestamp, a **Day / Week / Month range toggle**, and a moving vertical crosshair. Multi-series where relevant (e.g. two gas channels) with a small legend.
- **The zone map** is the one bespoke visual (§5) — the command-centre signature no finance dashboard has.
- Motion: restrained — numbers **tween** to new values (200ms), a new critical alert **pulses** its card border once, live cards carry a tiny "live" dot. No gratuitous animation (DOC-01 / frontend-design restraint).
- Charts via **recharts** (DOC-01); the map via **MapLibre GL** (offline, §5).

### 2.4 Restraint
One bold thing per screen: on the dashboard it's the **live map + critical-alert state**; everything else stays quiet. Severity color does the emphasis; the layout stays a calm grid. This keeps the wall readable across a room and avoids the "AI-generated dashboard" busyness.

---

## 3. The aggregate endpoint

- **`GET /api/dashboard/summary`** — one call returning everything the widget grid needs, so the dashboard loads in a single round-trip and the poll fallback (DOC-08 §5.4) is one request. Shape:
```json
{
  "headcount": { "total_on_site": 0, "by_zone": [ { "zone_id", "zone_name", "count" } ] },
  "alerts": { "open_critical": 0, "open_warning": 0, "latest": [ /* AlertResource, identity-stripped */ ] },
  "gas": { "panels": [ { "device_id", "asset", "status": "ok|warn|crit", "channels": {…}, "co2_ppm", "stale": false } ] },
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
| **Live Zone Map** | positions + zones (§5) | the map (dots + zone circles) | view-tracking |
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
- **Project-Manager KPI variant:** a PM (dashboard + published reports only, DOC-03) gets a **cut-down grid** — Total Manpower (count only, no map/identity), Open Incidents/LSR counts, Overdue Equipment, Last Report — the oversight KPIs, none of the operational live map or gas detail. Enforced by permission, not a separate page.

---

## 5. The live zone map (the signature visual)

- Rendered with **MapLibre GL** using an **uploaded site-plan image as a static raster overlay** (offline — no external tiles, DOC-01 on-prem), or a plain coordinate canvas if no plan is uploaded `[CONFIRM AT DESIGN]`.
- **Zones** drawn as translucent circles from their `map_x/map_y/map_radius` (DOC-06), colored by type (red for restricted, etc.), labeled with live occupancy count.
- **Workers** drawn as dots at their zone (approximate placement within the zone — RFID is zone-level, not GPS, so dots cluster in the zone, not precise points). Dots are **anonymized** (`Worker #id`, stable) unless the viewer has `view-worker-identity` (DOC-04 §5); hovering a dot shows the permitted detail.
- **Readers** shown as small badges with their current zone binding (DOC-06 coverage), so an operator can see which pole covers what.
- Updates live from the `tracking` channel (`HeadcountUpdated`/`PositionsUpdated`, throttled 5 s); reflects repositioning (a rebind moves a reader's coverage).
- The map is the `ZoneMap` component, shared with the tracking page (DOC-09) and the display.

---

## 6. The 55″ display (kiosk)

- **`/display`** — the authenticated extension view (DOC-02 §5.3): same login/session as the workstation, `view-dashboard` required, rendered through `DisplayLayout` (fullscreen, dark, no sidebar/chrome/controls). It keeps its session alive via the live-data heartbeat while open (DOC-02), but never bypasses auth.
- **Design:** larger type (readable across a room), higher contrast, no interactive affordances. **Auto-cycling panes** every ~20 s (`display.cycle_seconds`, DOC-18): (1) Live cameras + total headcount + zone headcount, (2) Gas/CO₂ panels, (3) the Live Zone Map. A **persistent top banner** shows open **critical** alerts in `--crit` with the audible loop (DOC-07); a bottom **ticker** scrolls recent warnings/events.
- **LIVE / RECONNECTING pill** always visible (DOC-08 §5.4) — on the wall it must be obvious if the feed froze.
- Carries the viewer's permissions (a PM opening `/display` sees the KPI-appropriate subset), but the display is normally opened by an operator/manager account.

---

## 7. Navigation & shell

- **Sidebar** (quiet, `--surface`, active item in `--accent`): grouped, with items hidden unless the user holds the matching `view-*` permission (DOC-03):
  - **Overview** — Dashboard, Live View
  - **Tracking** — Workers, Tags, Zones, Entry/Exit, Devices Register, Evacuation
  - **PPE** — Violations, Trends
  - **Gas & CO₂** — Live, Trends, Alarms, Thresholds
  - **Equipment** — Items, Checkouts
  - **HSE** — Incidents, LSR, Summary
  - **Reports** — Weekly, Vehicle Violations, Settings
  - **Alerts**
  - **Settings** — Assets, Cameras, Devices, Repositioning, Zones, Users, Roles, Aramco/Read-only Access, Audit Log, General
- **Top bar:** global search (workers/equipment/incidents), the **alert bell** with open count (DOC-07), the LIVE/RECONNECTING pill, the user menu (profile, theme toggle, logout), and an idle-timeout indicator (DOC-02).
- **`AppLayout`** hosts `AlertProvider` (toasts/chime), `useIdleLogout`, and the shared permission context. `DisplayLayout` is the stripped kiosk shell.

---

## 8. Frontend (React / Inertia)

- **`pages/dashboard/index.tsx`** — the widget grid; subscribes to `tracking`/`gas`/`alerts`/`environment`/`system` channels + 60 s poll of `/api/dashboard/summary`; LIVE/RECONNECTING pill.
- **`pages/display/index.tsx`** — kiosk cycler (`DisplayLayout`).
- **Components (`components/ir4/`):** `StatCard` (label + value + delta chip + sparkline), `RangeToggle` (Day/Week/Month), `AnalyticalChart` (area/line + hover tooltip + crosshair, recharts), `ZoneMap` (MapLibre), `GasPanelStrip`, `WeatherTiles`, `SystemHealthTiles`, `AlertFeed`, `SeverityBadge`, `LiveDot`, `DisplayBanner`, `EventTicker`.
- **Design tokens** in `resources/css/app.css` (the §2 palette + radius + shadows); a small `useTheme()` for dark/light.
- **Types (`types/dashboard.ts`):** `DashboardSummary` (typed to §3), `StatCardProps`, `ChartRange`, `ZoneMapData`.
- Every number that updates live uses tabular figures + a 200ms tween; no layout shift on update.

---

## 9. Real-life scenarios

- **Shift start:** operator opens the dashboard → sees 0 on-site climbing as workers badge in (Total Manpower tweens up, map dots appear), gas panels green, no open alerts → the calm baseline.
- **Critical event:** a gas alarm fires → the Gas Status card border pulses `--crit`, the Open Alerts card jumps, the display banner turns red with the chime → operator acts; when resolved, everything settles back to `--ok`.
- **On the wall:** the 55″ cycles cameras → gas → map every 20 s; a critical alert pins the red banner across all panes until acknowledged; the LIVE pill stays green.
- **PM check-in:** a Project Manager opens the dashboard → sees only KPI cards (manpower count, open incidents/LSR, overdue equipment, last report) — no live map, no gas detail — enough for oversight.
- **Feed drop:** the socket drops → the LIVE pill flips to RECONNECTING (amber), cards keep last values and poll every 60 s → on reconnect, LIVE (green) and a fresh snapshot.

---

## 10. Tests (this doc's slice of DOC-21)

- **Summary endpoint:** returns all sections; omits data the user lacks permission for; identity-stripped where applicable; cached.
- **Role-aware grid:** a user sees exactly the widgets their permissions allow; PM gets the KPI variant (no map/gas); an operator gets the full grid.
- **Live vs poll:** widgets patch from Reverb events; on socket loss the poll of `/api/dashboard/summary` reconciles and the LIVE/RECONNECTING pill reflects state.
- **Display:** `/display` requires auth + `view-dashboard`; renders the kiosk cycler; shows the red banner for open criticals; never bypasses auth (DOC-02 integration).
- **Map:** zones render from placement data with live occupancy; dots anonymize without `view-worker-identity`; reflects a reader rebind.
- **Design tokens:** severity colors map to meaning (crit/warn/ok) consistently; tabular figures on live numbers (visual/regression check).

---

## 11. Open decisions logged

| # | Decision | Default | Confirm in |
|---|---|---|---|
| 1 | Site-plan overlay for the map vs plain coordinate canvas | uploaded site-plan raster (offline); canvas fallback | DOC-20 |
| 2 | Display face (Inter Tight vs Space Grotesk) | Inter Tight (bundled) | this doc |
| 3 | Display cycle interval | 20 s | DOC-18 |
| 4 | Light theme scope | provided but dark is default; display always dark | this doc |
| 5 | Dashboard cache TTL | 5–10 s | DOC-18 |

---

### Next document
**DOC-17 — Audit Logging:** the append-only `audit_logs`, the `Auditable` trait coverage across config/security-relevant models, the read-only-role `data_access` logging, the event catalogue, sensitive-field masking, and the read-only audit viewer.