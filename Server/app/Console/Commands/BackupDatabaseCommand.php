<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

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
