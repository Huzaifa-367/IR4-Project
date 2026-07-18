# Off-site backup copy (DOC-20)

IR4 never ships backups to the cloud. Off-site copies are a **manual operational** step under client custody.

## Daily / weekly procedure

1. Mount or attach the approved offline media (encrypted USB / tape / secondary air-gapped host on the client's backup network).
2. Copy the newest `*.ir4bak` from `/mnt/ir4-backups/daily/` plus the matching size/mtime.
3. Verify the copy with `sha256sum` against the source file.
4. Store media per client policy; record custodian, date, and checksum in the site ops log.
5. Rotate media so at least the last `backup.keep_count` (default 30) nights remain recoverable off-box.

## Handover export (project end)

1. Run `php artisan ir4:export-all`.
2. Verify the `.ir4exp` archive decrypts with the client passphrase and checksum matches the export marker.
3. Deliver archive + passphrase via separate channels.
4. Record chain-of-custody (who received what, when, checksum).
5. Only then run `ir4:secure-wipe`. The verified archive stays immutable; wipe writes a separate receipt beside it.
