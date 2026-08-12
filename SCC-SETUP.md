# SCC fresh setup runbook

Step-by-step guide for a **new** Dell R360 / Lerd SCC box.  
Design rules: `Docs/` DOC-01…22 · Full ops depth: [DOC-20](Docs/Doc%2020%20deployment%20runbook.md).

---

## Before you start


| Item            | Value                                                             |
| --------------- | ----------------------------------------------------------------- |
| App root on SCC | `/data2/laravel/IR4-Project` (flattened `Server/` from monorepo)  |
| Deploy user     | non-root (`scc1`, `scc2`, …) — never run Laravel as root          |
| Backup volume   | `/data/ir4-backups` (separate disk; must be mounted in Lerd)      |
| Scripts on SCC  | `/data2/laravel/IR4-Project/scripts/01-setup.sh` … `05-update.sh` |


**Pick one browser URL mode and stick to it.** Mixing IP + `.test` breaks login (session cookies and redirects).


| Mode                             | When                            | Browser URL                | `.env`                                                            |
| -------------------------------- | ------------------------------- | -------------------------- | ----------------------------------------------------------------- |
| **A — LAN HTTP (commissioning)** | First bring-up, no TLS yet      | `http://<SCC-IP>:9100`     | `APP_URL=http://<SCC-IP>:9100` · `SESSION_SECURE_COOKIE=false`    |
| **B — HTTPS (production)**       | After `lerd secure` + hosts/DNS | `https://ir4-project.test` | `APP_URL=https://ir4-project.test` · `SESSION_SECURE_COOKIE=true` |


Example for this site (replace IP if yours differs):

```text
SCC IP:     192.168.8.38
LAN URL:    http://192.168.8.38:9100
HTTPS URL:  https://ir4-project.test   (after step 8)
```

---



## Flow at a glance

```text
 1. OS + Lerd          →  apt, lerd install, /data mount
 2. App install (01)   →  clone Server/, composer, npm build
 3. Configure .env     →  APP_URL + session + DB + MediaMTX
 4. Database + admin   →  migrate, ir4:install
 5. Boot units (02)    →  ir4.target systemd
 6. Live wall (03)     →  MediaMTX + camera sync
 7. Backups (04)       →  /data/ir4-backups
 8. TLS (optional)     →  lerd secure + workstation CA
 9. Verify             →  login, /up, reboot
10. Edge poles         →  see EdgeCompute/README.md (separate Orins)
```

---



## 1. OS baseline

On the **SCC host**:

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y git rsync unzip curl wget zip
```

MediaMTX needs a container runtime (either is fine):

```bash
# Option A — Docker
sudo apt install -y docker.io && sudo systemctl enable --now docker
sudo usermod -aG docker "$USER"   # re-login after this

# Option B — Podman (common on Lerd; no docker.service required)
```

Copy the bootstrap script **before** the app tree exists:

```bash
scp Server/scripts/01-setup.sh scc2@192.168.8.38:~/Desktop/
```

---



## 2. Lerd

Install: [lerd.sh/getting-started/installation](https://lerd.sh/getting-started/installation)

In `~/.config/lerd/config.yaml` ensure mounts include:

```yaml
mounts:
  - /data
```

Then:

```bash
lerd restart
lerd start
```

---



## 3. Application install (`01-setup.sh`)

```bash
bash ~/Desktop/01-setup.sh
```

This script:

- Sparse-clones `Server/` → `/data2/laravel/IR4-Project`
- Runs `composer install`, `npm run build`
- Creates `.env` from `.env.example` if missing
- Starts Lerd scheduler
- Installs `ir4.target` systemd units (if `02-install-systemd-units.sh` is present)

When prompted **Run migrations?** you may answer **N** — step 4 runs them with `--force`.

---



## 4. Configure `.env`

Edit `/data2/laravel/IR4-Project/.env`:

### 4a. URL + session (required — fixes login loops)

**LAN HTTP commissioning** (open `http://192.168.8.38:9100` in the browser):

```env
APP_URL=http://192.168.8.38:9100
SESSION_SECURE_COOKIE=false
SESSION_DOMAIN=
```

**HTTPS production** (after step 8):

```env
APP_URL=https://ir4-project.test
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=
```

> `APP_URL` must match the URL in the browser bar exactly (scheme + host + port).  
> Wrong `APP_URL` → login succeeds then immediately bounces back with no error.



### 4b. Core services

```env
APP_TIMEZONE=Asia/Riyadh
DB_HOST=lerd-mysql
DB_PORT=3306
DB_DATABASE=ir4_project
DB_USERNAME=root
DB_PASSWORD=lerd
REDIS_HOST=lerd-redis
SESSION_DRIVER=database
CACHE_STORE=database
```



### 4c. Cameras + backups

**Always set MediaMTX to this SCC’s LAN IP** — do not copy `MEDIAMTX_*` / `CAMERA_BROWSER_*` from another box (e.g. SCC1 `192.168.3.149` will break live wall on SCC2).

Permanent contract (auto-applied by `scripts/ensure-mediamtx-env.sh` and by `03-ensure-mediamtx.sh`):

```bash
cd /data2/laravel/IR4-Project
bash scripts/ensure-mediamtx-env.sh
```

That detects this host’s LAN IP and writes:

```env
CAMERA_BROWSER_STREAM_URL_TEMPLATE=/hls/{reference}/
MEDIAMTX_API_URL=http://<this-SCC-IP>:9997
MEDIAMTX_HOST_IP=<this-SCC-IP>
MEDIAMTX_API_USER=
MEDIAMTX_API_PASS=
MEDIAMTX_SOURCE_ON_DEMAND=false
MEDIAMTX_RTSP_TRANSPORT=tcp
BACKUP_DISK_ROOT=/data/ir4-backups
BACKUP_ARCHIVE_PASSWORD=<strong-site-secret>
MYSQL_DUMP_BINARY_PATH=/usr/bin
```

| Key | Permanent value |
| --- | ----------------- |
| `CAMERA_BROWSER_STREAM_URL_TEMPLATE` | **`/hls/{reference}/`** (same-origin HLS `<video>` — works on LAN IP and HTTPS) |
| `MEDIAMTX_API_URL` | `http://<this-SCC-IP>:9997` |
| `MEDIAMTX_HOST_IP` | This SCC LAN IP |
| `MEDIAMTX_SOURCE_ON_DEMAND` | `false` (warm RTSP for live wall) |
| `MEDIAMTX_RTSP_TRANSPORT` | `tcp` |
| `MEDIAMTX_API_USER` / `PASS` | Empty |

**Do not** use `http://<SCC-IP>:8888/{reference}` in the browser template (breaks HTTPS / mixed content). Live wall plays via `hls.js` → `/hls/{reference}/index.m3u8` → MediaMTX.

Apply:

```bash
cd /data2/laravel/IR4-Project
lerd artisan config:clear
lerd artisan cache:clear
```

---



## 5. Database + first admin

```bash
cd /data2/laravel/IR4-Project

lerd artisan migrate --force
lerd artisan storage:link
```

Create Super Admin + initial site registry (poles, devices, cameras):

```bash
# Non-interactive (commissioning):
lerd artisan ir4:install \
  --name="Super Admin" \
  --email="admin@ir4.local" \
  --password="password"

# Or interactive prompts:
lerd artisan ir4:install
```

`ir4:install` seeds RBAC, settings, gas thresholds, **DemoSeeder** (4 poles + device tokens), and PTW catalogue.  
**Save the device credential table** printed at the end — copy tokens into `EdgeCompute/configs/secrets.pole-NN.env`.

> Do **not** run `db:seed` separately if you already ran `ir4:install` — it duplicates work.  
> Re-running `DemoSeeder` generates **new random device tokens** (update Orin secrets after).



### Reset a locked-out admin

```bash
lerd artisan ir4:user:reset admin@ir4.local
# Use the temporary password printed once; change it on first login.
```

Or set a known password:

```bash
lerd artisan tinker --execute="
\$u = App\Models\User::where('email','admin@ir4.local')->firstOrFail();
\$u->password = 'Password123!';
\$u->must_change_password = true;
\$u->is_active = true;
\$u->save();
echo 'OK';
"
```

---



## 6. Log in (verify before continuing)

1. Open `http://192.168.8.38:9100/login` (same scheme/host/port as `APP_URL`).
2. Email: `admin@ir4.local` (or the email you passed to `ir4:install`).
3. Password: `Password123!` (or your chosen password).
4. First login forces a **password change** — that is expected.

Quick checks if login fails:

```bash
# Which Super Admin exists?
lerd artisan tinker --execute="
App\Models\User::role('Super Admin')->get(['email','is_active'])->each(
  fn(\$u) => print(\$u->email.' active='.(int)\$u->is_active.PHP_EOL)
);"

# Health endpoint
curl -sS -o /dev/null -w '%{http_code}\n' http://192.168.8.38:9100/up

# Session config loaded
grep -E '^(APP_URL|SESSION_SECURE_COOKIE|SESSION_DOMAIN)=' .env
lerd artisan config:show session.secure session.domain app.url
```


| Symptom                   | Fix                                                             |
| ------------------------- | --------------------------------------------------------------- |
| Page refreshes, no error  | `APP_URL` / `SESSION_SECURE_COOKIE` mismatch — see §4a          |
| “Credentials don’t match” | Wrong email — check `ir4:install` output or reset command above |
| Locked account            | `lerd artisan ir4:user:reset <email>`                           |


---



## 7. Boot units (`02-install-systemd-units.sh`)

Skip if `01-setup.sh` already installed units.

```bash
cd /data2/laravel/IR4-Project
bash scripts/02-install-systemd-units.sh
systemctl enable ir4.target
systemctl start ir4.target
systemctl status ir4.target
```


| Unit                              | Role                      |
| --------------------------------- | ------------------------- |
| `ir4-lerd.service`                | `lerd start` on boot      |
| `ir4-workers.service`             | schedule / queue / reverb |
| `ir4-mediamtx.service`            | MediaMTX container        |
| `ir4-sync-camera-streams.service` | RTSP → HLS sync           |
| `ir4.target`                      | Wants all of the above    |


---



## 8. Live wall — MediaMTX (`03-ensure-mediamtx.sh`)

Permanent path: same-origin `/hls/{reference}/` + MediaMTX on this SCC + `hls.js` player (already in the app build).

```bash
export PATH="$HOME/.local/share/lerd/bin:$HOME/.local/bin:$PATH"
cd /data2/laravel/IR4-Project

# Writes MEDIAMTX_* + CAMERA_BROWSER_* for THIS host’s LAN IP
bash scripts/ensure-mediamtx-env.sh
lerd artisan config:clear

sudo bash scripts/03-ensure-mediamtx.sh   # also re-runs ensure-mediamtx-env.sh
curl -sS -o /dev/null -w 'API %{http_code}\n' http://127.0.0.1:9997/v3/config/paths/list

lerd artisan ir4:sync-camera-streams --probe
lerd artisan ir4:sync-camera-streams
```

Camera `stream_url` in the UI is **RTSP** (e.g. `rtsp://admin:Unity@320@@192.168.1.185:554/Streaming/Channels/101`).  
Browsers use **HLS** via `/hls/{reference}/` — `ffplay` on RTSP working is not enough until sync succeeds.

After pulling live-wall frontend changes, rebuild once:

```bash
bash scripts/05-update.sh
# or: npm run build && lerd restart
```

### Smoothness

HLS always has a few seconds of lag. Defaults from `ensure-mediamtx-env.sh` + `scripts/mediamtx.yml` (warm RTSP, TCP, LL-HLS, streaming PHP proxy) are the permanent tuning. Re-apply after cloning `.env` from another SCC:

```bash
bash scripts/ensure-mediamtx-env.sh
sudo bash scripts/03-ensure-mediamtx.sh
lerd artisan config:clear
lerd artisan ir4:sync-camera-streams
```

### Edge / workstation cannot open `/live`

1. Open `http://192.168.8.38:9100/login` first (must be logged in — `/hls` requires auth).  
2. `APP_URL` must match how you browse (`http://192.168.8.38:9100` for LAN).  
3. From the Orin: `curl -sS -o /dev/null -w '%{http_code}\n' http://192.168.1.245:9100/up` → `200`.  
4. Hard-refresh `/live` after login.

Config template: `scripts/mediamtx.yml`.

---



## 9. Backups (`04-setup-backup.sh`)

```bash
cd /data2/laravel/IR4-Project
BACKUP_ARCHIVE_PASSWORD_OVERRIDE='<same-as-BACKUP_ARCHIVE_PASSWORD-in-env>' \
  bash scripts/04-setup-backup.sh
```

Verify:

```bash
ls -lah /data/ir4-backups/IR4/
lerd worker list    # schedule worker must be running
```

Detail: [DOC-19](Docs/Doc%2019%20retention%20backup.md) · [DOC-20 §8](Docs/Doc%2020%20deployment%20runbook.md).

---



## 10. TLS + operator workstations (optional)

Only when moving from LAN HTTP (mode A) to HTTPS (mode B).

### 10a. On the SCC (once)

```bash
cd /data2/laravel/IR4-Project
lerd secure

# Switch app to HTTPS mode
sed -i 's|^APP_URL=.*|APP_URL=https://ir4-project.test|' .env
grep -q '^SESSION_SECURE_COOKIE=' .env \
  && sed -i 's|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=true|' .env \
  || echo 'SESSION_SECURE_COOKIE=true' >> .env
sed -i 's|^SESSION_DOMAIN=.*|SESSION_DOMAIN=|' .env

lerd artisan config:clear
lerd artisan cache:clear

# Export root CA for operator PCs (copy the file or print base64)
mkcert -CAROOT
cat "$(mkcert -CAROOT)/rootCA.pem"
```

Copy `rootCA.pem` to each operator PC (USB, SCP, or paste base64). **Never share** `rootCA-key.pem`**.**

```bash
# Optional — SCP to an operator PC:
scp "$(mkcert -CAROOT)/rootCA.pem" operator@192.168.8.100:~/Downloads/lerd-rootCA.pem
```



### 10b. On each operator PC

Replace `192.168.8.38` if your SCC IP differs.

#### 1) Hosts file — map `ir4-project.test` → SCC

**Windows (PowerShell as Administrator):**

```powershell
Add-Content -Path C:\Windows\System32\drivers\etc\hosts -Value "`n192.168.8.38`tir4-project.test"
ping ir4-project.test
```

**macOS / Linux:**

```bash
echo '192.168.8.38 ir4-project.test' | sudo tee -a /etc/hosts
ping -c 1 ir4-project.test
```



#### 2) Trust the SCC mkcert root CA

**Windows:**

1. Copy `lerd-rootCA.pem` to the desktop, rename to `lerd-rootCA.crt` if needed.
2. Double-click → **Install Certificate** → **Local Machine** → **Trusted Root Certification Authorities**.
3. Or (PowerShell as Administrator):

```powershell
certutil -addstore -f "ROOT" "$env:USERPROFILE\Downloads\lerd-rootCA.pem"
```

Restart Chrome/Edge/Firefox.

**macOS:**

```bash
brew install mkcert nss   # once, if missing
sudo security add-trusted-cert -d -r trustRoot \
  -k /Library/Keychains/System.keychain ~/Downloads/lerd-rootCA.pem
```

**Linux (Debian/Ubuntu):**

```bash
sudo apt install -y mkcert libnss3-tools   # once, if missing
sudo cp ~/Downloads/lerd-rootCA.pem /usr/local/share/ca-certificates/lerd-ir4.crt
sudo update-ca-certificates
CAROOT=~/mkcert-ca mkdir -p ~/mkcert-ca
cp ~/Downloads/lerd-rootCA.pem ~/mkcert-ca/rootCA.pem
mkcert -install
```



#### 3) Open the app

In the browser (not on the SCC):

```text
https://ir4-project.test/login
```

Do **not** use `http://192.168.8.38:9100` after switching to HTTPS mode — sessions and redirects expect `https://ir4-project.test`.

**Verify from operator PC:**

```bash
curl -sk -o /dev/null -w '%{http_code}\n' https://ir4-project.test/up
# expect 200
```

Login: `admin@ir4.local` (or the email from `ir4:install`).

## 11. Reboot proof

```bash
sudo reboot
# after reboot:
systemctl is-active ir4.target
lerd worker list
curl -sk https://ir4-project.test/up    # or http://192.168.8.38:9100/up on LAN HTTP
ls -lah /data/ir4-backups/IR4/
```

Full acceptance: [DOC-20 §10](Docs/Doc%2020%20deployment%20runbook.md).

---



## 12. Edge poles (Orins — separate hosts)

SCC must be reachable from each Orin at the same base URL used in pole secrets:

```env
IR4_BASE_URL=http://192.168.1.245:9100
```

(Operators still use `http://192.168.8.38:9100` / `https://ir4-project.test`.)

On **each Orin** (`NN` = `01` … `04`):

```bash
sudo mkdir -p /opt/ir4-edge
git clone --depth 1 https://github.com/Huzaifa-367/IR4-Project.git /tmp/IR4-Project
sudo cp -a /tmp/IR4-Project/EdgeCompute /opt/ir4-edge/EdgeCompute

cd /opt/ir4-edge/EdgeCompute
git pull    # or re-copy after SCC re-seed
cp configs/secrets.pole-NN.env configs/secrets.env
sudo ./deploy/orin_bootstrap.sh
ir4-edge doctor
curl -sS -o /dev/null -w '%{http_code}\n' http://192.168.1.245:9100/up
```

Full detail: [EdgeCompute/README.md](EdgeCompute/README.md) · [EdgeCompute/docs/commissioning.md](EdgeCompute/docs/commissioning.md).

---



## Day-2 updates (`05-update.sh`)

```bash
cd /data2/laravel/IR4-Project
bash scripts/05-update.sh
```

Preserves `.env`, TLS certs, `vendor/`, and `storage/`. Re-run `lerd artisan config:clear` if `.env` changed.

### If `npm run build` fails on Wayfinder

The Vite plugin runs `wayfinder:generate` via PHP. On SCC that must use **Lerd PHP** (`lerd artisan`), not the host `php` binary.

After pulling this fix, re-run:

```bash
cd /data2/laravel/IR4-Project
bash scripts/05-update.sh
```

To see the real PHP error (instead of the vague Rolldown message):

```bash
cd /data2/laravel/IR4-Project
source scripts/resolve-artisan.sh
ir4_artisan wayfinder:generate --with-form -v
```

Common causes:

| Symptom | Fix |
| -------- | ----- |
| `Please provide a valid cache path` | `bash scripts/ensure-storage-dirs.sh` (creates `storage/framework/views`, etc.) |
| `.env` missing | `cp .env.example .env` then `lerd artisan key:generate --force` |
| `lerd: command not found` | Install Lerd (`01-setup.sh`) or add `~/.local/share/lerd/bin` to PATH |
| DB / bootstrap errors | `lerd artisan optimize:clear` then retry |
| Stale bootstrap cache after rsync | `05-update.sh` now runs `package:discover` + `optimize:clear` before build |

---



## Script index


| Monorepo path                                | On SCC                                | Purpose                                 |
| -------------------------------------------- | ------------------------------------- | --------------------------------------- |
| `Server/scripts/01-setup.sh`                 | `scripts/01-setup.sh`                 | First install (clone, build, scheduler) |
| `Server/scripts/02-install-systemd-units.sh` | `scripts/02-install-systemd-units.sh` | `ir4.target` → `/etc/systemd/system/`   |
| `Server/scripts/03-ensure-mediamtx.sh`       | `scripts/03-ensure-mediamtx.sh`       | MediaMTX Docker/Podman                  |
| `Server/scripts/ensure-mediamtx-env.sh`      | `scripts/ensure-mediamtx-env.sh`      | Permanent MediaMTX `.env` for this SCC  |
| `Server/scripts/04-setup-backup.sh`          | `scripts/04-setup-backup.sh`          | Backup volume + first run               |
| `Server/scripts/05-update.sh`                | `scripts/05-update.sh`                | Pull + rebuild                          |
| `Server/scripts/systemd/*`                   | `scripts/systemd/*`                   | Unit templates                          |
| `Server/scripts/mediamtx.yml`                | `scripts/mediamtx.yml`                | MediaMTX config                         |


---



## Related docs


| Doc                                               | Use when                  |
| ------------------------------------------------- | ------------------------- |
| [DOC-20](Docs/Doc%2020%20deployment%20runbook.md) | Full ops / acceptance     |
| [DOC-19](Docs/Doc%2019%20retention%20backup.md)   | Retention + backup policy |
| [Server/README.md](Server/README.md)              | Hostinger / local dev     |
| [EdgeCompute/README.md](EdgeCompute/README.md)    | Orin gas + RFID agents    |
| [README.md](README.md)                            | Monorepo map              |


