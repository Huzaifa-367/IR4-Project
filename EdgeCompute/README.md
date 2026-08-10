# IR4 Edge Compute (Orin)

Gas (YT-98H) and RFID (FXR90) ingest agents. Each runs as its **own** systemd unit —
turning one off does not affect the other.

## Setup

```bash
cd EdgeCompute
cp configs/secrets.example.env configs/secrets.env   # or: ir4-edge setup
# edit configs/edge.yaml → services.gas / services.rfid
sudo ir4-edge install
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
