# Rules.md — Boundaries for AI (and humans)

> Hard constraints while building IR4. If this file conflicts with `Docs/`, **Docs win** — update this file. Use skills in `.cursor/skills/` / `.claude/skills/` instead of inventing procedures.

---

## Always do

1. Read the owning `Docs/Doc XX …md` before implementing a module.
2. Pick a data-origin archetype first: ingest/telemetry · mixed · all-manual.
3. Keep controllers thin: FormRequest → policy → service → Inertia/JsonResource.
4. One FormRequest per write; whitelist only human-writable fields.
5. Check **permissions**, never role names. Wire all four RBAC layers for new capabilities.
6. Strip worker identity at every serialization boundary without `view-worker-identity`.
7. Raise alerts only via `AlertService`; never from a user “create alert” endpoint.
8. Prefill incident/LSR from alerts — **persist only on user submit**.
9. Store timestamps in UTC; soft-delete compliance/evidence tables.
10. Bundle all fonts/assets locally (Vite). Keep the app air-gapped.
11. Add Pest tests for every write: happy path + validation + authorization.
12. After enum changes: keep PHP backed enums and `Server/resources/js/types/enums.ts` in sync.
13. Follow `Phases.md` — finish the current phase before starting the next.
14. When coding starts, update `Memory.md` after each meaningful chunk of work.

---

## Never do

1. **Never** add `site_id`, tenant columns, or multi-tenancy.
2. **Never** call the public internet from shipped code (no CDN, analytics, cloud SDKs, cloud Pusher, external mail APIs).
3. **Never** build the operator UI as a REST SPA (no axios/fetch for operator CRUD — use Inertia forms).
4. **Never** let users write raw telemetry (readings, PPE detections). Entry/exit correction = **new** row only.
5. **Never** auto-insert `hse_incidents` or `lsr_violations` from alerts, ingest, or jobs.
6. **Never** put `worker_id` on `ppe_violations` or invent PPE identity.
7. **Never** regenerate `equipment.qr_token` after create.
8. **Never** edit a migration that has already been run — add a new one.
9. **Never** use `Gate::before` for Super Admin — grant the full permission catalogue explicitly.
10. **Never** hardcode pole/camera/device counts or production zone layouts.
11. **Never** expose raw storage paths — signed URLs only (≈15 min).
12. **Never** invent behavior missing from `Docs/` — Docs 01–21 are the full authoritative set; if code and Docs disagree, Docs win.
13. **Never** skip identity stripping on Reverb payloads, exports, or embedded props.
14. **Never** commit `.env`, tokens, or plaintext device secrets.

---

## Audit boundaries (DOC-17)

- `audit_logs` is append-only: no app update/delete path, no soft delete, and no retention pruning.
- Mask passwords, tokens, 2FA secrets, and other sensitive values before persisting diffs.
- Audit authentication plus configuration/security changes, including users, roles/permissions, settings/thresholds, hardware, zones/bindings, report settings, and first-class publish/acknowledge/export actions.
- For `is_read_only` roles, log one `data_access` event per meaningful index/show/export request; exclude assets, heartbeats, and high-volume ingest.
- Audit rows are path ② machinery only; users may view/export them with `view-audit-log`, never create or mutate them.

## Settings boundaries (DOC-18)

- Runtime tunables live in the `settings` table via `SettingsRegistry` / `SettingsService` only — no arbitrary keys.
- Deploy-fixed values (DB credentials, Reverb, printer IP/port, backup disk paths/keys) stay in `.env` / `config/*`.
- Per-key edit permissions; sensitive keys require a server-validated confirmation flag.
- Every successful `set` audits `config_changed` with old→new.

## Retention / backup boundaries (DOC-19)

- Prune only the explicit raw allow-list (`tag_readings`, `gas_readings`, `environmental_readings`); never compliance tables.
- Gas, environmental, and tag raw rows prune after the retention window (no sensor rollup tables; no rollup gate); tags likewise have no rollup in v1.
- Daily backups are encrypted on the separate `backups` disk; Spatie events raise/resolve `system` alerts (no mail).

## Deploy & test boundaries (DOC-20 / DOC-21)

- Deploy/ops details live in DOC-20 (LAN fences, Supervisor, DB grants, commissioning checklist) — do not invent alternate process models.
- DOC-21 invariant guards and CI greps are blocking; never weaken append-only audit, PPE anonymity, no-auto-incident, or on-prem/standalone greps to make a test pass.

---

## Libraries — use

| Use | Package / tool |
|---|---|
| Backend | Laravel 13.9+, PHP 8.4+ |
| UI | React 19, TypeScript, Inertia 3, Tailwind 4, shadcn/ui |
| Auth | Fortify + custom `auth.device` |
| Realtime | Laravel Reverb (self-hosted) |
| Queue/cache | Redis |
| RBAC | `spatie/laravel-permission` |
| PDF / QR | `barryvdh/laravel-dompdf`, `endroid/qr-code` |
| Charts | recharts |
| Dates / validation UX | date-fns, zod |
| Tests / quality | Pest, Larastan, Pint |

---

## Libraries — avoid

| Avoid | Why |
|---|---|
| Cloud Pusher / Ably / similar | On-prem rule — Reverb only |
| Next.js / separate SPA API client for operators | Architecture is Inertia |
| Prisma / non-Eloquent ORMs | Laravel Eloquent is the stack |
| Google Fonts / CDN script tags | Air-gapped — bundle via Vite |
| External “have I been pwned” / SaaS auth | No outbound HTTP |
| `any` in module TypeScript | Strict mode; CI should fail |
| Role-name checks (`hasRole('Admin')`) | Use permissions |
| Mass assignment from `$request->all()` | FormRequest whitelist only |

---

## Error handling

### Surfaces B & C (device + public)
```json
{ "error": { "code": "VALIDATION_FAILED", "message": "…", "details": {} } }
```
Codes: `VALIDATION_FAILED` 422 · `UNAUTHENTICATED` 401 · `FORBIDDEN` 403 · `NOT_FOUND` 404 · `CONFLICT` 409 · `RATE_LIMITED` 429 · `INGEST_PARTIAL` (inside 202 body).

### Surface A (operator)
- Validation → Inertia error bag (standard FormRequest).
- Authorization → 403 via policy.
- Domain conflicts (e.g. offboard while present) → 409 with clear message.

### Ingest
- Never all-or-nothing: return **202** with per-event `accepted` / `duplicates` / `rejected[]`.
- Unknown `*_ref` → reject that event with a clear code, accept the rest.

### Decision fields
`action_taken`, `corrective_action`, closure/review notes: `required|string|min:10|max:5000`.

---

## Code style (short)

- Models: `final`, `casts()` method, enums + booleans cast, both relation sides declared.
- `$guarded = ['id']` — writes through FormRequests/services.
- Tables plural snake_case; routes kebab-case; services `*Service`.
- Controllers in `Web/` / `Api/Ingest/` / `Public/` only for their surface.
- Frontend: match existing shadcn patterns; see `Design.md` for theme tokens.

---

## When unsure

1. Open the module Doc.
2. Check `Architecture.md` for where the file goes.
3. Check `Phases.md` — are you in the right phase?
4. Use the matching skill (`scaffold-domain-module`, `add-permission`, `raise-alert`, …).
5. Implement the Doc’s `[CONFIRM AT DESIGN]` **default** — do not invent a third option.
6. Record the decision in `Memory.md`.
