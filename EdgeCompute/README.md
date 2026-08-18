# IR4 Edge Compute (Orin)

Gas (YT-98H) and RFID (FXR90) ingest agents. Each runs as its **own** systemd unit — turning one off does not affect the other.


| Doc               | Path                                                 |
| ----------------- | ---------------------------------------------------- |
| Commissioning     | `[docs/commissioning.md](docs/commissioning.md)`     |
| Install / update  | `[deploy/README.md](deploy/README.md)`               |
| Day-2 runbook     | `[docs/runbook.md](docs/runbook.md)`                 |
| Troubleshooting   | `[docs/troubleshooting.md](docs/troubleshooting.md)` |
| Credentials notes | `[credentials.md](credentials.md)`                   |
| Site IPs / SCC split | `[docs/site-network.md](docs/site-network.md)`     |


---



## Setup

**Install and update (all three methods):** [deploy/README.md](deploy/README.md)

Operator UI stays on `https://ir4-project.test`.

### SSH to poles via SCC2 (fallback path)

When a pole is offline on Tailscale, use SCC2 as the jump host:

```bash
# laptop -> SCC2 -> pole 2 Jetson
ssh -J scc2@100.118.103.39 pole2@172.16.2.2
```

Or in two steps:

```bash
ssh scc2@100.118.103.39
ssh pole2@172.16.2.2
```

YAML `device_ref` / `reader_ref` / `mqtt.topic` are fallbacks only. Token UUID must match the ref — mismatch → `FORBIDDEN_REFERENCE`.

---

## Verify after install (pole bring-up)

Use this when configuring a pole (e.g. **pole 1**). Gas today uses a **USB–RS485** dongle; onboard VGA/UART RS485 is not commissioned yet.

### USB / serial hardware

**Plug the YT-98H USB–RS485 adapter into USB port 3** on the Orin (the port that works for the gas sensor on this hardware). Other USB ports may not enumerate the dongle reliably.

```bash
# Serial access (log out/in after first time)
sudo usermod -aG dialout "$USER"
groups | grep dialout || echo "re-login needed for dialout"

# USB adapter present?
ls -l /dev/ttyUSB* /dev/yt98h-rs485 2>/dev/null

# Expect something like:
#   /dev/ttyUSB0
#   /dev/yt98h-rs485 -> ttyUSB0
```

If the symlink is missing but `ttyUSB0` exists:

```bash
sudo udevadm control --reload-rules
sudo udevadm trigger
ls -l /dev/yt98h-rs485 /dev/ttyUSB*
```

Confirm gas agent port (default USB path):

```bash
grep -A5 '^serial:' /opt/ir4-edge/EdgeCompute/configs/gas.yaml
# port should be: "/dev/yt98h-rs485"
```

Reset to USB default if it was changed during UART probing:

```bash
cd /opt/ir4-edge/EdgeCompute
sudo sed -i 's|^  port: .*|  port: "/dev/yt98h-rs485"|' configs/gas.yaml
```

### Secrets + SCC reachability (pole 1 example)

```bash
cd /opt/ir4-edge/EdgeCompute
cp configs/secrets.pole-01.env configs/secrets.env   # pole 2/3/4 → pole-NN
# after install: ir4-edge secrets --pole 1
grep -E '^(IR4_BASE_URL|APP_TIMEZONE|IR4_GAS_|IR4_RFID_)' configs/secrets.env | sed 's/=.*/=***/'

# SCC must answer (use the URL in secrets.env)
source <(grep -E '^IR4_BASE_URL=' configs/secrets.env | sed 's/^/export /')
curl -sS -o /dev/null -w '%{http_code}\n' "${IR4_BASE_URL}/up"
# expect 200
```

### Gas dry-run + agents

Meter: **24 V**, Output Mode = **RS485**, warm-up **2–5 min**.

```bash
cd /opt/ir4-edge/EdgeCompute
# Avoid permission noise on var/ when dry-running as operator:
export IR4_EDGE_VAR_DIR=/tmp/ir4-edge-var
mkdir -p "$IR4_EDGE_VAR_DIR"

timeout 45 ir4-gas-agent --dry-run
# Success: gas fields / DRY-RUN ingest — not "No Modbus response"

sudo systemctl restart ir4-gas-agent ir4-rfid-agent
ir4-edge doctor
ir4-edge status
journalctl -u ir4-gas-agent -n 40 --no-pager
ir4-edge logs -f
```

### Quick failure table

| Symptom | Check |
| -------- | ----- |
| No `/dev/ttyUSB*` | Wrong USB port — use **USB port 3**; reseat dongle |
| No `/dev/yt98h-rs485` | Reload udev (commands above) or set `port: "/dev/ttyUSB0"` |
| `Permission denied` on serial | User not in `dialout` — re-login |
| `No Modbus response` | Meter power / RS485 mode / cable; USB on port 3 |
| `FORBIDDEN_REFERENCE` | Wrong pole secrets file (`secrets.pole-NN.env`) |
| `ir4-edge: command not found` | Bootstrap incomplete — re-run `sudo ./deploy/orin_bootstrap.sh` |

---

## Day-2

```bash
ir4-edge doctor | status | logs -f
sudo ir4-edge restart
ir4-edge up | down
```

Upgrade: [deploy/README.md](deploy/README.md).

---



## Uninstall (remove completely)

Stops agents, removes systemd units, udev rules, CLI links, Mosquitto IR4 config, the install tree, and the service user. Run as root on the Orin.

```bash
# 1) Stop & disable agents
sudo systemctl disable --now ir4-gas-agent ir4-rfid-agent 2>/dev/null || true

# 2) Remove systemd units
sudo rm -f /etc/systemd/system/ir4-gas-agent.service \
           /etc/systemd/system/ir4-rfid-agent.service
sudo systemctl daemon-reload
sudo systemctl reset-failed 2>/dev/null || true

# 3) Remove gas USB–RS485 udev rule + symlink
sudo rm -f /etc/udev/rules.d/99-yt98h-rs485.rules
sudo udevadm control --reload-rules
sudo udevadm trigger || true
sudo rm -f /dev/yt98h-rs485

# 4) Remove IR4 Mosquitto drop-in (keeps the mosquitto package installed)
sudo rm -f /etc/mosquitto/conf.d/ir4-edge.conf \
           /etc/mosquitto/ir4_passwd
sudo systemctl restart mosquitto 2>/dev/null || true

# 5) Remove CLI symlinks
sudo rm -f /usr/local/bin/ir4-edge \
           /usr/local/bin/ir4-gas-agent \
           /usr/local/bin/ir4-rfid-agent
hash -r

# 6) Remove install tree (code + venv + buffers + secrets)
sudo rm -rf /opt/ir4-edge

# 7) Remove service user + leftover group (default: ir4edge)
sudo userdel -r ir4edge 2>/dev/null || sudo userdel ir4edge 2>/dev/null || true
sudo groupdel ir4edge 2>/dev/null || true
```

Optional — also remove host packages installed for RFID (only if nothing else needs them):

```bash
sudo apt-get remove -y mosquitto mosquitto-clients || true
```

Verify clean:

```bash
systemctl status ir4-gas-agent ir4-rfid-agent 2>&1 | head -20
ls /opt/ir4-edge /usr/local/bin/ir4-edge 2>&1
command -v ir4-edge || echo "ir4-edge removed"
```

To reinstall later, follow **Setup** above.

---



## Independence


| Switch                                            | Effect                                 |
| ------------------------------------------------- | -------------------------------------- |
| `configs/edge.yaml` → `services.gas: false`       | No gas unit, no udev; doctor skips gas |
| `configs/edge.yaml` → `services.rfid: false`      | No RFID unit, no Mosquitto install     |
| `gas.yaml` / `rfid.yaml` → `agent.enabled: false` | Process exits cleanly if started       |


Secrets stay in **one** file (`secrets.env`) with namespaced keys (`IR4_GAS_`*, `IR4_RFID_`*, `IR4_MQTT_*`). Missing RFID tokens never block gas, and vice versa.

---



## Config & layout


| File                                                  | Role                           |
| ----------------------------------------------------- | ------------------------------ |
| `configs/edge.yaml`                                   | Which agents + Mosquitto mode  |
| `configs/gas.yaml`                                    | Serial / Modbus / `device_ref` |
| `configs/rfid.yaml`                                   | MQTT topic / `reader_ref`      |
| `credentials.md`                                      | Default UUID + tokens          |
| `configs/secrets.env`                                 | Live secrets (gitignored)      |
| `configs/secrets.pole-01.env` … `secrets.pole-04.env` | Per-pole MQTT + copied tokens  |
| `configs/secrets.example.env`                         | Empty template                 |


**On-device layout** (`install.root` in `edge.yaml`, default `/opt/ir4-edge`):

```
/opt/ir4-edge/
  EdgeCompute/     code + configs (this repo folder — clone/copy here)
  venv/            Python env + ir4-edge CLI
  var/             SQLite outage buffers
```

Edit live YAML/secrets under `/opt/ir4-edge/EdgeCompute/configs/`.

Repo layout:

```
ir4_edge/     Python package (gas / rfid / common / ctl)
configs/      YAML + secrets
deploy/       install, systemd templates, udev
scripts/      setup helpers + optional smoke/validate
docs/         commissioning / runbook / troubleshooting
```

FXR90 lab (WebSocket → local SQLite): `Research/Edge/Zebra FXR90 Configuration/`.  
Production RFID still uses MQTT into `ir4-rfid-agent` (see `docs/runbook.md`).