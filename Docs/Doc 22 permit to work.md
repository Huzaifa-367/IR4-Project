# DOC-22 — Permit to Work (PTW) — Saudi GI 2.100 Aligned

> **Depends on:** DOC-01 (conventions, private file storage, three-path origin), DOC-03 (new PTW permissions), DOC-04 (workers as permit personnel + identity stripping), DOC-06 (zones — permit scoped to a zone; adds `requires_permit` flag, §8), DOC-07 (new permit-derived alert types), DOC-09 (RFID presence cross-check), DOC-11 (live gas detectors feed atmospheric tests), DOC-13 (equipment isolation/LOTO link + document-attachment pattern), DOC-14 (permit violations → suggested LSR; still user-created), DOC-16 (active-permits dashboard widget + nav), DOC-17 (every permit action audited), DOC-18 (PTW registry settings / tunable defaults), DOC-19 (permits are compliance — never pruned). **Feeds:** DOC-14 (the three permit LSR categories become *detectable suggestions*, still path-③ authored), DOC-15 (permit stats in the weekly report), DOC-16 (active-permit board), DOC-07 (alert catalogue).
>
> **Scope:** the **Permit-to-Work system** modeled on **Saudi Aramco GI 2.100** (Work Permit System) and related Saudi practice (GI 2.709 gas testing, issuer/receiver certification, joint site inspection, shift validity / renewal, cancel vs close, Stop Work Authority), with IR4-specific sensor + RFID intelligence on top. **Governing design rule:** permit **types**, atmospheric **test packs**, type checklists, SIMOPS conflict rules, personnel roles, and **worker document requirements** are **fully dynamic registries** — nothing about the client's PTW catalogue is hardcoded in application logic (§3, §4.6). Seeded Saudi GI defaults ship so a greenfield install works out of the box; operators extend or retire catalogue rows without a deploy. **Out of scope:** being a legal system of record for a client that already runs Aramco SAPMT — see §12 (integrate vs. own).

---

## 1. Why this exists & the Saudi basis

The earlier design (DOC-14) treated **working-without-permit**, **hot-work-without-fire-watch**, and **SIMOPS violations** as *manual* LSR categories because "the platform can't detect them without access to the Digital Work Permit system." **DOC-22 gives IR4 that permit system.** Once permits live in IR4, the platform can cross-reference live sensor/RFID reality against active permits and *detect* those violations instead of waiting for an officer to observe them.

### 1.1 Saudi / Aramco alignment (what we implement)

IR4's PTW flow follows **Saudi Aramco General Instruction GI 2.100** — the mandatory work-permit framework used across KSA oil & gas and widely adopted as the industrial benchmark for hazardous-work authorization — plus adjacent Saudi practice:

| Saudi practice | IR4 implements as |
|---|---|
| **GI 2.100** work permit system | Full issuance lifecycle (§6) |
| **Four core SA forms** (9873-1 yellow Equipment Opening/Line Break, 9873-2 red Hot Work, 9873-3 blue Cold Work, 9873-4 green Confined Space Entry) | Seeded `permit_types` rows; catalogue remains extensible (§3) |
| **Certified Issuer ↔ certified Receiver** | User certifications gate `issue-permit` / `request-permit` (§2) |
| **Joint site inspection** before work starts | Mandatory inspection step + signatures (§6.2) |
| **JSA / Hazard Analysis Checklist** | Type-linked dynamic checklist pack (§3.2) |
| **Atmospheric gas testing** (GI 2.709) — H₂S, O₂, flammables/LEL, toxics as applicable | Dynamic test packs + live DOC-11 pre-fill (§5, §7) |
| **Validity = one operating shift**; **one consecutive-shift renewal** (total ≤ 24h) unless extended | Default validity / renew rules on each type; extended permits need higher approval (§6.5) |
| **Cancel** (conditions change / stop work) vs **Close** (job done / expired, joint close-out) | Distinct transitions in the state machine (§6) |
| **Stop Work Authority** | Any certified issuer (and Safety Manager) can suspend/cancel; auto-suspend on in-zone gas alarm (§7) |
| **Restricted areas** | Zones flagged `requires_permit` / linked to type rules (DOC-06) |
| **Saudi Labour Law / NCOSH** — occupational medical fitness, high-risk occupation competence | Dynamic **worker document packs** required before a worker may be listed on a permit (§4.6) |
| **Industry competence evidence** (H₂S, CSE entrant, fire watch, welding, LOTO, etc.) | Same document registry — site-tunable document types, not hardcoded |

Essence of GI 2.100 that IR4 encodes:

- A **permit is a written authorization** for a specific hazardous task, in a specific place, for a specific time window, with agreed precautions. **Work must not begin before the permit is properly signed.**
- A permit **authorizes a defined crew of workers** (RFID-tagged DOC-04 personnel) with explicit roles. Because the permit names **workers** and RFID tracks **workers**, IR4 can compare "who the permit authorizes" against "who is actually in the zone."
- **Issuer** (area authority) and **Receiver** (craft/contractor supervisor) are certified **users**; the crew are **workers**.
- **Multiple permits** may apply to one job (welding inside a tank = Confined Space Entry **and** Hot Work); the most restrictive controls apply.
- Permits are **time-boxed**, **closed/returned** on completion (or **cancelled** when conditions change), and everything is **auditable**.

This maps onto IR4's existing primitives — zones, live gas detectors, RFID rosters, equipment isolation, alerts, audit, private file storage — which is what makes cross-detection and document gating possible.

---

## 2. Roles — two distinct populations

PTW involves **two different populations**; IR4 keeps them strictly separate (Worker≠User from DOC-04):

**A. Authorizing / executing staff — software `users`** (log in, sign, hold issuer/receiver certificates). New permissions (DOC-03 catalogue):

| Permission | Who |
|---|---|
| `request-permit` | **Receiver** — requests/opens a permit; accountable for execution |
| `issue-permit` | **Issuer** — reviews, joint-inspects, authorizes; can suspend/cancel/close |
| `approve-permit` | **Approving Authority** — senior co-sign for high-risk / extended permits `[CONFIRM AT DESIGN]` |
| `perform-gas-test` | **Gas Tester** — records / confirms atmospheric tests |
| `manage-permit-catalogue` | Configure dynamic types, test packs, checklists, document requirements (Safety Manager) |
| `manage-worker-documents` | Upload / verify worker competence & fitness documents (may overlap DOC-04 manage-workers) |
| `view-permits` | Read the permit board / history |

**B. Authorized crew — `workers`** (DOC-04, RFID-tagged; they do **not** log in). A permit lists the specific **workers** permitted to work under it, each with a role (`entrant`, `standby`, `fire_watch`, `supervisor`, plus any **dynamic role codes** defined on the type). A permit with no authorized workers cannot go `active`.

> **Why this separation matters:** the Receiver (a user) is *accountable* and signs, but the people holding the tools are **workers** on `permit_personnel`. Cross-referencing only works because the permitted crew are RFID-tracked workers, not login accounts.

**Certification gating (users).** GI 2.100 requires issuers/receivers/gas-testers to be *certified*. IR4 records a `user_certification` (type + number + issued_at + expires_at + issuing_body). The service **hard-blocks** `issue-permit` / `request-permit` / `perform-gas-test` if the user lacks a current certification of the right type, with Safety-Manager override `[CONFIRM AT DESIGN]` (default: hard-block + override).

**Document gating (workers).** Before a worker can be added to `permit_personnel` (and again before the permit becomes `active`), IR4 verifies the **dynamic document pack** required for that permit type + personnel role (§4.6). Missing or expired required documents block the add / block issue.

Seeded starter roles (editable, DOC-03): **Safety Manager** holds all PTW perms; **Permit Issuer** (`issue-permit`, `perform-gas-test`, `view-permits`); **Permit Receiver** (`request-permit`, `view-permits`).

---

## 3. Fully dynamic permit catalogue (not a hardcoded enum)

### 3.1 Governing rule — dynamic like DOC-05 hardware

**Nothing about the client's permit catalogue is hardcoded in application logic.** There is no fixed `PermitType` PHP enum that locks the platform to seven cases. Operators (with `manage-permit-catalogue`) register, edit, disable, and extend:

- permit **types** (forms)
- **checklist packs** (hazard / precaution items per type)
- **atmospheric test packs** (which channels, pass thresholds, whether required)
- **required personnel roles** (e.g. fire watch for hot work)
- **SIMOPS conflict rules** (type A conflicts with type B in same/adjacent zone)
- **worker document requirements** (which documents a crew member must hold)

The platform always iterates over **registered** catalogue rows. A greenfield install **seeds** the Saudi GI 2.100 core four forms plus common industrial extensions; a client may retire unused rows or add site-specific forms (e.g. Radiography, Diving) without a code deploy.

> **Status / phase enums stay as PHP enums** (`PermitStatus`, `GasTestResult`, `GasTestPhase`, `GasTestSource`) — those are platform lifecycle vocabulary, not client catalogue. Catalogue identity is **data** (`permit_types.code` string FK), mirrored to the UI as options from the API — same pattern spirit as DOC-05's open registry, not DOC-01's closed domain enums.

### 3.2 Seeded Saudi GI 2.100 defaults (illustrative, not constraints)

| `code` | SA form / colour | Purpose | Typical dynamic controls (seeded) |
|---|---|---|---|
| `equipment_opening` | 9873-1 · yellow | Initial opening of closed systems with flammable / toxic / injurious contents | Isolation/LOTO refs, gas test pack, drain/depressure checklist |
| `hot_work` | 9873-2 · red | Ignition energy (flame, sparks, heat, blasting, unclassified engines) | Fire-watch role required, LEL/H₂S gas pack, extinguisher + adjacent clearance checklist |
| `cold_work` | 9873-3 · blue | Hazardous work without ignition energy | JSA checklist; gas pack where zone is restricted |
| `confined_space` | 9873-4 · green | Entry into spaces not designed for occupancy / hazardous atmosphere risk | Standby + entrant roles, continuous/periodic gas pack (O₂/LEL/H₂S/CO), isolation, rescue plan |
| `excavation` | site extension | Digging / earthwork | Utility clearance, shoring, edge protection; CSE rules if ≥ 1.2 m depth `[CONFIRM AT DESIGN]` |
| `electrical` | site extension | Live / electrical isolation work | Isolation + LOTO; classified-area check |
| `work_at_height` | site extension | Fall-from-height work | Harness/anchor; ties to DOC-14 height-harness checks |

- Each type carries a **seeded checklist pack** (GI hazard-analysis / precaution list) that the receiver completes and the issuer verifies — editable per site.
- **Combination permits:** one `work_order` may require several type rows; IR4 links them and enforces that **all** are `active` before work may proceed.
- Type metadata includes: `colour_token`, `sa_form_code`, `requires_gas_test`, `requires_approver`, `default_validity_minutes`, `max_renewals`, `max_total_minutes` (default 24h), `allows_extended` (≤ 30 days with approver), `retest_interval_minutes`, `is_active`.

---

## 4. Data model

### 4.1 Catalogue tables (dynamic)

```php
// Permit forms — fully dynamic catalogue
permit_types: id, code (unique), name, description, colour_token, sa_form_code nullable,
              requires_gas_test bool, requires_approver bool, requires_joint_inspection bool,
              default_validity_minutes, max_renewals, max_total_minutes, allows_extended bool,
              retest_interval_minutes nullable, sort_order, is_active, timestamps

// Checklist items bound to a type (JSA / precautions)
permit_type_checklist_items: id, permit_type_id, code, label, is_mandatory bool,
              sort_order, is_active, timestamps

// Required crew roles for a type (fire_watch, standby, …) — codes are free strings
permit_type_roles: id, permit_type_id, role_code, label, min_count unsigned default 1,
              is_mandatory bool, sort_order, timestamps

// Atmospheric channels required for a type (dynamic — not fixed columns only)
permit_type_gas_channels: id, permit_type_id, channel_code (o2_pct|lel_pct|h2s_ppm|co_ppm|custom…),
              label, unit, warn_below nullable, warn_above nullable, alarm_below nullable,
              alarm_above nullable, sort_order, timestamps

// SIMOPS conflict matrix (type A vs type B)
permit_type_conflicts: id, permit_type_id, conflicts_with_type_id, scope(same_zone|adjacent_zone),
              severity(block|warn), note nullable, timestamps
```

### 4.2 `permits`

```php
Schema::create('permits', function (Blueprint $table) {
    $table->id();
    $table->string('permit_number')->unique();            // PTW-{yyyy}-{seq}
    $table->foreignId('permit_type_id')->constrained('permit_types'); // DYNAMIC — not an enum column
    $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
    $table->text('task_description');
    $table->foreignId('receiver_id')->constrained('users');
    $table->foreignId('issuer_id')->nullable()->constrained('users');
    $table->foreignId('approver_id')->nullable()->constrained('users');
    $table->string('status')->default('draft');           // enum PermitStatus (§4.7)
    $table->unsignedTinyInteger('renewal_count')->default(0);
    $table->boolean('is_extended')->default(false);       // > one-shift / multi-day path
    $table->timestamp('valid_from')->nullable();
    $table->timestamp('valid_to')->nullable();
    $table->json('checklist')->nullable();                // completed item answers keyed by checklist item id
    $table->json('controls')->nullable();                 // isolation_refs[], etc.
    $table->boolean('gas_test_required')->default(true);  // snapshotted from type at create; overridable
    $table->timestamp('joint_inspection_at')->nullable();
    $table->foreignId('joint_inspection_by_issuer')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('joint_inspection_by_receiver')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('issued_at')->nullable();
    $table->timestamp('closed_at')->nullable();
    $table->string('close_note')->nullable();
    $table->string('cancel_reason')->nullable();
    $table->string('source')->default('ir4');             // ir4 | import (integration mode)
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();
    $table->index(['status']);
    $table->index(['permit_type_id', 'status']);
    $table->index(['zone_id', 'status']);
    $table->index(['valid_from', 'valid_to']);
});
```

### 4.3 `permit_gas_tests` (dynamic channel values)

```php
Schema::create('permit_gas_tests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('permit_id')->constrained()->cascadeOnDelete();
    $table->timestamp('tested_at');
    $table->json('readings');                             // { "o2_pct": 20.9, "lel_pct": 0, "h2s_ppm": 0, … } — keys from type's gas channels
    $table->string('result');                             // pass|fail
    $table->string('source');                             // manual|device
    $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('tested_by')->nullable()->constrained('users')->nullOnDelete();
    $table->string('phase')->default('pre_start');        // pre_start|periodic|post_break|renewal
    $table->timestamps();
    $table->index(['permit_id', 'tested_at']);
});
```

Pass/fail is evaluated against the **permit type's dynamic channel thresholds** (falling back to DOC-11 site thresholds when a channel maps to a known gas sensor key).

### 4.4 Crew, approvals, events

```php
permit_personnel:  id, permit_id, worker_id, role_code (string — from permit_type_roles),
                   documents_verified_at nullable, timestamps
                   // unique(permit_id, worker_id, role_code)

permit_approvals:  id, permit_id, user_id, action(issued|approved|renewed|suspended|resumed|cancelled|closed|rejected),
                   note, signed_at

permit_events:     id, permit_id, event, payload(json), user_id nullable, occurred_at
```

`permit_personnel.worker_id` → `workers` (never `users`). Identity display is `view-worker-identity`-gated (DOC-04).

### 4.5 `work_orders`

`work_orders`: id, reference, description, zone_id, status — groups combination permits that must **all** be `active` for work to proceed.

### 4.6 Dynamic worker document collection (Saudi competence / fitness gate)

Saudi practice (Labour Law medical-fitness duties, NCOSH high-risk occupation fitness, Aramco / industrial competence cards) requires **evidence on the worker** before hazardous work. IR4 models this as a **fully dynamic document registry** — not a fixed list of certificate columns on `workers`.

```php
// Catalogue of document kinds the site cares about (fully dynamic)
worker_document_types: id, code (unique), name, description,
                       category (identity|medical|competence|site_access|other),
                       requires_expiry bool default true,
                       requires_file bool default true,
                       is_active, sort_order, timestamps

// Seeded examples (editable / extensible — not hardcoded gates):
//   iqama                 — Iqama / national ID evidence (identity)
//   medical_fitness       — Occupational medical fitness (NCOSH / Labour Law)
//   h2s_awareness         — H₂S awareness / SCBA competence
//   cse_entrant           — Confined-space entrant training
//   cse_standby           — Confined-space standby / attendant
//   fire_watch            — Fire-watch competence
//   hot_work_welder       — Welding / hot-work craft card
//   loto_authorized       — LOTO authorized employee
//   work_at_height        — Fall-protection competence
//   gas_tester            — (also usable for users via user_certifications)

// Uploaded / recorded documents on a WORKER
worker_documents: id, worker_id, worker_document_type_id,
                  document_number nullable, issuing_body nullable,
                  issued_at nullable, expires_at nullable,
                  file_path nullable,                 // private disk (DOC-01 §10), like equipment_documents
                  verification_status (pending|verified|rejected|expired),
                  verified_by nullable → users, verified_at nullable,
                  notes nullable,
                  uploaded_by nullable → users,
                  timestamps, softDeletes
                  // index (worker_id, worker_document_type_id), index (expires_at)

// Which documents a permit type requires, optionally scoped to a crew role
permit_type_document_requirements: id, permit_type_id,
                  worker_document_type_id,
                  role_code nullable,                 // null = required for EVERY crew member on this type
                  is_mandatory bool default true,
                  must_be_verified bool default true, // verified_status=verified and not past expires_at
                  timestamps
```

**Gating rules (hard defaults):**

1. **Add to crew:** when attaching a worker to `permit_personnel`, `PermitService::assertWorkerDocuments(permit, worker, role_code)` loads all mandatory `permit_type_document_requirements` for the type (role-specific ∪ type-wide). Every required type must have a non-expired `worker_documents` row; if `must_be_verified`, status must be `verified`. Failure → 422 with the missing document codes.
2. **Issue / renew:** re-run the check for **every** `permit_personnel` row. A worker whose medical fitness or H₂S card expired overnight cannot remain on an issuing/renewing permit.
3. **Active monitoring:** a scheduled job flags workers on `active` permits whose required docs expire during the validity window → alert + suggest suspend `[CONFIRM AT DESIGN]` (default: alert only; issuer decides suspend).
4. **Reuse:** documents live on the **worker**, not the permit — one verified medical fitness card satisfies every future CSE/hot-work permit until expiry. Permit detail shows a **read-only snapshot** of which documents were accepted at issue time (`permit_events` payload) for audit.

**UI:** Worker show page gains a **Documents** tab (upload, expiry, verify). Permit create/show crew step shows per-worker document traffic lights (green = pack satisfied, amber = expiring ≤ 30 days, red = missing/expired/unverified) and blocks submit while any red remains for mandatory items.

Permissions: `manage-worker-documents` to upload/verify; `view-worker-identity` still strips identity fields in listings — document **metadata** (type, expiry, status) is visible to permit issuers; file download follows the same identity / data-access rules as other private worker files `[CONFIRM AT DESIGN]`.

### 4.7 Lifecycle enums (platform-fixed)

- **`PermitStatus`:** `draft`, `pending_inspection`, `pending_gas_test`, `pending_issue`, `pending_approval`, `active`, `suspended`, `expired`, `closed`, `cancelled`, `rejected`.
- **`GasTestResult`:** `pass`, `fail`. **`GasTestPhase`:** `pre_start`, `periodic`, `post_break`, `renewal`. **`GasTestSource`:** `manual`, `device`.
- **`WorkerDocumentVerificationStatus`:** `pending`, `verified`, `rejected`, `expired`.
- Personnel **role codes** are **not** a closed PHP enum — they come from `permit_type_roles.role_code` (seeded: `entrant`, `standby`, `fire_watch`, `supervisor`).

### 4.8 `user_certifications` (issuer / receiver / gas-tester cards)

```php
user_certifications: id, user_id → users, cert_type (issuer|receiver|gas_tester|other — string, site-extensible),
                     certificate_number nullable, issuing_body nullable,
                     issued_at, expires_at, file_path nullable, // private disk
                     is_active bool, timestamps, softDeletes
                     // index (user_id, cert_type, expires_at)
```

GI 2.100 certificates are organization-scoped in Aramco practice; IR4 (single-site) treats them as user-scoped. `PermitService` checks a non-expired active row of the required `cert_type` before request / issue / gas-test. Safety Manager override writes a `permit_events` + `audit_logs` row with reason.

### 4.9 Three-path origin (DOC-01)

| Action | Path | `created_by` / actor |
|---|---|---|
| Request, checklist, crew, close-return request | ③ User | acting user |
| Issue, approve, renew, cancel, joint-inspect sign, gas confirm | ③ User | acting user |
| Auto-suspend on gas alarm / missed re-test | ② System | null (`permit_events.user_id` null) |
| Import from external PTW | ③ User or ② System (integration job) | importer user or null |
| Document upload / verify | ③ User | uploader / verifier |

Machine truth (RFID presence, live gas) is never written into the permit as a fake signature — it only **gates**, **pre-fills**, or **suspends**.

---

## 5. Atmospheric tests — dynamic packs + live IR4 feed

GI 2.100 / GI 2.709 require atmospheric testing before issuing permits in restricted areas and for CSE / equipment-opening / hot work where flammables may be present — and **re-testing** periodically, after breaks, and on renewal.

- Each permit type declares its **gas channel pack** (`permit_type_gas_channels`). Seeded packs cover O₂ / LEL / H₂S / CO; sites may add custom toxics (e.g. benzene) as additional channels with thresholds.
- Readings are stored as **JSON keyed by channel_code** — never a fixed four-column schema — so new channels need no migration.
- **IR4 twist:** when a live detector (DOC-11) covers the permit's zone, `PermitService::suggestGasTest()` pre-fills matching channels from the latest live reading with `source=device`. The certified tester confirms or overrides with a handheld (`source=manual`).
- **Live suspension:** a `gas_alarm` in a zone with an `active` permit auto-**suspends** that permit and raises a critical alert.
- Pass/fail uses the type's channel thresholds (else DOC-11 site thresholds for known keys) — one source of truth with the platform's alarms.

---

## 6. Saudi-aligned issuance flow (state machine)

```
 draft ─(submit)→ pending_inspection ─(joint inspect signed)→ pending_gas_test ─(gas pass)→ pending_issue
   │                      │                                         │                          │
   │                      │                                         └─(gas fail)→ re-test       ├─(needs approver)→ pending_approval ─(approve)→ pending_issue
   │                      └─(waived: low-risk cold only)─────────────→ pending_issue / gas path │
   └─(reject)→ rejected                                                                              │
                                                                                               issuer signs → active
                                                                                                    │
                                              active ⇄ suspended (Stop Work / gas alarm / missed retest)
                                              active ─(renew once, ≤24h total)→ active (renewal gas + co-sign)
                                              active ─(valid_to)→ expired
                                              active ─(complete + joint close)→ closed
                                              active|suspended ─(conditions change)→ cancelled
```

### 6.1 Request (Receiver, `request-permit`)

Creates a `draft`: dynamic type, zone, task, planned window, **crew of workers + roles**, completes the type's **checklist / JSA**. Worker document pack must be green for every mandatory requirement before submit is accepted → `pending_inspection` (or skip to gas/issue path only when the type marks joint inspection not required — default **required**, waivable only for extremely low-risk cold work per GI practice).

### 6.2 Joint site inspection (Issuer + Receiver)

Both parties confirm conditions at the worksite (checklist sign-off + `joint_inspection_at`). GI 2.100: work shall not begin before the permit is properly signed; the joint inspection is the information-sharing gate. Recorded on the permit; both user ids captured.

### 6.3 Atmospheric gas test (`perform-gas-test`)

If `gas_test_required` is false for this permit (snapshotted from the type, e.g. low-risk cold work outside a restricted / `requires_permit` zone), the flow **skips** `pending_gas_test` and moves from inspection (or draft submit) straight to `pending_issue` / `pending_approval`.

Otherwise a certified tester records / confirms the type's channel pack. Fail blocks progress; pass → `pending_issue` (or `pending_approval` when the type / extended flag requires it).

### 6.4 Approve (high-risk / extended) & Issue

- **Approver** co-signs when `requires_approver` or `is_extended`.
- **Issuer** signs → `issued_at`, `issuer_id`, `valid_from` / `valid_to` (default = one operating shift from type metadata) → `active`.
- **Work must not begin until `active`.** Enforcement: UI + API reject "start work" / combination clearance until status is `active`; RFID cross-checks only treat `active` as authorizing (§8).

### 6.5 Validity, renewal, extended permits (Saudi defaults)

| Rule | Default |
|---|---|
| Initial validity | One operating shift (`default_validity_minutes`, site setting, typically 8–12h) |
| Renewal | **One** consecutive shift (`max_renewals = 1`); requires incoming issuer+receiver signatures, renewal gas test, document re-check; **total duration ≤ 24h** (`max_total_minutes`) |
| Extended permit | `allows_extended` types may request up to **30 days** with Approving Authority; still subject to periodic re-test / re-validation rules on the type |
| Expiry | Unclosed past `valid_to` → `expired` + alert; workers still in zone while expired → unauthorized-work style alert |

### 6.6 Active controls

While `active`: RFID / gas / fire-watch / SIMOPS cross-checks (§8); periodic re-test scheduler per `retest_interval_minutes`; missed re-test → alert and optional auto-suspend.

### 6.7 Suspend / Cancel / Close (Stop Work Authority)

- **Suspend:** temporary stop (gas alarm, missing fire watch, issuer decision). Resume only after conditions clear + re-test if required.
- **Cancel:** conditions or scope changed; permit is void; new permit required. Distinct from close.
- **Close / return:** job complete or natural end — issuer + receiver joint close-out note → `closed`. Filing retained per DOC-19 retention (permits are compliance records — **never pruned**).

Every transition writes `permit_events` **and** `audit_logs` (DOC-17); signatures land in `permit_approvals`.

---

## 7. Gas test — where IR4 is stronger than paper / handheld-only PTW

- Pre-fill from fixed detectors covering the zone (`source=device`).
- Continuous validation: in-zone `gas_alarm` → auto-suspend.
- Dynamic channels stay aligned with whatever the site configured on the type — not a one-off handheld form.

---

## 8. Cross-referencing — detecting the three "manual" LSR categories

With permits in IR4, previously-manual DOC-14 categories become **detectable** (alert suggests LSR; human confirms — never auto-creates). **DOC-14's wording that these three "cannot be detected without a Digital Work Permit system" is superseded by this doc once PTW is enabled** — detection becomes available; authorship remains path ③ (alert prefill only).

| Previously-manual LSR | Detection with PTW |
|---|---|
| **Working without a permit** | RFID workers active in a zone with `requires_permit = true` (DOC-06 extension — see below) and **no `active` permit** authorizing that zone/time → `work_without_permit` |
| **Hot work without fire watch** | `active` permit whose type requires a `fire_watch` role; that worker absent from zone (or never assigned) → `hot_work_no_fire_watch` |
| **SIMOPS violation** | Two `active` permits whose types appear in `permit_type_conflicts` for same/adjacent zones → `simops_conflict` |

Additional live checks: worker in zone **not** on `permit_personnel`; expired-but-not-closed permit with crew still present; crew count above type/permit limit; **required worker document expired mid-permit** → compliance alert.

### 8.1 DOC-06 extension — `zones.requires_permit`

DOC-06 gains a boolean `requires_permit` (default false) on `zones`. When true, presence of RFID-tracked workers in that zone without a covering `active` permit raises `work_without_permit`. Distinct from `requires_authorization` / `restricted_red` (access-list and always-alert semantics stay as DOC-06 defines). Operators mark process areas, tank farms, and other GI "restricted areas" accordingly.

---

## 9. Combination permits & isolation

- A `work_order` groups permits that must **all** be `active` (e.g. CSE + hot work for welding in a tank).
- `controls.isolation_refs` link to equipment (DOC-13) / hold-tags for equipment-opening and electrical-class types.

---

## 10. API / routes (operator surface A) & UI

### 10.1 Permit lifecycle

| Action | Route | Permission |
|---|---|---|
| Permit board / list | GET `/permits` | view-permits |
| Permit detail | GET `/permits/{permit}` | view-permits |
| Create (request) | POST `/permits` | request-permit |
| Update draft / checklist / crew | PUT `/permits/{permit}` | request-permit |
| Record / confirm gas test | POST `/permits/{permit}/gas-tests` | perform-gas-test |
| Suggest gas test from live detector | GET `/permits/{permit}/gas-suggestion` | perform-gas-test |
| Joint site inspection sign | POST `/permits/{permit}/inspection` | issue-permit (+ receiver countersign) |
| Approve (high-risk / extended) | POST `/permits/{permit}/approve` | approve-permit |
| Issue (activate) | POST `/permits/{permit}/issue` | issue-permit |
| Renew | POST `/permits/{permit}/renew` | issue-permit |
| Suspend / resume | POST `/permits/{permit}/suspend` · `/resume` | issue-permit |
| Cancel | POST `/permits/{permit}/cancel` | issue-permit |
| Close / return | POST `/permits/{permit}/close` | issue-permit |
| Reject | POST `/permits/{permit}/reject` | issue-permit |

### 10.2 Dynamic catalogue & worker documents

| Action | Route | Permission |
|---|---|---|
| List / CRUD permit types | `/settings/permit-types…` | manage-permit-catalogue |
| Checklist / roles / gas channels / conflicts / doc requirements | nested under type | manage-permit-catalogue |
| Worker documents list / upload / verify | `/workers/{worker}/documents…` | manage-worker-documents |
| Document type catalogue | `/settings/worker-document-types…` | manage-permit-catalogue |

### 10.3 Frontend (Inertia)

- **`pages/permits/board.tsx`** — Active Permit Board (Pending inspection · Gas test · Pending issue · Active · Suspended · Expiring). Cards colour-coded from `permit_types.colour_token` (GI form colours by default). Live via Reverb channel `permits` (`.PermitUpdated`) with poll fallback (DOC-08 pattern).
- **`pages/permits/show.tsx`** — JSA/checklist, crew with document traffic lights, gas-test history (device badges), approval chain, linked permits, timeline, suspend/cancel/close.
- **`pages/permits/create.tsx`** — wizard: **pick dynamic type** → task/zone/window → **crew + document gate** → checklist → submit.
- **`pages/settings/permit-types/…`** — catalogue admin (types, checklists, roles, gas packs, SIMOPS, document requirements).
- **`pages/workers/documents` tab** — upload / expiry / verify.
- **Nav (DOC-16):** Control Room sidebar entry **Permits** (gated by `view-permits`), placed with the other live HSE surfaces.
- **Dashboard widget (DOC-16):** Active Permits by type + expiring + suspended; map overlay for zones with active permits.
- **Types:** `Permit`, `PermitType` (resource, not closed enum), `PermitStatus`, `PermitGasTest`, `PermitPersonnel`, `PermitApproval`, `WorkerDocument`, `WorkerDocumentType`, `WorkOrder`.

---

## 11. Real-life scenarios

- **Hot work in Work Front A:** Receiver requests seeded `hot_work` type, selects welders + fire-watch worker. Platform blocks add until fire-watch worker has verified `fire_watch` + `medical_fitness` documents and welders have `hot_work_welder` + `h2s_awareness`. Joint inspection signed → gas pack pre-filled from Pole 2 → Issuer signs → `active`. Fire-watch RFID leaves zone → `hot_work_no_fire_watch` alert. LEL alarm → auto-suspend.
- **Confined space + welding:** `work_order` links `confined_space` + `hot_work`; both must be active; standby + entrant roles enforced by type role mins; continuous gas from fixed detector supplements handheld; missed periodic re-test auto-suspends.
- **Shift renewal (GI rule):** near end of shift, incoming issuer/receiver renew once (≤ 24h total), renewal gas test + document re-check.
- **Cancel vs close:** process upset mid-job → issuer **cancels** (void); next day a **new** permit is requested. Completed job → joint **close**.
- **Working without a permit:** RFID crew in restricted zone, no active permit → alert → suggested LSR.
- **Site adds Radiography type:** Safety Manager creates a new `permit_types` row, checklist, doc requirement (`radiography_cert`), no code change.
- **Weekly report:** permits issued/closed/cancelled/suspended and permit-derived LSRs feed DOC-15.

---

## 12. Own vs. integrate

Many KSA clients (especially on Aramco sites) already run an official electronic PTW (e.g. **SAPMT**) as the legal system of record. Two modes `[CONFIRM AT DESIGN]` per deployment:

- **IR4-owned PTW (this doc):** full issuance for sites without an existing digital PTW.
- **Integration / mirror mode:** client PTW stays authoritative; IR4 **imports** active permits (`source=import`) to power cross-referencing (§8) and live gas augmentation (§5/7). Imported permits still benefit from worker-document display when crew can be mapped to DOC-04 workers.

Detection (§8) and live gas augmentation remain the differentiators either way.

---

## 13. Tests (slice of DOC-21)

- **State machine:** cannot become `active` without issuer signature + (when required) joint inspection + passing gas pack + document gate; gas-fail blocks issue; high-risk requires approver; work-order blocked until all linked permits active; expiry → `expired`; cancel ≠ close.
- **Dynamic catalogue:** creating a new permit type with custom gas channels and doc requirements is honored by create/issue without code changes; disabling a type hides it from the request wizard but preserves historical permits.
- **Renewal:** second renewal beyond `max_renewals` rejected; total window > `max_total_minutes` rejected unless extended+approved.
- **Certification gating:** non-certified user cannot issue/receive/gas-test (403 or override path).
- **Worker documents:** missing/expired/unverified mandatory doc blocks crew add and blocks issue; verified in-date doc allows; mid-permit expiry raises alert.
- **Gas augmentation:** suggestion pulls live detector channels that intersect the type pack; in-zone gas alarm auto-suspends.
- **Cross-detection:** work-without-permit / hot-work-no-fire-watch / SIMOPS raise the right alert and *suggest* (never auto-create) LSR.
- **Audit:** every transition + signature + document verify writes `permit_events` / `audit_logs`.
- **Integration mode:** imported permit powers cross-referencing without the request wizard.

---

## 14. Open decisions logged

| # | Decision | Default | Confirm in |
|---|---|---|---|
| 1 | Own PTW vs integrate with client SAPMT | support both; per-deployment | this doc / DOC-20 |
| 2 | Distinct Approving-Authority for high-risk / extended | yes for CSE, high-risk hot work, extended ≤30d | this doc |
| 3 | User certification gating | hard-block + Safety-Manager override | this doc |
| 4 | Permit types / tests / checklists / doc packs | **fully dynamic registries**; GI 2.100 core four seeded | this doc |
| 5 | SIMOPS conflict matrix | seeded by type adjacency; catalogue-tunable | this doc |
| 6 | Auto-suspend on in-zone gas alarm | on | DOC-11/18 |
| 7 | Shift length / max total minutes | site setting; GI default ≤24h with one renewal | DOC-18 |
| 8 | Joint inspection waiver | only extremely low-risk cold work when type allows | this doc |
| 9 | Mid-permit document expiry | alert issuer (no auto-suspend) | this doc |
| 10 | Worker document file visibility | issuers see metadata; download follows identity / data-access rules | DOC-04/03 |

---

## 15. Retention (DOC-19)

Permits are **compliance records**. The DOC-19 pruner allow-list must **never** include:

`permits`, `permit_gas_tests`, `permit_personnel`, `permit_approvals`, `permit_events`, `work_orders`, `permit_types` (+ checklist/roles/gas/conflicts/doc-requirement children), `worker_document_types`, `worker_documents`, `user_certifications`.

Private document files on the `local`/`private` disk follow the same end-of-project export + wipe rules as other compliance attachments. Catalogue rows (`permit_types`, document types) are configuration — retained for the life of the deployment.

---

### Relationship to the set

DOC-22 extends the platform with a **Saudi GI 2.100–aligned Permit-to-Work** capability whose catalogue (types, atmospheric tests, checklists, roles, SIMOPS rules, **worker document requirements**) is **fully dynamic**, and closes the loop opened in DOC-14: the three permit-dependent LSR categories become sensor- and RFID-cross-referenced detections once IR4 *is* (or mirrors) the permit system — with the live fixed-gas network continuously validating the atmospheric gate and with Saudi competence / medical-fitness evidence enforced through a reusable worker document registry. It reuses zones (DOC-06), gas (DOC-11), RFID (DOC-09), equipment isolation + private attachments (DOC-13), alerts→suggested-LSR (DOC-07/14), settings (DOC-18), retention (DOC-19), and audit (DOC-17) rather than introducing parallel machinery.

**Follow-on doc edits when implementing (not blocking this spec):** add PTW permissions to DOC-03's catalogue; add `zones.requires_permit` to DOC-06; note in DOC-14 §6.1 that the three permit LSR categories become alert-detectable once DOC-22 is live; add permits tables to DOC-19's never-prune exclusion list; add Permits nav + summary fields to DOC-16.
