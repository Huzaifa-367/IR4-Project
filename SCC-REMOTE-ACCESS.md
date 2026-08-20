# SCC remote access runbook (laptop → Tailscale → Lerd HTTPS)

How to reach an already-installed IR4 SCC from a Mac/Linux laptop when you are **not** on the site LAN. This is **not** a fresh install — that is [SCC-SETUP.md](SCC-SETUP.md). Field IPs: [EdgeCompute/docs/site-network.md](EdgeCompute/docs/site-network.md).

Worked example below is **SCC1** (poles 5–8). The same sequence was used on **SCC2** (poles 1–4) on 14 Aug 2026 after a reboot left `/data2` unmounted.

**Do not mix URLs.** Operator browser = `https://ir4-project.test` only. Pole agents keep using `http://172.16.<subnet>.40:9100`. Do not browse `:9100` and `.test` in the same session.

Both SCCs use the hostname `ir4-project.test`. A laptop `/etc/hosts` can point that name at **one** SCC at a time. Swap the Tailscale IP when you switch boxes.

---

## 0. Values (substitute if you are on SCC2)

| Item | **SCC1** (this walkthrough) | SCC2 (already done) |
|---|---|---|
| Hostname | `scc1-PowerEdge-R360` | `scc2-PowerEdge-R360` |
| Linux user | `scc1` | `scc2` |
| Tailscale IPv4 | `100.96.105.106` | `100.118.103.39` |
| MagicDNS | `scc1-poweredge-r360` | `scc2-poweredge-r360` |
| Poles | 5–8 | 1–4 |
| App root | `/data2/laravel/IR4-Project` | same |
| Operator URL | `https://ir4-project.test` | same |
| Lerd LAN HTTP (agents / fallback) | `:9100` on the SCC LAN / VLAN `.40` | same |

SSH as the **Linux** user (`scc1` / `scc2`), not your Mac username.

```bash
ssh scc1@100.96.105.106
# or:  ssh scc1@scc1-poweredge-r360
```

Tailscale SSH needs a tailnet `ssh` ACL (`autogroup:member` → `autogroup:self` → `autogroup:nonroot`) and `sudo tailscale set --ssh=true` on the SCC. If the ACL is missing, use normal `sshd` over the Tailscale IP with a key in `~/.ssh/authorized_keys`.

Confirm you are on the right box:

```bash
hostname     # scc1-PowerEdge-R360
whoami       # scc1
tailscale ip -4
```

### SSH from SCC2 to poles (LAN fallback)

When Tailscale on a pole is offline, hop to SCC2 first, then SSH over the pole VLAN:

```bash
ssh scc2@100.118.103.39
ssh pole2@172.16.2.2
```

Quick map (SCC2 manages poles 1-4):

| Pole | SSH from SCC2 |
|---|---|
| 1 | `ssh pole1@172.16.3.2` |
| 2 | `ssh pole2@172.16.2.2` |
| 3 | `ssh pole3@172.16.1.50` |
| 4 | `ssh pole4@172.16.4.2` |

One-liner from laptop:

```bash
ssh -J scc2@100.118.103.39 pole2@172.16.2.2
```

---

## 1. Confirm the 2 TB app disk is actually mounted

IR4 lives on a **separate** ext4 volume mounted at `/data2` (fstab UUID, `nofail`). OS disk is `/` (PERC). Backups are `/data` (large data volume) and `/backup`. After a dirty reboot the 2 TB SATA SSD can enumerate late or not at all. Boot continues because of `nofail`. Lerd then logs `Removing stale site: ir4-project` and nginx 502s.

**Empty stub (disk not mounted):**

```text
/data2/laravel/IR4-Project/
  scripts/          # often only this, owned by root, timestamps = boot
```

`lost+found` is **absent**. `df /data2` shows the **OS** filesystem, not a 1.8T disk.

**Healthy mount:**

```text
/data2/laravel/IR4-Project/artisan
/data2/laravel/IR4-Project/.env
/data2/laravel/IR4-Project/public/index.php
```

`df -hT /data2` shows ~1.8T (Samsung 870 EVO 2 TB reports as 1.8T). `lsblk` shows a partition mounted on `/data2`.

### 1a. Inventory disks (read-only)

```bash
lsblk -o NAME,SIZE,TYPE,FSTYPE,LABEL,UUID,MOUNTPOINT,MODEL,SERIAL,ROTA,STATE
findmnt /data2
grep data2 /etc/fstab
sudo blkid
```

Typical R360 layout (names `sda`/`sdb`/`sdc` can swap):

| What | Typical size | Mount |
|---|---|---|
| PERC virtual disk (OS) | ~450G | `/`, `/boot/efi`, `/backup` |
| PERC virtual disk (data/backups) | ~3.6T | `/data` |
| **Samsung SSD 870 EVO 2TB** (app) | **1.8T** | **`/data2`** — UUID in fstab |

On SCC2 the app UUID was `53e93dba-e03a-421a-be96-f0e30a35bca1`. **Use whatever UUID is in SCC1’s `/etc/fstab`**, not that value blindly.

If the 2 TB disk is missing from `lsblk`, stop: reseat / iDRAC / cables. Do not recreate the Laravel tree on `/`.

### 1b. Mount without deleting anything

If the SSD is present but `/data2` is an empty stub on `/`:

1. Do **not** `rm -rf /data2`.
2. Mount to a temp path first and confirm `artisan` exists:

```bash
sudo mkdir -p /mnt/data2-disk
sudo mount /data2   # uses fstab UUID — only works if the mountpoint is empty enough
# if that fails because stub dirs exist, mount the UUID on the temp path:
# sudo mount -U <fstab-uuid-for-data2> /mnt/data2-disk
ls /mnt/data2-disk/laravel/IR4-Project/artisan
```

3. To get fstab `/data2` working without deleting the stub: **rename** the placeholder, then mount:

```bash
sudo mv /data2 /data2.root-stub-$(date +%Y%m%d)
sudo mkdir /data2
sudo mount /data2
df -hT /data2
ls /data2/laravel/IR4-Project/artisan /data2/laravel/IR4-Project/.env
```

The stub is preserved under `/data2.root-stub-*`. If you instead `mount` on top of the stub, the stub is hidden (not deleted) until unmount.

4. Verify markers:

```bash
cd /data2/laravel/IR4-Project
test -f artisan && test -f .env && test -f public/index.php && test -f vendor/autoload.php && echo OK
grep -E '^(APP_NAME|APP_ENV|APP_URL|SESSION_SECURE_COOKIE)=' .env
```

5. **Restart Lerd** so the PHP container remounts the real disk (otherwise it still sees the empty stub — `Could not open input file: artisan` on `lerd artisan` / `npm run build`):

```bash
export PATH="$HOME/.local/share/lerd/bin:$HOME/.local/bin:$PATH"
lerd restart
lerd artisan --version   # must print Laravel Framework …
```

Do not overwrite `.env` from `.env.example`. Optional backup only:

```bash
cp -a .env .env.bak-before-remote-$(date +%Y%m%d%H%M%S)
```

---

## 2. Re-link the site in Lerd (keep `.env`)

If Lerd dropped the site while `/data2` was missing:

```bash
export PATH="$HOME/.local/share/lerd/bin:$HOME/.local/bin:$PATH"
cd /data2/laravel/IR4-Project
# skip "Run lerd setup?" so composer/npm/.env are not rewritten
printf 'n\n' | lerd link ir4-project
```

Immediately:

```bash
rm -f public/hot
md5sum .env .env.bak-before-remote-* 2>/dev/null   # .env must match the backup
```

`lerd link` and `lerd secure` both recreate `public/hot`. Laravel then injects `/@lerd-vite/...` (404 on a production box). **Always** `rm -f public/hot` after those commands. Compiled assets must come from `public/build/manifest.json`.

Check:

```bash
curl -sS -o /dev/null -w '%{http_code}\n' http://127.0.0.1:9100/up
# expect 200
```

`:9100` is Lerd’s LAN proxy. Use it only as a health check from the SCC itself, not as the operator URL.

---

## 3. HTTPS on `ir4-project.test` (on the SCC)

```bash
cd /data2/laravel/IR4-Project
lerd secure ir4-project
rm -f public/hot

sed -i 's|^APP_URL=.*|APP_URL=https://ir4-project.test|' .env
grep -q '^SESSION_SECURE_COOKIE=' .env \
  && sed -i 's|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=true|' .env \
  || echo 'SESSION_SECURE_COOKIE=true' >> .env
sed -i 's|^SESSION_DOMAIN=.*|SESSION_DOMAIN=|' .env

lerd artisan config:clear
lerd artisan cache:clear
rm -f public/hot

grep listen ~/.local/share/lerd/nginx/conf.d/ir4-project.test.conf
# must include: listen 443 ssl;

curl -skS -o /dev/null -w '%{http_code}\n' \
  --resolve ir4-project.test:443:127.0.0.1 https://ir4-project.test/up
# expect 200

curl -skS --resolve ir4-project.test:443:127.0.0.1 https://ir4-project.test/login \
  | grep -oE 'src="[^"]+"' | head
# must be /build/assets/...  — if you see @lerd-vite, rm -f public/hot again
```

`APP_URL` must be exactly `https://ir4-project.test`. Setting it to a Tailscale IP or `:9100` makes asset URLs and session cookies CORS/419 when you later open `.test`.

Optional: `lerd reverb:start` if LIVE badges stay disconnected (same origin `/app` on the vhost).

---

## 4. Laptop: point `ir4-project.test` at **this** SCC

EnvKit (and similar) add:

```text
127.0.0.1 ir4-project.test
::1 ir4-project.test
```

Stopping EnvKit does **not** remove those lines. The browser still hits this Mac. `https://ir4-project.test` then fails (nothing on local 443) or shows the **local** app.

**macOS** (admin password). Backup first, then replace only the IR4 lines.

For **SCC1**:

```bash
sudo cp /etc/hosts /etc/hosts.bak-before-scc1-ir4
sudo sed -i '' \
  -e 's/^127.0.0.1[[:space:]]*ir4-project.test/100.96.105.106 ir4-project.test/' \
  -e 's/^::1[[:space:]]*ir4-project.test/# ::1 ir4-project.test/' \
  /etc/hosts
# If the name was already mapped to SCC2, change the IPv4:
sudo sed -i '' 's/^100.118.103.39[[:space:]]*ir4-project.test/100.96.105.106 ir4-project.test/' /etc/hosts

sudo dscacheutil -flushcache
sudo killall -HUP mDNSResponder
grep ir4-project.test /etc/hosts
ping -c 1 ir4-project.test
# must show 100.96.105.106 — not 127.0.0.1
```

For **SCC2**, use `100.118.103.39` instead of `100.96.105.106`.

**Chrome:** turn **off** Settings → Privacy and security → Security → **Use secure DNS**. Otherwise Chrome ignores `/etc/hosts` and `.test` never reaches Tailscale.

Trust the SCC mkcert CA (avoids the red padlock). On the SCC:

```bash
mkcert -CAROOT
# file: $CAROOT/rootCA.pem   — never copy rootCA-key.pem
```

Copy `rootCA.pem` to the laptop (SCP/USB) and on macOS:

```bash
sudo security add-trusted-cert -d -r trustRoot \
  -k /Library/Keychains/System.keychain ~/Downloads/lerd-rootCA.pem
```

Restart Chrome. Until the CA is installed, you can continue past the certificate warning for this host only.

---

## 5. Open the dashboard

```text
https://ir4-project.test/login
https://ir4-project.test/dashboard
```

Hard-refresh (Cmd-Shift-R) after hosts or `public/hot` changes.

### Checks from the laptop

```bash
curl -skS -o /dev/null -w '%{http_code}\n' \
  --resolve ir4-project.test:443:100.96.105.106 https://ir4-project.test/up
# 200 = SCC1 TLS + app are up even if local DNS/Chrome DoH is still wrong
```

| Symptom | Cause | Fix |
|---|---|---|
| Page is local EnvKit / connection refused on 443 | `/etc/hosts` → `127.0.0.1` | Step 4 |
| CORS: scripts from `https://ir4-project.test` while you opened `:9100` | Mixed origins | Use `.test` only; `APP_URL` must match |
| `419 Page Expired` | `SESSION_SECURE_COOKIE=true` on HTTP, or wrong `APP_URL` | Stay on HTTPS `.test`; `config:clear` |
| Console `@lerd-vite` 404 | `public/hot` | `rm -f public/hot` on the SCC |
| Login HTML 502 | `/data2` unmounted or Lerd site unlinked | Steps 1–2 |
| `Could not open input file: artisan` / Wayfinder build fails while host `ls` shows `artisan` | Lerd container still on pre-mount stub | `lerd restart` after `/data2` is healthy (step 1 §5) |
| TLS `unrecognized name` on `:443` | Site HTTP-only | `lerd secure` (step 3) |
| curl DNS timeout, `ping` works | Chrome/curl DoH | Disable Secure DNS; `curl --resolve ...` |

---

## 6. What not to do

- Do not `rm -rf /data2` or the Laravel tree to “fix” the stub.
- Do not run `lerd link` setup (`y`), `lerd env`, or copy `.env.example` over a live `.env`.
- Do not set `APP_URL` to `http://100.x.x.x:9100` if operators will use `https://ir4-project.test`.
- Do not SSH-tunnel `:9100` / `:7073` as the normal operator path (slow; Vite `hot` files; extra 403s on Lerd UI).
- Do not enable `lerd remote-control on` unless you need the Lerd dashboard from the LAN (it sets HTTP Basic auth). IR4 itself does not need that.
- Pole Jetsons stay on VLAN `http://172.16.<n>.40:9100` — do not point them at Tailscale or `.test`.

---

## 7. Switching between SCC1 and SCC2 on one laptop

Edit `/etc/hosts` IPv4 for `ir4-project.test` to the Tailscale IP of the SCC you want, flush DNS, disable Chrome Secure DNS, hard-refresh. Only one SCC is reachable as `https://ir4-project.test` at a time.

---

## 9. Poles not ready (SCC2 walkthrough standby)

Poles 1–4 may not be live. Cheat sheet: [Server/scripts/ir4-standby/Cheatsheet.md](Server/scripts/ir4-standby/Cheatsheet.md).

`ir4:s` calls the **same device APIs** as EdgeCompute (`/api/ingest/*`, heartbeats). Base URL: `--url` → `IR4_STANDBY_URL` → `IR4_BASE_URL` → `APP_URL`. Expect ingest `http=202`. On a laptop, do not target Flutter `:9100`.

Someone at a pole with a badge does **not** update tracking — type `ir4:s r 1` (first site EPC from `database/data/rfid_tags.php`).

```bash
lerd artisan ir4:s t --loop             # heartbeats poles 1–4
lerd artisan ir4:s g all --loop         # ambient gas poles 1–4
lerd artisan ir4:s g 1 --alarm --loop   # hold gas alarm on pole 1
lerd artisan ir4:s r 1                  # RFID at pole 1 (first site EPC)
lerd artisan ir4:s h 1                  # missing helmet (no photo)
lerd artisan ir4:s v 2                  # missing vest (no photo)
```

## 8. Aftercare (optional)

- `sudo mount -a` after reboot; confirm `findmnt /data2` before expecting `:9100` / 443.
- If Lerd nginx fails with `statfs /data2/laravel/IR4-Project: no such file or directory`, the disk is not up yet — wait/mount, then `systemctl --user restart lerd-nginx` (or `lerd start`).
- Keep `.env.bak-*` until you confirm login; then you may delete those backups on the SCC.
- Fresh box install remains [SCC-SETUP.md](SCC-SETUP.md) (`01-setup.sh` … TLS step 10).
