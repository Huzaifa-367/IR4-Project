# Design.md — Visual design system

> Authoritative visual identity: **DOC-16 §2**. Stack: Tailwind 4 + shadcn/ui. **Bundle all fonts locally** (no Google Fonts / CDN). This file restates DOC-16 so every module’s screens cohere.

---

## Design intent

IR4 is a **safety command-centre**, not a marketing site or a finance dashboard.

- Dark, analytical, data-dense but calm — readable across a room on a **55″ wall**.
- The hero is the **live safety picture** (headcount, open critical alerts, zone map), not a decorative KPI.
- Severity color is reserved for meaning; layout stays a quiet grid.
- No decorative gradients, glow effects, playful illustrations, or emoji as UI chrome.

---

## Theme modes

| Surface | Mode | Notes |
|---|---|---|
| Operator app (`AppLayout`) | **Dark-first** | Light theme provided for daytime office / printing; dark is default |
| Kiosk (`DisplayLayout`, `/display`) | **Always dark** | Higher contrast, larger type, no interactive chrome |
| Auth screens | Dark or light via tokens; minimal chrome |
| Public equipment page | Mobile-first, simple, large type; may stay light for outdoor phone readability |

Use CSS variables so both themes share the same semantic tokens (`--ok` / `--warn` / `--crit` stay consistent).

---

## Color tokens — “Control Room”

Named tokens in `resources/css/app.css` (map into shadcn’s `--background`, `--foreground`, `--primary`, etc.).

### Dark (default)

| Token | Hex | Use |
|---|---|---|
| `--bg` | `#0B0F14` | App background (deep slate-black) |
| `--surface` | `#131A22` | Cards / panels |
| `--surface-2` | `#1B2530` | Raised elements, table rows, tooltips |
| `--border` | `#243040` | Hairline borders, dividers |
| `--text` | `#E6EDF3` | Primary text |
| `--text-dim` | `#8A97A6` | Labels, captions, axes |
| `--accent` | `#38BDF8` | Primary accent (signal cyan) — links, active nav, focus |
| `--ok` | `#34D399` | Healthy / on-track |
| `--warn` | `#F5A524` | Warning severity |
| `--crit` | `#F0506E` | Critical severity / alarms |

Severity colors are **never decorative** — a red element always means critical. Accent cyan is the only brand color and is used sparingly.

### Light (optional daytime / print)

Flip the neutrals; keep `--ok` / `--warn` / `--crit` / `--accent` meaning-identical.

### Live connection pill

| State | Color |
|---|---|
| LIVE | `--ok` |
| RECONNECTING | `--warn` |
| OFFLINE (poll only) | `--text-dim` |

### Gas gauge bands

Color the **value**, not the whole page: green below warning → amber between warn and alarm → red at/above alarm (O₂ respects low/high directions per DOC-11).

### Charts / map

- Chart series prefer accent + muted companions; purple only as a 4th data series if needed, never as theme.
- Zone fills: each zone’s `color` at ~25% opacity; selected ~45%.
- Map: uploaded site-plan raster overlay (offline) or plain coordinate canvas — no external tiles.

---

## Typography

### Fonts (self-hosted via Vite / local files — no CDN)

| Role | Family | Fallback |
|---|---|---|
| **Display / numbers** | `Inter Tight` | `Inter, system-ui, sans-serif` |
| **Body / UI** | `Inter` | `system-ui, sans-serif` |
| **Data / mono** | `JetBrains Mono` | `ui-monospace, monospace` |

Use **tabular figures** on every live-updating number (headcount, gas ppm) so digits do not jitter.

### Scale

| Name | Size / weight | Use |
|---|---|---|
| `stat` | 40–56px / 600 tabular | Dashboard / kiosk hero numbers |
| `h1` | 18–20px / 600 | Section headers |
| `body` | 14px / 400 | Default operator UI |
| `caption` | 12px / 500 uppercase-tracked | Card eyebrow labels |
| `mono` | 12–14px mono | IDs, timestamps, tag UIDs, ppm |

Line height: ~1.45 body, ~1.2 headings/metrics.

---

## Spacing, radius & motion

- Base unit: **4px**. Common gaps: 8 / 12 / 16 / 24.
- Card radius: **`--radius: 14px`** (DOC-16 signature).
- Shadows: one quiet level or prefer borders on the wall.
- Motion: restrained — 200ms number tween; critical alert border pulses once; live cards carry a tiny “live” dot. Respect `prefers-reduced-motion`.

---

## Card & chart system (signature)

- **Stat cards:** `--surface`, 12px uppercase-tracked label + deep-link chevron, large tabular value, trend delta chip (`--ok` / `--crit` vs stated baseline), sparkline with one accent stroke + soft gradient fill.
- **Analytical charts (recharts):** subtle grid, hover tooltip, Day/Week/Month range toggle, vertical crosshair; multi-series with a small legend.
- **Zone map (MapLibre GL offline):** translucent zone circles + anonymized worker dots + reader badges; shared with tracking and display.

One bold thing per screen: on the dashboard it’s the **live map + critical-alert state**.

---

## Components (visual rules)

| Component | Rule |
|---|---|
| Buttons | Accent for primary; `--crit` for destructive; ghost for tertiary. |
| Tables | Compact rows; sticky header; mono for codes/IDs. |
| Badges | Status colors above; text + color, never color alone. |
| Alerts / toasts | Critical: `--crit` bar + audible affordance. Warning: `--warn`. |
| Forms | Label above field; Inertia error text in `--crit` under field. |
| Empty states | One short sentence + one CTA; no illustrations. |

---

## Icons

- lucide-react (shadcn starter).
- Stroke icons, 16–20px in UI, 24px on kiosk.
- No emoji in product chrome.

---

## Accessibility

- Text/background contrast ≥ WCAG AA.
- Never convey alarm state by color alone — include label/icon.
- Focus rings use `--accent`.
- Kiosk text minimum 16px body; metrics much larger.
- Audible alerts must have a visible mute/ack control.

---

## Public equipment page (`/e/{token}`)

- Mobile-first, large name + status; RETIRED banner when retired.
- Show custody (available / checked out) with identity stripped for unauthenticated viewers.
- No edit affordances, no internal numeric ids, `noindex`.
- Document links are 15-minute signed URLs only.
- Keep the page calm and phone-readable even if the operator app is dark-first.

---

## What not to do

- Do not load fonts or charting assets from the public internet.
- Do not use purple/cream brochure aesthetics or generic “AI dashboard” gradients.
- Do not use severity red/amber as decoration.
- Do not invent a third brand palette — DOC-16 owns the tokens.
