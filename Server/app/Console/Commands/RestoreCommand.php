<?php

namespace App\Console\Commands;

use App\Services\Backup\RestoreService;
use Illuminate\Console\Command;

/**
 * Restore DB/database.sql from a site backup zip into staging (DOC-19).
 *
 * Restoring server/ files is an ops/rsync step — this command only restores the DB.
 * Live restore requires --force-live and --confirm=RESTORE-INTO-LIVE.
 *
 * Usage:
 *   php artisan ir4:restore path/to/ir4-backup-….zip --verify-only
 *   php artisan ir4:restore path/to/ir4-backup-….zip
 *   php artisan ir4:restore path/to/ir4-backup-….zip --force-live --confirm=RESTORE-INTO-LIVE
 */
final class RestoreCommand extends Command
{
    protected $signature = 'ir4:restore
                            {archive : Absolute path or backups-disk relative path}
                            {--connection= : Target DB connection (default: ir4_restore)}
                            {--force-live : Allow restoring into the default connection}
                            {--confirm= : Exact phrase RESTORE-INTO-LIVE when --force-live}
                            {--verify-only : Open archive and list contents only}';

    protected $description = 'Restore or verify a site backup zip (DB into staging by default)';

    public function handle(RestoreService $restore): int
    {
        $archive = (string) $this->argument('archive');
        $connection = (string) ($this->option('connection') ?: config('backup.restore_connection', 'ir4_restore'));
        $forceLive = (bool) $this->option('force-live');

        if ($forceLive && $this->option('confirm') !== 'RESTORE-INTO-LIVE') {
            $this->error('Live restore requires --confirm=RESTORE-INTO-LIVE');

            return self::FAILURE;
        }

        if ($this->option('verify-only')) {
            $result = $restore->verify($archive);
            $this->info('Archive verified.');
            $this->line(json_encode($result['meta'], JSON_PRETTY_PRINT) ?: '{}');
            $this->line('Entries: '.count($result['files']));

            return self::SUCCESS;
        }

        $result = $restore->restore($archive, $connection, $forceLive);

        $this->info("Restored DB into connection [{$connection}].");
        $this->line(json_encode($result['meta'], JSON_PRETTY_PRINT) ?: '{}');

        return self::SUCCESS;
    }
}
