# IR4 Edge Compute (Orin)

Gas (YT-98H) and RFID (FXR90) ingest agents. Each runs as its **own** systemd unit —
turning one off does not affect the other.

## Setup

First install (no `ir4-edge` on PATH yet — bootstrap creates it):

```bash
cd ~/Downloads/EdgeCompute
cp configs/secrets.example.env configs/secrets.env
# 1) fill IR4_* tokens/UUIDs for THIS pole (credentials.md / SCC Hardware)
# 2) set per-pole refs in gas.yaml + rfid.yaml (table below)
sudo ./deploy/orin_bootstrap.sh
ir4-edge doctor
```

### Per-pole config (required)

Each Orin is one pole. Token UUID must match the `*_ref` you send — mismatch → `FORBIDDEN_REFERENCE`.

| Pole | `gas.yaml` `device_ref` | `rfid.yaml` `reader_ref` | `rfid.yaml` `mqtt.topic` | Secrets |
|------|-------------------------|--------------------------|--------------------------|---------|
| 1 | `DEV-GAS-01` | `DEV-RFID-01` | `zebra/fxr90-01/tags` | DEV-GAS-01 + DEV-RFID-01 |
| 2 | `DEV-GAS-02` | `DEV-RFID-02` | `zebra/fxr90-02/tags` | DEV-GAS-02 + DEV-RFID-02 |
| 3 | `DEV-GAS-03` | `DEV-RFID-03` | `zebra/fxr90-03/tags` | DEV-GAS-03 + DEV-RFID-03 |
| 4 | `DEV-GAS-04` | `DEV-RFID-04` | `zebra/fxr90-04/tags` | DEV-GAS-04 + DEV-RFID-04 |

FXR90 IoT Connector MQTT topic must equal `mqtt.topic` on that Orin.

After that, day-2 uses the CLI:

```bash
sudo ir4-edge install   # or: sudo ir4-edge apply
ir4-edge setup          # interactive secrets
ir4-edge doctor
```

## Day-2

```bash
ir4-edge status | restart | logs -f | doctor
ir4-edge up | down          # only agents enabled in edge.yaml
```

## Independence

| Switch | Effect |
|---|---|
| `configs/edge.yaml` → `services.gas: false` | No gas unit, no udev, doctor skips gas |
| `configs/edge.yaml` → `services.rfid: false` | No RFID unit, no Mosquitto install |
| `gas.yaml` / `rfid.yaml` → `agent.enabled: false` | Process exits cleanly if started |

Secrets stay in **one** file (`secrets.env`) with namespaced keys (`IR4_GAS_*`, `IR4_RFID_*`, `IR4_MQTT_*`). Missing RFID tokens never block gas, and vice versa.

## Config

| File | Role |
|---|---|
| `configs/edge.yaml` | Which agents + Mosquitto mode |
| `configs/gas.yaml` | Serial / Modbus / `device_ref` |
| `configs/rfid.yaml` | MQTT topic / `reader_ref` |
| `configs/secrets.env` | Live tokens (gitignored) |
| `configs/secrets.example.env` | Template |

## Layout

```
ir4_edge/          # Python package (gas / rfid / common / ctl)
configs/           # YAML + secrets
deploy/            # install, systemd templates, udev
scripts/           # setup helpers + optional smoke/validate
docs/              # commissioning / runbook / troubleshooting
```

FXR90 lab (WebSocket → local SQLite): `Research/Edge/Zebra FXR90 Configuration/`.  
Production RFID still uses MQTT into `ir4-rfid-agent` (see `docs/runbook.md`).
