# DOC-19 — Data Retention, Rollups, Backup & End-of-Project

> **Depends on:** DOC-01 (queues/scheduler, storage), DOC-05 (device offline durations), DOC-09/11/12 (raw readings + rollup tables), DOC-15 (report PDFs), DOC-17 (audit logs — never pruned), DOC-18 (`retention.*`, `backup.*` settings). **Feeds:** DOC-20 (backup/restore drills, wipe as a commissioning/decommissioning step), DOC-16/11/12 (trends read rollups beyond 24 h).
>
> **Scope:** the **data lifecycle** — hourly **rollups** of high-volume sensor data, **pruning** of raw readings (and the hard rule that **compliance tables are never pruned**), the **data-volume math** that justifies the on-prem disk, **encrypted daily backups** with rotation, and the **end-of-project** `ir4:export-all` and `ir4:secure-wipe` commands. **Out of scope:** what the data means (owned by its module) — this doc owns *how long it lives, how it's compacted, backed up, exported, and destroyed*.

---

## 1. Purpose & the two-tier data model

The platform generates two very different kinds of data:

- **High-volume machine telemetry** — tag reads, gas/CO₂/environmental readings. Millions of rows over a project. Valuable in aggregate (trends, reports) but not individually forever. These are **rolled up** (hourly min/avg/max) and the **raw rows pruned** after a retention window.
- **Compliance & safety records** — alerts, gas alarms, incidents, LSR, weekly reports, audit logs, entry/exit logs, equipment records, evacuation reports. Low-volume, legally/operationally significant. These are **never pruned** — retained for the life of the deployment and exported at project end.

The dividing line is a hard invariant (DOC-21): **pruning touches only raw sensor-reading tables; it never touches a compliance table.** The pruner operates on an explicit allow-list, not a deny-list, so a new table is safe by default (not pruned unless deliberately added).

---

## 2. Rollups (compaction, ②)

### 2.1 `BuildSensorRollups` — hourly (DOC-01 §A8)
- For each completed hour, aggregates raw readings into the per-hour rollup tables: `gas_reading_rollups` (DOC-11), `environmental_rollups` (DOC-12), and a `tag_reading_rollups` (per zone/hour headcount + read counts) `[CONFIRM AT DESIGN]` — tracking trends may derive from `entry_exit_logs`/positions rather than a tag rollup; default: entry/exit drives manpower, so a tag rollup is optional.
- Each rollup row: min/avg/max per channel + `sample_count`, keyed `(device_id, bucket_start)` (unique — idempotent; re-running an hour recomputes it).
- Rollups are **kept long-term** (they're small and power historical trends + the weekly report). They are **not** pruned with the raw data.
- Backfilled readings (DOC-08) that arrive after an hour was rolled up **trigger a recompute** of the affected buckets (the job re-aggregates any hour with new rows since last run), so late data still lands in trends/reports.

### 2.2 Trend reads use rollups beyond 24 h
Trends/charts read **raw** rows for the recent window (≤24 h, high resolution) and **rollups** beyond (DOC-11/12/16). This keeps charts fast and lets raw pruning proceed without losing history.

---

## 3. Pruning (raw-data lifecycle, ②)

### 3.1 `PruneRawSensorData` — daily (DOC-01 §A8)
Operates on an **explicit allow-list** of raw sensor tables only:
| Table | Setting | Default | Condition to prune |
|---|---|---|---|
| `tag_readings` | `retention.tag_readings_days` | 90 | `recorded_at` older — **unconditionally** after window (v1 has no tag rollup; manpower derives from entry/exit) |
| `gas_readings` | `retention.sensor_readings_days` | 180 | older AND the hour is rolled up |
| `environmental_readings` | `retention.sensor_readings_days` | 180 | older AND the hour is rolled up |
- **Guard:** a raw row is pruned only if its hour has a corresponding rollup (so compaction never loses aggregate history). If rollups are behind, pruning waits.
- Deletes in **chunks** (avoid long locks), off-peak, logged (rows pruned per table) as a `system` info summary.
- **Explicitly excluded (never in the allow-list):** `alerts`, `gas_alarms`, `hse_incidents`, `incident_*`, `lsr_violations`, `weekly_reports`, `audit_logs`, `entry_exit_logs`, `worker_positions` (current state), `equipment*`, `evacuation_*`, all registry/config tables. A code comment + a DOC-21 test asserts this list can't accidentally include a compliance table.

### 3.2 Generated-file cleanup
- Old **export files** (PPE trend exports, evacuation PDFs, ad-hoc CSVs) older than `retention.exports_days` (**7** — DOC-18 registry is authoritative) are removed from disk by a small daily sweep. **Published weekly-report PDFs are exempt** (they're compliance artifacts, kept).

---

## 4. Data-volume math (sizing the on-prem disk)

An order-of-magnitude estimate so the Dell R360's storage is provisioned correctly (DOC-20). Illustrative — actual depends on registered hardware (dynamic, DOC-05):

- **Tag reads:** ~5 readers × ~1 read/worker/few-seconds. Say ~80 workers, a read every ~5 s per in-range worker ⇒ order ~1–2 M rows/day. At ~120 bytes/row ⇒ ~150–250 MB/day raw. Pruned at 90 days ⇒ steady-state raw ~15–25 GB; rollups negligible (hourly).
- **Gas/CO₂:** ~4 detectors × 1 reading/10–60 s ⇒ tens of thousands rows/day ⇒ a few MB/day; pruned at 180 days ⇒ ~1–2 GB.
- **Environmental:** ~1 sensor × 1/min ⇒ ~1.5 k rows/day ⇒ trivial.
- **Snapshots (PPE):** the space driver — each violation stores a JPEG (~100–300 KB). At, say, 200 violations/day ⇒ ~40 MB/day ⇒ ~15 GB/year (kept, they're evidence). Sizing assumes retaining snapshots for the project; a snapshot-thinning policy is a `[CONFIRM AT DESIGN]` option if space is tight.
- **Compliance rows:** kilobytes; negligible over years.
- **Backups:** each daily dump compressed; 30 kept (§5).

**Takeaway:** provision on the order of **hundreds of GB** for a multi-year project (dominated by snapshots), with raw telemetry bounded by pruning. DOC-20 specifies the actual disk + monitoring; a `disk_space_low` `system` alert warns before it fills.

---

## 5. Backups (encrypted, rotated, ②)

### 5.1 `BackupDatabase` — daily (DOC-01 §A8)
- Dumps the database (and a manifest of the storage/ snapshot dir) to an **encrypted** archive on a **separate disk/volume** (not the same physical disk as the live DB — DOC-20). Encryption at rest via the deploy key (`.env`, not in the DB).
- **Rotation:** keep `backup.keep_count` (30) daily archives; older removed. Optionally a weekly/monthly long-retention copy `[CONFIRM AT DESIGN]`.
- Each backup logs success/size; a **failed or missing** backup raises a `system` warning (a silent backup gap is itself a risk).
- **On-prem, no cloud egress** (DOC-01) — backups stay on site; off-site copy is a manual/operational step the runbook documents (DOC-20).

### 5.2 Restore drill
- `ir4:restore {archive}` (privileged CLI, DOC-20) restores a backup into a staging DB for verification. The runbook mandates a **restore drill at commissioning** (prove backups are usable, not just created) and periodically.

---

## 6. End-of-project commands (decommissioning)

The system is an **on-prem, project-scoped** deployment; at project end the client gets their data and the site data is destroyed. Two privileged artisan commands (run by an admin at the console, not exposed in the UI):

### 6.1 `ir4:export-all` — the complete handover export
- Produces a single **encrypted archive** containing: a full DB export (SQL + CSV per table), **all weekly-report PDFs**, all **incident/LSR evidence** (snapshots, documents), the **audit log** (CSV), evacuation PDFs, and a manifest + checksums.
- This is the client's permanent record. Generating it writes an `exported` audit row (DOC-17); the archive is encrypted with a key handed to the client.
- Idempotent and resumable for large snapshot sets.

### 6.2 `ir4:secure-wipe` — destruction after handover
- **Two-step, guarded:** requires an explicit `--confirm` phrase and a prior successful `ir4:export-all` (checks a recorded export marker) — refuses to wipe if no verified export exists.
- Securely removes the database contents and the private storage (snapshots, documents, backups) per the client's data-destruction requirement. The verified `.ir4exp` handover archive is **immutable** — wipe never mutates it. Instead the command writes a separate signed **wipe receipt** beside the archive on the exports disk (and a local `wiped` audit row before clearing audit last).
- Intended to run once, at decommissioning, by an administrator. Logged and irreversible — the runbook (DOC-20) covers chain-of-custody.
- `[CONFIRM AT DESIGN]` exact wipe standard (e.g. crypto-erase vs overwrite) per client policy.

---

## 7. Scheduled-job summary (this doc's jobs)

| Job | Cadence | Action |
|---|---|---|
| `BuildSensorRollups` | hourly | compact raw → rollups; recompute backfilled hours |
| `PruneRawSensorData` | daily (off-peak) | prune raw sensor tables past retention (allow-list only) |
| export-file sweep | daily | remove ad-hoc exports past `retention.exports_days` (not report PDFs) |
| `BackupDatabase` | daily | encrypted DB backup to separate volume; rotate to `backup.keep_count` |
| `ir4:export-all` | manual (project end) | full encrypted handover archive |
| `ir4:secure-wipe` | manual (decommission) | guarded destruction after verified export |

All registered in the scheduler (DOC-01 §A8), monitored; failures raise `system` alerts.

---

## 8. Real-life scenarios

- **Steady state:** hourly rollups accumulate; nightly pruning trims 90-day-old tag reads and 180-day-old sensor reads (only where rolled up); trends still show a year of history from rollups; nightly backup runs; disk stays bounded.
- **Backfill after an outage:** a pole flushes 6 h of buffered reads → the next rollup run recomputes those hours → trends/report update; pruning still respects the retention window.
- **Backup gap:** a nightly backup fails (disk full) → a `system` warning fires + `disk_space_low` → ops intervenes before data is at risk.
- **Project handover:** at close, an admin runs `ir4:export-all` → hands the encrypted archive + key to the client → verifies → runs `ir4:secure-wipe --confirm` → the site install is destroyed; the verified archive stays intact and a separate wipe receipt is written beside it.
- **Compliance never lost:** across all pruning, every incident, LSR, alarm, report, and audit row remains — a two-year-old incident is still fully retrievable.

---

## 9. Tests (this doc's slice of DOC-21)

- **Rollups:** `BuildSensorRollups` computes correct min/avg/max + sample_count per hour; idempotent re-run; a backfilled reading triggers recompute of its hour.
- **Pruning allow-list:** `PruneRawSensorData` removes only allow-listed raw tables past their window; a **compliance table is never touched** (explicit test iterating the excluded set); a raw row is not pruned until its hour is rolled up; chunked deletes.
- **Export-file sweep:** ad-hoc exports past window removed; **published report PDFs retained**.
- **Backup:** produces an encrypted archive on the configured volume; rotates to `keep_count`; a failure raises a `system` warning; `ir4:restore` restores into staging.
- **export-all:** archive contains DB + report PDFs + evidence + audit CSV + manifest/checksums; writes an `exported` audit row.
- **secure-wipe:** refuses without `--confirm` and without a prior verified export; on success removes data, leaves the verified archive immutable, and writes a separate wipe receipt on the exports disk.
- **Volume guard:** `disk_space_low` `system` alert fires below the threshold.

---

## 10. Open decisions logged

| # | Decision | Default | Confirm in |
|---|---|---|---|
| 1 | Tag rollup table vs deriving manpower from entry/exit | derive from entry/exit; tag rollup optional | this doc / DOC-09 |
| 2 | Snapshot retention / thinning | keep for project; thinning optional if space tight | this doc / DOC-10 |
| 3 | Long-retention (weekly/monthly) backup copies | 30 daily only | DOC-20 |
| 4 | Off-site backup copy | manual/operational (no cloud egress) | DOC-20 |
| 5 | Secure-wipe standard | crypto-erase/overwrite per client policy | DOC-20 |
| 6 | Retention windows (raw) | 90 / 180 days | DOC-18 |

---

### Next document
**DOC-20 — Deployment & Operations Runbook:** server prep (Dell R360), the app/queue/Reverb/scheduler process model, reverse-proxy LAN enforcement for the public QR page + device ingest, ZT411 printer setup, the DB-permission hardening (append-only audit, wipe privileges), backup/restore + wipe drills, and the Phase-3 commissioning acceptance checklist.