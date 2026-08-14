# SCC2 walkthrough cheat sheet

Run on SCC2 from `/data2/laravel/IR4-Project`. Poles **1–4** only. Tag default is **1**. RFID is **poles only** (no gate).

A person at a pole with a badge does **not** update the screens — type the RFID command yourself. Do **not** loop `a` / `r` / `h` / `v`. Stop `t --loop` with Ctrl-C when real poles are online.

| Command | What it does |
|---|---|
| `cd /data2/laravel/IR4-Project` | Go into the IR4 app folder. Type every command below from here. |
| `lerd artisan migrate --force` | First time only. Lets PPE events save without a photo. |
| `lerd artisan ir4:s t --loop` | Leave running. Every 30s: normal gas + “online” for poles 1–4. |
| `lerd artisan ir4:s t` | Same, once. Use `--loop` for the visit. |
| `lerd artisan ir4:s a {pole} {tag}` | RFID at that pole. Example: `a 2 3`. Omit tag → 1. |
| `lerd artisan ir4:s r {pole} {tag}` | Same as `a`. Example: `r 1`. |
| `lerd artisan ir4:s h {pole}` | Missing helmet, no photo. Example: `h 1`. |
| `lerd artisan ir4:s v {pole}` | Missing vest, no photo. Example: `v 3`. |
| `lerd artisan ir4:s g {pole}` | One gas spike. Next loop tick returns to normal. Example: `g 1`. |
