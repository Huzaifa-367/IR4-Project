<?php

namespace App\Jobs;

use App\Services\Backup\BackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class BackupDatabase implements ShouldQueue
{
    use Queueable;

    public function handle(BackupService $backups): void
    {
        $backups->run();
    }
}
