# IR4 Edge Compute (Orin)

Gas (YT-98H) and RFID (FXR90) ingest agents. Each runs as its **own** systemd unit — turning one off does not affect the other.


| Doc               | Path                                                 |
| ----------------- | ---------------------------------------------------- |
| Commissioning     | `[docs/commissioning.md](docs/commissioning.md)`     |
| Day-2 runbook     | `[docs/runbook.md](docs/runbook.md)`                 |
| Troubleshooting   | `[docs/troubleshooting.md](docs/troubleshooting.md)` |
| Credentials notes | `[credentials.md](credentials.md)`                   |


---



## Setup

Install lives at `/opt/ir4-edge/EdgeCompute` (code) with `venv/` + `var/` beside it. Clone or copy the `EdgeCompute` tree there, then bootstrap in place.

```bash
# Place the EdgeCompute folder (from the IR4 monorepo) at the install path:
sudo mkdir -p /opt/ir4-edge
# Example from a full clone:
  sudo rm -rf /tmp/IR4-Project
  git clone https://github.com/Huzaifa-367/IR4-Project /tmp/IR4-Project
  sudo rm -rf /opt/ir4-edge/EdgeCompute
  sudo cp -a /tmp/IR4-Project/EdgeCompute /opt/ir4-edge/EdgeCompute
  sudo rm -rf /tmp/IR4-Project

cd /opt/ir4-edge/EdgeCompute
cp configs/secrets.pole-01.env configs/secrets.env   # NN = this pole (01 … 04)
sudo ./deploy/orin_bootstrap.sh
hash -r
ir4-edge doctor
```

Bootstrap refuses to run from other paths (e.g. `~/Downloads/EdgeCompute`).

### Per-pole secrets

Copy the file for **this** Orin (`NN` = `01` … `04`):

```bash
cp configs/secrets.pole-NN.env configs/secrets.env
```


| Pole | Copy command                                         |
| ---- | ---------------------------------------------------- |
| 1    | `cp configs/secrets.pole-01.env configs/secrets.env` |
| 2    | `cp configs/secrets.pole-02.env configs/secrets.env` |
| 3    | `cp configs/secrets.pole-03.env configs/secrets.env` |
| 4    | `cp configs/secrets.pole-04.env configs/secrets.env` |


YAML `device_ref` / `reader_ref` / `mqtt.topic` are fallbacks only. Token UUID must match the ref — mismatch → `FORBIDDEN_REFERENCE`.

Day-2 CLI:

```bash
cd /opt/ir4-edge/EdgeCompute
sudo ir4-edge apply     # re-run install from this tree
ir4-edge setup          # interactive secrets
ir4-edge doctor
```

---



## Day-2

```bash
ir4-edge status | restart | logs -f | doctor
ir4-edge up | down          # only agents enabled in edge.yaml
```

Upgrade code:

```bash
cd /opt/ir4-edge/EdgeCompute
# refresh tree (git pull if this folder is a checkout, or re-copy from monorepo)
sudo ./deploy/orin_bootstrap.sh
```

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
| `configs/secrets.env`                                 | Live secrets (gitignored)      |
| `configs/secrets.pole-01.env` … `secrets.pole-04.env` | Pre-filled per pole            |
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