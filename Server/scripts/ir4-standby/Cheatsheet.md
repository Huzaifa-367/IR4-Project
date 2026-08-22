# SCC2 walkthrough cheat sheet

Fake poles **1–4** when Jetsons are offline. Same device APIs as EdgeCompute:

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
| `php artisan ir4:s t --loop` | Heartbeats only for poles 1–4 every 30s |
| `php artisan ir4:s g all --loop` | Normal gas for poles 1–4 every 30s |
| `php artisan ir4:s g 1 --loop` | Normal gas for pole 1 every 30s |
| `php artisan ir4:s g all --alarm --loop` | Alarm gas for all poles every 30s |
| `php artisan ir4:s g 1 --alarm --loop` | Alarm gas for pole 1 every 30s |
| `php artisan ir4:s g 1` / `g all` | One-shot ambient (add `--alarm` to spike) |
| `php artisan ir4:s m 2 --loop` | Copy latest pole-2 gas from DB → poles 1,3,4 every 30s |
| `php artisan ir4:s m 2 --to=1,4` | Copy pole-2 gas to poles 1 and 4 only |
| `php artisan ir4:s r 2 3` | RFID at pole 2, 3rd site EPC (omit → first; or pass a full EPC) |
| `php artisan ir4:s h 1` | Missing helmet, no photo |
| `php artisan ir4:s v 3` | Missing vest, no photo |
| `php artisan ir4:s w 4` | Working at heights (`missing_harness`) |
| `php artisan ir4:s f 4` | Fall detection |
| `php artisan ir4:s k 1` | Missing mask |

RFID indexes are 1-based into `Server/database/data/rfid_tags.php` (physical `AA0004EF55555555…` EPCs). `r 1` is the first of those, not a dummy `E280…` tag.

On SCC2 from `/data2/laravel/IR4-Project`, prefer `lerd artisan …`. First time PPE without photos may need `lerd artisan migrate --force`.
