# Commissioning (SCC + Orin prerequisites)

Do this on the IR4 Server **before** enabling agents on the Orin.

## Server (DOC-05 / DOC-08)

1. Create/select the pole (or gate) **asset**.
2. Add a device of type **`gas_detector`** with a stable `reference` (e.g. `DEV-GAS-01` — Gas Skid Hot Work).
3. Add a device of type **`rfid_reader`** with a stable `reference` (e.g. `DEV-RFID-01` — Pole P-01 North RFID).
4. Bind the RFID reader to the correct zone(s) (DOC-06).
5. Issue an API token for **each** device (Settings → Devices → Token). Keep the
   plaintext and each device **UUID** for `ir4-edge setup`.
6. Confirm the Orin can reach **its** SCC (poles 1–4 → SCC2, poles 5–8 → SCC1). Per-pole IPs: [site-network.md](site-network.md).
7. For RFID live tracking: unknown EPCs are **auto-registered** as `in_stock` tags
   on first ingest. Assign them to workers in Hardware → Tags before they appear
   on the live map. Each reading stores `rssi` (peakRssi) and `antenna`. The
   reader does not report tag distance; proximity (near/mid/far) is derived from RSSI.

## LAN URLs (test vs on-site)

| Who / phase | URL | Why |
|---|---|---|
| **Orin agents (SCC2, poles 1–4)** | `http://172.16.<pole-subnet>.40:9100` | Pole VLAN — see [site-network.md](site-network.md) |
| **SSH to SCC2 (no display / AnyDesk)** | `ssh scc2@100.118.103.39` | Tailscale |
| **Operator browsers (SCC2)** | `http://<SCC2-LAN>:9100` or `https://ir4-project.test` | Operator / MediaMTX LAN |
| **Laptop over Tailscale (either SCC)** | `https://ir4-project.test` after hosts → that SCC | [SCC-REMOTE-ACCESS.md](../../SCC-REMOTE-ACCESS.md) |

Confirm from the Orin: `curl -sS -o /dev/null -w '%{http_code}\n' http://172.16.<subnet>.40:9100/up` (expect `200`). Subnet per pole is in [site-network.md](site-network.md).

## Config files (no copy step)

| File | Role |
|---|---|
| [`../configs/edge.yaml`](../configs/edge.yaml) | Boot enable / install root / Mosquitto listener |
| [`../credentials.md`](../credentials.md) | Default UUID + tokens — `ir4-edge secrets --pole NN` copies these into `secrets.env` |
| [`../configs/secrets.pole-01.env`](../configs/secrets.pole-01.env) … `secrets.pole-04.env` | Per-pole MQTT + copied tokens |
| [`../configs/secrets.example.env`](../configs/secrets.example.env) | Empty template |
| `../configs/secrets.env` | Live secrets (gitignored) |
| [`../configs/gas.yaml`](../configs/gas.yaml) | Serial / Modbus / **per-pole** `device_ref` |
| [`../configs/rfid.yaml`](../configs/rfid.yaml) | **Per-pole** MQTT topic + `reader_ref` |

Before bootstrap, copy the pole file (`NN` = `01`…`04`). Tokens in those files are copied from `credentials.md`:

```bash
cp configs/secrets.pole-NN.env configs/secrets.env
# after install: ir4-edge secrets --pole N
```

YAML `device_ref` / `reader_ref` / `topic` are fallbacks only. Mismatch → ingest `FORBIDDEN_REFERENCE`. See [runbook.md](runbook.md) and [../README.md](../README.md).

```bash
sudo mkdir -p /opt/ir4-edge
# Place EdgeCompute at /opt/ir4-edge/EdgeCompute (clone/copy from monorepo)
cd /opt/ir4-edge/EdgeCompute
cp configs/secrets.pole-01.env configs/secrets.env
sudo ./deploy/orin_bootstrap.sh                      # in-place install
ir4-edge doctor
```

| Variable | Used by |
|---|---|
| `IR4_BASE_URL` | both |
| `APP_TIMEZONE` | both (same as Server — `Asia/Riyadh`) |
| `IR4_GAS_DEVICE_REF` / `IR4_GAS_DEVICE_TOKEN` / `IR4_GAS_DEVICE_UUID` | gas |
| `IR4_RFID_READER_REF` / `IR4_RFID_MQTT_TOPIC` / `IR4_RFID_DEVICE_TOKEN` / `IR4_RFID_DEVICE_UUID` | RFID |
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
    "tag_uid": "AA0004EF55555555AA21BF43",
    "recorded_at": "2026-08-13T09:18:15.603+0000",
    "rssi": -26,
    "antenna": 1
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
| 5 | Tag ingest | 202 with `accepted`; unknown EPCs appear in Hardware → Tags as `in_stock`; assigned tags move live position |
| 6 | Stop agent >5 min | Device goes stale / `device_offline` alert |
| 7 | Restart agent | Heartbeat clears health |
| 8 | Rotate token | Old token → 401; new token works |

Next: [runbook.md](runbook.md).
