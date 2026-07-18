# PRD.md — Project Requirements Document

> Authoritative behavior: `Docs/Doc 01`–`Doc 21`. This file tells an AI **what** to build and **for whom**.

---

## What we're building

**IR4** — a standalone, on-premise **safety command-centre** for one industrial/construction site.

- Field hardware (RFID, AI cameras, gas/CO₂/environmental sensors) streams data into the server.
- Operators run the site from a real-time web UI plus one always-on **55″ kiosk display**.
- Field staff scan equipment QR codes on a **public LAN page** (no login).
- Single installation, single site — **no multi-tenancy**, no cloud dependency, no outbound internet in the app.

---

## Target users

| User | Needs |
|---|---|
| **SCC Operator** | Live headcount/map, gas panels, PPE wall, acknowledge alerts, log LSR, review false positives |
| **Safety Manager** | Thresholds, classify incidents, configure alerts, manage hardware/zones/workers |
| **Super Admin** | Users, roles, settings, full access; always ≥1 active |
| **Project Manager** | Read-only ops views; often without worker identity |
| **Client Representative** | Optional read-only access (perms empty until enabled) |
| **Field Staff** | Scan equipment QR only — **no platform login** |
| **Devices** | Token-authenticated ingest + heartbeats only |

**Worker ≠ User.** Workers are tracked people. Users are login accounts.

---

## Core features

### Platform
- [ ] Login / logout / password change (Fortify); idle timeout; optional offline 2FA
- [ ] Roles & permissions (dynamic roles; fixed Super Admin); four enforcement layers
- [ ] Runtime settings; unified alerts (raise / ack / resolve / dedupe / suggested actions)
- [ ] Dark-first, role-aware aggregate dashboard with permission-filtered widgets, PM KPI variant, shared live zone map, and one cached `/api/dashboard/summary`
- [ ] Authenticated 55″ display with session keep-alive, cycling camera/headcount, gas/CO₂, and map panes, plus persistent critical-alert banner
- [ ] Live screens: Reverb + LIVE/RECONNECTING + poll fallback

### Registries
- [ ] Workers CRUD + CSV import + identity privacy
- [ ] Hardware registry (assets, cameras, devices) — fully dynamic; tokens; heartbeats; offline alerts
- [ ] Zones + time-aware reader bindings + repositioning + access lists

### Live safety (device-fed)
- [ ] RFID tracking: positions, gate entry/exit, headcount, zone alerts, stationary/worker-down, evacuation + PDF
- [ ] PPE: anonymous violations, false-positive review, live camera wall; fall → critical alert
- [ ] Gas & CO₂: live gauges, thresholds, hysteresis alarms (backfill never raises live alarms)
- [ ] Environmental: weather display + trends (no alarms in v1)

### Manual + public
- [ ] Equipment: permanent QR, inspections/maintenance, overdue alerts, ZPL print, checkout/return
- [ ] Public page `GET /e/{qr_token}` for Field Staff
- [ ] HSE incidents & LSR: **always user-created**; alert only prefills forms; mandatory action fields

### Reporting & governance
- [ ] Weekly report with all 10 Section 6.5 items: nine module-assembled items plus manual vehicle violations with required action taken
- [ ] Scheduled/manual queued generation; frozen data snapshot; PDF and zipped per-item CSV exports; automation badges and sensor-outage completeness notes
- [ ] Published-report lock and supersede-don't-edit amendments; PM/read-only users see published reports only
- [ ] Append-only, never-pruned audit trail with masked diffs for authentication, configuration/security changes, publish/acknowledge/export actions, and read-only-role meaningful data access
- [ ] Permission-gated, read-only audit viewer with filters, expandable diffs, and audited CSV export
- [ ] Whitelisted runtime settings registry with per-key permissions, validation, confirm for sensitive keys, and `config_changed` audit
- [ ] Hourly sensor rollups, allow-listed raw pruning, encrypted daily backups, and guarded `ir4:export-all` / `ir4:secure-wipe`

### Deploy & quality (Docs 20–21)
- [ ] On-prem deploy runbook artifacts: Supervisor process set, Nginx LAN segmentation, audit DB grants, ZT411 setup notes, commissioning acceptance checklist
- [ ] DOC-21 test strategy: invariant-guard suite, cross-module scenario catalogue, CI gates (enum-sync, PERMISSIONS.md, on-prem/standalone greps)

---

## Explicit non-goals

- Multi-tenant / multi-site SaaS
- Cloud Pusher, CDN fonts/scripts, analytics, external APIs in shipped code
- Worker login accounts
- Auto-creating incidents or LSR from alerts
- Inventing PPE worker identity
- Operator UI as a REST SPA (use Inertia, not fetch/axios for operator pages)
- Hardcoded counts of poles/cameras/devices or seeded production inventory

---

## Success looks like

1. Operator sees live headcount, map, gas, PPE, and alerts with clear LIVE/RECONNECTING state.
2. Evacuation freezes roster and accounts people at muster.
3. Gas outages/backfills do not fabricate late alarms.
4. Equipment QR works on the LAN without login.
5. Every closed HSE/LSR record has mandatory human judgment text.
6. Every weekly report freezes all 10 items, declares material telemetry gaps, and cannot be edited after publication.
7. Audit history cannot be mutated or pruned, never stores sensitive values, and records meaningful read-only access.
8. CI blocks external-host URLs, enum drift, and failing tests.
