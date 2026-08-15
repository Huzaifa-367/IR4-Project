# SCC2 walkthrough cheat sheet

Fake poles **1–4** when Jetsons are offline. Same device APIs as EdgeCompute:

- `POST /api/ingest/gas-readings`
- `POST /api/ingest/tag-readings`
- `POST /api/ingest/ppe-violations`
- `POST /api/devices/{uuid}/heartbeat`

**Base URL** (first match): `--url` → `IR4_STANDBY_URL` → `IR4_BASE_URL` → `APP_URL`  
On a laptop use Laravel (`http://127.0.0.1:8000`), not Flutter `:9100`. Expect ingest **`http=202`**.

Device letters: **t** tick · **g** gas · **r** rfid · **h** helmet · **v** vest.  
Do not loop `r` / `h` / `v`. A person with a badge does nothing until you type `r`.

| Command | What it does |
|---|---|
| `php artisan ir4:s help` | Print this command list |
| `php artisan ir4:s t --loop` | Every 30s: ambient gas + online for poles 1–4 |
| `php artisan ir4:s g 1` | One ambient gas sample on pole 1 |
| `php artisan ir4:s g 1 --loop` | Normal gas readings every 30s on pole 1 |
| `php artisan ir4:s g 1 --alarm` | One reading above warn thresholds |
| `php artisan ir4:s g 1 --alarm --loop` | Alarm readings every 30s on pole 1 |
| `php artisan ir4:s r 2 3` | RFID at pole 2, tag 3 (omit tag → 1) |
| `php artisan ir4:s h 1` | Missing helmet, no photo |
| `php artisan ir4:s v 3` | Missing vest, no photo |

On SCC2 from `/data2/laravel/IR4-Project`, prefer `lerd artisan …`. First time PPE without photos may need `lerd artisan migrate --force`.
