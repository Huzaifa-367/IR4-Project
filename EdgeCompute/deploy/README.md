# Deploy

```bash
cd EdgeCompute
cp configs/secrets.example.env configs/secrets.env
sudo ir4-edge install
ir4-edge doctor
```

| Path | Role |
|---|---|
| `orin_bootstrap.sh` | Host install (`ir4-edge install`) — installs only what `edge.yaml` enables |
| `lib.sh` | Shared helpers |
| `systemd/*.service.in` | Unit templates (rendered at install) |
| `udev/` | `/dev/yt98h-rs485` (gas only) |
