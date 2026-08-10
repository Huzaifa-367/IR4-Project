# FXR90 runbook — verified end to end 2026-08-10

Goal: read UHF tags on the Zebra FXR90 from the Jetson Orin (pole1-desktop) and store them ACID-safe in SQLite.

## The setup as it actually is

- Reader: FXR90 `FXR9071B8F1` at **192.168.8.31** (MAC 88:BC:AC:71:B8:F1), powered by PoE+ injector, antenna on port 1 only.
- Jetson: pole1@pole1-desktop, eth1 = **192.168.8.26**, tailscale = **100.71.23.62**. Reader and Jetson share the 192.168.8.x router.
- Reader web console: `https://192.168.8.31` (self-signed cert, click through the warning). User `admin`, password was changed from factory `change` — use the current one.
- Script: `~/Downloads/zebra-reader/read_tags.py` on the Jetson (master copy in atomcamp/zebra-reader on the Mac). Runs with uv, no venv/setup.

## Key facts learned the hard way

1. FXR90 has NO classic LLRP server like FX9600/FX7500 — sllurp and FX9600-era tooling is useless. It speaks Zebra IoT Connector: REST to control, websocket for data. (The console's LLRP setting is set to CLIENT mode as part of setup.)
2. The websocket is TLS: `wss://<ip>/ws` (plain `ws://` port 80 = connection refused).
3. `/cloud/start` returns 422 if no operating mode is set. Fix: `PUT /cloud/mode {"type":"SIMPLE"}` then start again. The script does this automatically.
4. Auth: `GET /cloud/localRestLogin` with basic auth returns a JWT; pass it as `Authorization: Bearer <token>` everywhere including the websocket handshake. Tokens expire (~hours); script logs in fresh each run.
5. The websocket carries heartbeats (system + radio_control stats, incl. temperature) as well as tag events. Tag events have `idHex`. `numTagReads` in the heartbeat is the ground truth for whether the radio decoded anything.
6. Temperature in heartbeats = reader internals (`ambient` and `pa`, °C), not tagged-item temperature.
7. Only UHF Gen2 / ISO18000-6C tags (860–960 MHz) are readable. NFC cards do nothing. Paper labels fail on metal/liquids.
8. Multi-line pastes into the SSH terminal get mangled — paste heredoc blocks whole, but run normal commands one line at a time. Quote passwords with special chars: `'admin:P@ss'`.

## Run it

```bash
ssh pole1@192.168.8.26            # or pole1@100.71.23.62 via tailscale
cd ~/Downloads/zebra-reader
uv run read_tags.py 192.168.8.31 admin 'YOURPASS'
# wave a UHF tag at antenna 1; Ctrl-C stops cleanly
```

Output DB: `tags.db` next to the script.

- `tag_reads`: id, ts_utc (host clock, ISO 8601 UTC), reader_ts, epc, antenna, peak_rssi, raw_json
- `reader_status`: id, ts_utc, temp_ambient, temp_pa, num_tag_reads (cumulative), raw_json
- ACID: WAL mode, synchronous=FULL, one BEGIN/COMMIT per event, rollback on failure. No filters — every decoded tag is stored; RSSI is metadata (negative dBm; NULL if absent), never a gate.

Inspect:

```bash
sqlite3 tags.db "SELECT ts_utc, epc, antenna, peak_rssi FROM tag_reads ORDER BY id DESC LIMIT 20;"
sqlite3 tags.db "SELECT epc, COUNT(*) reads, MIN(ts_utc) first_seen, MAX(ts_utc) last_seen FROM tag_reads GROUP BY epc;"
sqlite3 tags.db "SELECT ts_utc, temp_ambient, temp_pa, num_tag_reads FROM reader_status ORDER BY id DESC LIMIT 10;"
```

## Diagnosis toolbox (in the order we actually used them)

```bash
ip -br addr                                        # which interface/subnet am I on
sudo arp-scan --interface=eth1 --localnet          # find devices; reader may show unknown vendor
curl -sk -o /dev/null -w "%{http_code}\n" https://IP    # is something serving HTTPS
curl -sk -u 'admin:PASS' https://IP/cloud/localRestLogin # is it the FXR90 (JSON token = yes)
```

Manual control (each line separately):

```bash
TOKEN=$(curl -sk -u 'admin:PASS' https://192.168.8.31/cloud/localRestLogin | python3 -c "import sys,json;print(json.load(sys.stdin)['message'])")
curl -sk -X PUT -H "Authorization: Bearer $TOKEN" https://192.168.8.31/cloud/stop
curl -sk -X PUT -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"type":"SIMPLE"}' https://192.168.8.31/cloud/mode
curl -sk -X PUT -H "Authorization: Bearer $TOKEN" https://192.168.8.31/cloud/start
```

## Troubleshooting

- Connection refused on websocket → you used ws:// instead of wss://.
- 422 on /cloud/start → mode not set (script auto-fixes) or inventory already running (stop first).
- "Unauthorized access" → $TOKEN empty/stale; re-run the TOKEN line, check `echo $TOKEN` prints eyJ...
- Script runs, zero rows, heartbeats show numTagReads: 0 → radio decodes nothing: wrong tag type (must be UHF Gen2), tag too far/badly oriented, or antenna cable loose. Check antenna status + TX power on the console Status page.
- Reader vanished from network → re-run arp-scan; check the injector's data-in cable goes to the router.
- Web console reachable from Jetson but not Mac → router client isolation; tunnel: `ssh -L 8443:192.168.8.31:443 pole1@192.168.8.26` then browse https://localhost:8443.

## From factory reset: full first-time setup

Order matters. All in the web console (`https://<reader-ip>`, factory login admin/change) unless noted.

1. Find the reader's IP: run `./find_reader.sh admin change` on the Jetson (scans the subnet, probes the Zebra login endpoint; the host that returns a token is the reader). Manual fallback: arp-scan + curl toolbox above.
2. Log in with admin/change → console forces a password change. Set it, record it.
3. Regulatory environment: set region, then enable ALL channels (unless a deployment has a specific channel plan — default for us is all channels).
4. LLRP: set to CLIENT mode.
5. Endpoint / IoT Connector: map the Data interface to the websocket endpoint (Communication → Zebra IoT Connector → Configuration). This one usually survives as-is.
6. Operating mode: SIMPLE (the script sets this automatically via `PUT /cloud/mode` if missing).
7. Verify: `uv run read_tags.py <ip> admin 'YOURPASS'` → heartbeats flow, then wave a tag.

Current reader state (done 2026-08-10, don't redo unless factory reset): password changed, all channels enabled, LLRP client, Data → WEBSOCKET_TEST endpoint, mode SIMPLE.

## Tags: no writer needed

- Every UHF label ships with a unique factory-burned EPC. For testing, just wave it — the EPC hex in tags.db IS the test. Two labels = two different EPCs = everything works.
- A "writer" is only for encoding your own numbering scheme later (e.g. PALLET-0042), and even then no extra hardware: the FXR90 writes EPCs itself via the same IoT Connector API (access/write operations).
- EPC memory is rewritable (and lockable/permalockable with an access password); TID is factory-burned and immutable — use TID as the true unique ID if tamper resistance matters.

## Next steps (the pipeline phase)

- Real UHF test tags (860–960 MHz, ISO18000-6C adhesive labels — ordered separately).
- **Production path (IR4):** configure FXR90 IoT Connector → **MQTT** to the Orin Mosquitto broker; `ir4-rfid-agent` in [`EdgeCompute/`](../../../EdgeCompute/) ingests `idHex` JSON to `/api/ingest/tag-readings`. See [`EdgeCompute/docs/runbook.md`](../../../EdgeCompute/docs/runbook.md) § RFID.
- Optional: run this lab script as a systemd service for offline capture only (does not replace the IR4 MQTT agent).
- Change WEBSOCKET_TEST endpoint name to something permanent if it matters.
