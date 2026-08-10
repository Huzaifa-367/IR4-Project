# Orin bring-up runbook

After [commissioning.md](commissioning.md). Host details: [../deploy/README.md](../deploy/README.md).

## 1. Configure + bootstrap (once)

```bash
cd /path/to/IR4-Project/EdgeCompute
cp configs/secrets.example.env configs/secrets.env   # edit tokens, or: ir4-edge setup
sudo ir4-edge install                                  # only installs what edge.yaml enables
```

If secrets are already filled, install **starts** the agents immediately (`services.auto_start` in `configs/edge.yaml`).

One-shot interactive + start (prompts only for enabled agents):

```bash
ir4-edge setup --up
```

Log out/in once if you were just added to `dialout`.

## 2. Everyday ops

```bash
ir4-edge doctor
ir4-edge status
ir4-edge logs -f
ir4-edge restart
```

Agents start on every reboot. To run **gas only** or **RFID only**: set `services.gas` / `services.rfid` in `configs/edge.yaml`, then `sudo ir4-edge apply`. The other unit is disabled and left alone.

## 3. Gas (YT-98H)

- 24 V, Output Mode = RS485, warm-up 2–5 min.
- USB–RS485 → `/dev/yt98h-rs485` (or set `serial.port` in `configs/gas.yaml`).
- **9600 8N1**, slaves **1–5**, FC03 `start=0 count=32`.
- Ingest rate: `poll_interval_seconds: 30` in `configs/gas.yaml` (1 POST/30s). Server has no min interval; live panel goes stale after `health.gas_stale_minutes` (default **5**).

```bash
ir4-gas-agent --dry-run
ir4-edge logs -f
```

Expect O₂ ≈ 20.9 %VOL, CO₂ ≈ 800–1200 ppm indoors.

## 4. RFID (FXR90)

MQTT secrets live in `configs/secrets.env`. Default broker is anonymous (`edge.yaml` → `mosquitto.anonymous: true`); passwords are kept for FXR90 / later lock-down.

| Secret | Used by |
|---|---|
| `IR4_MQTT_FXR90_PASSWORD` | FXR90 IoT Connector (`fxr90` user) when auth is on |
| `IR4_MQTT_PASSWORD` | `ir4-rfid-agent` (`ir4-rfid` user) when `IR4_MQTT_USE_AUTH=1` |

**FXR90 Admin Console**

1. IoT Connector → MQTT endpoint = Orin LAN IP, port **1883**.
2. With anonymous broker: no MQTT user/pass. With auth: `fxr90` + `IR4_MQTT_FXR90_PASSWORD`.
3. Tag Data topic = `mqtt.topic` in `configs/rfid.yaml`.
4. Start inventory.

```bash
ir4-rfid-agent --dry-run
```

## Config reference

| File | Purpose |
|---|---|
| `configs/edge.yaml` | Boot / install / Mosquitto listener |
| `configs/gas.yaml` | Serial, Modbus map, `device_ref` |
| `configs/rfid.yaml` | MQTT topic, `reader_ref`, debounce |
| `configs/secrets.example.env` | Tracked template |
| `configs/secrets.env` | Live secrets (`ir4-edge setup`, gitignored) |

Agents need no CLI flags; systemd sets `IR4_EDGE_CONFIG_DIR` / `IR4_EDGE_VAR_DIR`.

When done: [commissioning.md](commissioning.md#verification-checklist) · [troubleshooting.md](troubleshooting.md).
