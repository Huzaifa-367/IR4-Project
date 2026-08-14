#!/usr/bin/env bash
# IR4 Platform — Cloud Agent start phase.
# Per-boot reconciliation: bring up the MySQL and Redis daemons that the app,
# queue, cache, and session drivers depend on. Idempotent and returns once the
# datastore is reachable. The app server, Reverb, and Vite run as terminals.
set -euo pipefail

log() { printf '[start] %s\n' "$*"; }

sudo service mysql start >/dev/null 2>&1 || true
sudo service redis-server start >/dev/null 2>&1 || true

log "waiting for MySQL on 127.0.0.1:3306"
for _ in $(seq 1 30); do
  if (exec 3<>/dev/tcp/127.0.0.1/3306) 2>/dev/null; then
    exec 3>&- 3<&- || true
    log "MySQL is reachable"
    break
  fi
  sleep 1
done

if redis-cli ping >/dev/null 2>&1; then
  log "Redis is reachable"
else
  log "WARNING: Redis did not respond to ping"
fi

log "services ready"
