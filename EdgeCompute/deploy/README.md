# Install and update EdgeCompute

This is the **only** place with install/update commands.

Software on each pole: `/opt/ir4-edge/` (`EdgeCompute` + `venv` + `var`).

Pick **one** of the three methods below. Use that same method for first install and for later updates. Updates keep existing tokens (`secrets.env`).

| Pole | `--pole` | On the Jetson use secrets file | Jetson SSH | SCC URL the pole talks to |
|---|---|---|---|---|
| 1 | `1` | `secrets.pole-01.env` | `pole1@172.16.3.2` | `http://172.16.3.40:9100` |
| 2 | `2` | `secrets.pole-02.env` | `pole2@172.16.2.2` | `http://172.16.2.40:9100` |
| 3 | `3` | `secrets.pole-03.env` | `pole3@172.16.1.50` | `http://172.16.1.40:9100` |
| 4 | `4` | `secrets.pole-04.env` | `pole4@172.16.4.2` | `http://172.16.4.40:9100` |

After any method: `ir4-edge doctor` on the pole.

---

## Method 1 — Online SCC → pole (poles are offline)

**When:** SCC2 is online. Poles have **no** internet. You push from SCC2 over LAN SSH.

**Where you type:** SCC2 (after copying `EdgeCompute/` there once).

### Put EdgeCompute on SCC2 (laptop, once)

```bash
rsync -az --exclude configs/secrets.env --exclude .venv --exclude .git \
  EdgeCompute/ scc2@scc2-poweredge-r360:~/EdgeCompute/
```

### Install

```bash
ssh scc2@scc2-poweredge-r360
~/EdgeCompute/deploy/scc_install.sh
```

Installs every reachable pole (1–4). Skips poles that are down.

One pole only:

```bash
~/EdgeCompute/deploy/scc_install.sh --poles 2
```

### Update

Same as install:

```bash
~/EdgeCompute/deploy/scc_install.sh
```

---

## Method 2 — Pole with internet

**When:** the Jetson can reach GitHub and pip. You work **on the pole**.

Examples use pole **2**. For pole 1/3/4 change `02` and `--pole 2`.

### Install

```bash
sudo mkdir -p /opt/ir4-edge
git clone --depth 1 https://github.com/Huzaifa-367/IR4-Project /tmp/IR4-Project
sudo cp -a /tmp/IR4-Project/EdgeCompute /opt/ir4-edge/EdgeCompute
cd /opt/ir4-edge/EdgeCompute
cp configs/secrets.pole-02.env configs/secrets.env
sudo ./deploy/orin_bootstrap.sh
sudo ir4-edge secrets --pole 2
ir4-edge doctor
```

### Update

```bash
sudo ir4-edge update
ir4-edge doctor
```

If `ir4-edge update` is missing (very old install):

```bash
git clone --depth 1 https://github.com/Huzaifa-367/IR4-Project /tmp/IR4-Project
sudo /tmp/IR4-Project/EdgeCompute/deploy/orin_update.sh
```

---

## Method 3 — Pole with USB

**When:** no useful SCC hop, and the pole has no internet. You carry a USB stick.

Install and update are the **same two steps**.

### Step A — build the stick (laptop or SCC, needs internet)

```bash
cd EdgeCompute
./deploy/pack_bundle.sh
```

Copy `dist/ir4-edge-offline.tar.gz` onto the USB.

### Step B — on the pole (example: pole 2)

```bash
tar -xzf ir4-edge-offline.tar.gz
cd ir4-edge-offline
sudo ./install.sh --pole 2
```

First run installs. Later runs update (tokens kept).
