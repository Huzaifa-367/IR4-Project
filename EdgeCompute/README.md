# IR4 Edge Compute (Orin)

Gas (YT-98H) and RFID (FXR90) ingest agents. Each runs as its **own** systemd unit — turning one off does not affect the other.

| Doc | Path |
|-----|------|
| Commissioning | [`docs/commissioning.md`](docs/commissioning.md) |
| Day-2 runbook | [`docs/runbook.md`](docs/runbook.md) |
| Troubleshooting | [`docs/troubleshooting.md`](docs/troubleshooting.md) |
| Credentials notes | [`credentials.md`](credentials.md) |

---

## Setup

Install lives at **`/opt/ir4-edge/EdgeCompute`** (code) with `venv/` + `var/` beside it. Clone or copy the `EdgeCompute` tree there, then bootstrap in place.

```bash
# Place the EdgeCompute folder (from the IR4 monorepo) at the install path:
sudo mkdir -p /opt/ir4-edge
# Example from a full clone:
#   git clone https://github.com/Huzaifa-367/IR4-Project.git /tmp/IR4-Project
#   sudo rm -rf /opt/ir4-edge/EdgeCompute
#   sudo cp -a /tmp/IR4-Project/EdgeCompute /opt/ir4-edge/EdgeCompute

cd /opt/ir4-edge/EdgeCompute
sudo cp configs/secrets.example.env configs/secrets.env
# 1) fill IR4_* tokens/UUIDs for THIS pole (credentials.md / SCC Hardware)
# 2) set per-pole refs in gas.yaml + rfid.yaml (table below)
sudo ./deploy/orin_bootstrap.sh
hash -r
ir4-edge doctor
```

Bootstrap refuses to run from other paths (e.g. `~/Downloads/EdgeCompute`).

### Per-pole config

Each Orin is one pole. Set refs in **`secrets.env`** (preferred) so they stay with the tokens:

| Pole | `IR4_GAS_DEVICE_REF` | `IR4_RFID_READER_REF` | `IR4_RFID_MQTT_TOPIC` |
|------|----------------------|------------------------|------------------------|
| 1 | `DEV-GAS-01` | `DEV-RFID-01` | `zebra/fxr90-01/tags` |
| 2 | `DEV-GAS-02` | `DEV-RFID-02` | `zebra/fxr90-02/tags` |
| 3 | `DEV-GAS-03` | `DEV-RFID-03` | `zebra/fxr90-03/tags` |
| 4 | `DEV-GAS-04` | `DEV-RFID-04` | `zebra/fxr90-04/tags` |

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

## Independence

| Switch | Effect |
|--------|--------|
| `configs/edge.yaml` → `services.gas: false` | No gas unit, no udev; doctor skips gas |
| `configs/edge.yaml` → `services.rfid: false` | No RFID unit, no Mosquitto install |
| `gas.yaml` / `rfid.yaml` → `agent.enabled: false` | Process exits cleanly if started |

Secrets stay in **one** file (`secrets.env`) with namespaced keys (`IR4_GAS_*`, `IR4_RFID_*`, `IR4_MQTT_*`). Missing RFID tokens never block gas, and vice versa.

---

## Config & layout

| File | Role |
|------|------|
| `configs/edge.yaml` | Which agents + Mosquitto mode |
| `configs/gas.yaml` | Serial / Modbus / `device_ref` |
| `configs/rfid.yaml` | MQTT topic / `reader_ref` |
| `configs/secrets.env` | Live tokens (gitignored) |
| `configs/secrets.example.env` | Template |

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
