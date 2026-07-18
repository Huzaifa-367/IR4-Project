<?php

namespace App\Jobs;

use App\Services\Backup\DiskSpaceMonitor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class CheckDiskSpace implements ShouldQueue
{
    use Queueable;

    public function handle(DiskSpaceMonitor $monitor): void
    {
        $monitor->check();
    }
}
