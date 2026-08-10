# Orin bring-up runbook

After [commissioning.md](commissioning.md). Host details: [../deploy/README.md](../deploy/README.md).

## 1. Bootstrap + configure

```bash
cd /path/to/IR4-Project/EdgeCompute
sudo ./deploy/orin_bootstrap.sh
./scripts/configure.sh
```

`configure.sh` writes `configs/secrets.local.env` and updates `device_ref` / `reader_ref` / MQTT topic.
Base URL defaults to `http://192.168.3.149:9100`.

Log out/in once if you were just added to `dialout`.

## 2. Gas (YT-98H)

- 24 V, Output Mode = RS485, warm-up 2–5 min.
- USB–RS485 → `/dev/yt98h-rs485` (or set `serial.port` in `configs/gas.yaml`).
- **9600 8N1**, slaves **1–5**, FC03 `start=0 count=32`.

```bash
ir4-gas-agent --dry-run
sudo systemctl enable --now ir4-gas-agent
journalctl -u ir4-gas-agent -f
```

Expect O₂ ≈ 20.9 %VOL, CO₂ ≈ 800–1200 ppm indoors.

## 3. RFID (FXR90)

Mosquitto users `fxr90` + `ir4-rfid` are created by bootstrap. Set the agent password
in `configure.sh` (`IR4_MQTT_PASSWORD`).

**FXR90 Admin Console**

1. IoT Connector → MQTT endpoint = Orin LAN IP, port **1883**.
2. Auth = Mosquitto `fxr90` user.
3. Tag Data topic = `mqtt.topic` in `configs/rfid.yaml`.
4. Start inventory.

```bash
ir4-rfid-agent --dry-run
sudo systemctl enable --now ir4-rfid-agent
journalctl -u ir4-rfid-agent -f
```

## Config reference

| File | Purpose |
|---|---|
| `configs/gas.yaml` | Serial, Modbus map, `device_ref` |
| `configs/rfid.yaml` | MQTT topic, `reader_ref`, debounce |
| `configs/secrets.env` | Shared defaults (tracked) |
| `configs/secrets.local.env` | Tokens / overrides (`configure.sh`) |

Agents need no `--config` flag; they load `configs/*.yaml` and secrets automatically.

When done: [commissioning.md](commissioning.md#verification-checklist) · [troubleshooting.md](troubleshooting.md).
