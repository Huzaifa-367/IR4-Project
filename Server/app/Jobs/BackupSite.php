<?php

namespace App\Jobs;

use App\Services\Backup\BackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Queued stage creation using Lerd's current DB credentials. */
final class BackupSite implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function handle(BackupService $backups): void
    {
        $backups->run();
    }
}
