# Fake poles when Jetsons are offline (or to fill gaps). Same device APIs as EdgeCompute.

Pole set = `IR4_SEED_POLES` (SCC2 default `1,2,3,4`; SCC1 `5,6,7,8`).

- `POST /api/ingest/gas-readings`
- `POST /api/ingest/tag-readings`
- `POST /api/ingest/ppe-violations`
- `POST /api/devices/{uuid}/heartbeat`

**Base URL** (first match): `--url` → `IR4_STANDBY_URL` → `IR4_BASE_URL` → `APP_URL`  
On a laptop use Laravel (`http://127.0.0.1:8000`), not Flutter `:9100`. Expect ingest **`http=202`**.

Device letters: **t** heartbeat · **g** gas · **m** mimic gas · **r** rfid · **h** helmet · **v** vest · **w** heights · **f** fall · **k** mask.  
`t` never posts gas. Run heartbeat and gas/mimic loops in separate terminals. Do not loop `r` / `h` / `v` / `w` / `f` / `k`.

| Command | What it does |
|---|---|
| `php artisan ir4:s help` | Print this command list |
| `php artisan ir4:s t --loop` | Heartbeats for site poles every 30s |
| `php artisan ir4:s g all --loop` | Normal gas for site poles every 30s |
| `php artisan ir4:s g 5 --loop` | Normal gas for pole 5 every 30s |
| `php artisan ir4:s g all --alarm --loop` | Alarm gas for all site poles every 30s |
| `php artisan ir4:s g 5 --alarm --loop` | Alarm gas for pole 5 every 30s |
| `php artisan ir4:s g 5` / `g all` | One-shot ambient (add `--alarm` to spike) |
| `php artisan ir4:s m 5 --loop` | Copy latest pole-5 gas → other site poles every 30s |
| `php artisan ir4:s m 5 --to=8 --loop` | Copy pole-5 gas → pole 8 only |
| `php artisan ir4:s r 5 1` | RFID at pole 5, 1st site EPC |
| `php artisan ir4:s h 5` | Missing helmet, no photo |
| `php artisan ir4:s v 7` | Missing vest, no photo |
| `php artisan ir4:s w 8` | Working at heights (`missing_harness`) |
| `php artisan ir4:s f 8` | Fall detection |
| `php artisan ir4:s k 5` | Missing mask |

RFID indexes are 1-based into `Server/database/data/rfid_tags.php` (physical `AA0004EF55555555…` EPCs). `r 1` is the first of those, not a dummy `E280…` tag.

On SCC from `/data2/laravel/IR4-Project`, prefer `lerd artisan …`. First time PPE without photos may need `lerd artisan migrate --force`.
