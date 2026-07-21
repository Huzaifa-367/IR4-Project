# DOC-03 — Dynamic Roles & Permissions (RBAC)

> **Depends on:** DOC-01 (conventions, enum pattern, settings, hybrid surfaces), DOC-02 (authenticated identity — this doc decides what that identity may do). **Feeds:** every module DOC (each declares the permissions its actions require) and DOC-17 (audit of role/permission changes).
>
> **Scope:** the authorization model — the spatie setup, the complete permission catalogue, the fully dynamic role system (admin-created/edited roles) with the single fixed `Super Admin` role, the seeded starter roles and their default capability matrix, the read-only-role pattern (configurable client window), how enforcement is applied at four layers (route → policy → API resource → frontend), role/user provisioning, and the generated `PERMISSIONS.md`. **Out of scope:** authentication (DOC-02); the *content* each permission unlocks (defined in the owning module DOC).

---

## 1. Purpose & model choice

Authentication says *who you are*; authorization says *what you may do*. IR4 uses **role-based access control with permission-level granularity** via `spatie/laravel-permission`. Access is always checked against **permissions**, never role names directly (roles are just bundles of permissions). This keeps checks stable however the role↔permission mapping is edited.

**Fully dynamic roles.** Every role in the system is **created and edited at runtime** by an authorized administrator — role names and their exact permission sets are data, not code. Admins can add roles, rename them, retune which permissions each holds, and assign them to users, all without a deploy. The platform **ships a set of sensible starter roles** (seeded once), but those seeded roles are ordinary editable rows — nothing about them is locked except one:

**The single fixed role — `Super Admin`.** Exactly one role is immutable and always holds **every permission**, including permissions added by future modules. It cannot be edited, cannot have permissions removed, and cannot be deleted. It is the root-of-trust that guarantees the system is never left with no one able to administer it. Everything else is clay; `Super Admin` is bedrock.

Guiding invariants (enforced, re-checked in DOC-21):
- Every write action is gated by a **permission** via a Policy — never by a role name (so custom roles work automatically).
- `Super Admin` always has all permissions, is non-editable and non-deletable, and at least one active user always holds it (§7.4 lockout guard).
- Any role flagged **read-only** (§6) can hold only `view-*` permissions, enforced server-side regardless of what the editing UI sends.
- Permission checks are identical on backend and frontend (the frontend only hides; the backend enforces).

---

## 2. spatie configuration

- Install & publish: `composer require spatie/laravel-permission`, publish config + migrations, run them. `config/permission.php` uses the default table names.
- `User` model uses the `HasRoles` trait.
- **Guard:** all roles/permissions are registered under the `web` guard (the only guard that carries a spatie identity — device and display are not spatie subjects; DOC-02 §10).
- **Cache:** spatie caches the permission map; the cache is flushed automatically on any role/permission mutation (including the role editor's permission syncs, §4.3/§6.2).
- **Super Admin handling:** we do **not** use a global `Gate::before` bypass. Instead, the fixed `Super Admin` role is **granted the full permission set explicitly** and kept in sync whenever new permissions are introduced (§7.3). Every capability is therefore visible in `PERMISSIONS.md` and auditable — there is no hidden all-access code path, yet Super Admin still effectively has everything because it literally holds every permission.

Tables in play (spatie defaults): `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`. Users get capabilities **through a role**; IR4 does not grant permissions directly to individual users (§4.4) — all authority flows through role membership so `PERMISSIONS.md` fully describes the system.

---

## 3. Canonical permission catalogue

Permissions are lower-kebab strings, grouped by domain. This is the **complete, authoritative list** — module DOCs reference these names and add none silently. Seeded by `RolePermissionSeeder` (§7).

**Live view & cameras**
- `view-live-cameras`

**Alerts (DOC-07)**
- `acknowledge-alerts`
- `resolve-alerts`

**PPE (DOC-10)**
- `view-ppe`
- `update-ppe-violations`
- `export-ppe-violations`

**Tracking / RFID (DOC-09)**
- `view-tracking`
- `view-worker-identity` (see names/identity on the map & positions; without it, data is anonymized)
- `create-workers`
- `update-workers`
- `delete-workers`
- `create-tags`
- `update-tags`
- `view-zones`
- `create-zones`
- `update-zones`
- `delete-zones`
- `view-entry-exit`
- `view-portable-devices`
- `create-portable-devices`
- `update-portable-devices`
- `create-evacuation`
- `update-evacuation`

**Gas (DOC-11)**
- `view-gas`
- `view-gas-thresholds`
- `update-gas-thresholds`

**Equipment / QR (DOC-13)**
- `view-equipment`
- `create-equipment`
- `update-equipment`
- `delete-equipment`

**HSE incidents & LSR (DOC-14)**
- `view-incidents`
- `create-incidents`
- `update-incidents`
- `view-lsr`
- `create-lsr`
- `update-lsr`

**Permit to Work (DOC-22)**
- `view-permits`
- `create-permits`
- `update-permits`
- `create-permit-gas-tests`
- `view-permit-catalogue`
- `create-permit-catalogue`
- `update-permit-catalogue`
- `delete-permit-catalogue`
- `view-worker-documents`
- `create-worker-documents`
- `update-worker-documents`
- `delete-worker-documents`

**Reports (DOC-15)**
- `view-reports`
- `create-reports`
- `update-reports`
- `view-vehicle-violations`
- `create-vehicle-violations`
- `delete-vehicle-violations`

**Dashboard (DOC-16)**
- `view-dashboard`

**Administration**
- `view-audit-log`
- `view-users`
- `create-users`
- `update-users`
- `view-roles` (list role editor)
- `create-roles`
- `update-roles`
- `delete-roles`
- `view-devices`
- `create-devices`
- `update-devices`
- `delete-devices`
- `view-settings`
- `update-settings`
- `update-alert-settings`

**Enum mirror:** these are also emitted as a `Permission` TS union in `resources/js/types/enums.ts` (DOC-01 §6) so `usePermissions()` (frontend) type-checks against the same list. The exporter includes permissions alongside enums.

---

## 4. Roles

### 4.1 The role model
A role is a runtime row: `name` (unique), `description`, a set of permissions, and two system flags:
- `is_system` (bool) — true only for `Super Admin`; blocks deletion and permission edits.
- `is_read_only` (bool) — marks a role as a read-only profile; when true, the role may hold only `view-*` permissions and any attempt to attach a non-view permission is rejected server-side (§6). Admin-editable on custom roles.

Everything about a non-system role is editable: name, description, permission set, and which users hold it.

### 4.2 Seeded starter roles (editable)
The installer seeds a practical starting set so the platform is usable on day one. **All of these except Super Admin are ordinary editable roles** — an admin may rename them, change their permissions, or delete them entirely.

| Role | `is_system` | `is_read_only` | Intent (starting point) | Login? |
|---|:--:|:--:|---|:--:|
| **Super Admin** | ✅ fixed | — | All permissions, always. Root of trust. | yes |
| **Safety Manager** | — | — | Full operational authority incl. classification, publishing, thresholds, users, audit | yes |
| **SCC Operator** | — | — | Day-to-day command-centre operation: watch, acknowledge, log, run workflows | yes |
| **Project Manager** | — | read-only | Oversight: dashboard KPIs + published reports | yes |
| **Client Representative** | — | read-only | Configurable read-only client window, fully audited (the "Aramco rep" profile) | yes |
| **Field Staff** | — | — | Personnel who only scan equipment QR codes | **no** (public page only) |

The seeded permission sets for the non-system roles are the recommended defaults in §5; because they are editable, §5 is a *starting configuration*, not a locked contract (only the Super Admin row is guaranteed permanent).

### 4.3 Role management (admin, per-route permissions)
The role editor at **`/access/roles`** is gated per action (DOC-03 four-layer enforcement):
- **`view-roles`** — list roles with holder counts and read-only/system badges.
- **`create-roles`** — create a role: name, description, `is_read_only` flag, pick permissions from the catalogue (§3). If `is_read_only`, the picker shows only `view-*` permissions and the server enforces it (§6).
- **`update-roles`** — rename, retune permissions, toggle `is_read_only` — **except** the `Super Admin` row, which is fully locked (all controls disabled; API rejects edits with 403 `FORBIDDEN`).
- **`delete-roles`** — delete a role: allowed only if `is_system=false` and it has zero assigned users (else 409 `CONFLICT` — reassign users first).
- Every create/edit/delete/permission-sync writes a `config_changed` audit row with before/after permission sets.

### 4.4 Assignment model
- Users hold **exactly one role** (single-role model — §7.3). Assignment happens in the user editor (`view-users` / `create-users` / `update-users`).
- **No direct per-user permissions.** All authority flows through the role, so a role's permission set fully describes what its users can do, and `PERMISSIONS.md` is complete.
- `[CONFIRM AT DESIGN]` multi-role per user — deferred; single-role keeps audit and reasoning simple.

---

## 5. Seeded permission matrix (starting configuration)

This is the **default permission set the installer seeds** for each starter role — the recommended day-one configuration, **not a locked contract**. Because all non-system roles are editable (§4.3), an admin can change any cell after install. Only the `Super Admin` column is permanent: it holds **every** permission, always, and cannot be edited.

✅ = granted at seed. Blank = not granted at seed. `cfg` = a `view-*` permission an admin may enable for the read-only **Client Representative** role (default **off**; the read-only guard permits only `view-*`, §6). **Super Admin** implicitly holds everything including permissions not yet invented, so its column is shown as "all". Field Staff holds **no** platform permissions (its access is the unauthenticated QR page), so it has no column.

| Permission | Super Admin | Safety Manager | SCC Operator | Project Manager | Client Rep |
|---|:--:|:--:|:--:|:--:|:--:|
| view-dashboard | all | ✅ | ✅ | ✅ | cfg |
| view-live-cameras | all | ✅ | ✅ |  | cfg |
| view-ppe | all | ✅ | ✅ |  | cfg |
| update-ppe-violations | all | ✅ | ✅ |  |  |
| export-ppe-violations | all | ✅ |  |  |  |
| view-tracking | all | ✅ | ✅ | ✅ (headcount only, DOC-09) | cfg |
| view-worker-identity | all | ✅ | ✅ |  |  |
| create-workers | all | ✅ | ✅ |  |  |
| update-workers | all | ✅ | ✅ |  |  |
| delete-workers | all | ✅ | ✅ |  |  |
| create-tags | all | ✅ | ✅ |  |  |
| update-tags | all | ✅ | ✅ |  |  |
| view-zones | all | ✅ | ✅ |  |  |
| create-zones | all | ✅ | ✅ |  |  |
| update-zones | all | ✅ | ✅ |  |  |
| delete-zones | all | ✅ | ✅ |  |  |
| view-entry-exit | all | ✅ | ✅ |  | cfg |
| view-portable-devices | all | ✅ | ✅ |  |  |
| create-portable-devices | all | ✅ | ✅ |  |  |
| update-portable-devices | all | ✅ | ✅ |  |  |
| create-evacuation | all | ✅ | ✅ |  |  |
| update-evacuation | all | ✅ | ✅ |  |  |
| view-gas | all | ✅ | ✅ |  | cfg |
| view-gas-thresholds | all | ✅ |  |  |  |
| update-gas-thresholds | all | ✅ |  |  |  |
| acknowledge-alerts | all | ✅ | ✅ |  |  |
| resolve-alerts | all | ✅ | ✅ |  |  |
| view-equipment | all | ✅ | ✅ | ✅ | cfg |
| create-equipment | all | ✅ | ✅ |  |  |
| update-equipment | all | ✅ | ✅ |  |  |
| delete-equipment | all | ✅ | ✅ |  |  |
| view-incidents | all | ✅ | ✅ |  | cfg |
| create-incidents | all | ✅ | ✅ |  |  |
| update-incidents | all | ✅ |  |  |  |
| view-lsr | all | ✅ | ✅ |  | cfg |
| create-lsr | all | ✅ | ✅ |  |  |
| update-lsr | all | ✅ | ✅ |  |  |
| view-permits | all | ✅ | ✅ |  | cfg |
| create-permits | all | ✅ | ✅ |  |  |
| update-permits | all | ✅ |  |  |  |
| create-permit-gas-tests | all | ✅ | ✅ |  |  |
| view-worker-documents | all | ✅ | ✅ |  |  |
| create-worker-documents | all | ✅ | ✅ |  |  |
| update-worker-documents | all | ✅ | ✅ |  |  |
| delete-worker-documents | all | ✅ | ✅ |  |  |
| view-reports | all | ✅ | ✅ | ✅ (published only, DOC-15) | cfg |
| create-reports | all | ✅ |  |  |  |
| update-reports | all | ✅ |  |  |  |
| view-vehicle-violations | all | ✅ | ✅ |  |  |
| create-vehicle-violations | all | ✅ | ✅ |  |  |
| delete-vehicle-violations | all | ✅ | ✅ |  |  |
| view-audit-log | all | ✅ |  |  |  |
| view-users | all | ✅ |  |  |  |
| create-users | all | ✅ |  |  |  |
| update-users | all | ✅ |  |  |  |
| view-roles | all |  |  |  |  |
| create-roles | all |  |  |  |  |
| update-roles | all |  |  |  |  |
| delete-roles | all |  |  |  |  |
| view-devices | all | ✅ |  |  |  |
| create-devices | all | ✅ |  |  |  |
| update-devices | all | ✅ |  |  |  |
| delete-devices | all | ✅ |  |  |  |
| view-settings | all | ✅ |  |  |  |
| update-settings | all | ✅ |  |  |  |
| update-alert-settings | all | ✅ |  |  |  |

**Deliberate boundaries in the seeded defaults (an admin may change any of these):**
- **Role-editor permissions (`view-roles` / `create-roles` / `update-roles` / `delete-roles`) seed to Super Admin only.** Editing roles is the most powerful capability (a role editor could grant itself anything), so by default only Super Admin manages roles. An admin may extend any of these to Safety Manager if desired — a documented, audited choice.
- **Operator vs Manager:** the seeded Operator runs operations but cannot **update incidents**, **update reports**, **change gas thresholds**, **manage users/devices/settings**, **export PPE violations**, or **view the audit log**.
- **Operator seeds with `update-lsr` and `resolve-alerts`** — closing an LSR with an action-taken and resolving alerts are operational.
- **Project Manager is read-only** and narrow: dashboard KPIs, headcount total (not identity, not live-map detail — DOC-09), equipment view, and **published** reports (DOC-15).
- **Client Representative** seeds with **nothing** enabled; an admin opts specific `view-*` permissions in (§6). Its read-only flag makes non-view permissions impossible regardless.

---

## 6. Read-only roles (the configurable client-facing pattern)

Any role can be flagged `is_read_only` (§4.1). The seeded **Client Representative** is the canonical example — a client's on-site representative needs a read-only window whose breadth the safety team controls, with every access traceable — but the mechanism is generic and applies to any read-only role an admin creates.

### 6.1 Runtime configuration
- Read-only roles are edited in the same role editor (`/access/roles`, permissions `view-roles` + `update-roles`). When a role has `is_read_only=true`, its permission picker shows **only the `view-*` permissions**.
- Toggling permissions syncs them onto the role immediately (`syncPermissions`) and flushes the spatie cache; all users holding that role inherit the change on their next request (no re-login).
- The Client Representative seeds with an **empty** permission set (sees nothing until an admin enables specific views) — enabling is a conscious act. A recommended starter set is documented, not defaulted.

### 6.2 Enforcement that a write can never be granted
`RoleService::syncPermissions(Role $role, array $permissionNames): void`:
- If `role->is_read_only`, intersect the requested list with the **view-only whitelist** (all permissions whose name starts with `view-`, computed from the catalogue — not a hardcoded per-role list, so it covers future `view-*` permissions automatically).
- Reject (422 `VALIDATION_FAILED`) if any requested permission falls outside that whitelist — so even a buggy or tampered admin screen cannot attach `manage-*`, `close-*`, `trigger-*`, etc. to a read-only role.
- If `role->is_system` (Super Admin), reject any permission change (403 `FORBIDDEN`) — it always holds everything.
- Writes a `config_changed` audit row (before/after permission sets).
Defence in depth: the UI shows only view permissions for read-only roles, **and** the service refuses anything else even if called directly.

### 6.3 Per-request access logging for read-only roles
Every request by a user whose role is `is_read_only` is recorded. Middleware `AuditDataAccess` (authenticated web group, active when the user's role is read-only):
- Writes a `data_access` audit row (user, route/action, key parameters, timestamp, IP) on each meaningful data route (index/show/exports — not asset requests or heartbeats; allow-list in the middleware).
- Satisfies the proposal's requirement that a client representative's activity is fully auditable, and extends the same guarantee to any future read-only profile. DOC-17 owns storage.

### 6.4 Why this stays clean
Read-only is a role flag, not a bespoke ACL. There are no per-user grants and no special-case code per client — one flag drives the view-only whitelist, the picker, and the access logging uniformly.

---

## 7. Seeding & provisioning

### 7.1 `RolePermissionSeeder` (idempotent)
- Creates every permission in §3 (create-or-ignore).
- **Creates/repairs the fixed `Super Admin` role** (`is_system=true`) and **re-syncs it to the full permission set on every run** — so whenever a new module adds permissions, running the seeder (part of deploy/migrate) re-grants them to Super Admin automatically. Super Admin is never left missing a permission.
- Creates the editable starter roles (Safety Manager, SCC Operator, Project Manager, Client Representative, Field Staff) **only if they don't already exist** — it does **not** overwrite their permissions on re-run, because after install they are admin-owned data (an admin's edits must survive re-seeding). First install applies the §5 defaults; subsequent runs leave them alone.
- Marks Project Manager and Client Representative `is_read_only=true`; Client Representative seeds with an empty permission set.
- Self-heals invariants without clobbering admin intent: prunes any non-`view-*` permission that somehow attached to a read-only role, and guarantees Super Admin is complete and locked.

### 7.2 New-permission hook
When a module introduces a permission, it is added to the catalogue and the seeder run (or a dedicated `php artisan ir4:sync-permissions`) ensures: the permission row exists and Super Admin holds it. Other roles do **not** receive new permissions automatically — an admin grants them deliberately via the role editor. This keeps least-privilege intact as the system grows while never breaking Super Admin.

### 7.3 First admin user (Super Admin)
- `php artisan ir4:install` creates the initial **Super Admin** user (prompts name/email/password; dev seed uses a default with `must_change_password=true`, DOC-02). This is the only account before any human logs in; it can then create roles and provision everyone else through the UI.
- The installer guarantees the Super Admin role exists and is assigned to this first user.

### 7.4 User provisioning UI (`view-users` / `create-users` / `update-users`)
- **`/settings/users`** — list, create, edit, deactivate users; assign **exactly one role** (single-role model — §4.4). The role dropdown lists all roles (seeded + admin-created).
- Creating a user: name, email, initial password (or generate), role → sets `must_change_password=true`.
- Editing: change role (audited), toggle `is_active`, trigger password reset (DOC-02 §6.3).
- **Lockout guards (409):** cannot delete/deactivate oneself; **cannot remove the last active user holding the `Super Admin` role** (there must always be at least one active Super Admin); cannot change the last Super Admin's role away from Super Admin.
- Every user/role mutation writes an audit row.
- User provisioning permissions let an admin assign roles but **not edit role definitions** — that requires the role-editor permissions (§4.3). Separating the two means a user-manager can place people into roles without being able to redefine what those roles can do.

---

## 8. Enforcement — four layers

Authorization is enforced defensively at every layer. The frontend layer is **UX only**; the backend layers are the real security boundary.

### 8.1 Route middleware (coarse gate)
Routes are grouped by the permission they require, using spatie's middleware:
```php
Route::middleware(['auth', 'verified.active', 'permission:update-gas-thresholds'])
    ->group(function () { /* threshold routes */ });
```
Coarse gate: blocks whole route groups. Fine-grained record checks still go through policies (§8.2). `verified.active` = our middleware rejecting `is_active=false` mid-session (DOC-02).

### 8.2 Policies (per-model, authoritative)
- **Every model has a Policy** (`WorkerPolicy`, `HseIncidentPolicy`, …) registered in `AuthServiceProvider`.
- Policy methods map to actions and check permissions: `viewAny`, `view`, `create`, `update`, `delete`, plus domain methods (`classify`, `close`, `acknowledge`, `trigger`, `publish`, `review`).
- Controllers call `$this->authorize('classify', $incident)` (or `Gate::authorize`) before any write; FormRequest `authorize()` returns the same check so validation and authorization fail consistently (403).
- Policies encode **record-level** rules where they exist (e.g. a report can only be published while in `generated` status — combines permission + state, DOC-15).

### 8.3 API resource / prop field-stripping (data-level)
Some permissions gate **fields, not endpoints**. The clearest case is `view-worker-identity`:
- `WorkerResource` / tracking position payloads include `name`, `badge_number`, `photo` **only if** the current user has `view-worker-identity`; otherwise those fields are replaced with an anonymized token (`Worker #<id>`), at the **resource level** — not merely hidden in the UI (DOC-09).
- Same principle for the PM headcount-only tracking view and PM published-only reports: the controller/resource restricts the payload by permission, so a crafted request can't retrieve more than the role allows.

### 8.4 Frontend guard (UX only)
- Shared Inertia prop `auth.permissions: string[]` (from `HandleInertiaRequests`, DOC-02 §11) is the single source.
- **`usePermissions()`** hook → `can(permission: Permission): boolean`, typed against the mirrored `Permission` union.
- **`<RequirePermission permission="...">`** component wraps protected page sections; a route-level guard redirects to a "no access" page if the entry permission is missing.
- Nav items (DOC-16 sidebar) render only when the matching `view-*` permission is present.
- **This layer never decides access** — it prevents dead-end clicks and hides what the user can't use. The backend re-checks everything.

---

## 9. Data model additions (this doc)

Beyond spatie's default tables, no new domain tables. We add:
- Add two columns to spatie's `roles` table via a migration: `is_system` (bool, default false) and `is_read_only` (bool, default false) — §4.1. Optional `description` (string, nullable).
- The read-only view-only whitelist is **computed** (permissions whose name starts with `view-`), not a hardcoded constant — so it self-updates as new `view-*` permissions are added (§6.2).
- Role intent lives in `PERMISSIONS.md` (generated) and the `description` field.
- `audit_logs` (DOC-17) receives `config_changed` (role create/edit/delete, permission syncs, user CRUD) and `data_access` (read-only-role per-request) rows — schema owned by DOC-17.

---

## 10. `PERMISSIONS.md` generation (build artifact)

- `php artisan ir4:export-permissions` writes `PERMISSIONS.md` at the repo root from the **live role definitions in the database** (not from hardcoded code): the full permission catalogue (grouped), and a role×permission matrix reflecting current roles including any admin-created ones, with Super Admin shown as all-access and read-only roles flagged.
- Because roles are runtime-editable, `PERMISSIONS.md` is regenerated on demand / in deploy and reflects the current install rather than a compile-time constant. (It is not a CI drift gate for the editable roles — those legitimately change; the CI check instead asserts that Super Admin holds every permission and every permission is exported.)
- This is the single artifact reviewers (including the client) read to understand who can currently do what.

---

## 11. Real-life scenarios

- **New operator onboarded:** an admin creates the user with role SCC Operator → temp password handed over → operator logs in, forced to change password → sidebar shows only their permitted modules → they can acknowledge alerts and log incidents but the "Classify" button and Thresholds screen are absent (frontend) and would 403 if hit directly (backend).
- **Admin creates a custom role:** a `create-roles` holder opens `/access/roles`, creates "Night Supervisor" with `view-*` + `acknowledge-alerts` + `create-incidents`, saves → assignable immediately in the user editor; the change is audited; no deploy.
- **Client rep arrives for an audit week:** an admin edits the read-only "Client Representative" role, enables `view-dashboard`, `view-reports`, `view-incidents`, `view-lsr` → the rep logs in and can read those, nothing else; every page they open writes a `data_access` audit row; when the week ends the admin clears those permissions and the rep sees nothing.
- **Attempted privilege bug:** a malformed request tries to add `update-reports` to a read-only role → `RoleService::syncPermissions` rejects it (not a `view-*` permission) with 422, audit unchanged. A request to edit the Super Admin role's permissions → 403.
- **Lockout prevented:** an admin tries to change the only Super Admin's role to Operator → 409 (there must always be an active Super Admin).
- **PM checks status:** Project Manager opens the dashboard → sees KPI cards + total headcount + published reports; the live camera wall, gas panels, and the identity-level map are not in their nav and 403 on direct access.
- **Threshold change:** only roles holding `update-gas-thresholds` can change threshold values; the editor page is visible with `view-gas-thresholds`. Changing a value writes a `config_changed` audit row with before/after (DOC-11 + DOC-17).

---

## 12. Tests (this doc's slice of DOC-21)

- Seeder produces the full §3 permission set, a locked `Super Admin` holding **every** permission, and the editable starter roles at their §5 defaults on first install.
- **Super Admin is immutable:** editing its permissions → 403; deleting it → 403/409; removing the last active Super Admin user → 409; adding a new permission then running `ir4:sync-permissions` re-grants it to Super Admin.
- Re-running the seeder does **not** overwrite an admin-edited starter role's permissions (admin intent survives).
- For each permission, a user **with** it passes the guarded route/policy and a user **without** it gets 403 — parametrised across the catalogue.
- **Custom role:** creating a role via `/settings/roles` makes it assignable and enforced; a user in it gets exactly its permissions; deleting a role with assigned users → 409.
- `view-worker-identity`: resource returns real names with the permission, anonymized tokens without — proven at the **API/resource** level, not just UI.
- PM `view-tracking` returns headcount-only (no identity, no positions); PM `view-reports` returns only published reports.
- **Read-only role guard:** enabling a `view-*` permission grants read access immediately (cache flush works); attempting a non-view permission via `syncPermissions` on a read-only role → 422; every request by a read-only-role user writes a `data_access` row; a write attempt by such a user → 403.
- User provisioning vs role-editor permissions separation: a user-manager can assign roles but cannot edit role definitions (403 on the role editor).
- No `Gate::before` super-admin bypass exists (Super Admin's access comes only from explicitly holding every permission).
- `ir4:export-permissions` produces a `PERMISSIONS.md` reflecting current DB roles; Super Admin shows all-access.
- Frontend: `usePermissions().can()` matches server truth for a sampled user; nav hides denied items; `RequirePermission` blocks a section the user lacks.

---

## 13. Open decisions logged

| # | Decision | Default | Confirm in |
|---|---|---|---|
| 1 | Single-role vs multi-role per user | single-role | this doc |
| 2 | Who holds role-editor permissions by default | Super Admin only | this doc / DOC-18 |
| 3 | Client Representative recommended starter permissions | empty (enable consciously) | DOC-18 |
| 4 | Mandatory 2FA for user-provisioning / role-editor holders | off (from DOC-02) | DOC-18 |

The role system is fully dynamic: admins compose any number of roles from the permission catalogue, flag any of them read-only (inheriting the view-only guard + access logging), and assign one role per user. The lone fixed point is `Super Admin` — always all-access, never editable, never deletable, always held by at least one active user — which guarantees the system can never be locked out of its own administration.

---

### Next document
**DOC-04 — Workers / Employees:** the tracked-personnel domain (distinct from Users), its table and lifecycle, the `view-worker-identity` field-stripping this doc introduced, and the relationships every tracking/HSE/evacuation feature hangs off.