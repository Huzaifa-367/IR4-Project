# DOC-21 — Testing Strategy

> **Depends on:** every prior DOC (each ends with a "Tests" section this doc aggregates and systematizes). **Feeds:** CI, the commissioning sign-off (DOC-20), and the confidence that the invariants asserted throughout actually hold.
>
> **Scope:** the **testing strategy** — the per-endpoint test matrix (happy / validation / authorization × roles), the **cross-module scenario catalogue** (the flows that span DOCs), factories & seeders, the **invariant guards** (the hard rules stated across the set, made into failing tests), and the **CI gates** (Pint / PHPStan / TS / enum-sync / append-only / on-prem-grep). **Out of scope:** the behavior being tested (owned by each module DOC) — this doc defines *how we prove it*.

---

## 1. Philosophy

This is a safety-critical, compliance-bearing system, so tests exist to prove two things: (1) each endpoint behaves and is **authorized** correctly, and (2) the **cross-cutting invariants** the whole design rests on cannot silently break. The second matters most — a regression that lets an alert auto-create an incident, or lets the app delete an audit row, or attaches a worker to a PPE violation, is a design-integrity failure, not a bug. Each such invariant gets a dedicated, named test that fails loudly.

**Test pyramid:** many fast **unit** tests (services, enums, policies), a solid layer of **feature/HTTP** tests (endpoints with auth), a focused set of **scenario/integration** tests (cross-module flows), and a few **frontend component** tests for the permission-aware and live UI. Target meaningful coverage on the domain services and every policy — not a vanity percentage.

Stack: **Pest/PHPUnit** for PHP, **Vitest + Testing Library** for React, factories + a deterministic test seeder, an in-memory/SQLite or a MySQL test DB (MySQL preferred for the JSON/enum fidelity).

---

## 2. Per-endpoint matrix

Every write endpoint is tested across four axes:

| Axis | What it proves |
|---|---|
| **Happy path** | valid input → correct state change + response + side-effect (broadcast/audit) |
| **Validation** | each FormRequest rule (required, enum, min-length decision fields, uniqueness) → 422 with the field error |
| **Authorization** | a user **with** the permission passes; a user **without** it → 403; identity-stripped where applicable |
| **Edge/guard** | the documented 409/422 guards (e.g. offboard-with-open-checkout, close-LSR-without-action, rebind-non-reader, double-checkout) |

Authorization is parametrised across roles: for each permission, a role holding it and a role lacking it. Read endpoints are tested for the **identity-stripping** and **scope** rules (PM headcount-only, PM published-reports-only, read-only-role `data_access`).

A shared `assertAudited($event, $model)` helper checks that config/security actions wrote the expected `audit_logs` row (DOC-17).

---

## 3. Invariant guards (the hard rules → named tests)

These are the design-integrity tests. Each corresponds to a "hard rule" asserted in a DOC; each must fail if the rule is violated.

| Invariant | Source | Test asserts |
|---|---|---|
| **Audit log is append-only** | DOC-17 | no route updates/deletes an audit row; model throws on update/delete; (deploy) app DB user lacks UPDATE/DELETE on `audit_logs` |
| **PPE has no worker identity** | DOC-10 | `ppe_violations` has no `worker_id` column; no resource/endpoint exposes a worker on a PPE violation |
| **No auto-created incidents/LSR** | DOC-14 | raising any alert creates **no** incident/LSR row; records appear only via user-submitted endpoints; created records link `alert_id`/`ppe_violation_id` |
| **Mandatory action taken** | DOC-14/15 | an LSR can't close without `action_taken`; a real incident can't be classified/closed without its action fields; a vehicle violation needs `action_taken` |
| **Compliance tables never pruned** | DOC-19 | `PruneRawSensorData` operates on the allow-list only; iterating the excluded compliance set proves none is touched |
| **QR token is permanent** | DOC-13 | create sets `qr_token` once; no update/inspection/status path regenerates it; reprint yields the same token |
| **One assigned tag per worker** | DOC-09 | assigning a second active tag → 409; replace-tag is atomic |
| **One open checkout per item** | DOC-13 | a second checkout of an out item → 409 |
| **Backfill raises no alarm / doesn't rewind live state** | DOC-08/11 | a >10-min-old reading is stored, not broadcast, raises no alarm, and doesn't move `worker_positions`/live panels |
| **Time-aware zone resolution** | DOC-06 | `resolveZoneAt(recorded_at)` returns the historically correct zone; snapshot `zone_id` frozen on the reading |
| **Super Admin fixed & complete** | DOC-03 | Super Admin holds every permission, is non-editable/non-deletable, ≥1 active user; no `Gate::before` bypass |
| **Read-only role can't gain writes** | DOC-03 | syncing a non-`view-*` permission to a read-only role → 422; every read-only-role request writes `data_access` |
| **Ingest is idempotent** | DOC-08 | replayed `event_uid`s insert nothing, report duplicates; `(device_id, event_uid)` unique |
| **Device writes only its own data** | DOC-05/08 | a device can't post under another device's reference; unknown ref → per-event rejection |
| **Standalone — no location/site** | DOC-01 | no `site_id`/`sites` anywhere; codes carry no location segment (grep guard, §7) |

---

## 4. Cross-module scenario catalogue

Integration tests that exercise a whole flow across DOCs — the highest-value tests, because they prove the seams hold.

1. **Read → position → headcount → map:** ingest a tag read → `worker_positions` updated, headcount broadcast, map data reflects it; a backfilled read stores history without rewinding (DOC-08/09).
2. **Gate cycle:** two gate reads (with debounce) → in then out → presence + entry/exit logs correct (DOC-09).
3. **Zone rule → suggested LSR:** a red-zone entry → `red_zone_intrusion` alert with a suggested-action prefill → **no** LSR until a user submits → the submitted LSR links the alert and requires an action to close (DOC-06/07/14).
4. **PPE → alert → linkable LSR:** a PPE violation ingests → record + alert + wall broadcast; a user logs an LSR linking it; the violation stays worker-anonymous (DOC-10/14).
5. **Fall + stationary → worker-down → incident:** a fall event and a stationary tag in the same zone within the window → `worker_down` → suggests an incident → user creates it with the frozen RFID roster snapshot as evidence (DOC-09/10/14).
6. **Gas excursion:** a live over-threshold reading → `gas_alarm` (audible) → acknowledge → hysteresis auto-resolve; a **backfilled** excursion raises no alarm but appears in trends/report flagged `during_outage` (DOC-08/11/15).
7. **Repositioning across an outage:** rebind a reader; flush buffered reads spanning the move → each resolves to the historically correct zone (DOC-06/08/09).
8. **Evacuation:** trigger → freeze on-site set → auto-account at muster/gate reads → manual account the rest → close (blocked while unaccounted unless forced) → PDF (DOC-09).
9. **Equipment lifecycle:** import → one-click labels → mobile-scan checkout → return (damaged → maintenance) → overdue flag; worker with open checkout can't be offboarded (DOC-13/04).
10. **Weekly report:** seed a week of module data (incl. an outage) → generate → all 10 items present, PPE excludes false positives, incidents/LSR auto-included, completeness note for the outage → publish (lock) → regenerate → supersedes, original intact (DOC-15).
11. **Read-only client week:** a read-only-role user browses dashboards/reports → every request writes `data_access` → they can't reach any write route (DOC-03/17).
12. **End-of-project:** seed data → `ir4:export-all` archive verifiable → `ir4:secure-wipe` refuses without a verified export, then wipes and records the marker (DOC-19).

---

## 5. Factories & seeders

- **Factories** for every model, producing valid related graphs: `WorkerFactory`, `RfidTagFactory` (states: inStock/assigned/lost), `AssetFactory`/`CameraFactory`/`DeviceFactory` (with token), `ZoneFactory` (+ binding helper), `AlertFactory` (per type), `PpeViolationFactory`, `GasReadingFactory` (live/backfill states), `HseIncidentFactory`, `LsrViolationFactory`, `EquipmentFactory` (+ checkout state), `WeeklyReportFactory`.
- **Ingest helpers:** builders that post valid `/api/ingest/*` batches (live/backfill/duplicate/skewed) so scenario tests drive the real ingest path, not direct inserts.
- **Deterministic test seeder:** a small fixed site (a few poles/zones/workers/devices) for scenario tests and local demo — **guarded to non-production** (the production seeder ships **no** hardware/zones, DOC-05/06). Time is controlled via `Carbon::setTestNow` for the windowed logic (debounce, stationary, hysteresis, backfill).

---

## 6. Frontend tests (Vitest + Testing Library)

- **Permission-aware rendering:** `usePermissions().can()` matches server truth; nav/widgets hide denied items; `RequirePermission` blocks a section (DOC-03/16).
- **Identity stripping in UI:** worker dots/labels show `Worker #id` without `view-worker-identity` (DOC-04).
- **Live + fallback:** `useReverbChannel` patches on events; on socket loss the poll runs and the LIVE/RECONNECTING pill flips; reconnect reconciles (DOC-08/16).
- **Alert UX:** audible loop while an unacknowledged audible-critical exists; toast severity styling; dedup occurrence badge (DOC-07).
- **Forms:** min-length decision fields (classify, close-LSR, action-taken) block submit; from-alert prefill populates the incident/LSR form (DOC-14).
- **Mobile custody:** scan → checkout vs return chosen by open-checkout state (DOC-13).

---

## 7. CI gates

The pipeline fails on any of:
- **Pint** — PHP code style.
- **PHPStan** (high level) — static analysis on app code.
- **TypeScript** `tsc --noEmit` — no type errors.
- **Enum sync** — a check that every PHP backed enum has a matching TS union in `enums.ts` (DOC-01 §6) — values can't drift between backend and frontend.
- **PERMISSIONS.md** — Super Admin holds every permission and every permission is exported (DOC-03 §regeneration).
- **On-prem / standalone grep guards** — CI greps that fail the build if forbidden patterns appear:
  - no `site_id` / `sites` table / location-segmented codes (standalone, DOC-01).
  - no `worker_id` on `ppe_violations` (DOC-10).
  - no external CDN/asset URLs or outbound HTTP in app code (on-prem, DOC-01) — assets bundled.
  - no `Gate::before` super-admin bypass (DOC-03).
  - no update/delete on `audit_logs` in app code (DOC-17).
  - no `qr_token` reassignment outside creation (DOC-13).
- **Test suite** — unit + feature + scenario green; the §3 invariant guards are part of this and are treated as blocking.
- **Migrations** — `migrate:fresh` + seed runs clean; a schema test asserts the Worker≠User FK rule and the no-`worker_id`-on-PPE rule.

---

## 8. Coverage priorities (where to be thorough)

1. **Policies & authorization** — every permission, both sides (has/lacks). Authorization bugs are security bugs.
2. **Ingest pipeline** — idempotency, backfill, skew, reference resolution — the data integrity foundation.
3. **The §3 invariant guards** — non-negotiable.
4. **Windowed logic** — debounce, stationary, hysteresis, absence sweep, correlation windows (time-controlled).
5. **Report generation** — the compliance artifact; freeze + supersede + completeness.
6. **Identity stripping** — proven at the resource/API level, not just UI.

Lower priority: exhaustive CRUD permutations already covered by the matrix generator; pure-display components.

---

## 9. Real-life validation (beyond automated tests)

- The **commissioning acceptance checklist** (DOC-20 §10) is the human-run acceptance suite — functional smoke per module on the real hardware, which automated tests (with simulated ingest) can't fully replace.
- A **soak period** at commissioning: run the system live for a defined window, watch for missed reads, alarm behavior, backfill after a deliberate link drop, and a restore drill — before sign-off.

---

## 10. Open decisions logged

| # | Decision | Default | Confirm in |
|---|---|---|---|
| 1 | Test DB engine | MySQL (fidelity) over SQLite | this doc |
| 2 | Coverage threshold enforced in CI | meaningful on services/policies; no vanity % gate | this doc |
| 3 | Scenario tests via real ingest endpoints vs direct inserts | via ingest endpoints (proves the seam) | this doc |
| 4 | Frontend test depth | permission/live/forms/custody; not pixel snapshots | this doc |

---

## 11. Closing — the documentation set

DOC-01 through DOC-21 form the complete build specification for the IR4 Safety Command Center:
- **Foundation (01–03):** base structure, authentication, dynamic roles.
- **Core domain scaffolding (04–06):** workers, dynamic hardware registry, zones + time-aware bindings.
- **Cross-cutting systems (07–08):** unified alerts, ingestion + real-time backbone.
- **Modules (09–14):** RFID tracking, PPE, gas/CO₂, environmental, QR equipment (+ custody), HSE incidents & LSR.
- **Surfaces & reporting (15–16):** weekly report, dashboard + display + design language.
- **Operations (17–20):** audit, settings, retention/backup/wipe, deployment runbook.
- **Quality (21):** this testing strategy.

Every module DOC carries its own Tests section; this document aggregates them into a matrix, a scenario catalogue, the invariant guards, and the CI gates — so the hard rules the design depends on are enforced continuously, not just asserted in prose.