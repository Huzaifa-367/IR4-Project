# Commissioning (SCC + Orin prerequisites)

Do this on the IR4 Server **before** enabling agents on the Orin.

## Server (DOC-05 / DOC-08)

1. Create/select the pole (or gate) **asset**.
2. Add a device of type **`gas_detector`** with a stable `reference` (e.g. `DEV-GAS-01` — Gas Skid Hot Work).
3. Add a device of type **`rfid_reader`** with a stable `reference` (e.g. `DEV-RFID-01` — Pole P-01 North RFID).
4. Bind the RFID reader to the correct zone(s) (DOC-06).
5. Issue an API token for **each** device (Settings → Devices → Token). Keep the
   plaintext and each device **UUID** for `ir4-edge setup`.
6. Confirm the Orin can reach `https://ir4.ispc-ai.com` (Hostinger test). On-site later use SCC1 LAN `http://192.168.3.149:9100`.
7. For RFID live tracking: register physical tags in `rfid_tags` and assign them.
   Unknown EPCs are rejected as `UNKNOWN_TAG` (HTTP still 202).

## LAN URLs (test vs on-site)

| Who / phase | URL | Why |
|---|---|---|
| **Orin agents (current test)** | `https://ir4.ispc-ai.com` | Hostinger; edge and SCC1 are not on the same LAN yet |
| **Orin agents (on-site later)** | `http://192.168.3.149:9100` | SCC1 Lerd LAN expose |
| **Operator browsers (Hostinger)** | `https://ir4.ispc-ai.com` | Production test host |
| **Operator browsers (SCC1)** | `https://ir4-project.test` | dnsmasq `*.test` → `192.168.3.149` |

Confirm from the Orin: `curl -sS -o /dev/null -w '%{http_code}\n' https://ir4.ispc-ai.com/api/health`

## Config files (no copy step)

| File | Role |
|---|---|
| [`../configs/edge.yaml`](../configs/edge.yaml) | Boot enable / install root / Mosquitto listener |
| [`../configs/secrets.example.env`](../configs/secrets.example.env) | Tracked template |
| `../configs/secrets.env` | Live secrets (gitignored) — gas + RFID + MQTT |
| [`../configs/gas.yaml`](../configs/gas.yaml) | Serial / Modbus / `device_ref` |
| [`../configs/rfid.yaml`](../configs/rfid.yaml) | MQTT topic / `reader_ref` |

```bash
cd EdgeCompute
cp configs/secrets.example.env configs/secrets.env   # or: ir4-edge setup
sudo ir4-edge install
ir4-edge doctor
```

| Variable | Used by |
|---|---|
| `IR4_BASE_URL` | both |
| `IR4_GAS_DEVICE_TOKEN` / `IR4_GAS_DEVICE_UUID` | gas |
| `IR4_RFID_DEVICE_TOKEN` / `IR4_RFID_DEVICE_UUID` | RFID |
| `IR4_MQTT_USERNAME` / `IR4_MQTT_PASSWORD` | RFID agent ↔ Mosquitto |
| `IR4_MQTT_FXR90_PASSWORD` | FXR90 ↔ Mosquitto |
| `IR4_DRY_RUN` | both (`1` = no HTTP) |

Agents load a single `configs/secrets.env`.

## Ingest contracts (DOC-08)

Header: `X-Device-Token: <plaintext>`. Expect **202** `{accepted, duplicates, rejected}`.
Max **1000** events/batch. Outages: SQLite buffer keeps `event_uid` and retries.

### Gas — `POST /api/ingest/gas-readings`

```json
{
  "events": [{
    "event_uid": "<uuid>",
    "device_ref": "DEV-GAS-01",
    "recorded_at": "2026-08-10T08:45:01Z",
    "lel_pct": 0.0,
    "h2s_ppm": 0.0,
    "o2_pct": 20.9,
    "co_ppm": 0.0,
    "co2_ppm": 900.0
  }]
}
```

### RFID — `POST /api/ingest/tag-readings`

```json
{
  "events": [{
    "event_uid": "<uuid>",
    "reader_ref": "DEV-RFID-01",
    "tag_uid": "E280116060000203ABC12345",
    "recorded_at": "2026-08-10T08:45:01Z",
    "rssi": -62
  }]
}
```

## Verification checklist

| # | Check | Pass criteria |
|---|---|---|
| 1 | Gas dry-run on live RS-485 | O₂ ~20.9; agent POSTs ~1×/30s (`poll_interval_seconds: 30`) |
| 2 | Live gas ingest | Control Room gas panel updates; heartbeats green |
| 3 | Kill LAN briefly | Events buffer in SQLite; flush on reconnect as backfill (no gas alarms) |
| 4 | FXR90 → Mosquitto | `mosquitto_sub -t 'zebra/+/tags'` shows JSON with `idHex`; agent logs EPC |
| 5 | Lab reader bring-up (optional) | [`Research/…/Zebra FXR90 Configuration`](../../Research/Edge/Zebra%20FXR90%20Configuration/) `read_tags.py` → `tags.db` |
| 5 | Tag ingest | 202 with `accepted`; assigned tags move live position |
| 6 | Stop agent >5 min | Device goes stale / `device_offline` alert |
| 7 | Restart agent | Heartbeat clears health |
| 8 | Rotate token | Old token → 401; new token works |

Next: [runbook.md](runbook.md).
