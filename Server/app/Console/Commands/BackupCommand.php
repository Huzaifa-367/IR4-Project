<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

/**
 * Daily full live snapshot: server/ + DB/ as a timestamped zip on the backup volume.
 *
 * On-prem: app on /data2, archives on /data (BACKUP_DISK_ROOT). Scheduled at 02:30
 * before retention prune (03:15) so aged telemetry is still archived that night.
 *
 * Usage:
 *   php artisan ir4:backup
 *   php artisan ir4:backup --no-rotate
 *   php artisan ir4:backup --keep=14
 */
final class BackupCommand extends Command
{
    protected $signature = 'ir4:backup
                            {--no-rotate : Skip deletion of old archives}
                            {--keep= : Override backup.keep_count}';

    protected $description = 'Create a timestamped server+DB zip backup on the backup volume';

    public function handle(BackupService $backups): int
    {
        $keep = $this->option('keep') !== null ? (int) $this->option('keep') : null;
        $result = $backups->run(
            rotate: ! $this->option('no-rotate'),
            keep: $keep,
        );

        $this->info("Backup written: {$result['path']} ({$result['bytes']} bytes, kept {$result['kept']})");
        $this->line("sha256: {$result['sha256']}");

        return self::SUCCESS;
    }
}
