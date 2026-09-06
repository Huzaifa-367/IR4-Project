# SCC1 setup runbook

**SCC1-only** guide for `scc1-PowerEdge-R360` (poles **5–8**).

- Shared install scripts / Lerd / TLS flow: [SCC-SETUP.md](SCC-SETUP.md)  
- Field / LiteBeam IPs: [site-network.md](site-network.md)  
- Laptop → Tailscale → HTTPS: [SCC-REMOTE-ACCESS.md](SCC-REMOTE-ACCESS.md)  
- SCC2 (poles 1–4) is a **different site and LAN** — do not copy its office IP/gateway.

---

## 0. Identity (current)

| Item | Value |
|------|--------|
| Host | `scc1-PowerEdge-R360` |
| Linux user | `scc1` |
| Tailscale | `100.96.105.106` (`scc1-poweredge-r360`) |
| Poles | **5–8** only |
| App root | `/data2/laravel/IR4-Project` |
| Office NIC | **Port 1** = `eno8303` |
| Pole / LiteBeam NIC | **Port 2** = `eno8403` |

### SSH

```bash
ssh scc1@100.96.105.106
# or:  ssh scc1@scc1-poweredge-r360
export PATH="$HOME/.local/share/lerd/bin:$HOME/.local/bin:$PATH"
```

---

## 1. Office LAN (Port 1) — permanent internet

This site’s office network is **`192.168.4.0/24`**, gateway **`192.168.4.1`**.

| Profile | NIC | Addresses | Role |
|---------|-----|-----------|------|
| **`scc1-site`** | `eno8303` | **`192.168.4.41/24`**, gw **`192.168.4.1`**, DNS `8.8.8.8` + `1.1.1.1` | Internet + Tailscale uplink |

**Do not use** old values on this box:

| Wrong (legacy) | Why |
|----------------|-----|
| `192.168.2.41` / gw `192.168.2.1` | Other site plan; ARP to `.1` fails here |
| `192.168.3.149` | Older docs / previous site |

### Recreate / repair `scc1-site`

```bash
sudo nmcli con mod scc1-site \
  connection.interface-name eno8303 \
  ipv4.method manual \
  ipv4.addresses 192.168.4.41/24 \
  ipv4.gateway 192.168.4.1 \
  ipv4.dns "8.8.8.8,1.1.1.1" \
  ipv4.never-default no \
  connection.autoconnect yes \
  connection.autoconnect-priority 100

sudo nmcli con up scc1-site
```

If the subnet ever changes, **DHCP once** on Port 1, then lock the leased subnet into `scc1-site` (do not guess from SCC2):

```bash
sudo nmcli con add type ethernet ifname eno8303 con-name scc1-dhcp-probe \
  ipv4.method auto connection.autoconnect no
sudo nmcli con down scc1-site
sudo nmcli con up scc1-dhcp-probe
ip -4 addr show eno8303
ip route
# then rewrite scc1-site from that lease and delete scc1-dhcp-probe
```

### Verify office only (no phone tether)

```bash
ip -4 -br addr          # expect eno8303 = 192.168.4.41/24; no enx… tether
ip route                # default via 192.168.4.1 dev eno8303
ping -c 3 192.168.4.1
ping -c 3 8.8.8.8
ping -c 3 google.com
tailscale ip -4         # 100.96.105.106
```

---

## 2. Tailscale

```bash
# install only if missing
curl -fsSL https://tailscale.com/install.sh | sh
sudo tailscale up
sudo tailscale set --hostname=scc1-poweredge-r360
tailscale ip -4
```

Keep Tailscale up after office LAN works so remote SSH does not depend on being on site.

---

## 3. App `.env` (MediaMTX / URL)

Always use **this SCC’s office IP**, never SCC2’s and never `192.0.2.1` (Lerd `lerd0`).

```bash
cd /data2/laravel/IR4-Project
grep -E '^(APP_URL|MEDIAMTX_|CAMERA_BROWSER_|SESSION_)' .env
```

Expected for production HTTPS:

```env
APP_URL=https://ir4-project.test
SESSION_SECURE_COOKIE=true
CAMERA_BROWSER_STREAM_URL_TEMPLATE=/hls/{reference}/
MEDIAMTX_API_URL=http://192.168.4.41:9997
MEDIAMTX_HOST_IP=192.168.4.41
MEDIAMTX_SOURCE_ON_DEMAND=false
MEDIAMTX_RTSP_TRANSPORT=tcp
# Seed poles 5–8 (DEV-/CAM-*-05…08). Required for SCC1 fresh install.
IR4_SEED_POLES=5,6,7,8
```

Commissioning (LAN HTTP) before TLS:

```env
APP_URL=http://192.168.4.41:9100
SESSION_SECURE_COOKIE=false
IR4_SEED_POLES=5,6,7,8
```

Fresh registry (wipes DB — after confirming):

SCC1 gets **only poles 5–8** (`AST-POLE-05…08`, `DEV-*-05…08`, `CAM-*-05…08`). No poles 1–4, no Main Gate asset.

```bash
# in .env: IR4_SEED_POLES=5,6,7,8
lerd artisan migrate:fresh --force
lerd artisan ir4:install --poles=5,6,7,8 --email=admin@gmail.com --password=12345677
lerd artisan ir4:sync-camera-streams
```

Then sync EdgeCompute and push poles with native 05–08 tokens:

```bash
# from SCC1 ~/EdgeCompute
./deploy/scc_push.sh --poles 5,6,7,8
# each Jetson: ir4-edge secrets --pole N   # N=5…8
```

Or without editing `.env`:

```bash
lerd artisan ir4:install --poles=5,6,7,8 --email=admin@gmail.com --password=12345677
```

After edits:

```bash
lerd artisan config:clear
# optional: lerd artisan ir4:sync-camera-streams   # after cameras/LiteBeams are up
```

Quick health:

```bash
curl -sS -o /dev/null -w '%{http_code}\n' http://127.0.0.1:9100/up   # 200
```

---

## 4. Operator workstation (this site)

Hosts for HTTPS mode (one SCC at a time — same hostname as SCC2):

```text
192.168.4.41   ir4-project.test
```

- Browser: `https://ir4-project.test` (after `lerd secure` + this SCC’s CA).  
- Commissioning: `http://192.168.4.41:9100`.  
- Trust **`lerd-rootCA-scc1.pem`** — SCC2’s CA does not trust SCC1.

Remote laptop (not on `192.168.4.0/24`): point hosts at Tailscale instead and follow [SCC-REMOTE-ACCESS.md](SCC-REMOTE-ACCESS.md):

```text
100.96.105.106   ir4-project.test
```

---

## 5. Pole / LiteBeam NIC (Port 2) — next after office

Leave until office is stable. Then:

| Profile | NIC | Addresses | Role |
|---------|-----|-----------|------|
| **`camera-beam`** | `eno8403` | `172.16.5.40/24`, `172.16.6.40/24`, `172.16.7.40/24`, `172.16.8.40/24`, **`172.16.1.191/24`** | Poles 5–8 + reach managed switch; `ipv4.never-default=yes` |

### Managed switch (fixed)

| Device | IP | Notes |
|--------|-----|--------|
| **SCC1 managed switch** | **`172.16.1.190`** | **Keep this IP** — do not change. SCC1 uses `172.16.1.191` on `camera-beam` only to talk to it. |

```bash
# physical: cable Port 2 → LiteBeam / pole fabric switch
ethtool eno8403 | grep 'Link detected'   # yes
sudo nmcli con up camera-beam
ip -4 addr show eno8403

# smoke
ping -c 2 172.16.1.190   # SCC managed switch (must stay .190)
ping -c 2 172.16.5.21    # pole 5 LiteBeam AP
ping -c 2 172.16.5.20    # pole 5 station
```

### Lock `camera-beam` (schema)

```bash
sudo nmcli con mod camera-beam \
  connection.interface-name eno8403 \
  connection.autoconnect yes \
  ipv4.method manual \
  ipv4.addresses "172.16.5.40/24,172.16.6.40/24,172.16.7.40/24,172.16.8.40/24,172.16.1.191/24" \
  ipv4.gateway "" \
  ipv4.never-default yes \
  ipv6.method ignore
sudo nmcli con up camera-beam
```

Pole agents use VLAN SCC IP, **not** the office IP:

| Pole | Jetson | `IR4_BASE_URL` |
|------|--------|----------------|
| 5 | `172.16.5.2` | `http://172.16.5.40:9100` |
| 6 | `172.16.6.2` | `http://172.16.6.40:9100` |
| 7 | `172.16.7.2` | `http://172.16.7.40:9100` |
| 8 | `172.16.8.2` | `http://172.16.8.40:9100` |

Full LiteBeam / camera table: [site-network.md](site-network.md). Camera RTSP passwords for poles 5–8: [SCC-SETUP.md §13](SCC-SETUP.md).

### ZT411 label printer (office LAN)

IR4 prints raw ZPL from **SCC1** (not the workstation) to `EQUIPMENT_PRINTER_HOST:9100`.

| Item | Value |
|------|--------|
| Target static IP | `192.168.4.210` |
| Port | `9100` |
| SCC1 `.env` | `EQUIPMENT_PRINTER_HOST=192.168.4.210` |

Factory default `192.168.254.254` is **not** on the office LAN — SCC1 cannot reach it until the panel IP is changed.

On the ZT411 touchscreen:

1. Clear **SUPPLIES** / **PAUSE** (load 3.15″×1.85″ labels + ribbon, then Pause to resume).
2. **Menu → Connection → Wired → IPv4** → **Static**:
   - IP `192.168.4.210`
   - Netmask `255.255.255.0`
   - Gateway `192.168.4.1`
3. Save / reboot print server if prompted.
4. Confirm **Active IP (Wired)** shows `192.168.4.210` and NETWORK stays green.
5. From SCC1: `ping 192.168.4.210` and `nc -vz 192.168.4.210 9100`, then Equipment → Print label.

Printer ethernet must share the **same office switch** as SCC1 Port 1 (`192.168.4.0/24`), not an isolated workstation-only segment.

---

## 6. Fresh / reinstall path (same scripts as SCC2)

On SCC1 as `scc1`, with office LAN already working:

```bash
# rsync Server/ from laptop → /data2/laravel/IR4-Project/  (exclude .env, vendor, storage, …)
cd /data2/laravel/IR4-Project
bash scripts/01-setup.sh      # or 05-update.sh for updates
# then 02 boot units, 03 MediaMTX, 04 backups — see SCC-SETUP.md flow
```

Never point poles 5–8 at SCC2, and never copy SCC2 `MEDIAMTX_*` / office IPs into SCC1 `.env`.

---

## 7. Checklist (office done / LiteBeam later)

**Office (done when green):**

- [ ] Port 1 linked; `scc1-site` = `192.168.4.41` / `192.168.4.1`
- [ ] No phone tether required for internet
- [ ] `ping 8.8.8.8` and Tailscale SSH work
- [ ] `MEDIAMTX_*` = `192.168.4.41`
- [ ] `/up` returns 200
- [ ] Login `admin@gmail.com` / `12345677` (change on first login)

**LiteBeam (next):**

- [ ] Port 2 cabled; `camera-beam` up; `172.16.5–8.40` + `172.16.1.191` present
- [ ] Managed switch answers at **`172.16.1.190`** (do not re-IP)
- [ ] LiteBeam AP/station ping OK
- [ ] MediaMTX paths `ready=true`
- [ ] Poles 5–8 agents heartbeat to VLAN `.40:9100`
