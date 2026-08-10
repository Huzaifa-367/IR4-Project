# Troubleshooting

| Symptom | Likely cause |
|---|---|
| Cannot reach IR4 from Orin | Wrong base URL — use `https://ir4.ispc-ai.com` for Hostinger test (or SCC1 `http://192.168.3.149:9100` on-site); run `ir4-edge setup` |
| Missing device token | Run `ir4-edge setup` or fill `configs/secrets.env` |
| `ERR_NAME_NOT_RESOLVED` on workstation | Point DNS at `192.168.3.149` (or hosts: `192.168.3.149 ir4-project.test`) and open `https://ir4-project.test` |
| Permission denied on `/dev/ttyUSB*` | User not in `dialout`; re-login after bootstrap |
| Modbus “silence” with good wiring | A/B swapped; warm-up; wrong port; stock pymodbus often fails on YT-98H quirks — use `ir4_edge.gas.yt98h` |
| CO₂ looks frozen | NDIR averages 15–45 s — wait a minute |
| MQTT connect refused / not authorized | Mosquitto password file / `allow_anonymous false` |
| `FORBIDDEN_REFERENCE` | `device_ref` / `reader_ref` ≠ authenticated device |
| `UNKNOWN_TAG` | EPC not in `rfid_tags` |
| `UNAUTHENTICATED` | Bad/rotated token |
| Rate limited `429` | Inventory flood — lower reader rate or raise `debounce_seconds` |

## Useful commands

```bash
# Health
ir4-edge doctor
ir4-edge status

# Serial presence
ls -l /dev/yt98h-rs485 /dev/serial/by-id/ /dev/ttyUSB*

# Mosquitto
sudo systemctl status mosquitto
mosquitto_sub -h 127.0.0.1 -t 'zebra/+/tags' -u ir4-rfid -P '<password>'

# Agents
ir4-edge logs -f
sudo ir4-edge restart

# Outage buffers
ls -l /opt/ir4-edge/var/
```

Lab Modbus tools: [`Research/Edge/YT98H/`](../../Research/Edge/YT98H/).
