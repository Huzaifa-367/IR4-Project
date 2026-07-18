# DOC-17 — Audit Logging

> **Depends on:** DOC-01 (conventions), DOC-02 (login/logout events), DOC-03 (`view-audit-log`; read-only-role `data_access`), and every module that mutates config/security-relevant data. **Feeds:** DOC-19 (audit logs are retained/exported, never pruned), DOC-20 (the audit trail is a commissioning/compliance deliverable), DOC-21 (append-only invariant test).
>
> **Scope:** the **append-only audit trail** — the `audit_logs` table, the `Auditable` trait/observer that records create/update/delete diffs on configured models, the **read-only-role per-request `data_access` logging**, the event catalogue, sensitive-field masking, and the read-only audit viewer. **Out of scope:** what each module logs semantically (defined where the action lives) — this doc owns the shared logging machinery.

---

## 1. Purpose

Safety and access decisions must be traceable: who changed a gas threshold, who classified an incident, who published a report, who logged in, and — for a client representative — everything they looked at. The audit log is the platform's tamper-evident record of **who did what, when, and what changed.** It is **append-only** (never edited or deleted through the app), retained for the life of the deployment, and included in the end-of-project export (DOC-19). The proposal (§3) requires a full audit of logins, data access, and configuration changes; this module delivers that.

**Hard rules (DOC-21 invariants):**
- **Append-only:** there is no update or delete route for `audit_logs`; the model blocks `updating`/`deleting`; ideally the DB user lacks UPDATE/DELETE on the table (DOC-20).
- **Never pruned:** DOC-19 retention explicitly excludes audit logs.
- **Every write is attributed** to a user (or system, for scheduled jobs) with IP and timestamp.
- **Sensitive values are masked** (passwords, tokens) — the log records that a change happened, not the secret.

---

## 2. Data origin

- **② system:** all audit rows are written by observers/middleware/services, never by a user-facing "create audit" endpoint. The *subject* of the audit is a ③ user action (or a ② system action like a scheduled report), but the audit row itself is machinery.
- **③ user:** only *reads* the log (`view-audit-log`).
- **① device:** device actions (ingest) are not individually audited (too high-volume); device *configuration* (token issuance, status changes — DOC-05) is audited as a config change.

---

## 3. Data model

### 3.1 `audit_logs` (append-only; no soft-delete needed — nothing is ever removed)
```php
Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // null = system/scheduled
    $table->string('event');                              // enum AuditEvent (§4)
    $table->nullableMorphs('auditable');                  // the affected record (polymorphic), when applicable
    $table->string('description')->nullable();            // human summary ("Changed CO alarm level 50→40")
    $table->json('old_values')->nullable();               // masked diff (updates/deletes)
    $table->json('new_values')->nullable();               // masked diff (creates/updates)
    $table->string('ip_address')->nullable();
    $table->string('user_agent')->nullable();
    $table->string('route')->nullable();                  // for data_access rows
    $table->timestamp('occurred_at')->index();
    $table->timestamps();
    $table->index(['event', 'occurred_at']);
    $table->index(['user_id', 'occurred_at']);
    $table->index(['auditable_type', 'auditable_id']);
});
```
- No `deleted_at` — the table is append-only by design.
- `old_values`/`new_values` hold **only the changed attributes** (a diff), already masked.

### 3.2 Enum — `AuditEvent`
`login`, `logout`, `login_failed`, `data_access`, `created`, `updated`, `deleted`, `config_changed`, `published`, `acknowledged`, `exported`, `wiped`.
(`config_changed` is used for settings/threshold/role/whitelist edits; `published` for reports; `acknowledged` for alerts/alarms; `exported` for data exports; `wiped` for the end-of-project wipe.)

---

## 4. What is audited (coverage)

### 4.1 Authentication & session (DOC-02)
`login`, `logout` (with reason: user / idle_timeout), `login_failed` (reason: bad_credentials / inactive / locked). IP + user-agent captured.

### 4.2 Configuration & security changes (the `Auditable` trait)
A model gets the **`Auditable`** trait (backed by an `AuditableObserver`) to auto-record `created`/`updated`/`deleted` with masked diffs. Applied to the security- and config-relevant models (the proposal's "configuration changes"):
- `users` (create/role-change/deactivate/reset — `config_changed`), `roles` + permission syncs (DOC-03), read-only-role whitelist edits.
- `settings` (`config_changed`, DOC-18), `gas_thresholds` (DOC-11).
- `devices` (token issuance/rotation, status), `cameras` (AI toggle), `assets` (DOC-05).
- `zones`, `zone_access_lists`, `reader_zone_bindings` (rebind — DOC-06).
- `maintenance_schedules` (DOC-13), report settings (DOC-15).
- entry/exit **manual corrections**, evacuation **force-closes**, RFID **tag assignments/replacements** (DOC-09).
Not every domain row is audited via the trait (that would drown the log) — high-churn operational records (readings, positions) are excluded; their *governing config* is what's audited.

### 4.3 Explicit domain events (logged by the action, not the trait)
Some actions deserve a first-class event beyond a generic update:
- Report `published` (DOC-15), alert/alarm `acknowledged` (DOC-07/11), data `exported` (PPE trends, evacuation PDF, end-of-project — DOC-10/15/19), incident classify/close & LSR close (as `updated` with a clear description).

### 4.4 Read-only-role data access (`data_access`)
For any user whose role is `is_read_only` (DOC-03 — e.g. the Client Representative), middleware `AuditDataAccess` writes a `data_access` row **per meaningful request** (index/show/export routes — an allow-list; not asset fetches or heartbeats). Captures user, `route`, key parameters, IP, timestamp. This satisfies the proposal's requirement that a client representative's activity is fully auditable, and generalizes to any read-only profile.

---

## 5. The `Auditable` trait & masking

- **`Auditable`** trait on a model registers the `AuditableObserver`, which on `created`/`updated`/`deleted` computes the changed attributes and writes an `audit_logs` row with `old_values`/`new_values` (diff only), the acting user (`auth()->id()`, or null for system), IP/UA (from the request), and a generated `description`.
- **Masking:** a per-model `$auditMasked = ['password', 'api_token_hash', 'two_factor_secret', …]` list replaces those values with `••••` in the diff — the log records *that* they changed, never the secret. Applied before persistence.
- **System writes** (scheduled jobs, ingest-derived config changes) have `user_id = null` and a description noting the source.
- **Attribution safety:** the observer reads `auth()->user()` at write time; service/job code that must attribute to a specific actor passes it explicitly.

---

## 6. Append-only enforcement

- The `AuditLog` model overrides `updating`/`deleting` to throw (no code path mutates a row).
- No controller/route exposes update/delete for audit logs — only `index`/`show`.
- **DB-level (DOC-20):** the application DB user is granted only INSERT/SELECT on `audit_logs` (no UPDATE/DELETE), so even a bug can't rewrite history. The wipe command (DOC-19) runs as a separate privileged step.
- Retention: DOC-19 never prunes this table; it is included verbatim in the end-of-project export.

---

## 7. The audit viewer (read-only, `view-audit-log`)

- **`GET /settings/audit-log`** (Inertia) — a filterable, read-only table: filter by `event`, `user`, `auditable_type` (model), date range, and free-text on description. Newest first, paginated.
- Each row expands to show the masked `old`/`new` diff, IP, and route. No edit/delete affordances anywhere.
- Deep-links to the affected record where it still exists (via `auditable`).
- Only Safety-Manager-level roles hold `view-audit-log` by default (DOC-03); it's a governance surface.
- Export: `GET /settings/audit-log/export?from&to` (CSV) for compliance hand-off — itself audited as an `exported` event.

---

## 8. Frontend (React / Inertia)

- **`pages/settings/audit-log/index.tsx`** — AuditLogPage: filter bar (event/user/model/date/search), read-only table, expandable diff rows, export button.
- **Components:** `AuditEventBadge`, `AuditDiff` (renders old→new with masked fields shown as `••••`), `AuditFilters`.
- **Types (`types/audit.ts`):** `AuditLog`, `AuditEvent`, `AuditDiff`.
- No create/edit UI — the viewer is read-only by design.

---

## 9. Real-life scenarios

- **Threshold change:** a Safety Manager lowers the CO alarm level 50→40 → an `updated`/`config_changed` row on `gas_thresholds` with `old:{alarm_level:50}`, `new:{alarm_level:40}`, the user, and IP → visible in the viewer, deep-linking to the threshold.
- **Client rep audit week:** the read-only rep opens dashboards and reports all week → each meaningful page view writes a `data_access` row → at week's end the manager can show exactly what the rep accessed.
- **Login anomaly:** repeated `login_failed` rows for an account from one IP → visible in the viewer, evidencing a lockout event.
- **Report published:** a manager publishes the weekly report → a `published` row referencing the report → the compliance trail shows who signed it off and when.
- **Token rotation:** an operator rotates a device token → a `config_changed` row on the device with the token value **masked** (`••••`) — the change is recorded, the secret is not.

---

## 10. Tests (this doc's slice of DOC-21)

- **Append-only:** no route updates/deletes an audit log; the model throws on `updating`/`deleting`; (DB-permission enforcement verified in the deploy check, DOC-20).
- **Coverage:** editing a `gas_threshold`/`setting`/`zone`/role writes a `config_changed`/`updated` row with a correct masked diff; login/logout/login_failed write rows with IP/UA; report publish writes `published`; alert acknowledge writes `acknowledged`.
- **Masking:** password/token/2FA-secret changes appear as `••••` in the diff, never the real value.
- **data_access:** a read-only-role user's index/show/export requests each write a `data_access` row; a normal operator's do not; asset/heartbeat routes are excluded.
- **System attribution:** a scheduled report generation writes rows with `user_id=null` and a source description.
- **Viewer:** `view-audit-log` required; filters work; no edit/delete affordances; export is itself audited.
- **Never pruned:** the retention job (DOC-19) leaves `audit_logs` untouched; the end-of-project export includes it.

---

## 11. Open decisions logged

| # | Decision | Default | Confirm in |
|---|---|---|---|
| 1 | DB-level INSERT/SELECT-only grant on `audit_logs` | yes (enforced at deploy) | DOC-20 |
| 2 | `data_access` route allow-list scope | index/show/export only (not assets/heartbeats) | DOC-03/18 |
| 3 | Retention of audit logs | forever (never pruned); exported at end-of-project | DOC-19 |
| 4 | Which models get the `Auditable` trait | the §4.2 config/security set | this doc |

---

### Next document
**DOC-18 — Settings & Configuration:** the consolidated settings registry (every tunable key + default + who edits + audit), the `SettingsService`, config-vs-`.env` boundary, and the general settings editor — the single home for all the `[CONFIRM AT DESIGN]` values accumulated across the docs.