# Edge troubleshooting

| Symptom | Fix |
|---|---|
| `ir4-edge install` says use sudo | Run `sudo ir4-edge install --pole N` |
| Another deploy holds the lock | Wait for the other run to finish, or check `ir4-edge deploy-status` for a stale row (>1h auto-reconciled) |
| Deploy failed verification | Fix doctor failures; re-run `sudo ir4-edge apply --pole N` |
| `host.sh` / apt failures on Jetson | Bootstrap skips apt when python/mosquitto already installed. If apt is still required: `sudo apt-mark hold nvidia-l4t-kernel nvidia-l4t-kernel-headers nvidia-l4t-kernel-dtbs nvidia-l4t-display-kernel` then retry |
| Wrong pole SCC URL in secrets | `ir4-edge secrets --pole N` then `sudo ir4-edge restart` |
| Code still under `~/Downloads/EdgeCompute` symlink | `sudo ir4-edge apply --pole N` migrates to `/opt/ir4-edge/EdgeCompute` |
| SCC push skips a pole | Pole Jetson down (ping fail) — expected skip; bring pole up and re-run `scc_push.sh --poles N` |
