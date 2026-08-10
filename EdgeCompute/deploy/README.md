# Deploy — Orin host install

```bash
cd EdgeCompute
sudo ./deploy/orin_bootstrap.sh
./scripts/configure.sh
```

| Path | Purpose |
|---|---|
| [`orin_bootstrap.sh`](orin_bootstrap.sh) | Packages, `ir4edge` user, venv, Mosquitto, udev, systemd |
| [`systemd/`](systemd/) | `ir4-gas-agent` / `ir4-rfid-agent` (no CLI flags needed) |
| [`udev/99-yt98h-rs485.rules`](udev/99-yt98h-rs485.rules) | `/dev/yt98h-rs485` |
| [`mosquitto/ir4-edge.conf`](mosquitto/ir4-edge.conf) | Password MQTT on `:1883` |

Install root: `/opt/ir4-edge` (`IR4_EDGE_INSTALL_ROOT` to override).
