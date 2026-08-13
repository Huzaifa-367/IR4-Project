# Deploy

Canonical install path: **`/opt/ir4-edge/EdgeCompute`**.

```bash
sudo mkdir -p /opt/ir4-edge
# put EdgeCompute tree at /opt/ir4-edge/EdgeCompute (clone/copy from monorepo)
cd /opt/ir4-edge/EdgeCompute
cp configs/secrets.pole-01.env configs/secrets.env
sudo ./deploy/orin_bootstrap.sh
ir4-edge doctor
```

Bootstrap installs **in place** (venv + systemd + udev). It does not copy from Downloads. Running from any other path exits with an error.

Day-2 update (keeps `configs/secrets.env`, does not uninstall):

```bash
sudo ir4-edge update
# or, if the new script is not on the pole yet:
sudo /tmp/IR4-Project/EdgeCompute/deploy/orin_update.sh
```

| Path | Role |
|---|---|
| `orin_bootstrap.sh` | Host install — venv under `/opt/ir4-edge`, units, udev |
| `orin_update.sh` | Overlay latest code + reinstall package/units |
| `lib.sh` | Shared helpers |
| `systemd/*.service.in` | Unit templates (`WorkingDirectory` / config → this tree) |
| `udev/` | `/dev/yt98h-rs485` (gas only) |
