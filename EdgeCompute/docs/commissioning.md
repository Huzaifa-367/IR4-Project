# Commissioning (SCC + Orin prerequisites)

Do this on the IR4 Server **before** enabling agents on the Orin.

## Server (DOC-05 / DOC-08)

1. Create/select the pole (or gate) **asset**.
2. Add a device of type **`gas_detector`** with a stable `reference` (e.g. `pole1-gas`).
3. Add a device of type **`rfid_reader`** with a stable `reference` (e.g. `pole1-reader`).
4. Bind the RFID reader to the correct zone(s) (DOC-06).
5. Issue an API token for **each** device (Settings → Devices → Token). Keep the
   plaintext and each device **UUID** for `./scripts/configure.sh`.
6. Confirm the Orin can reach `http://192.168.3.149:9100` (device LAN / DOC-20).
7. For RFID live tracking: register physical tags in `rfid_tags` and assign them.
   Unknown EPCs are rejected as `UNKNOWN_TAG` (HTTP still 202).

## LAN URLs (SCC workstation vs Orin)

| Who | URL | Why |
|---|---|---|
| **Orin edge agents** | `http://192.168.3.149:9100` | Lerd LAN-exposed HTTP; no DNS or TLS trust needed |
| **Operator browsers** | `https://ir4-project.test` | `APP_URL`; dnsmasq `*.test` → `192.168.3.149` |

Confirm from the Orin: `curl -sS -o /dev/null -w '%{http_code}\n' http://192.168.3.149:9100/`

## Config files (no copy step)

| File | Role |
|---|---|
| [`../configs/secrets.env`](../configs/secrets.env) | Base URL + empty token placeholders (tracked) |
| `../configs/secrets.local.env` | Filled by `configure.sh` (gitignored) |
| [`../configs/gas.yaml`](../configs/gas.yaml) | Serial / Modbus / `device_ref` |
| [`../configs/rfid.yaml`](../configs/rfid.yaml) | MQTT topic / `reader_ref` |

```bash
cd EdgeCompute
./scripts/configure.sh
```

| Variable | Used by |
|---|---|
| `IR4_BASE_URL` | both |
| `IR4_GAS_DEVICE_TOKEN` / `IR4_GAS_DEVICE_UUID` | gas |
| `IR4_RFID_DEVICE_TOKEN` / `IR4_RFID_DEVICE_UUID` | RFID |
| `IR4_MQTT_USERNAME` / `IR4_MQTT_PASSWORD` | RFID |
| `IR4_DRY_RUN` | both (`1` = no HTTP) |

Agents auto-load `secrets.env` then `secrets.local.env` (local wins).

## Ingest contracts (DOC-08)

Header: `X-Device-Token: <plaintext>`. Expect **202** `{accepted, duplicates, rejected}`.
Max **1000** events/batch. Outages: SQLite buffer keeps `event_uid` and retries.

### Gas — `POST /api/ingest/gas-readings`

```json
{
  "events": [{
    "event_uid": "<uuid>",
    "device_ref": "pole1-gas",
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
    "reader_ref": "pole1-reader",
    "tag_uid": "E280116060000203ABC12345",
    "recorded_at": "2026-08-10T08:45:01Z",
    "rssi": -62
  }]
}
```

## Verification checklist

| # | Check | Pass criteria |
|---|---|---|
| 1 | Gas dry-run on live RS-485 | O₂ ~20.9, CO₂ updating every 15–45 s |
| 2 | Live gas ingest | Control Room gas panel updates; heartbeats green |
| 3 | Kill LAN briefly | Events buffer in SQLite; flush on reconnect as backfill (no gas alarms) |
| 4 | FXR90 → Mosquitto | `mosquitto_sub` sees tag JSON; agent logs EPC |
| 5 | Tag ingest | 202 with `accepted`; assigned tags move live position |
| 6 | Stop agent >5 min | Device goes stale / `device_offline` alert |
| 7 | Restart agent | Heartbeat clears health |
| 8 | Rotate token | Old token → 401; new token works |

Next: [runbook.md](runbook.md).
