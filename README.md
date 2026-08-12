# IR4 Platform

On-premise safety command-centre.

| Path | Contents |
|------|----------|
| `Server/` | Laravel + Inertia UI, device API, public QR pages |
| `Server/scripts/` | Ordered SCC scripts `01`…`05` + systemd / MediaMTX |
| `Mobile/` | Android Flutter app |
| `EdgeCompute/` | Orin J4012 gas + RFID agents |
| `Docs/` | Design specs (DOC-01 … DOC-22) |
| **`SCC-SETUP.md`** | **Fresh SCC install runbook** |

## Handbooks

| Topic | Document |
|-------|----------|
| Fresh SCC install | [`SCC-SETUP.md`](SCC-SETUP.md) |
| Full ops / acceptance | [`Docs/Doc 20 deployment runbook.md`](Docs/Doc%2020%20deployment%20runbook.md) |
| Retention / backup | [`Docs/Doc 19 retention backup.md`](Docs/Doc%2019%20retention%20backup.md) |
| Local / Hostinger | [`Server/README.md`](Server/README.md) |
| Edge Orin | [`EdgeCompute/README.md`](EdgeCompute/README.md) |

---

## Quick start (development)

```bash
cd Server && composer setup && php artisan serve --host=0.0.0.0 --port=8000
```

---

## On-prem SCC (ordered scripts)

See **[`SCC-SETUP.md`](SCC-SETUP.md)**.

```bash
# 01 — first box (copy from monorepo once)
bash ~/Desktop/01-setup.sh

cd /data2/laravel/IR4-Project
bash scripts/02-install-systemd-units.sh
sudo bash scripts/03-ensure-mediamtx.sh
BACKUP_ARCHIVE_PASSWORD_OVERRIDE='…' bash scripts/04-setup-backup.sh

# 05 — day-2
bash scripts/05-update.sh
```

---

## Production (Hostinger)

See [`Server/README.md`](Server/README.md).
