# SCC fresh setup runbook

Field guide for a **new** Dell R360 / Lerd install. Design rules: `Docs/` DOC-01…22. Full ops depth: [DOC-20](Docs/Doc%2020%20deployment%20runbook.md).

| Assumption | Value |
|------------|--------|
| App root | `/data2/laravel/IR4-Project` (flattened `Server/`) |
| Deploy user | non-root (e.g. `scc1` / `scc2`) |
| Site URL | `https://ir4-project.test` |
| Backup volume | `/data/ir4-backups` (separate from live data) |

Ordered scripts: **`Server/scripts/01-*.sh` … `05-*.sh`** (on SCC after install: `/data2/laravel/IR4-Project/scripts/`).

```bash
# First box — copy from monorepo before the app tree exists:
scp Server/scripts/01-setup.sh scc1@192.168.x.x:~/Desktop/
```

After **01**, run **02–05** from the app root.

---

## Checklist (ordered)

| Order | Action |
|-------|--------|
| A | OS baseline (Ubuntu, Docker, disks) — manual |
| B | Lerd install + `/data` in mounts — manual |
| **01** | `bash ~/Desktop/01-setup.sh` |
| C | Edit `.env` |
| D | `migrate` / `db:seed` / `ir4:install` |
| **02** | `bash scripts/02-install-systemd-units.sh` |
| **03** | `sudo bash scripts/03-ensure-mediamtx.sh` + `ir4:sync-camera-streams` |
| **04** | `bash scripts/04-setup-backup.sh` |
| E | `lerd secure` + workstation CA |
| F | Reboot proof |
| G | Smoke — [DOC-20 §10](Docs/Doc%2020%20deployment%20runbook.md) |
| **05** | Day-2: `bash scripts/05-update.sh` |

---

## A. OS baseline

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y docker.io git rsync unzip curl
sudo systemctl enable --now docker
sudo usermod -aG docker "$USER"   # re-login after
```

---

## B. Lerd

Install per [lerd.sh](https://lerd.sh/getting-started/installation). In `~/.config/lerd/config.yaml` mounts include `- /data`, then `lerd restart`.

---

## 01 — Application tree

```bash
bash ~/Desktop/01-setup.sh
```

Sparse-clones `Server/` → `/data2/laravel/IR4-Project`, composer/npm, schedule; runs `scripts/02-install-systemd-units.sh` when present in the synced tree.

---

## C. `.env` essentials

Edit `/data2/laravel/IR4-Project/.env`:

```env
APP_URL=https://ir4-project.test
APP_TIMEZONE=Asia/Riyadh
DB_HOST=lerd-mysql
REDIS_HOST=lerd-redis
CAMERA_BROWSER_STREAM_URL_TEMPLATE=/hls/{reference}/
MEDIAMTX_API_URL=http://192.168.x.x:9997
BACKUP_DISK_ROOT=/data/ir4-backups
BACKUP_ARCHIVE_PASSWORD=<strong-site-secret>
MYSQL_DUMP_BINARY_PATH=/usr/bin
```

Then: `lerd artisan config:clear`

---

## D. Database & first admin

```bash
cd /data2/laravel/IR4-Project
lerd artisan migrate --force
lerd artisan db:seed --force
lerd artisan ir4:install
lerd artisan storage:link
```

---

## 02 — System boot (`ir4.target`)

Templates: `scripts/systemd/` → installed to `/etc/systemd/system/`.

```bash
cd /data2/laravel/IR4-Project
bash scripts/02-install-systemd-units.sh
systemctl status ir4.target
```

| Unit | Starts |
|------|--------|
| `ir4-lerd.service` | `lerd start` |
| `ir4-workers.service` | schedule / queue / reverb |
| `ir4-mediamtx.service` | runs `scripts/03-ensure-mediamtx.sh` |
| `ir4-sync-camera-streams.service` | camera sync |
| `ir4.target` | all of the above |

---

## 03 — Live wall

```bash
cd /data2/laravel/IR4-Project
sudo bash scripts/03-ensure-mediamtx.sh
lerd artisan ir4:sync-camera-streams --probe
lerd artisan ir4:sync-camera-streams
```

Config: `scripts/mediamtx.yml`.

---

## 04 — Backups

```bash
cd /data2/laravel/IR4-Project
BACKUP_ARCHIVE_PASSWORD_OVERRIDE='<strong-site-secret>' \
  bash scripts/04-setup-backup.sh
```

Must pass: same inode host↔PHP for `/data/ir4-backups`; host `ls` shows `ir4-*.zip`; schedule worker running. Detail: [DOC-20 §8](Docs/Doc%2020%20deployment%20runbook.md) / [DOC-19](Docs/Doc%2019%20retention%20backup.md).

---

## E. TLS & workstations

```bash
cd /data2/laravel/IR4-Project
lerd secure
# hosts: ir4-project.test → SCC IP; install mkcert rootCA on Windows
```

---

## F. Reboot proof

```bash
sudo reboot
systemctl is-active ir4.target && lerd worker list
curl -sk https://ir4-project.test/up
ls -lah /data/ir4-backups/IR4/
```

---

## 05 — Day-2 updates

```bash
cd /data2/laravel/IR4-Project
bash scripts/05-update.sh
```

Preserves `.env`, `auto.crt` / `auto.key`, `vendor/`, `storage/`.

---

## Script index

| Path (monorepo) | On SCC (app root) | Purpose |
|-----------------|-------------------|---------|
| `Server/scripts/01-setup.sh` | `scripts/01-setup.sh` | First install |
| `Server/scripts/02-install-systemd-units.sh` | `scripts/02-install-systemd-units.sh` | → `/etc/systemd/system/ir4.*` |
| `Server/scripts/03-ensure-mediamtx.sh` | `scripts/03-ensure-mediamtx.sh` | MediaMTX Docker |
| `Server/scripts/04-setup-backup.sh` | `scripts/04-setup-backup.sh` | Backup volume + first run |
| `Server/scripts/05-update.sh` | `scripts/05-update.sh` | Day-2 pull + build |
| `Server/scripts/systemd/*` | `scripts/systemd/*` | Unit templates |
| `Server/scripts/mediamtx.yml` | `scripts/mediamtx.yml` | MediaMTX config |
| `Server/scripts/link-public-storage.sh` | `scripts/link-public-storage.sh` | Storage symlink |
| `Server/scripts/build-encoded-release.php` | — (dev/build machine) | Encoded Release tree |

---

## Related docs

| Doc | Use when |
|-----|----------|
| [DOC-20](Docs/Doc%2020%20deployment%20runbook.md) | Full ops / acceptance |
| [DOC-19](Docs/Doc%2019%20retention%20backup.md) | Retention policy |
| [README](README.md) | Monorepo map |
| [Server/README](Server/README.md) | Hostinger / local |
| [EdgeCompute/README](EdgeCompute/README.md) | Orin agents |
