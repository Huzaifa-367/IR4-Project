# IR4 Edge Compute

Production agents for the **reComputer Super J4012** (Ubuntu 20.04 / Orin NX):

- **Gas** — YT-98H Modbus RTU → `POST /api/ingest/gas-readings`
- **RFID** — Zebra FXR90 ZIOTC MQTT → `POST /api/ingest/tag-readings`

```
FXR90 ──MQTT──► Mosquitto (Orin) ──► ir4-rfid-agent ──► /api/ingest/tag-readings
YT-98H ─RS485─► ir4-gas-agent ──────────────────────► /api/ingest/gas-readings
```

## Quick start

```bash
cd EdgeCompute
sudo ./deploy/orin_bootstrap.sh
./scripts/configure.sh          # tokens + device refs (writes secrets.local.env)
ir4-gas-agent --dry-run
ir4-rfid-agent --dry-run
sudo systemctl enable --now ir4-gas-agent ir4-rfid-agent
```

Default IR4 base URL: `http://192.168.3.149:9100` (Lerd LAN).  
Browsers still use `https://ir4-project.test`.

## Layout

| Path | Role |
|---|---|
| [`configs/gas.yaml`](configs/gas.yaml) | Gas hardware / poll settings |
| [`configs/rfid.yaml`](configs/rfid.yaml) | MQTT topic / RFID settings |
| [`configs/secrets.env`](configs/secrets.env) | Shared base URL + token placeholders |
| `configs/secrets.local.env` | Machine overrides from `configure.sh` (gitignored) |
| [`ir4_edge/`](ir4_edge/) | Python package |
| [`deploy/`](deploy/) | Bootstrap, systemd, udev, Mosquitto |
| [`docs/`](docs/) | Commissioning / runbook / troubleshooting |
| [`scripts/`](scripts/) | `configure.sh` + smoke helpers |

Lab research: [`Research/Edge/`](../Research/Edge/).
