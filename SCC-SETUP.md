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

**Pole split and field IPs:** [EdgeCompute/docs/site-network.md](EdgeCompute/docs/site-network.md) — SCC2 = poles **1–4**, SCC1 = poles **5–8**. Remote access is **SSH over Tailscale only** (no AnyDesk / KVM).

Laptop → Tailscale → `https://ir4-project.test` (mount `/data2`, re-link Lerd, hosts file): [SCC-REMOTE-ACCESS.md](SCC-REMOTE-ACCESS.md).

```text
ssh scc2@100.118.103.39    # SCC2
ssh scc1@100.96.105.106    # SCC1
```

**Pick one browser URL mode and stick to it.** Mixing IP + `.test` breaks login (session cookies and redirects).


| Mode                             | When                            | Browser URL                | `.env`                                                            |
| -------------------------------- | ------------------------------- | -------------------------- | ----------------------------------------------------------------- |
| **A — LAN HTTP (commissioning)** | First bring-up, no TLS yet      | `http://<SCC-IP>:9100`     | `APP_URL=http://<SCC-IP>:9100` · `SESSION_SECURE_COOKIE=false`    |
| **B — HTTPS (production)**       | After `lerd secure` + hosts/DNS | `https://ir4-project.test` | `APP_URL=https://ir4-project.test` · `SESSION_SECURE_COOKIE=true` |

Reverb WebSockets use **the same origin as that browser URL** (Lerd proxies `/app` on the site vhost). Do not bake `127.0.0.1` / `localhost` into the frontend — that is the SCC, not the workstation.


**Known office LAN IPs (hosts → `ir4-project.test`):**

| SCC | Fill-in IP | Commissioning URL (mode A) | Production URL (mode B) |
| --- | --- | --- | --- |
| **SCC1** | `192.168.3.149` | `http://192.168.3.149:9100` | `https://ir4-project.test` |
| **SCC2** | `192.168.2.91` | `http://192.168.2.91:9100` | `https://ir4-project.test` |

Workstation setup for mode B is **§10** below. Remote / Tailscale laptop: [SCC-REMOTE-ACCESS.md](SCC-REMOTE-ACCESS.md).

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
scp Server/scripts/01-setup.sh scc2@192.168.8.40:~/Desktop/
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

**LAN HTTP commissioning** (open `http://192.168.8.40:9100` in the browser):

```env
APP_URL=http://192.168.8.40:9100
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



### 4b. Reverb (workstation LIVE vs SCC-only LIVE)

Laravel HTTP can work through Lerd’s exposed URL while the WebSocket still fails. The Reverb **process** listens internally; the **browser** must reach it through that same Lerd site.

```env
# Process bind — never 127.0.0.1 (workstations cannot connect to the SCC’s loopback)
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

# PHP inside Lerd talking to Reverb (not the operator PC)
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
```

On the SCC:

```bash
lerd reverb:start    # regenerates the site vhost with /app WebSocket proxy
lerd worker list     # reverb must be running
```

The compiled UI opens `ws(s)://<page-host>:<page-port>/app/<APP_KEY>…` (same origin as the dashboard). After changing `REVERB_APP_KEY`, rebuild (`npm run build` / `05-update.sh`) and `lerd artisan reverb:restart`.

**Check from a workstation (not from the SCC):** Chrome → F12 → Network → WS → reload `/dashboard`.

| You see | Meaning |
| -------- | ------- |
| `ws://192.168.8.40:9100/app/…` or `wss://ir4-project.test/app/…` then 101 | Correct |
| `ws://127.0.0.1:8080` or `ws://localhost:8080` | Old frontend still targeting the workstation itself — pull + rebuild |
| Correct host but 404 / 502 | `lerd reverb:start` not running, or vhost missing Upgrade headers — restart Reverb so Lerd regenerates nginx |

Dashboard LIVE on the SCC but offline on a workstation is almost always this path, not Inertia.


### 4c. Core services

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



### 4d. Cameras + backups

**Always set MediaMTX to this SCC’s LAN IP** — do not copy `MEDIAMTX_`* / `CAMERA_BROWSER_*` from another box (e.g. SCC1 `192.168.3.149` will break live wall on SCC2).

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


| Key                                  | Permanent value                                                             |
| ------------------------------------ | --------------------------------------------------------------------------- |
| `CAMERA_BROWSER_STREAM_URL_TEMPLATE` | `/hls/{reference}/` (same-origin HLS `<video>` — works on LAN IP and HTTPS) |
| `MEDIAMTX_API_URL`                   | `http://<this-SCC-IP>:9997`                                                 |
| `MEDIAMTX_HOST_IP`                   | This SCC LAN IP                                                             |
| `MEDIAMTX_SOURCE_ON_DEMAND`          | `false` (warm RTSP for live wall)                                           |
| `MEDIAMTX_RTSP_TRANSPORT`            | `tcp`                                                                       |
| `MEDIAMTX_API_USER` / `PASS`         | Empty                                                                       |


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
**Save the device credential table** printed during install — copy tokens into `EdgeCompute/configs/secrets.pole-NN.env`.

> `DemoSeeder` is idempotent: if `AST-POLE-01` already exists it skips and **does not** print new tokens.  
> Fresh install (empty registry) is when the credential table appears.



### Reset a locked-out admin

```bash
lerd artisan ir4:user:reset admin@ir4.local
# Use the temporary password printed once; change it on first login.
```

Or set a known password:

```bash
lerd artisan tinker --execute="
\$u = App\Models\User::where('email','admin@gmail.com')->firstOrFail();
\$u->password = '12345677';
\$u->must_change_password = true;
\$u->is_active = true;
\$u->save();
echo 'OK';
"
```

---



## 6. Log in (verify before continuing)

1. Open `http://192.168.8.40:9100/login` (same scheme/host/port as `APP_URL`).
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
curl -sS -o /dev/null -w '%{http_code}\n' http://192.168.8.40:9100/up

# Session config loaded
grep -E '^(APP_URL|SESSION_SECURE_COOKIE|SESSION_DOMAIN)=' .env
lerd artisan config:show app.url
lerd artisan config:show session.secure
lerd artisan config:show session.domain
```


| Symptom                                                                  | Fix                                                                                                                  |
| ------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------- |
| Page refreshes, no error                                                 | Usually `SESSION_SECURE_COOKIE` / `APP_URL` mismatch — see §4a. Also: bare `false` in `.env` used to be treated as secure (fixed in `config/session.php`); run `lerd artisan config:clear` after pull. |
| Login **419** / cookies rejected as “secure” on HTTP                     | `.env` still has HTTPS settings — set §4a LAN HTTP values, then `lerd artisan config:clear`                          |
| CSRF / session expired banner on login                                   | Cookies blocked or CSRF stale — refresh once; confirm scheme matches `APP_URL`                                       |
| Page reload-loops / assets from Vite HMR (`/@lerd-vite/`, `127.0.0.1:5173`) | SCC must serve `public/build` only. Do **not** run `npm run dev` or `lerd worker start vite` on the SCC. `bash scripts/05-update.sh` stops Vite, deletes `public/hot`, rebuilds, and **fails** if login HTML still references HMR. Emergency: `lerd worker stop vite && rm -f public/hot` then hard-refresh. |
| “Credentials don’t match”                                                | Wrong email — check `ir4:install` output or reset command above                                                      |
| Locked account                                                           | `lerd artisan ir4:user:reset <email>`                                                                                |


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

Camera `stream_url` in the UI is **RTSP** (e.g. `rtsp://admin:UNity@320@@172.16.2.10:554/Streaming/Channels/101`). Full pole list is **§13**.  
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

1. Open `https://ir4-project.test/login` (or LAN HTTP mode A) first — `/hls` requires auth.
2. `APP_URL` must match how you browse.
3. From the Orin: `curl -sS -o /dev/null -w '%{http_code}\n' http://172.16.<subnet>.40:9100/up` → `200` (see §12a).
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



## 10. HTTPS dashboard on a workstation (mode B)

**Goal:** open the Control Room from an office PC as `https://ir4-project.test` with a green/trusted padlock.

**You need three things on the PC:**

1. **Hosts** — name `ir4-project.test` → this SCC’s office LAN IP  
2. **CA trust** — install that SCC’s mkcert `rootCA.pem` (removes “Not secure”)  
3. **Browser URL** — only `https://ir4-project.test` (never `:9100` in mode B)

Both SCCs share the **same hostname**. The PC can point at **only one** SCC at a time (change the hosts IP to switch). Each SCC has its **own** CA file — SCC1’s CA does not trust SCC2’s certificate.

| Which SCC? | Hosts IP | CA file to install |
| --- | --- | --- |
| SCC1 | `192.168.3.149` | `lerd-rootCA-scc1.pem` |
| SCC2 | `192.168.2.91` | `lerd-rootCA-scc2.pem` |

SCC2 also has `192.168.2.42` on the same NIC — use **`.91`** unless it is missing (`ip -4 addr show eno8303`).  
Do **not** put pole IPs (`172.16.*`), Tailscale (`100.*`), or `192.0.2.1` in hosts for LAN workstations.

---

### Step A — On the SCC (once per box)

Run on the SCC you want operators to use (example: SCC2).

```bash
cd /data2/laravel/IR4-Project
lerd secure

sed -i 's|^APP_URL=.*|APP_URL=https://ir4-project.test|' .env
grep -q '^SESSION_SECURE_COOKIE=' .env \
  && sed -i 's|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=true|' .env \
  || echo 'SESSION_SECURE_COOKIE=true' >> .env
sed -i 's|^SESSION_DOMAIN=.*|SESSION_DOMAIN=|' .env

lerd artisan config:clear
lerd artisan cache:clear
lerd reverb:stop || true
lerd reverb:start
```

**Export the CA** (this is what the workstation installs — not the site leaf cert):

```bash
mkcert -CAROOT
# → e.g. /home/scc2/.local/share/mkcert/rootCA.pem

# Prove the dashboard cert is mkcert for ir4-project.test
echo | openssl s_client -connect 127.0.0.1:443 -servername ir4-project.test 2>/dev/null \
  | openssl x509 -noout -subject -issuer -dates

# Put a copy on the Desktop (USB / easy copy). Rename for SCC1 → …-scc1.pem
cp "$(mkcert -CAROOT)/rootCA.pem" ~/Desktop/lerd-rootCA-scc2.pem
```

**Never copy** `rootCA-key.pem` off the SCC.

Copy the Desktop file to the PC (USB, shared folder, or SCP), e.g. from a laptop:

```bash
scp scc2@100.118.103.39:~/Desktop/lerd-rootCA-scc2.pem ~/Downloads/
```

---

### Step B — On the Windows workstation

Do this on the **operator PC**, PowerShell **as Administrator**.  
Examples below are for **SCC2**. For SCC1, use `192.168.3.149` and `lerd-rootCA-scc1.pem` instead.

#### B1 — Hosts (name → IP)

**Must have a space** between IP and name.  
Wrong: `192.168.2.91ir4-project.test` → ping fails.  
Right: `192.168.2.91 ir4-project.test`

**Option 1 — Notepad (same method used on SCC1):**

1. Open Notepad **as Administrator**  
2. Open `C:\Windows\System32\drivers\etc\hosts`  
3. Delete any old line containing `ir4-project.test`  
4. Add one line: `192.168.2.91 ir4-project.test`  
5. Save  
6. Run: `ipconfig /flushdns`

**Option 2 — PowerShell (one line at a time):**

```powershell
$hostsFile = "$env:SystemRoot\System32\drivers\etc\hosts"
```

```powershell
$lines = Get-Content $hostsFile | Where-Object { $_ -notmatch 'ir4-project\.test' }
```

```powershell
Set-Content -Path $hostsFile -Value $lines
```

```powershell
Add-Content -Path $hostsFile -Value "192.168.2.101 ir4-project.test"
```

```powershell
ipconfig /flushdns
```

```powershell
Select-String -Path $hostsFile -Pattern "ir4-project\.test"
```

```powershell
ping ir4-project.test
```

**Pass:** Select-String shows `192.168.2.91 ir4-project.test` and ping replies from that IP.

**Chrome:** Settings → Privacy and security → Security → turn **off** “Use secure DNS” (otherwise `.test` may ignore hosts).

#### B2 — Trust the CA (padlock)

1. Copy `lerd-rootCA-scc2.pem` into Downloads (rename to `.crt` if Windows asks).  
2. Double-click → **Install Certificate** → **Local Machine** → **Trusted Root Certification Authorities** → Finish.  
   Or Admin PowerShell:

```powershell
certutil -addstore -f "ROOT" "$env:USERPROFILE\Downloads\lerd-rootCA-scc2.pem"
```

3. Fully quit and reopen Chrome/Edge.

#### B3 — Open the dashboard

```text
https://ir4-project.test/login
```

| Do | Don’t |
| --- | --- |
| `https://ir4-project.test` | `http://192.168.2.91:9100` |
| Hosts IP = office LAN | Pole / Tailscale / `lerd0` IPs |

Login: `admin@ir4.local` (or the email from `ir4:install`).

---

### Step C — Switch the PC from SCC1 ↔ SCC2

1. Change the hosts IP (B1) to the other SCC.  
2. Install that SCC’s CA if not already trusted (B2).  
3. Flush DNS, restart the browser, open `https://ir4-project.test` again.

---

### macOS / Linux workstation (hosts + CA)

```bash
# Hosts — SCC2 example
sudo sed -i.bak '/ir4-project\.test/d' /etc/hosts
echo '192.168.2.91 ir4-project.test' | sudo tee -a /etc/hosts
ping -c 1 ir4-project.test

# CA — macOS
sudo security add-trusted-cert -d -r trustRoot \
  -k /Library/Keychains/System.keychain ~/Downloads/lerd-rootCA-scc2.pem

# CA — Debian/Ubuntu
sudo cp ~/Downloads/lerd-rootCA-scc2.pem /usr/local/share/ca-certificates/lerd-ir4-scc2.crt
sudo update-ca-certificates
```

Then open `https://ir4-project.test/login`.

---

## 11. Reboot proof

```bash
sudo reboot
# after reboot:
systemctl is-active ir4.target
lerd worker list
curl -sk https://ir4-project.test/up    # mode B; or http://<SCC-IP>:9100/up for mode A
ls -lah /data/ir4-backups/IR4/
```

Full acceptance: [DOC-20 §10](Docs/Doc%2020%20deployment%20runbook.md).

---



## 12. Edge poles (Orins — separate hosts)

Poles **1–4** ingest to **SCC2**. Each Jetson uses the SCC IP on **that pole’s VLAN** (port `9100`), not the office LAN and not `https://ir4-project.test`.

Authoritative IPs: [EdgeCompute/docs/site-network.md](EdgeCompute/docs/site-network.md).

### 12a. `IR4_BASE_URL` (EdgeCompute secrets)

| Pole | Jetson LAN | `IR4_BASE_URL` in `configs/secrets.env` |
| --- | --- | --- |
| 1 | `172.16.3.2` | `http://172.16.3.40:9100` |
| 2 | `172.16.2.2` | `http://172.16.2.40:9100` |
| 3 | `172.16.1.50` | `http://172.16.1.40:9100` |
| 4 | `172.16.4.2` | `http://172.16.4.40:9100` |

From the Orin (example pole 2):

```bash
curl -sS -o /dev/null -w '%{http_code}\n' http://172.16.2.40:9100/up
# expect 200
```

Do **not** point poles at `http://192.168.2.91:9100`, Tailscale, or old template `192.168.8.40`. Operators still use `https://ir4-project.test` (hosts → `192.168.2.91`) — that is a different path.

### 12b. SSH to a pole desktop / Jetson — two ways

Keep **both** paths available.

| Path | When | How |
| --- | --- | --- |
| **A — Online (Tailscale direct)** | Pole shows **active** on the tailnet | Laptop → `poleN@100.x` (no SCC hop) |
| **B — LAN (via SCC)** | Tailscale offline / last-seen stale | Laptop → SCC Tailscale → `poleN@172.16.x.y` |

---

#### A) Online Tailscale — direct access setup

Goal: SSH/rsync from your laptop **straight to the Jetson** on Tailscale (same pattern as SCC access).

##### 1) On the Jetson / pole desktop (once per pole)

Connect over **LAN via SCC** first if Tailscale is not up yet (§12b B), then:

```bash
# Install (Ubuntu / Jetson)
curl -fsSL https://tailscale.com/install.sh | sh
sudo tailscale up
# Follow the URL / auth key for the IR4 tailnet

# Stable MagicDNS name (example pole 2)
sudo hostnamectl set-hostname pole2-desktop
sudo tailscale set --hostname=pole2-desktop

# Optional: Tailscale SSH (needs tailnet ssh ACL — see SCC-REMOTE-ACCESS.md)
sudo tailscale set --ssh=true

# Always keep a normal sshd key for user pole2 as fallback
mkdir -p ~/.ssh && chmod 700 ~/.ssh
# paste laptop/SCC public key into ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys

tailscale ip -4
tailscale status
```

Record the printed `100.x` address in the table below.

##### 2) On your laptop (every session)

```bash
# Same tailnet as SCCs / poles
tailscale status | grep -iE 'pole|scc'

# Expect "active" (not "offline, last seen …")
# Direct SSH — pole 2 example:
ssh pole2@100.104.14.30
# MagicDNS (if enabled on the tailnet):
ssh pole2@pole2-desktop
```

Copy / update EdgeCompute **directly** when online:

```bash
rsync -az --delete \
  --exclude 'configs/secrets.env' \
  --exclude '.venv' --exclude 'venv' --exclude '__pycache__' \
  ./EdgeCompute/ pole2@100.104.14.30:/tmp/EdgeCompute/

ssh pole2@100.104.14.30 \
  'sudo mkdir -p /opt/ir4-edge && sudo rsync -a --delete --exclude configs/secrets.env \
   /tmp/EdgeCompute/ /opt/ir4-edge/EdgeCompute/ && \
   cd /opt/ir4-edge/EdgeCompute && sudo ./deploy/orin_update.sh'
```

##### 3) Known Tailscale targets (fill as poles come online)

| Pole | Linux user | Tailscale IP | MagicDNS / hostname | LAN IP (fallback §12b B) |
| --- | --- | --- | --- | --- |
| 1 | `pole1` (confirm) | *(from `tailscale ip -4`)* | `pole1-desktop` | `172.16.3.2` |
| **2** | **`pole2`** | **`100.104.14.30`** | **`pole2-desktop`** | **`172.16.2.2`** |
| 3 | `pole3` (confirm) | *(fill)* | `pole3-desktop` | `172.16.1.50` |
| 4 | `pole4` (confirm) | *(fill)* | `pole4-desktop` | `172.16.4.2` |

SCC2 for comparison: `scc2@100.118.103.39` (`scc2-poweredge-r360`).

##### 4) If direct Tailscale fails

| Symptom | Action |
| --- | --- |
| `offline, last seen …` | Bring Jetson online; check WAN/LTE/Teltonika; or use **§12b B** |
| Connection timeout | `tailscale ping 100.104.14.30` from laptop; check ACL / key expiry |
| Permission denied | Install SSH pubkey for `pole2` (Tailscale SSH ACL alone may not cover all users) |

**Do not** set `IR4_BASE_URL` to a Tailscale IP — ingest stays on VLAN `http://172.16.x.40:9100`.

---

#### B) LAN SSH (via SCC2)

Use when Tailscale on the Jetson is down. SCC reaches the pole over the radio/VLAN.

```bash
# Laptop → SCC2
ssh scc2@100.118.103.39

# SCC2 → Jetson (pole 2)
ssh pole2@172.16.2.2
# prompt: pole2@pole2-desktop:~$
```

One-liner (ProxyJump):

```bash
ssh -J scc2@100.118.103.39 pole2@172.16.2.2
```

| Pole | LAN SSH from SCC2 |
| --- | --- |
| 1 | `ssh pole1@172.16.3.2` (confirm user) |
| 2 | `ssh pole2@172.16.2.2` |
| 3 | `ssh pole3@172.16.1.50` (confirm user) |
| 4 | `ssh pole4@172.16.4.2` (confirm user) |

Prerequisite: from SCC2, `ping 172.16.2.2` (etc.) succeeds. User `scc2` needs key/password access to `poleN@…`.

### 12c. Push EdgeCompute from SCC → Orin (preferred on site)

Yes — if the **`EdgeCompute/`** tree is on the SCC, you can update poles over the pole VLAN without USB or public git on the Jetson.

**SCC2 app root is usually flattened Laravel only** (`/data2/laravel/IR4-Project` = `Server/`). That tree does **not** include `EdgeCompute/`. Put a copy on the SCC first, e.g.:

```bash
# From your laptop (monorepo):
scp -r EdgeCompute scc2@100.118.103.39:~/EdgeCompute
```

Then on **SCC2**, rsync to the pole (example pole 2) and run the Orin updater:

```bash
# On SCC2 — sync code (does not overwrite secrets.env on first copy if dest missing; update script preserves secrets)
rsync -az --delete \
  --exclude 'configs/secrets.env' \
  --exclude '.venv' --exclude 'venv' --exclude '__pycache__' \
  ~/EdgeCompute/ pole2@172.16.2.2:/tmp/EdgeCompute/

ssh pole2@172.16.2.2 'sudo mkdir -p /opt/ir4-edge && sudo rsync -a --delete \
  --exclude configs/secrets.env \
  /tmp/EdgeCompute/ /opt/ir4-edge/EdgeCompute/ && \
  cd /opt/ir4-edge/EdgeCompute && sudo ./deploy/orin_update.sh'
```

First-time install on a blank Orin (instead of `orin_update.sh`):

```bash
ssh pole2@172.16.2.2
sudo mkdir -p /opt/ir4-edge
sudo rsync -a /tmp/EdgeCompute/ /opt/ir4-edge/EdgeCompute/
cd /opt/ir4-edge/EdgeCompute
cp configs/secrets.pole-02.env configs/secrets.env
# set IR4_BASE_URL=http://172.16.2.40:9100
sudo ./deploy/orin_bootstrap.sh
```

You can also sync over **Tailscale** when the pole is online (`rsync … pole2@100.104.14.30:…`) — same exclude rules. Prefer LAN (§12b B) when Tailscale is offline.

### 12d. Deploy notes

On **each Orin** (`NN` = `01` … `04`), after SSH (§12b) and code sync (§12c):

```bash
cd /opt/ir4-edge/EdgeCompute
# Ensure IR4_BASE_URL matches §12a
ir4-edge doctor
curl -sS -o /dev/null -w '%{http_code}\n' "$(grep ^IR4_BASE_URL= configs/secrets.env | cut -d= -f2-)/up"
```

Full detail: [EdgeCompute/README.md](EdgeCompute/README.md) · [EdgeCompute/docs/commissioning.md](EdgeCompute/docs/commissioning.md).

---



## Day-2 updates (`05-update.sh`)

```bash
cd /data2/laravel/IR4-Project
bash scripts/05-update.sh
```

Do **not** use `sudo` — Lerd is installed for the SCC user; root falls back to host PHP and cannot resolve `DB_HOST=lerd-mysql` (`CACHE_STORE=database`).

Preserves `.env`, TLS certs, `vendor/`, and `storage/`. Re-run `lerd artisan config:clear` if `.env` changed.

**Frontend on SCC = compiled assets only.** The update script (via `scripts/ensure-no-vite-hmr.sh`) stops the Lerd Vite worker, removes `public/hot` before/after build and after `lerd restart`, then verifies `https://…/login` serves `/build/assets/` and not `@lerd-vite`. Never start Vite on the SCC for day-2 ops.

### If `npm run build` fails on Wayfinder

The Vite plugin runs `wayfinder:generate` via PHP. On SCC, `PATH` usually prefers Lerd’s `php` shim (`~/.local/share/lerd/bin/php`), which runs **inside** the FPM container. That must see the real app tree and use Lerd’s MySQL DNS — not a bare host `php` without Lerd, and not a **stale container mount**.

**After remounting `/data2`** (empty file-manager stub → real 1.8T disk), always restart Lerd before build/update. The container can still be bound to the old empty stub (`scripts/` only) until restart:

```bash
export PATH="$HOME/.local/share/lerd/bin:$HOME/.local/bin:$PATH"
cd /data2/laravel/IR4-Project
findmnt /data2          # must be the 1.8T volume, not OS /
test -f artisan && echo OK
lerd restart            # remounts the site into the PHP container
lerd artisan --version  # must print Laravel Framework … not "Could not open input file: artisan"
bash scripts/05-update.sh
```

Prefer `bash scripts/05-update.sh` over a raw `npm run build`. The update script sets `WAYFINDER_COMMAND` to `lerd artisan wayfinder:generate`. Manual build:

```bash
export PATH="$HOME/.local/share/lerd/bin:$HOME/.local/bin:$PATH"
cd /data2/laravel/IR4-Project
export WAYFINDER_COMMAND="lerd artisan wayfinder:generate"
npm run build
```

To see the real PHP error (instead of the vague Rolldown message):

```bash
cd /data2/laravel/IR4-Project
source scripts/resolve-artisan.sh
ir4_artisan wayfinder:generate --with-form -v
```

Common causes:


| Symptom | Fix |
| --- | --- |
| `Could not open input file: artisan` while host `ls` shows `artisan` | `/data2` was remounted but Lerd still sees the stub — `lerd restart`, then retry |
| File manager / `ls` shows only `scripts/` under the project | `/data2` not mounted — see [SCC-REMOTE-ACCESS.md](SCC-REMOTE-ACCESS.md) §1, then `lerd restart` |
| `Please provide a valid cache path` | `bash scripts/ensure-storage-dirs.sh` (creates `storage/framework/views`, etc.) |
| `.env` missing | `cp .env.example .env` then `lerd artisan key:generate --force` |
| `lerd: command not found` | Install Lerd (`01-setup.sh`) or add `~/.local/share/lerd/bin` to PATH |
| `getaddrinfo for lerd-mysql failed` | Ran update as root/`sudo` — re-run as the SCC user: `bash scripts/05-update.sh` |
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



## 13. Pole camera RTSP

Paste these as `stream_url` on each camera in Hardware → Cameras. User/pass is the same on every pole: **`admin` / `UNity@320@`**. Path is Hikvision main stream (`/Streaming/Channels/101`). `.10` = PTZ, `.11` = bullet.

VLAN map: [EdgeCompute/docs/site-network.md](EdgeCompute/docs/site-network.md). Play from a host on that pole’s VLAN (SCC2 for poles **1–4**, SCC1 for poles **5–8**).

```bash
ffplay -rtsp_transport tcp "rtsp://admin:UNity@320@@172.16.2.10:554/Streaming/Channels/101"
```

### SCC2 — poles 1–4

| Pole | Camera | IP | RTSP |
| --- | --- | --- | --- |
| 1 | PTZ | `172.16.3.10` | `rtsp://admin:UNity@320@@172.16.3.10:554/Streaming/Channels/101` |
| 1 | Bullet | `172.16.3.11` | `rtsp://admin:UNity@320@@172.16.3.11:554/Streaming/Channels/101` |
| 2 | PTZ | `172.16.2.10` | `rtsp://admin:UNity@320@@172.16.2.10:554/Streaming/Channels/101` |
| 2 | Bullet | `172.16.2.11` | `rtsp://admin:UNity@320@@172.16.2.11:554/Streaming/Channels/101` |
| 3 | PTZ | `172.16.1.10` | `rtsp://admin:UNity@320@@172.16.1.10:554/Streaming/Channels/101` |
| 3 | Bullet | `172.16.1.11` | `rtsp://admin:UNity@320@@172.16.1.11:554/Streaming/Channels/101` |
| 4 | PTZ | `172.16.4.10` | `rtsp://admin:UNity@320@@172.16.4.10:554/Streaming/Channels/101` |
| 4 | Bullet | `172.16.4.11` | `rtsp://admin:UNity@320@@172.16.4.11:554/Streaming/Channels/101` |

### SCC1 — poles 5–8

| Pole | Camera | IP | RTSP |
| --- | --- | --- | --- |
| 5 | PTZ | `172.16.5.10` | `rtsp://admin:UNity@320@@172.16.5.10:554/Streaming/Channels/101` |
| 5 | Bullet | `172.16.5.11` | `rtsp://admin:UNity@320@@172.16.5.11:554/Streaming/Channels/101` |
| 6 | PTZ | `172.16.6.10` | `rtsp://admin:UNity@320@@172.16.6.10:554/Streaming/Channels/101` |
| 6 | Bullet | `172.16.6.11` | `rtsp://admin:UNity@320@@172.16.6.11:554/Streaming/Channels/101` |
| 7 | PTZ | `172.16.7.10` | `rtsp://admin:UNity@320@@172.16.7.10:554/Streaming/Channels/101` |
| 7 | Bullet | `172.16.7.11` | `rtsp://admin:UNity@320@@172.16.7.11:554/Streaming/Channels/101` |
| 8 | PTZ | `172.16.8.10` | `rtsp://admin:UNity@320@@172.16.8.10:554/Streaming/Channels/101` |
| 8 | Bullet | `172.16.8.11` | `rtsp://admin:UNity@320@@172.16.8.11:554/Streaming/Channels/101` |

MediaMTX encodes `@` in the password as `%40` when it pulls the stream. Leave the **unencoded** URL in the UI as above.

## Related docs


| Doc                                               | Use when                  |
| ------------------------------------------------- | ------------------------- |
| [DOC-20](Docs/Doc%2020%20deployment%20runbook.md) | Full ops / acceptance     |
| [DOC-19](Docs/Doc%2019%20retention%20backup.md)   | Retention + backup policy |
| [Server/README.md](Server/README.md)              | Hostinger / local dev     |
| [EdgeCompute/README.md](EdgeCompute/README.md)    | Orin gas + RFID agents    |
| [README.md](README.md)                            | Monorepo map              |