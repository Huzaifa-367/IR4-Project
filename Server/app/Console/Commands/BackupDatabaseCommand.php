<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

/**
 * Encrypted daily database backup onto the separate backup volume (DOC-19).
 *
 * Uses BACKUP_ENCRYPTION_KEY from .env. Rotation deletes older archives unless
 * --no-rotate; --keep overrides the backup.keep_count setting.
 *
 * Usage:
 *   php artisan ir4:backup
 *   php artisan ir4:backup --no-rotate
 *   php artisan ir4:backup --keep=14
 */
final class BackupDatabaseCommand extends Command
{
    protected $signature = 'ir4:backup
                            {--no-rotate : Skip deletion of old archives}
                            {--keep= : Override backup.keep_count}';

    protected $description = 'Create an encrypted daily database backup (DOC-19)';

    public function handle(BackupService $backups): int
    {
        $keep = $this->option('keep') !== null ? (int) $this->option('keep') : null;
        $result = $backups->run(
            rotate: ! $this->option('no-rotate'),
            keep: $keep,
        );

        $this->info("Backup written: {$result['path']} ({$result['bytes']} bytes, kept {$result['kept']})");

        return self::SUCCESS;
    }
}
