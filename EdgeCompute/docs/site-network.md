# Site network — poles, SCC split, addressing

Authoritative IP plan for field hardware. Remote access to SCCs is **SSH over Tailscale only** (no AnyDesk, no local display).

## SCC ownership

| SCC | Host | Linux user | Tailscale | Poles |
|---|---|---|---|---|
| **SCC2** | `scc2-PowerEdge-R360` | `scc2` | `100.118.103.39` (`scc2-poweredge-r360`) | **1–4** |
| **SCC1** | `scc1-PowerEdge-R360` | `scc1` | `100.96.105.106` (`scc1-poweredge-r360`) | **5–8** |

Poles ingest to **their** SCC (`IR4_BASE_URL`). Do not point poles 1–4 at SCC1 or poles 5–8 at SCC2.

### SSH (from a laptop on the same tailnet)

```bash
ssh scc2@100.118.103.39          # SCC2 — poles 1–4
ssh scc1@100.96.105.106          # SCC1 — poles 5–8
# or:  ssh scc2@scc2-poweredge-r360
```

Operator browser from a laptop (HTTPS `.test`, `/data2` mount, Lerd link, `/etc/hosts`): [SCC-REMOTE-ACCESS.md](../../SCC-REMOTE-ACCESS.md).

Use the Linux account (`scc2` / `scc1`), not the Mac username. Tailscale SSH needs the tailnet `ssh` ACL (`autogroup:member` → `autogroup:self` → `autogroup:nonroot`).

## Address pattern

Each pole is `172.16.<subnet>.<host>`.

| Pole | Subnet (3rd octet) | Notes |
|---|---|---|
| 1 | **3** | Subnet number is swapped with pole 3 |
| 2 | 2 | Standard suffixes |
| 3 | **1** | Subnet swapped with pole 1; Jetson / LiteBeam suffixes differ |
| 4 | 4 | Standard suffixes |
| 5 | 5 | SCC1 |
| 6 | 6 | SCC1 |
| 7 | 7 | SCC1 |
| 8 | 8 | SCC1 |

Standard last-octet (poles 1–2 and 4–8):

| Device | Last octet |
|---|---|
| TSW202 switch | `.1` |
| Jetson J4012 | `.2` |
| Camera 1 PTZ | `.10` |
| Camera 2 bullet | `.11` |
| RFID FXR90 | `.12` |
| LiteBeam station | `.20` |
| LiteBeam AP (SCC) | `.21` |
| SCC on this VLAN | `.40` |

**Pole 3 exceptions:** Jetson `.50`, LiteBeam station `.91`, LiteBeam AP `.81` (switch / cameras / RFID / SCC still follow the standard last octets on subnet `1`).

## Full table

| Device | Pole 1 | Pole 2 | Pole 3 | Pole 4 | Pole 5 | Pole 6 | Pole 7 | Pole 8 |
|---|---|---|---|---|---|---|---|---|
| TSW202 switch | 172.16.3.1 | 172.16.2.1 | 172.16.1.1 | 172.16.4.1 | 172.16.5.1 | 172.16.6.1 | 172.16.7.1 | 172.16.8.1 |
| Jetson J4012 | 172.16.3.2 | 172.16.2.2 | **172.16.1.50** | 172.16.4.2 | 172.16.5.2 | 172.16.6.2 | 172.16.7.2 | 172.16.8.2 |
| Camera 1 PTZ | 172.16.3.10 | 172.16.2.10 | 172.16.1.10 | 172.16.4.10 | 172.16.5.10 | 172.16.6.10 | 172.16.7.10 | 172.16.8.10 |
| Camera 2 bullet | 172.16.3.11 | 172.16.2.11 | 172.16.1.11 | 172.16.4.11 | 172.16.5.11 | 172.16.6.11 | 172.16.7.11 | 172.16.8.11 |
| RFID FXR90 | 172.16.3.12 | 172.16.2.12 | 172.16.1.12 | 172.16.4.12 | 172.16.5.12 | 172.16.6.12 | 172.16.7.12 | 172.16.8.12 |
| LiteBeam station | 172.16.3.20 | 172.16.2.20 | **172.16.1.91** | 172.16.4.20 | 172.16.5.20 | 172.16.6.20 | 172.16.7.20 | 172.16.8.20 |
| LiteBeam AP (SCC) | 172.16.3.21 | 172.16.2.21 | **172.16.1.81** | 172.16.4.21 | 172.16.5.21 | 172.16.6.21 | 172.16.7.21 | 172.16.8.21 |
| SCC on this VLAN | 172.16.3.40 | 172.16.2.40 | 172.16.1.40 | 172.16.4.40 | 172.16.5.40 | 172.16.6.40 | 172.16.7.40 | 172.16.8.40 |

From a pole Jetson, IR4 HTTP is that row’s **SCC on this VLAN** (port `9100`), e.g. pole 1 → `http://172.16.3.40:9100`. Operator / Tailscale management of the R360 is a separate path (`ssh scc2@100.118.103.39`).

### SSH to pole Jetson / desktop — two ways

| Path | When | Example (pole 2) |
|---|---|---|
| **A — Online (Tailscale direct)** | Device **active** on tailnet | `ssh pole2@100.104.14.30` or `ssh pole2@pole2-desktop` |
| **B — LAN (via SCC)** | Tailscale offline | `ssh -J scc2@100.118.103.39 pole2@172.16.2.2` |

**A — bring the pole onto Tailscale (once), then direct access:**

```bash
# On Jetson (first time — often over LAN SSH)
curl -fsSL https://tailscale.com/install.sh | sh
sudo tailscale up
sudo hostnamectl set-hostname pole2-desktop
sudo tailscale set --hostname=pole2-desktop
sudo tailscale set --ssh=true   # optional; also keep ~/.ssh/authorized_keys
tailscale ip -4

# From laptop
tailscale status | grep -i pole2
ssh pole2@100.104.14.30
```

**B — LAN via SCC2 (always works if radio/VLAN is up):**

```bash
ssh scc2@100.118.103.39
ssh pole2@172.16.2.2
```

`IR4_BASE_URL` for agents stays on the **VLAN** (`http://172.16.x.40:9100`), not Tailscale.

Full tables + rsync: [SCC-SETUP.md §12](../../SCC-SETUP.md).
