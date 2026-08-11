# Orin bring-up runbook

After [commissioning.md](commissioning.md). Host details: [../deploy/README.md](../deploy/README.md).

## 1. Configure + bootstrap (once)

Pick the pole number **NN** (`01`…`04`). Edit configs **before** starting agents:

| File | Set to (example pole 02) |
|---|---|
| `configs/secrets.env` | `IR4_GAS_*` + `IR4_RFID_*` for `DEV-GAS-02` / `DEV-RFID-02` |
| `configs/gas.yaml` → `agent.device_ref` | `DEV-GAS-02` |
| `configs/rfid.yaml` → `agent.reader_ref` | `DEV-RFID-02` |
| `configs/rfid.yaml` → `mqtt.topic` | `zebra/fxr90-02/tags` |

| Pole | `device_ref` | `reader_ref` | MQTT topic |
|------|--------------|--------------|------------|
| 1 | `DEV-GAS-01` | `DEV-RFID-01` | `zebra/fxr90-01/tags` |
| 2 | `DEV-GAS-02` | `DEV-RFID-02` | `zebra/fxr90-02/tags` |
| 3 | `DEV-GAS-03` | `DEV-RFID-03` | `zebra/fxr90-03/tags` |
| 4 | `DEV-GAS-04` | `DEV-RFID-04` | `zebra/fxr90-04/tags` |

Token authenticates the device; `device_ref` / `reader_ref` in the payload must be **that same** reference or ingest returns `FORBIDDEN_REFERENCE` (HTTP still 202).

```bash
cd ~/Downloads/EdgeCompute
cp configs/secrets.example.env configs/secrets.env   # edit tokens for this pole
# edit gas.yaml + rfid.yaml refs/topic for this pole (table above)
sudo ./deploy/orin_bootstrap.sh                      # first time — installs ir4-edge onto PATH
# later: sudo ir4-edge install | apply
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

Production path: **FXR90 IoT Connector → MQTT → Mosquitto on Orin → `ir4-rfid-agent` → IR4 ingest**.

Lab / bring-up (direct WebSocket + local SQLite, no IR4):  
[`Research/Edge/Zebra FXR90 Configuration/`](../../Research/Edge/Zebra%20FXR90%20Configuration/) (`find_reader.sh`, `read_tags.py`, RUNBOOK).

### Facts (verified 2026-08-10)

- FXR90 has **no classic LLRP server** (FX9600-era tools / sllurp do not apply). Control = REST (`/cloud/*`); live data = ZIOTC endpoint (MQTT for IR4, or `wss://` for lab).
- Tag JSON fields: `idHex`, `antenna`, `peakRssi`, `timestamp`. Heartbeats carry `system.temperature` / `radio_control.numTagReads` — agent ignores those for ingest.
- Only **UHF Gen2 / ISO18000-6C** (860–960 MHz). NFC / wrong band = zero reads.
- Factory console: `https://<reader-ip>` (self-signed), `admin` / `change` → forced password change.

### Console checklist (once per reader)

1. Find IP: on Orin, `./find_reader.sh admin 'PASS'` from the Research folder (or arp-scan + `curl …/cloud/localRestLogin`).
2. Regulatory: set region; enable channels as required.
3. LLRP: **CLIENT** mode.
4. Operating mode: **SIMPLE** (lab script also `PUT /cloud/mode {"type":"SIMPLE"}`).
5. **IoT Connector → MQTT** endpoint = Orin LAN IP, port **1883**.  
   Topic = `mqtt.topic` in `configs/rfid.yaml` for **this** pole  
   (`zebra/fxr90-01/tags` … `zebra/fxr90-04/tags` — must match `reader_ref` pole number).  
   Anonymous broker (`edge.yaml` `mosquitto.anonymous: true`): no MQTT user. With auth: `fxr90` + `IR4_MQTT_FXR90_PASSWORD`.
6. Start inventory / cloud start; wave a UHF tag at antenna 1.

MQTT secrets in `configs/secrets.env`:

| Secret | Used by |
|---|---|
| `IR4_MQTT_FXR90_PASSWORD` | FXR90 → Mosquitto when auth is on |
| `IR4_MQTT_PASSWORD` | `ir4-rfid-agent` when `IR4_MQTT_USE_AUTH=1` |

```bash
mosquitto_sub -h 127.0.0.1 -t 'zebra/+/tags'
ir4-rfid-agent --dry-run
ir4-edge logs -f
```

## Config reference

| File | Purpose |
|---|---|
| `configs/edge.yaml` | Boot / install / Mosquitto listener |
| `configs/gas.yaml` | Serial, Modbus map, **per-pole** `device_ref` (`DEV-GAS-NN`) |
| `configs/rfid.yaml` | **Per-pole** MQTT topic `zebra/fxr90-NN/tags`, `reader_ref` (`DEV-RFID-NN`) |
| `configs/secrets.example.env` | Tracked template |
| `configs/secrets.env` | Live secrets (`ir4-edge setup`, gitignored) |

Agents need no CLI flags; systemd sets `IR4_EDGE_CONFIG_DIR` / `IR4_EDGE_VAR_DIR`.

When done: [commissioning.md](commissioning.md#verification-checklist) · [troubleshooting.md](troubleshooting.md).
