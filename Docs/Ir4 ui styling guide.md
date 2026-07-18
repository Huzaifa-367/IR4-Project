# IR4 Safety Command Center — UI & Layout Styling Guide

> **Purpose.** One authoritative visual spec so **every** dashboard, module page, table, and chart across the platform is built to the same bold, analytical, information-dense standard — the "Control Room" look established in DOC-16 and shown in the reference dashboards. Where a module DOC says *what* a page contains, this guide says *how it looks and lays out*. Cursor/engineers follow this for all frontend work; designers extend it, never contradict it.
>
> **Applies to:** all operator (surface A) screens, the 55″ display, and the public QR page (a lighter, read-only descendant). React 19 + TypeScript + Inertia + Tailwind 4 + shadcn/ui + recharts + MapLibre (DOC-01).

---

## 0. Design thesis (read this first)

The platform is a **safety command center**, not a CRUD admin panel. The screen's job is to answer *"is everyone safe right now, and what needs attention?"* at a glance, then let an operator drill in. So every page is built from **information-dense, bold, analytical modules** — KPI stat cards with sparklines and deltas, rich charts with hover detail and range toggles, live feeds, and scannable tables with status pills and mini-progress bars — arranged on a calm dark grid. The reference dashboards (a trading terminal, a fintech overview, a SOC command center, a CCTV wall) share one DNA: **dark surface, big confident numbers, one saturated accent, color reserved for meaning, and charts that carry the story.** We adopt that DNA wholesale.

Three non-negotiables:
1. **Dark-first, instrument-panel calm.** Viewed in a control room and on a 55″ wall in low light.
2. **Color means something.** Green = healthy/ok, amber = warning, red = critical. Never decorative.
3. **Numbers are the hero.** Big tabular figures, sparklines, deltas — the data does the talking; chrome stays quiet.

---

## 1. Design tokens

Define once in `resources/css/app.css` as CSS variables, mapped into the Tailwind theme. **Every** color/space/radius reference below is a token — no hardcoded hex in components.

### 1.1 Color — dark theme (default)
```css
:root {
  /* surfaces — layered, never pure black */
  --bg:            #0A0E14;   /* app background */
  --bg-grid:       #0D1219;   /* faint grid/pattern behind cards (optional) */
  --surface:       #121924;   /* card / panel */
  --surface-2:     #18212E;   /* raised: table header, tooltip, popover, hover */
  --surface-3:     #1F2A38;   /* input, chip background */
  --border:        #263341;   /* hairline border / divider */
  --border-strong: #35455870; /* stronger divider */

  /* text */
  --text:          #E8EEF5;   /* primary */
  --text-dim:      #93A1B2;   /* secondary / labels */
  --text-faint:    #5E6B7A;   /* axis, disabled, captions */

  /* brand accent — "signal cyan" (the ONE brand color) */
  --accent:        #38BDF8;
  --accent-dim:    #38BDF833;  /* fills, focus ring bg */
  --accent-strong: #0EA5E9;

  /* semantic — RESERVED for meaning */
  --ok:            #34D399;   /* healthy / on-track / resolved / pass */
  --ok-bg:         #34D39918;
  --warn:          #F5A524;   /* warning severity */
  --warn-bg:       #F5A52418;
  --crit:          #F0506E;   /* critical / alarm / fail */
  --crit-bg:       #F0506E18;
  --info:          #8B93A7;   /* neutral info */

  /* data-viz categorical palette (charts with multiple series) */
  --viz-1: #38BDF8;  --viz-2: #A78BFA;  --viz-3: #34D399;
  --viz-4: #F5A524;  --viz-5: #F472B6;  --viz-6: #22D3EE;

  /* elevation */
  --shadow-card: 0 1px 2px #0006, 0 8px 24px #0003;
  --shadow-pop:  0 8px 32px #0009;

  /* geometry */
  --radius:      14px;   /* cards */
  --radius-sm:   10px;   /* inputs, chips, buttons */
  --radius-pill: 999px;  /* pills, toggles */
}
```

### 1.2 Color — light theme (office/daytime, print)
Provided but **dark is default; the 55″ display is always dark.** Flip surfaces to near-white (`--bg:#F6F8FB`, `--surface:#FFFFFF`, `--surface-2:#F1F4F8`), text to slate (`--text:#0F1722`), keep the same accent + semantic hues (they read on both). Toggle via a `data-theme="light"` attribute on `<html>`; `useTheme()` persists per-user (in state, not localStorage in artifacts — real app uses a user preference).

### 1.3 Spacing & sizing scale
4px base. Tokens: `--s-1:4px --s-2:8px --s-3:12px --s-4:16px --s-5:20px --s-6:24px --s-8:32px --s-10:40px --s-12:48px`. Card padding **20–24px**. Grid gutter **16–20px**. Page max-width **1440px** (dashboards breathe); tables/wide analytics may go edge-to-edge within the content column.

### 1.4 Radius, borders, shadows
- Cards: `--radius`, 1px `--border`, `--shadow-card`. On dark, elevation comes from the **surface step-up** (bg → surface → surface-2), not heavy shadows.
- Inputs/buttons/chips: `--radius-sm`. Pills/toggles: `--radius-pill`.
- Dividers are 1px `--border`; avoid boxing everything — whitespace + a surface step is usually enough separation.

---

## 2. Typography

Three roles, bundled locally (no CDN — on-prem, DOC-01).

| Role | Face | Use |
|---|---|---|
| **Display / numbers** | **Inter Tight** (or Space Grotesk) | big stat values, page titles, chart hero numbers. Tight, confident. |
| **Body / UI** | **Inter** | labels, table cells, forms, buttons, nav — legible at a distance |
| **Mono / data** | **JetBrains Mono** | IDs, tag UIDs, timestamps, ppm readouts, code — so columns align |

**Rules:**
- **Tabular figures everywhere numbers change live** (`font-variant-numeric: tabular-nums`) so headcount/gas digits don't jitter as they update.
- **Type scale:** stat number 40–56px / 600–700; page title 24–28px / 600; section header 16–18px / 600; body 14px / 400–500; **eyebrow label 12px / 600 uppercase, letter-spacing .06em, `--text-dim`** (the small ALL-CAPS labels above cards — a signature of every reference).
- Line-height tight on numbers (1.0–1.1), comfortable on body (1.5).
- Never more than two weights visible in one card. Let size + color carry hierarchy, not weight soup.

---

## 3. Layout system

### 3.1 App shell (operator surface)
```
┌───────────────────────────────────────────────────────────────┐
│  TOPBAR: logo · breadcrumb · ⌘K search · [LIVE●] · 🔔 · user   │
├──────────┬────────────────────────────────────────────────────┤
│          │  Page title ·············· [range ▾] [Export]       │
│ SIDEBAR  │  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐        │
│ (grouped │  │ KPI    │ │ KPI    │ │ KPI    │ │ KPI    │  ← stat row
│  nav,    │  └────────┘ └────────┘ └────────┘ └────────┘        │
│  perms-  │  ┌───────────────────────┐ ┌──────────────────┐     │
│  hidden) │  │  primary chart / map  │ │  side panel/feed │     │  ← 2-col analytics
│          │  └───────────────────────┘ └──────────────────┘     │
│          │  ┌───────────────────────────────────────────┐     │
│          │  │  table / secondary charts                  │     │  ← full-width detail
│  ┌────┐  │  └───────────────────────────────────────────┘     │
│  │stat│  │                                                    │
│  └────┘  │                                                    │
└──────────┴────────────────────────────────────────────────────┘
```
- **Sidebar** ~240px, `--surface`, grouped sections with 12px uppercase group labels (GENERAL / TRACKING / GAS / HSE …, DOC-16 §7), active item pill in `--accent` with a left accent bar; items **hidden** unless the user holds the matching `view-*` permission (DOC-03). A small **system-status footer** in the sidebar (like image 5's "Defense Posture A+") shows overall health green/amber/red.
- **Topbar** ~64px: breadcrumb, a ⌘K command/search, the **LIVE/RECONNECTING pill** (always visible — DOC-08), the alert bell with count, user menu + theme toggle.
- **Content:** a **12-column responsive grid**, 16–20px gutters. The canonical page rhythm is **stat row → primary analytics (2-col) → detail (full-width table / secondary charts)** — exactly the reference layout.

### 3.2 The 12-column grid & standard spans
- KPI stat cards: **3 cols each** (4 across) on desktop, 2-up on tablet, 1-up on mobile.
- Primary chart / live map: **8 cols**; its companion feed/side-panel: **4 cols**.
- Secondary charts: **4 cols each** (3 across) or **6/6**.
- Wide tables: **12 cols**.
- Everything reflows to 1 column < 768px; the sidebar collapses to icons then to a drawer.

### 3.3 Density
Information-dense but not cramped: generous card padding, tight internal rhythm. Prefer **more small modules** over few big empty ones (image 5 packs ~15 modules and still reads calmly because each is quiet and the grid is strict).

---

## 4. Core components (the building blocks)

Every page is assembled from these. Build them once in `components/ir4/` and reuse.

### 4.1 `StatCard` (the KPI hero — references 1, 3, 4, 5)
The most-used module. Anatomy:
```
┌──────────────────────────────┐
│ EYEBROW LABEL            ↗    │  12px uppercase dim + optional deep-link chevron
│ 128            ▲ +12 today    │  40–56px tabular value + delta chip
│ ﹏﹏╱﹏╱﹏﹏╱  (sparkline)     │  sparkline fills the base, accent stroke + gradient
└──────────────────────────────┘
```
- **Value:** display face, tabular, tweens 200ms to new values.
- **Delta chip:** small pill, `▲ +12` in `--ok-bg`/`--ok` or `▼ −6%` in `--crit-bg`/`--crit`, with the baseline in `--text-faint` ("today" / "vs last shift").
- **Sparkline:** 40px tall, one accent stroke + soft gradient fill (reference 1's motif), no axes.
- **Variants:** `mini` (no sparkline, just value+delta), `gauge` (radial %, reference 1 donut), `progress` (segmented bar, reference 2), `status` (big value + state pill like "99.94% · stable").
- Semantic border/tint when it represents a live safety metric crossing a threshold (gas card border → `--crit` and a one-time pulse on alarm).

### 4.2 `AnalyticalChart` (references 2, 3, 4, 5)
The workhorse chart (recharts), used for gas trends, manpower, PPE, cashflow-style series.
- Area or line, subtle 1px dashed gridlines in `--border`, axes in `--text-faint`.
- **Range toggle** (Day / Week / Month or shift/day/week) as a pill group top-right (reference 2).
- **Hover:** a moving vertical crosshair + a floating tooltip card (`--surface-2`, `--shadow-pop`) showing the timestamp and each series value (references 2, 3, 4). This is a signature interaction — every trend chart has it.
- **Multi-series:** use the `--viz-*` categorical palette with a small top-left legend showing each series + its current delta (reference 4: "Portfolio +4.25% · S&P −1.39%").
- Gradient area fills at ~15% opacity of the series color; stroke at full.
- Empty state: a centered dim "No data for this range" — never a broken axis.

### 4.3 `DonutChart` / `RadialGauge` (references 1, 5)
- Donut for composition (e.g. workers by zone, threat sources) with a centered total and a legend list with values + percentages beneath (reference 1 "Staked TAO", reference 5 "Attack sources").
- Radial gauge for a single 0–100 score (a **Site Safety Score** hero, à la reference 5's "94/100" security score) — thick ring, big centered number, small sub-metrics under it.

### 4.4 `BarChart` (references 1, 4, 5)
- Rounded-top bars, single accent or `--viz-*` for grouped/stacked (reference 5 CVE-by-severity stacked crit/high/med). Used for PPE-by-type, gas alarm counts, incidents-by-category.
- Highlight the peak bar in a brighter accent with a value callout (reference 1, reference 3 revenue analytics).

### 4.5 `DataTable` (references 4, 5)
The scannable record list — incidents, LSR, entry/exit, equipment, audit, active sessions.
- Header row `--surface-2`, 12px uppercase-dim column labels, sortable carets.
- Rows: 1px `--border` separators, hover → `--surface-2`, comfortable 44–52px height, mono for IDs/timestamps.
- **Status pills** in the Status column (see 4.6). **Mini progress bars** for progress/SLA columns (reference 5 incident management: a thin bar + "62%" + SLA countdown). **Owner avatars** (reference 5). Trailing "⋯" row actions.
- Sticky header on scroll; paginated; a filter/search bar + "Sort by" above (reference 3).
- Right-align numbers; use tabular figures.

### 4.6 `StatusPill` / `SeverityBadge`
- Small pill, `--radius-pill`, semantic bg tint + text: `Critical` (`--crit`), `Warning` (`--warn`), `Resolved`/`Passed` (`--ok`), `Open`/`Investigating` (`--accent`), neutral (`--surface-3`). A leading dot or icon. Used in tables, alert rows, incident status, equipment custody. This is the primary way color enters the UI — always meaningful.

### 4.7 `LiveFeed` (references 5 threat feed, 6 activity)
Streaming event list (alerts, recent activity): each row = severity pill + a mono ID + a one-line description + source meta (dim) + a relative timestamp ("12s ago"). New items slide in at top; a subtle left border in the severity color. Filter chips (All / Critical / High / Medium) at the top.

### 4.8 `RangeToggle`, `Segmented`, `FilterChips`
Pill-group toggles (Day/Week/Month, All/Critical/…). Active segment = `--surface-3` bg + `--text`; inactive = transparent + `--text-dim`.

### 4.9 `MiniProgress` / `SegmentedBar`
Thin (6–8px) rounded progress bars for SLAs, completion, checkout dueness, evacuation accounting; segmented "equalizer" bars (reference 2's challenge progress) for phased/target progress. Fill in semantic color.

### 4.10 `MetricRow` / `LabeledStat`
Compact "label + big value + tiny delta" used inside panels (reference 4 Allocation footer: Volatility / Market Cap / Sortino as three inline stats). For dense secondary metrics that don't each deserve a card.

### 4.11 The `ZoneMap` (the bespoke signature — DOC-16 §5)
MapLibre with an offline site-plan raster; zone circles colored by type with live occupancy labels; worker dots (anonymized without `view-worker-identity`); reader badges. This is the one visual no generic dashboard has — spend the boldness here. On the CCTV/live view (reference 6) the same dark chrome frames a camera grid with per-tile labels + status dots.

---

## 5. Page archetypes (compose the components)

Every screen is one of these patterns — don't invent new layouts per page.

### 5.1 Command dashboard (`/dashboard`, `/display`)
Stat row (Manpower, Open Alerts, Gas status, System Health) → **primary: live Zone Map (8 col) + Alert feed (4 col)** → secondary charts (PPE today, gas trend, headcount trend) → optional recent-incidents table. This is reference 5 applied to site safety. The `/display` variant strips chrome, enlarges type, and auto-cycles panes.

### 5.2 Module overview (e.g. Gas, PPE, Tracking)
Stat row (the module's KPIs) → primary `AnalyticalChart` with range toggle (8 col) + a side panel (live panels / breakdown, 4 col) → a `DataTable` of the module's records. Reference 4's Investment overview is the template.

### 5.3 Record list (Incidents, LSR, Equipment, Entry/Exit, Audit)
A thin stat/filter row → a full-width `DataTable` with status pills, mini-progress, owner avatars, row actions, filter+sort bar. Reference 5's "Incident management" table.

### 5.4 Record detail (an incident, an equipment item)
A header card (title, status pill, key meta, primary action) → tabs → per-tab content (evidence gallery, history timeline, linked records). Quiet, form-forward, but still dark and bold in its headers.

### 5.5 Live monitoring (`/live`, CCTV wall)
Reference 6: dark shell, a camera-tree sidebar grouped by area, a responsive camera grid with per-tile label + live dot + grid-size toggle, real-time PPE/fall violation toasts overlaid.

### 5.6 Settings / forms
Grouped sections, `SettingField` rows (label + control + "last changed" meta), per-key permission gating (read-only styling when not permitted). Calm, single-column, generous spacing — the one place density relaxes.

---

## 6. Motion & interaction

Restrained, purposeful (DOC-16 §2.3 / frontend-design restraint):
- **Number tweens:** 200ms ease on any live-updating value; no layout shift (tabular figures).
- **New critical alert:** the relevant card border pulses `--crit` **once** (~600ms), the alert-feed row slides in, the audible loop starts (DOC-07).
- **Live dot:** a 2px `--ok` dot with a slow 2s pulse on live cards/tiles.
- **Chart hover:** crosshair + tooltip follow the cursor (60fps, no jank).
- **Range/tab switches:** 150ms cross-fade of the chart series.
- **Page load:** a brief top-down stagger of cards (≤300ms total) — subtle, once. Respect `prefers-reduced-motion` (disable all of the above → instant).
- No decorative/ambient animation — it reads as AI-generated and distracts a control room.

---

## 7. Data-visualization rules (so charts stay honest & bold)

- **Color = meaning first.** In safety contexts (gas, alerts, severity) use `--ok/--warn/--crit`. Only use the categorical `--viz-*` palette for neutral multi-series (e.g. two gas channels, workers-by-zone) where hue is just identity, not judgment.
- **Always label the current value + delta**, not just the shape (references 1, 4). A trend with no number is decoration.
- **Range toggles on every time-series** (shift/day/week), reading raw ≤24h and rollups beyond (DOC-19).
- **Thresholds drawn on charts:** gas trends show a dashed `--warn`/`--crit` threshold line so an excursion is visually obvious.
- **Never fake precision:** show "—" / "No data" / a completeness note when a stream was offline (DOC-15 honesty), not a flat zero line.
- **Tabular figures** on all axis + tooltip numbers.
- Keep ≤6 series per chart; beyond that, aggregate or facet.

---

## 8. Accessibility & the 55″ wall

- **Contrast:** body text ≥ 4.5:1 on its surface; large numbers ≥ 3:1. The dark palette above meets this; verify any new tint.
- **Never color-alone:** severity always pairs color with a label/icon (a red pill says "Critical"), so it survives color-blindness and the wall-across-a-room test.
- **Focus:** visible `--accent` focus ring on every interactive element; full keyboard nav; logical tab order.
- **Wall mode:** larger type, higher contrast, no hover-dependent info (the wall isn't moused) — key data is always on-screen, not in tooltips. LIVE/RECONNECTING pill oversized.
- **Reduced motion** respected everywhere.

---

## 9. Do / Don't

**Do**
- Lead every card with a 12px uppercase eyebrow label + a big tabular number.
- Give every time-series a range toggle + hover tooltip + current-value/delta.
- Reserve red/amber/green for real severity/health.
- Use the surface step (bg→surface→surface-2) for depth; keep shadows subtle.
- Pack the grid with quiet, disciplined modules; let the map + criticals be the one bold focus.

**Don't**
- Don't use pure black (`#000`) or pure white cards on dark — use the surface tokens.
- Don't color things for decoration; don't put more than one "brand" color on screen.
- Don't animate ambiently or bounce things; don't block the wall's key data behind hover.
- Don't box every element in borders — whitespace + surface steps separate.
- Don't hardcode hex in components — everything is a token.
- Don't let a chart show only a shape with no number.

---

## 10. Implementation notes (for Cursor)

- **Tokens → Tailwind:** expose the §1 CSS vars in `tailwind.config` `theme.extend.colors` as `bg`, `surface`, `surface-2`, `border`, `text`, `text-dim`, `accent`, `ok`, `warn`, `crit`, `viz-1…6`, plus the radius/spacing scale. Components use semantic classes (`bg-surface`, `text-dim`, `border-border`), never raw hex.
- **Component home:** all shared pieces in `components/ir4/` (StatCard, AnalyticalChart, DonutChart, RadialGauge, BarChart, DataTable, StatusPill, LiveFeed, RangeToggle, MiniProgress, MetricRow, ZoneMap, DisplayBanner, EventTicker). shadcn/ui for primitives (dialog, dropdown, tabs, tooltip) themed to the tokens.
- **Charts:** recharts, wrapped so the crosshair-tooltip, gridline, axis, and palette styling are defined **once** in `AnalyticalChart` and inherited — pages pass data + series config, never restyle.
- **Fonts:** self-host Inter Tight / Inter / JetBrains Mono via `@font-face` (no Google Fonts CDN — on-prem).
- **Theme:** `data-theme` on `<html>`; `useTheme()` reads a user preference; display is forced dark.
- **Live values:** a `<LiveNumber>` wrapper handles the tween + tabular figures so any streamed value animates consistently.
- **Consistency gate:** a page is "done" only if it uses these components and the standard archetype (§5) — reviewers reject bespoke one-off layouts.

---

## 11. Reference mapping (what we took from the images)

| Reference | What we adopt |
|---|---|
| **1 — dark KPI cards** | the `StatCard` system: eyebrow label + chevron, big tabular value, delta chip, sparkline/donut/segmented-bar variants; peak-bar highlight |
| **2 — TVL area chart** | `AnalyticalChart` hover tooltip + crosshair + **Day/Week/Month toggle**; `SegmentedBar` progress (challenge-progress equalizer look) |
| **3 — fintech overview** | the full **shell**: dark sidebar w/ grouped nav, topbar w/ ⌘K search, KPI row with up/down deltas, cashflow multi-series chart w/ marker, recent-activity feed |
| **4 — investment dashboard** | KPI row with sub-deltas, **Performance-vs-benchmark** multi-series chart w/ legend deltas, holdings table with weight bars + P/L pills, allocation + top-movers modules, `MetricRow` inline stats |
| **5 — Sentinel SOC** | the **command-center archetype** end-to-end: hero + safety-score gauge, semantic KPI strip, live threat feed, geo map, trend/donut/stacked-bar analytics, network-health tiles, incident table w/ owner avatars + SLA progress, identity/access panels — our closest visual target |
| **6 — CCTV live wall** | the `/live` monitoring archetype: dark shell, camera-tree sidebar, camera grid w/ per-tile label + live dot + grid-size toggle |

---

### How this fits the doc set
This guide is the visual companion to **DOC-16** (which defines the design language and the dashboard/display behavior) and governs the frontend layer referenced across **DOC-01** (stack) and every module DOC's "Frontend" section. Module DOCs specify *which* components and data a page uses; this guide specifies *how they look and lay out*. Together they make every screen analytical, informative, and unmistakably one product.