# IR4 Phase-3 commissioning sign-off (DOC-20 §10)

| Field | Value |
|---|---|
| Site / project | |
| Server serial / asset tag | |
| Hostname / LAN IP | |
| Operator CIDR | |
| Device CIDR | |
| TLS cert fingerprint | |
| App version / git SHA | |
| Commissioning engineer | |
| Client safety lead | |
| Date | |

## Infrastructure

- [ ] Server prepped (OS, packages, NTP, LUKS on data+backup volumes, disk sized per DOC-19).
- [ ] App deployed, migrated, seeded (permissions + Super Admin + settings defaults; **no** seeded hardware/zones).
- [ ] Supervisor: reverb / queues(default,ingest,reports) / scheduler auto-restart; php-fpm via systemd.
- [ ] Nginx TLS up; LAN segmentation verified (device path device-only, public QR LAN-only, no internet egress).
- [ ] DB grants: `ir4_app` INSERT/SELECT-only on `audit_logs`; separate `ir4_backup` / `ir4_restore` / `ir4_wipe`.

## Hardware registration

- [ ] Assets/cameras/devices registered with references + tokens.
- [ ] Zones created; every reader bound; gate reader bound; map placements set.
- [ ] Heartbeats green; system-health widget all-green.
- [ ] ZT411 test label + bulk run (`EQUIPMENT_PRINTER_*`).

## Functional smoke

- [ ] Tag read → map + headcount; gate in/out toggles presence.
- [ ] PPE violation → toast + record; fall → alert suggests incident.
- [ ] Gas live excursion → alarm/ack/hysteresis; **backfill raises no alarm**.
- [ ] Environmental reading → weather widget.
- [ ] Equipment register + label + mobile checkout/return.
- [ ] Incident + LSR create/classify/close with mandatory action.
- [ ] Evacuation trigger → account → close → PDF.
- [ ] Weekly report generate → publish; completeness note on outage.

## Safety-critical confirmations

- [ ] Gas thresholds confirmed by safety lead.
- [ ] Tracking windows, session/lockout, retention, week boundary confirmed.

## Data lifecycle

- [ ] Rollups building; pruning dry-run allow-list only.
- [ ] Daily backup runs; **restore drill passed** on `ir4_restore`.
- [ ] `ir4:export-all` verifiable (dry/live as agreed).

## Access & audit

- [ ] Roles configured; Super Admin present; read-only role writes `data_access`.
- [ ] Audit log records logins/config/publishes; append-only verified.

## Approvals

| Role | Name | Signature | Date |
|---|---|---|---|
| Commissioning engineer | | | |
| Client safety lead | | | |
| Client IT / ops | | | |

Exceptions / deviations:
