<?php

namespace App\Jobs;

use App\Services\BackupStatusService;
use App\Services\RetentionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class PruneRawSensorData implements ShouldQueue
{
    use Queueable;

    public function handle(BackupStatusService $backups, RetentionService $retention): void
    {
        if (! $backups->canPrune()) {
            return;
        }
        $retention->pruneRawSensorData();
    }
}
