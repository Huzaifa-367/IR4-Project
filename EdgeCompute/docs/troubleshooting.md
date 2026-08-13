# Troubleshooting

| Symptom | Likely cause |
|---|---|
| `orin_bootstrap.sh` dies on `nvidia-l4t-kernel` / dpkg | Jetson L4T package half-configured — bootstrap now skips apt when python/mosquitto already installed. If apt is still required: `sudo apt-mark hold nvidia-l4t-kernel nvidia-l4t-kernel-headers nvidia-l4t-kernel-dtbs nvidia-l4t-display-kernel` then retry, or install packages by hand without `apt-get update` |
| Cannot reach IR4 from Orin | Wrong base URL — on-site poles use `http://192.168.8.40:9100` (`IR4_BASE_URL` in secrets); Hostinger test uses `https://ir4.ispc-ai.com`. Confirm `curl …/up` from the Orin returns 200. |
| Missing device token | Run `ir4-edge setup` or fill `configs/secrets.env` |
| `ERR_NAME_NOT_RESOLVED` on workstation | Point hosts/DNS at SCC (`192.168.8.40 ir4-project.test`) and open `https://ir4-project.test`, or use `http://192.168.8.40:9100` on LAN HTTP |
| Permission denied on `/dev/ttyUSB*` | User not in `dialout`; re-login after bootstrap |
| Modbus “silence” with good wiring | A/B swapped; warm-up; wrong port; stock pymodbus often fails on YT-98H quirks — use `ir4_edge.gas.yt98h` |
| CO₂ looks frozen | NDIR averages 15–45 s — wait a minute |
| MQTT connect refused / not authorized | Mosquitto down / `allow_anonymous false` with bad creds; or FXR90 pointing at wrong Orin IP |
| FXR90 HTTPS OK but no MQTT tags | IoT Connector not mapped to MQTT endpoint; topic ≠ `rfid.yaml`; inventory not started; mode not SIMPLE |
| Agent connected, zero EPCs | Wrong tag type (need UHF Gen2); antenna/TX; check `mosquitto_sub` for JSON with `idHex` |
| `ws://` connection refused (lab) | Use `wss://` — see Research FXR90 RUNBOOK |
| `FORBIDDEN_REFERENCE` | `IR4_GAS_DEVICE_REF` / `IR4_RFID_READER_REF` ≠ authenticated device (wrong pole in secrets.env) |
| Unknown EPC | Auto-registered as `in_stock` (Hardware → Tags); assign to a worker to track |
| `UNAUTHENTICATED` / `Invalid device token` (401) | SCC hash ≠ pole secrets. On SCC: `php artisan db:seed --class=DeviceCredentialsSeeder --force` (or `php artisan ir4:sync-edge-tokens`). On the pole: `ir4-edge secrets --pole NN && sudo ir4-edge restart`. |
| `StartLimitIntervalSec` unknown in `[Service]` | Old Jetson systemd — key belongs in `[Unit]`. Harmless after `sudo ir4-edge update` (units re-rendered). |
| Updating wipes tokens / need uninstall | Do not `rm -rf /opt/ir4-edge`. Use `sudo ir4-edge update` (or `deploy/orin_update.sh`) — keeps `secrets.env`. |
| Rate limited `429` | Inventory flood — lower reader rate or raise `debounce_seconds` |

## Useful commands

```bash
# Health
ir4-edge doctor
ir4-edge status

# Serial presence
ls -l /dev/yt98h-rs485 /dev/serial/by-id/ /dev/ttyUSB*

# Mosquitto / RFID
sudo systemctl status mosquitto
mosquitto_sub -h 127.0.0.1 -t 'zebra/+/tags'

# Agents
ir4-edge logs -f
sudo ir4-edge restart

# Outage buffers
ls -l /opt/ir4-edge/var/
```

Lab tools: [`Research/Edge/YT98H/`](../../Research/Edge/YT98H/) · [`Research/Edge/Zebra FXR90 Configuration/`](../../Research/Edge/Zebra%20FXR90%20Configuration/).
