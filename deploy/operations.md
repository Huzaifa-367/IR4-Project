# IR4 day-2 operations (DOC-20)

## Restart stuck workers / Reverb

```bash
sudo supervisorctl status ir4:*
sudo supervisorctl restart ir4:ir4-queue-default:*
sudo supervisorctl restart ir4:ir4-queue-ingest:*
sudo supervisorctl restart ir4:ir4-queue-reports:*
sudo supervisorctl restart ir4:ir4-reverb
php /var/www/ir4/artisan queue:restart
```

## Re-run a failed scheduled job

```bash
cd /var/www/ir4
php artisan schedule:list
php artisan schedule:test  # if available
# Or invoke the named job / artisan command directly, e.g.:
php artisan ir4:backup
```

## Recache after `.env` change

```bash
cd /var/www/ir4
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
sudo supervisorctl restart ir4:*
```

## Rotate a leaked device token

1. Operator UI → Hardware → Device → **Rotate token** (DOC-05).
2. Deliver the new plaintext once; old token stops authenticating immediately.
3. Confirm heartbeats resume; acknowledge any offline alert.

## Printer test / troubleshooting

1. Confirm `EQUIPMENT_PRINTER_HOST` / `EQUIPMENT_PRINTER_PORT` in `.env` (not settings table).
2. `nc -vz $EQUIPMENT_PRINTER_HOST 9100`
3. Print a single label from Equipment; if TCP fails the UI offers `.zpl` download.
4. Calibrate media (50×50 mm) on the ZT411 once at commissioning.

## Backup restore drill

```bash
# Copy latest .ir4bak from /mnt/ir4-backups into a working path
php artisan ir4:restore /mnt/ir4-backups/daily/LATEST.ir4bak --connection=ir4_restore
# Verify row counts / sample tables on ir4_restore, then drop staging data when done
```

Never restore into the live connection without an explicit, documented emergency procedure.

## Secure wipe (project end)

1. `php artisan ir4:export-all`
2. Verify archive checksum + decrypt with the client passphrase.
3. Hand over archive + key; record chain of custody (`offsite-backup.md`).
4. `php artisan ir4:secure-wipe --confirm=WIPE-IR4-PROJECT-DATA`
5. Confirm the verified `.ir4exp` is unchanged and a wipe receipt exists beside it.

## Incident audit review

Operator UI → Audit log (`view-audit-log`). Filter by time/user/event. Export CSV if required; export itself is audited.

## Rollback after failed deploy

1. `php artisan down`
2. `git checkout PREVIOUS_TAG`
3. `composer install --no-dev --optimize-autoloader`
4. Restore previous `public/build` if needed, or `npm ci && npm run build`
5. Do **not** roll back migrations unless a reverse migration exists and is reviewed.
6. Recache + `supervisorctl restart ir4:*` + `php artisan up`
