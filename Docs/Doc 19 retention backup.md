# DOC-19 — Data Retention, Backup & End-of-Project

> **Depends on:** DOC-01 (queues/scheduler, storage), DOC-05 (device offline durations), DOC-09/11/12 (raw readings), DOC-15 (report PDFs), DOC-17 (audit logs — never pruned), DOC-18 (`retention.*` settings). **Feeds:** DOC-20 (backup/restore drills), DOC-16/11/12 (trends: gas and env from on-read raw aggregates beyond 24 h).
>
> **Scope:** the **data lifecycle** — **on-read SQL aggregates** for gas and environmental trends/reports (no sensor rollup tables), **pruning** of raw readings (and the hard rule that **compliance tables are never pruned**), the **data-volume math** that justifies the on-prem disk, and **encrypted daily backups** with rotation. **Out of scope:** what the data means (owned by its module) — this doc owns *how long it lives, how it's compacted, and backed up*.

---

## 1. Purpose & the two-tier data model

The platform generates two very different kinds of data:

- **High-volume machine telemetry** — tag reads, gas/CO₂/environmental readings. Millions of rows over a project. Valuable in aggregate (trends, reports) but not individually forever. **Gas** and **environmental** stay raw-only with on-read SQL aggregates (≤24 h raw points; hourly min/avg/max beyond); raw rows prune by age. Tags prune by age (no tag rollup in v1).
- **Compliance & safety records** — alerts, gas alarms, incidents, LSR, weekly reports, audit logs, entry/exit logs, equipment records, evacuation reports. Low-volume, legally/operationally significant. These are **never pruned** — retained for the life of the deployment and exported at project end.

The dividing line is a hard invariant (DOC-21): **pruning touches only raw sensor-reading tables; it never touches a compliance table.** The pruner operates on an explicit allow-list, not a deny-list, so a new table is safe by default (not pruned unless deliberately added).

---

## 2. Trend reads (② — on-read aggregates)

There is **no** `BuildSensorRollups` job and **no** `environmental_rollups` / `gas_reading_rollups` table. Trends and weekly-report sensor items aggregate raw rows with SQL on read.

- **Gas (DOC-11):** raw point series for ≤24 h; SQL hourly min/avg/max over raw beyond that (indexed `recorded_at`).
- **Environmental (DOC-12):** same pattern — raw for ≤24 h; SQL hourly min/avg/max over raw beyond that.
- Optional `tag_reading_rollups` `[CONFIRM AT DESIGN]` — tracking trends may derive from `entry_exit_logs`/positions; default: entry/exit drives manpower, so a tag rollup is optional.

---

## 3. Pruning (raw-data lifecycle, ②)

### 3.1 `PruneRawSensorData` — daily (DOC-01 §A8)
Operates on an **explicit allow-list** of raw sensor tables only:
| Table | Setting | Default | Condition to prune |
|---|---|---|---|
| `tag_readings` | `retention.tag_readings_days` | 90 | `recorded_at` older — **unconditionally** after window (v1 has no tag rollup; manpower derives from entry/exit) |
| `gas_readings` | `retention.sensor_readings_days` | 180 | older — **unconditionally** after window (gas is raw-only; no rollup gate) |
| `environmental_readings` | `retention.sensor_readings_days` | 180 | older — **unconditionally** after window (env is raw-only; no rollup gate) |
- Gas, environmental, and tags all prune by age alone (no rollup gate).
- Deletes in **chunks** (avoid long locks), off-peak, logged (rows pruned per table) as a `system` info summary.
- **Explicitly excluded (never in the allow-list):** `alerts`, `gas_alarms`, `hse_incidents`, `incident_*`, `lsr_violations`, `weekly_reports`, `audit_logs`, `entry_exit_logs`, `worker_positions` (current state), `equipment*`, `evacuation_*`, all registry/config tables. A code comment + a DOC-21 test asserts this list can't accidentally include a compliance table.

### 3.2 Generated-file cleanup
- Old **export files** (PPE trend exports, evacuation PDFs, ad-hoc CSVs) older than `retention.exports_days` (**7** — DOC-18 registry is authoritative) are removed from disk by a small daily sweep. **Published weekly-report PDFs are exempt** (they're compliance artifacts, kept).

---

## 4. Data-volume math (sizing the on-prem disk)

An order-of-magnitude estimate so the Dell R360's storage is provisioned correctly (DOC-20). Illustrative — actual depends on registered hardware (dynamic, DOC-05):

- **Tag reads:** ~5 readers × ~1 read/worker/few-seconds. Say ~80 workers, a read every ~5 s per in-range worker ⇒ order ~1–2 M rows/day. At ~120 bytes/row ⇒ ~150–250 MB/day raw. Pruned at 90 days ⇒ steady-state raw ~15–25 GB.
- **Gas/CO₂:** ~4 detectors × 1 reading/10–60 s ⇒ tens of thousands rows/day ⇒ a few MB/day; pruned at 180 days ⇒ ~1–2 GB.
- **Environmental:** ~1 sensor × 1/min ⇒ ~1.5 k rows/day ⇒ trivial; pruned at 180 days like gas.
- **Snapshots (PPE):** the space driver — each violation stores a JPEG (~100–300 KB). At, say, 200 violations/day ⇒ ~40 MB/day ⇒ ~15 GB/year (kept, they're evidence). Sizing assumes retaining snapshots for the project; a snapshot-thinning policy is a `[CONFIRM AT DESIGN]` option if space is tight.
- **Compliance rows:** kilobytes; negligible over years.
- **Backups:** each daily encrypted Spatie archive contains the MySQL dump and deployed application tree; one archive per day is retained for 30 days (§5).

**Takeaway:** provision on the order of **hundreds of GB** for a multi-year project (dominated by snapshots), with raw telemetry bounded by pruning. DOC-20 specifies the actual disk + monitoring; a `disk_space_low` `system` alert warns before it fills.

---

## 5. Backups (encrypted, rotated, ②)

### 5.1 Spatie `backup:run` — daily (DOC-01 §A8)
- `spatie/laravel-backup` v10 dumps the fixed `mysql` connection and the deployed Laravel tree to an **AES-256 encrypted** ZIP on the `backups` filesystem rooted at `/data/ir4-backups`. `/data` is a separate physical volume from live data (DOC-20). The archive password is `BACKUP_ARCHIVE_PASSWORD` in `.env`, never a runtime DB setting.
- Scheduler order follows Spatie's install guide: `backup:clean` at 01:00, then `backup:run` at 01:30 (avoid the 02:00–03:00 DST window). `backup:monitor` runs at 03:00.
- **Rotation:** `backup:clean` retains one daily archive for 30 days. Weekly/monthly/yearly tiers are disabled by default.
- **Notifications (on-prem):** Spatie mail/Slack/Discord channels are empty. Spatie events are routed through `BackupStatusService` → unified `AlertService` as deduplicated `system` warnings; success/recovery resolves matching alerts.
- Raw-data pruning requires a successful backup marker from the current day. A failed, missing, or incomplete backup blocks pruning and raises a deduplicated warning.
- **On-prem, no cloud egress** (DOC-01) — backups stay on site; off-site copy is a manual/operational step (DOC-20).

### 5.2 Restore drill
- Spatie does not restore archives. The privileged DOC-20 procedure copies an archive, decrypts and extracts it with AES-capable ZIP tooling, creates a **new staging MySQL schema**, imports `db-dumps/*.sql` with a MySQL-8-compatible client, and validates the schema/data. Deployed files are recovered manually only when required.
- No in-app live restore command exists. The runbook mandates this staging restore drill at commissioning and periodically; a backup that has not been restored is unproven.

---

## 6. End of project

Daily Spatie backups on the separate volume are the recoverable copy of the install. At project close, ops copies those archives (and the backup password) off the box; host-level decommissioning is outside the application. There is no in-app export-all or wipe command.

---

## 7. Scheduled-job summary (this doc's jobs)

| Job | Cadence | Action |
|---|---|---|
| Spatie `backup:clean` | daily 01:00 | retain one daily archive for 30 days |
| Spatie `backup:run` | daily 01:30 | encrypted MySQL + application archive to separate volume |
| Spatie `backup:monitor` | daily 03:00 | detect missing/unhealthy backups |
| `PruneRawSensorData` | daily 03:15 | prune raw sensor tables past retention (allow-list only; requires same-day backup) |
| export-file sweep | daily 03:30 | remove ad-hoc exports past `retention.exports_days` (not report PDFs) |

All registered in the scheduler (DOC-01 §A8), monitored; failures raise `system` alerts.

---

## 8. Real-life scenarios

- **Steady state:** nightly backup then pruning; gas and env trends stay on raw aggregates within the retention window; disk stays bounded.
- **Backfill after an outage:** a pole flushes 6 h of buffered reads → gas and env trends/report see them immediately from raw; pruning still respects the retention window.
- **Backup gap:** a nightly backup fails (disk full) → a `system` warning fires + `disk_space_low` → pruning is blocked → ops intervenes before data is at risk.
- **Project close:** ops copies Spatie backup archives off-box; host-level decommissioning is outside the application.
- **Compliance never lost:** across all pruning, every incident, LSR, alarm, report, and audit row remains — a two-year-old incident is still fully retrievable.

---

## 9. Tests (this doc's slice of DOC-21)

- **Trend aggregates:** gas and env trends use raw points ≤24 h and SQL hourly aggregates beyond; weekly-report sensor items read raw.
- **Pruning allow-list:** `PruneRawSensorData` removes only allow-listed raw tables past their window; a **compliance table is never touched**; gas/env/tag rows prune by age alone; chunked deletes.
- **Export-file sweep:** ad-hoc exports past window removed; **published report PDFs retained**.
- **Backup:** Spatie configuration fixes the source to `mysql`, produces an AES-256 archive on the configured volume, verifies the ZIP, retains 30 daily archives, raises failures as `system` alerts (no mail), and blocks pruning without a current successful backup.
- **Restore drill:** an encrypted archive decrypts successfully and its SQL imports into a new staging MySQL schema; no live schema is modified.
- **Volume guard:** `disk_space_low` `system` alert fires below the threshold.

---

## 10. Open decisions logged

| # | Decision | Default | Confirm in |
|---|---|---|---|
| 1 | Tag rollup table vs deriving manpower from entry/exit | derive from entry/exit; tag rollup optional | this doc / DOC-09 |
| 2 | Snapshot retention / thinning | keep for project; thinning optional if space tight | this doc / DOC-10 |
| 3 | Long-retention (weekly/monthly) backup copies | 30 daily only | DOC-20 |
| 4 | Off-site backup copy | manual/operational (no cloud egress) | DOC-20 |
| 5 | Retention windows (raw) | 90 / 180 days | DOC-18 |

---

### Next document
**DOC-20 — Deployment & Operations Runbook:** server prep (Dell R360), the app/queue/Reverb/scheduler process model, reverse-proxy LAN enforcement for the public QR page + device ingest, ZT411 printer setup, the DB-permission hardening (append-only audit), backup/restore drills, and the Phase-3 commissioning acceptance checklist.
