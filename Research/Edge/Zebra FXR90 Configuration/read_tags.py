# /// script
# requires-python = ">=3.9"
# dependencies = ["requests", "websocket-client"]
# ///
"""Minimal FXR90 tag reader -> SQLite (ACID).

Usage: uv run read_tags.py <ip> <user> <password>
Writes every tag read to tags.db (table tag_reads) and every reader
temperature/health heartbeat to reader_status. One committed txn per event.
"""
import json, sqlite3, ssl, sys
from datetime import datetime, timezone

import requests, urllib3, websocket

urllib3.disable_warnings()
ip, user, pw = sys.argv[1], sys.argv[2], sys.argv[3]
base = f"https://{ip}"

# --- database (ACID: WAL journal, synchronous=FULL, one transaction per event) ---
db = sqlite3.connect("tags.db", isolation_level=None)
db.execute("PRAGMA journal_mode=WAL")
db.execute("PRAGMA synchronous=FULL")
db.execute("""
CREATE TABLE IF NOT EXISTS tag_reads (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    ts_utc      TEXT    NOT NULL,               -- ISO 8601, host clock
    reader_ts   TEXT,                           -- reader-supplied timestamp, if any
    epc         TEXT    NOT NULL,
    antenna     INTEGER,
    peak_rssi   REAL,
    raw_json    TEXT    NOT NULL                -- full event, for audit/replay
)""")
db.execute("""
CREATE TABLE IF NOT EXISTS reader_status (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    ts_utc        TEXT NOT NULL,
    temp_ambient  REAL,                         -- degrees C, reader ambient sensor
    temp_pa       REAL,                         -- degrees C, power amplifier
    num_tag_reads INTEGER,                      -- cumulative counter from radio_control
    raw_json      TEXT NOT NULL
)""")
db.execute("CREATE INDEX IF NOT EXISTS idx_tag_reads_epc ON tag_reads(epc)")
db.execute("CREATE INDEX IF NOT EXISTS idx_tag_reads_ts  ON tag_reads(ts_utc)")
db.execute("CREATE INDEX IF NOT EXISTS idx_status_ts     ON reader_status(ts_utc)")

def txn(sql, params):
    now = datetime.now(timezone.utc).isoformat()
    db.execute("BEGIN")
    try:
        db.execute(sql, (now, *params))
        db.execute("COMMIT")
    except Exception:
        db.execute("ROLLBACK")
        raise
    return now

def save_tag(ev):
    return txn(
        "INSERT INTO tag_reads (ts_utc, reader_ts, epc, antenna, peak_rssi, raw_json) "
        "VALUES (?, ?, ?, ?, ?, ?)",
        (ev.get("timestamp"), ev["idHex"], ev.get("antenna"),
         ev.get("peakRssi"), json.dumps(ev)),
    )

def save_status(ev):
    temp = (ev.get("system") or {}).get("temperature") or {}
    reads = (ev.get("radio_control") or {}).get("numTagReads")
    return txn(
        "INSERT INTO reader_status (ts_utc, temp_ambient, temp_pa, num_tag_reads, raw_json) "
        "VALUES (?, ?, ?, ?, ?)",
        (temp.get("ambient"), temp.get("pa"), reads, json.dumps(ev)),
    )

# --- reader ---
r = requests.get(f"{base}/cloud/localRestLogin", auth=(user, pw), verify=False, timeout=10)
r.raise_for_status()
token = r.json()["message"]
hdr = {"Authorization": f"Bearer {token}"}

def put(path, body=None):
    return requests.put(f"{base}{path}", headers=hdr, json=body, verify=False, timeout=10)

put("/cloud/stop")  # clean slate, ignore result

ws = websocket.create_connection(f"wss://{ip}/ws",
    header=[f"Authorization: Bearer {token}"],
    sslopt={"cert_reqs": ssl.CERT_NONE})

resp = put("/cloud/start")
if resp.status_code == 422:
    put("/cloud/mode", {"type": "SIMPLE"})
    resp = put("/cloud/start")
resp.raise_for_status()
print("reading -> tags.db ... Ctrl-C to stop")

tags = 0
try:
    while True:
        msg = json.loads(ws.recv())
        data = msg.get("data", msg)
        for ev in (data if isinstance(data, list) else [data]):
            if not isinstance(ev, dict):
                continue
            if ev.get("idHex"):  # tag read
                ts = save_tag(ev)
                tags += 1
                print(f"[{tags}] {ts} {ev['idHex']} ant={ev.get('antenna')} rssi={ev.get('peakRssi')}")
            elif "system" in ev or "radio_control" in ev:  # health heartbeat
                ts = save_status(ev)
                t = (ev.get("system") or {}).get("temperature") or {}
                print(f"    status {ts} temp ambient={t.get('ambient')}C pa={t.get('pa')}C")
except KeyboardInterrupt:
    pass
finally:
    put("/cloud/stop")
    ws.close()
    db.close()
    print(f"stopped. {tags} tag reads in tags.db")
