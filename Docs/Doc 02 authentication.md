# DOC-02 — Authentication

> **Depends on:** DOC-01 (stack, hybrid surfaces, conventions, settings). **Feeds:** DOC-03 (RBAC layers on top of authenticated identity), DOC-08 (device auth, introduced here, specified there).
>
> **Scope:** how humans and machines prove identity to the platform. Human auth (Fortify, session lifecycle, password/lockout policy, idle timeout, first-login flow), the authenticated kiosk/display view, the device-auth path (`auth.device`) at the contract level, and the frontend auth shell. **Out of scope:** *what* an identity is allowed to do — that is authorization (DOC-03).

---

## 1. Purpose & the two identity types

The platform authenticates two fundamentally different callers. Keep them separate at every layer.

| | **Human users** | **Field devices** |
|---|---|---|
| Who | operators, safety managers, PM, Aramco rep | RFID readers, AI cameras, gas/CO₂/env gateways, edge units |
| Proves identity with | email + password (Fortify session) | static bearer token (`X-Device-Token`) |
| Guard | `web` (session) | `auth.device` (custom, DOC-08) |
| Routes | `routes/web.php` (Inertia) | `routes/api.php` (`/api/ingest/*`) |
| Session concept | yes — cookie, timeout, CSRF | none — stateless, per-request token |
| Covered by | this doc | §7 here (contract) + DOC-08 (full) |

Neither is a "Worker" (DOC-04). Workers are tracked personnel and never authenticate — they don't log in and hold no credentials.

---

## 2. On-premise auth posture (what we keep and cut)

The React starter kit ships **Laravel Fortify** with a broad feature set. On an air-gapped LAN box, several features are inert or actively undesirable. Configure `config/fortify.php` and `config/features` as follows:

**Keep (enabled):**
- Username/password login (`Features::authentication()`), scoped to email + password.
- Logout.
- Password update (profile) — for users changing their own password.
- Two-factor authentication (TOTP) — **optional, app-based (Google Authenticator / Aegis), no SMS/email.** TOTP is offline and strengthens Safety-Manager/admin accounts. Default **off**, enableable per user (§8). `[CONFIRM AT DESIGN]` whether 2FA is mandatory for `manage-users` holders.

**Cut (disabled / removed):**
- **Registration** — no self-registration, ever. Users are provisioned by a Safety Manager (DOC-03). Remove the register route and Fortify's `registerView`/`createUser`.
- **Password reset via email** — the box has no reliable outbound mail; a public "forgot password" email flow is both broken and a footgun. Replace with **admin-initiated reset** (§6.3): a Safety Manager sets a temporary password and forces a change on next login. Remove `resetPasswordView`/`requestPasswordResetLink` routes.
- **Email verification** — no email; disable `emailVerification`. Accounts are trusted at creation.
- **Social / OAuth / SSO / passkeys / "code via email"** — all require internet or email. Remove entirely.

Net result: a local login form, optional TOTP, admin-managed accounts. No route in the app can create or recover an account without an authenticated Safety Manager.

---

## 3. `users` table (auth-relevant columns)

Full user/role modeling is DOC-03; the auth-relevant shape:

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->boolean('is_active')->default(true);            // deactivated users cannot log in
    $table->boolean('must_change_password')->default(true); // forces change on first / post-reset login
    $table->timestamp('password_changed_at')->nullable();
    $table->timestamp('last_login_at')->nullable();
    $table->string('last_login_ip')->nullable();
    // 2FA (Fortify columns, nullable)
    $table->text('two_factor_secret')->nullable();
    $table->text('two_factor_recovery_codes')->nullable();
    $table->timestamp('two_factor_confirmed_at')->nullable();
    $table->rememberToken();
    $table->timestamps();
    $table->softDeletes();   // deactivation is preferred over deletion; soft-delete retains audit linkage
});
```

Notes: `is_active=false` blocks login while preserving the account for audit history (a deactivated user's past `created_by`/`classified_by` links stay valid). Soft-deleting a user must never orphan audit rows — those FKs are `nullOnDelete` (DOC-01 §5.1) but we prefer deactivation over deletion.

---

## 4. Session model & configuration

- **Guard:** Fortify's default `web` session guard (cookie-based) for surface A. Inertia rides the same session; CSRF is handled by the kit's Inertia + Sanctum-cookie setup for same-origin requests.
- **Driver:** `SESSION_DRIVER=redis` (preferred) or `database`. Not `file` (kiosk + multiple workstation tabs share cleanly via Redis).
- **Cookie:** `secure` when served over HTTPS (recommended even on LAN with a self-signed/internal CA), `http_only`, `same_site=lax`.
- **Absolute lifetime:** `config/session.php` `lifetime` = the idle window (see §5). We also enforce an **idle timeout** independently on the client (§5.2) because `lifetime` alone is a sliding cookie, not a true inactivity cutoff.

---

## 5. Session timeout (idle) — server + client

The proposal requires session timeout. We implement it in two cooperating layers, driven by the setting `session_timeout_minutes` (default **15**, DOC-18).

### 5.1 Server enforcement
Middleware `EnforceIdleTimeout` (aliased, applied to the authenticated web group):
- On each authenticated request, compare `now()` to `session('last_activity_at')`.
- If the gap exceeds `session_timeout_minutes` → invalidate the session, log the user out, write a `logout` audit row with reason `idle_timeout`, and redirect to `/login?timeout=1`.
- Otherwise refresh `last_activity_at = now()`.
This guarantees timeout even if the client clock is wrong or JS is disabled.

### 5.2 Client enforcement (UX)
Hook `useIdleLogout()` (mounted in the authenticated layout):
- Tracks activity events (`mousemove`, `keydown`, `click`, `scroll`, `touchstart`), throttled.
- Shows a **warning modal 60 seconds before** expiry ("You'll be signed out for inactivity in 0:59 — Stay signed in").
- "Stay signed in" pings a lightweight `POST /session/heartbeat` (authenticated, refreshes `last_activity_at`).
- On expiry, redirects to `/login?timeout=1`.

### 5.3 The display/kiosk view (authenticated extension of the operator session)

The 55″ display is **not** a separate identity — it is simply a **full-screen extension view of a logged-in operator's session**, rendered on the SCC workstation. There is no display token, no `auth.display` guard, and no special-casing out of authentication: **a user must be logged in to view `/display`, exactly like any other operator screen.**

- `/display` lives in the normal authenticated `web` group behind Fortify session + RBAC. Reaching it requires an authenticated user with `view-dashboard` (DOC-03). Unauthenticated access redirects to `/login` like everywhere else.
- It renders through `DisplayLayout` (kiosk chrome: fullscreen, no sidebar, no controls — DOC-16), but it is the same session, same cookie, same user as the workstation the operator signed into. Think of it as a "present mode" of the dashboard, not a distinct account.
- Because the SCC workstation is manned and the display is meant to run continuously during a shift, **idle timeout must not silently blank the wall to a login screen mid-shift.** We reconcile "must stay logged in" with "must not drop out unattended" by keeping the session **alive while the display view is actually open and streaming**, rather than by exempting it from auth:
  - While `/display` is mounted, its live data connection (the Reverb subscription + the polling fallback, DOC-08) counts as activity: each successful poll/reconnect calls `POST /session/heartbeat`, so an open, working display keeps its own session fresh. An operator watching the wall is, by definition, an active session.
  - This is a deliberate, documented choice (setting `display.keep_session_alive=true`, default on). It applies **only** while the display route is open in that session; close the display and normal idle timeout resumes. It never disables authentication — if the user is logged out by an admin, their sessions invalidated, or their account deactivated, the display drops to `/login` on its next request/heartbeat like any other screen.
  - `[CONFIRM AT DESIGN]` whether to additionally require periodic human presence confirmation for the wall (e.g. a once-per-shift re-auth). Default: not required, since the workstation is manned and physically secured.
- The display view carries the **operator's own permissions** — it shows only what that user is allowed to see. There is no elevated or reduced "display" permission set; a PM opening `/display` sees the KPI-limited variant (DOC-16), an operator sees the full wall.
- Session end still ends the display: logout, admin session invalidation, or account deactivation all bounce `/display` to the login screen on the next request. Nothing about the kiosk bypasses that.

---

## 6. Login lifecycle & endpoints (surface A)

### 6.1 Routes
| Method | Path | Handler | Notes |
|---|---|---|---|
| GET | `/login` | Fortify login view → `pages/auth/login.tsx` | shows `?timeout=1` / `?locked=1` banners |
| POST | `/login` | Fortify `AuthenticatedSessionController` (extended) | throttled (§7 below) |
| POST | `/logout` | Fortify logout | writes `logout` audit |
| POST | `/session/heartbeat` | `SessionController@heartbeat` | auth’d; refreshes activity |
| GET/POST | `/user/two-factor-*` | Fortify 2FA (kept) | enable/confirm/disable, recovery codes |
| GET | `/force-password` | `PasswordController@edit` | shown when `must_change_password` |
| POST | `/force-password` | `PasswordController@update` | clears the flag |

Registration, password-reset-by-email, and email-verification routes from Fortify are **removed** (§2).

### 6.2 Custom login pipeline
Extend Fortify's authentication to enforce our rules, in order:
1. **Rate-limit** (§7.1) — before credential check.
2. **Credential check** — email + password (Fortify default, bcrypt/argon).
3. **Active check** — reject `is_active=false` with a generic "invalid credentials or inactive account" message (don't reveal which). Audit `login_failed` reason `inactive`.
4. **2FA challenge** — if `two_factor_confirmed_at` set, redirect to the TOTP challenge before establishing the session.
5. **On success:** regenerate session id, set `last_activity_at`, update `last_login_at`/`last_login_ip`, write a `login` audit row.
6. **Force-change gate:** if `must_change_password=true`, every authenticated request (except `/force-password` and `/logout`) redirects to `/force-password` until a new password is set.

### 6.3 First login & admin-initiated reset
- **Account creation** (DOC-03, Safety Manager): the manager sets an initial password (or the system generates one shown once); the new user row has `must_change_password=true`.
- **Reset:** a Safety Manager triggers `POST /settings/users/{user}/reset-password` → sets a new temporary password (generated, shown once to the manager to hand over) and `must_change_password=true`, and invalidates the user's existing sessions. No email involved. Audited `config_changed` (target user) + forces the change on next login.
- There is **no self-service password reset** without logging in. This is an intentional on-prem trade-off; it is documented for operations (DOC-20) so a locked-out sole admin scenario is handled by a documented Artisan fallback: `php artisan ir4:user:reset {email}` (server console access = physical/SSH access = trusted).

---

## 7. Credential hardening

### 7.1 Login throttling & lockout
- **Throttle:** max **5 attempts/minute** per (email + IP) — Fortify's `LoginRateLimiter`, tuned. Exceeding → 429 with a retry hint.
- **Lockout:** **10 failed attempts** → account locked **15 minutes** (`locked_until` derived; simplest: a cache/DB counter keyed by email). During lockout, `/login` shows `?locked=1` and rejects even correct credentials until the window passes. Each failed attempt writes a `login_failed` audit row (reason `bad_credentials` | `inactive` | `locked`).
- Both thresholds are `[CONFIRM AT DESIGN]` values surfaced as settings (`auth.login_max_per_min=5`, `auth.lockout_threshold=10`, `auth.lockout_minutes=15`).

### 7.2 Password policy
- Minimum **12 characters**; must not equal the previous password; standard Laravel `Password::min(12)` rules (mixed case + number recommended `[CONFIRM AT DESIGN]`, uncompromised/`->uncompromised()` is **disabled** because it calls an external API — on-prem rule).
- `password_changed_at` recorded on every change. Optional expiry (`auth.password_max_age_days`) **off by default**.
- Hashing: framework default (bcrypt or argon2id via `config/hashing.php`).

### 7.3 Session security
- Session id regenerated on login and on privilege-relevant changes.
- Logout invalidates + regenerates.
- Admin reset invalidates the target user's other sessions (`Auth::logoutOtherDevices` equivalent or session table purge).

---

## 8. Two-factor (optional, offline)

- App-based TOTP only (Fortify's built-in), enabled per user from their profile (`/settings/profile` → Security). QR provisioning is rendered locally (no external QR service).
- Recovery codes generated and shown once; stored encrypted (Fortify columns).
- A Safety Manager can **require** 2FA for privileged roles via setting `auth.require_2fa_for_admins` `[CONFIRM AT DESIGN]`; when on, a `manage-users` holder without confirmed 2FA is routed to set it up before accessing anything else.
- No SMS, no email OTP — those need connectivity.

---

## 9. Device authentication path (contract here; full spec DOC-08)

Introduced in auth because it *is* authentication — for machines.

- **Guard/middleware:** `auth.device`. Field hardware sends header `X-Device-Token: <token>`.
- **Resolution:** the token is looked up by hash against `devices.api_token_hash` (DOC-05). A match resolves the calling **Device** (and its parent asset). The device is attached to the request (`$request->device()`).
- **Rejections:** unknown/absent token → `401 UNAUTHENTICATED`; device `status = retired` → `403 FORBIDDEN`.
- **No session, no cookies, no CSRF** — stateless, per request, over the LAN only.
- **Token issuance/rotation:** a plaintext token is generated once at device registration and shown a single time (`POST /settings/devices/{device}/regenerate-token`, permission `manage-devices`); only its hash is stored. Rotating invalidates the old token immediately.
- Scope: `auth.device` grants access **only** to `/api/ingest/*` and `/api/devices/{id}/heartbeat`. It can never reach operator or admin routes.

Everything about batching, idempotency, rate limiting, and per-event outcomes lives in DOC-08; DOC-02 only fixes *how the caller is identified*.

---

## 10. Authorization boundary (handoff to DOC-03)

Authentication answers "who are you"; it stops there. Once a user is authenticated:
- Their **roles/permissions** (spatie) determine access — enforced by route middleware, policies, resource field-stripping, and the frontend `usePermissions()` guard. All of that is **DOC-03**.
- The authenticated user object is available to controllers/services (`auth()->user()`), to the `CreatedByObserver` (DOC-01 §5.4), and to the audit layer (DOC-17).
- The **display view** is just a logged-in user in kiosk chrome — it holds that user's own spatie roles/permissions, nothing special. Only the **device identity** is a non-user caller, and its capability is fixed by its guard (ingest only).

---

## 11. Frontend auth shell (React / Inertia)

- **`pages/auth/login.tsx`** — shadcn form (email, password, optional 2FA step), inline error display via Inertia's error bag, banners for `?timeout=1` / `?locked=1`. No "register" or "forgot password" links.
- **`pages/auth/force-password.tsx`** — shown when `must_change_password`; blocks navigation until submitted.
- **`pages/auth/two-factor-challenge.tsx`** — TOTP / recovery-code entry.
- **`layouts/AppLayout.tsx`** (authenticated shell) — mounts `useIdleLogout()`, renders the sidebar (DOC-16), and exposes the shared auth/permission context (`usePage().props.auth.user` + permissions from DOC-03).
- **`layouts/DisplayLayout.tsx`** — the kiosk shell for `/display`: fullscreen, no sidebar, no user menu, no controls. It is an authenticated layout (same session as the operator); it keeps the session alive via the live-data heartbeat (§5.3) but does **not** disable auth. Unauthenticated access falls through to `/login` like any page.
- **`hooks/useAuth.ts`** — thin accessor over Inertia's shared `auth` prop (`user`, `isAuthenticated`); never stores tokens in `localStorage` (Inertia uses the session cookie; DOC-01 forbids browser storage for auth).
- **Shared props:** a middleware `HandleInertiaRequests` shares `auth.user` (id, name, email, roles, permissions, `must_change_password`, 2FA state) on every Inertia response so pages and guards read a single source.

---

## 12. Audit touchpoints (defined here, implemented in DOC-17)

Authentication emits these audit events (append-only log): `login`, `logout` (with reason: user / idle_timeout), `login_failed` (with reason: bad_credentials / inactive / locked), plus `config_changed` for admin-initiated password resets and 2FA enable/disable. IP and user-agent are captured on each. DOC-17 owns the storage and viewer.

---

## 13. Tests (this doc's slice of DOC-21)

Feature tests to ship with DOC-02:
- login success sets session + `last_login_at` + `login` audit; wrong password → `login_failed` audit, no session.
- inactive user cannot log in (generic message); locked account rejects correct credentials during the window.
- throttle at 5/min returns 429; lockout at 10 fails for 15 min.
- `must_change_password` forces `/force-password` and blocks all other routes until changed.
- idle timeout: server logs out after `session_timeout_minutes`; heartbeat extends it; warning modal fires client-side (component test).
- registration / email-reset / verification routes **do not exist** (404) — proves on-prem cuts.
- 2FA challenge required when confirmed; recovery code works; disabling requires re-auth.
- **display view:** requires an authenticated user with `view-dashboard` (unauthenticated → redirect to `/login`); shows the permission-appropriate variant for the logged-in user; while open, its live-data heartbeat keeps the session fresh so the wall doesn't drop mid-shift; but admin logout / session invalidation / account deactivation bounces it to `/login` on the next request.
- **device path:** valid `X-Device-Token` resolves the device on an ingest route; unknown → 401; retired device → 403; device token rejected on any web/operator route.
- admin reset: sets temp password, forces change, invalidates target's sessions, writes audit; no email dispatched.

---

## 14. Open decisions logged

| # | Decision | Default | Confirm in |
|---|---|---|---|
| 1 | Mandatory 2FA for admins | optional (off) | this doc / DOC-18 |
| 2 | Password complexity beyond length | length 12 + not-reused only | DOC-18 |
| 3 | Display keeps session alive while open | on (`display.keep_session_alive=true`) | DOC-16/18 |
| 4 | Lockout thresholds as settings | 5/min, 10 fails, 15 min | DOC-18 |

---

### Next document
**DOC-03 — Dynamic Roles & Permissions (RBAC):** the spatie model, the canonical permission list, the five seeded roles + matrix, the runtime-configurable Aramco-representative view-only whitelist, enforcement across route/policy/resource/frontend layers, and `PERMISSIONS.md` generation — all keyed to the authenticated identity established here.