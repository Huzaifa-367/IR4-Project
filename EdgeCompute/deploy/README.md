# Install and update EdgeCompute

Two transport methods share one Python deploy pipeline (`ir4_edge.deploy`):

| Method | Path | Where you run commands |
|---|---|---|
| **Direct** | Pole → Internet → Server (git) | On the pole: `sudo ir4-edge install\|update\|apply` |
| **SCC** | SCC → Pole (offline rsync payload) | On SCC: `deploy/scc_push.sh` or `ir4-edge scc push` |

Both support fresh install, update, version check, status, verification, retries, and recovery.
Success is recorded only after **doctor verification** passes on the pole.

Software on each pole: `/opt/ir4-edge/` (`EdgeCompute` + `venv` + `var` + `wheels`).

| Pole | `--pole` | Jetson SSH | SCC URL |
|---|---|---|---|
| 1 | `1` | `pole1@172.16.3.2` | `http://172.16.3.40:9100` |
| 2 | `2` | `pole2@172.16.2.2` | `http://172.16.2.40:9100` |
| 3 | `3` | `pole3@172.16.1.50` | `http://172.16.1.40:9100` |
| 4 | `4` | `pole4@172.16.4.2` | `http://172.16.4.40:9100` |

After any method: `ir4-edge doctor` and `ir4-edge deploy-status`.

---

## Method 1 — SCC → pole (poles offline)

**Where:** SCC2 (or any machine with SSH to poles).

### Sync EdgeCompute to SCC (once per release)

```bash
rsync -az --exclude configs/secrets.env --exclude .venv --exclude .git \
  EdgeCompute/ scc2@scc2-poweredge-r360:~/EdgeCompute/
```

### Install or update all reachable poles

```bash
ssh scc2@scc2-poweredge-r360
~/EdgeCompute/deploy/scc_push.sh
# or: cd ~/EdgeCompute && ir4-edge scc push
```

One pole:

```bash
~/EdgeCompute/deploy/scc_push.sh --poles 2
```

With wheel pack (slow, needs internet on SCC):

```bash
~/EdgeCompute/deploy/scc_push.sh --pack
```

---

## Method 2 — Direct (pole has internet)

**Where:** On the Jetson.

### Fresh install (example pole 2)

```bash
sudo ir4-edge install --pole 2
ir4-edge doctor
```

From a local checkout instead of git:

```bash
sudo ir4-edge install --pole 2 --from /path/to/IR4-Project
```

### Update

```bash
sudo ir4-edge update --pole 2
ir4-edge doctor
```

Auto-detect install vs update:

```bash
sudo ir4-edge apply --pole 2
```

---

## Method 3 — USB offline payload

Same SCC payload layout; build on a laptop with internet:

```bash
cd EdgeCompute
./deploy/pack_bundle.sh
```

On the pole:

```bash
tar -xzf ir4-edge-offline.tar.gz
cd ir4-edge-offline
sudo ./install.sh --pole 2
```

---

## Status and verification

```bash
ir4-edge deploy-status    # SQLite audit + deployed version
ir4-edge verify           # Doctor gate (exit 1 on failure)
ir4-edge doctor           # Full diagnostic report
sudo ir4-edge status      # systemd agent status
```

Deploy state lives in `/opt/ir4-edge/var/deploy_state.sqlite`.

---

## Architecture

```
Controller (ctl.py / install.sh / scc_push.sh)
    → DeployService (lock, retry, recovery)
        → DeployPipeline (shared steps)
            → Transport (Direct git | SCC payload)
            → host.sh (venv, systemd, mosquitto)
            → verify_pole (doctor — required for success)
```

Never marks success until verification passes. Interrupted runs are reconciled via stale-state recovery and file locking.
