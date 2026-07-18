<?php

namespace App\Jobs;

use App\Services\SensorRollupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class BuildSensorRollups implements ShouldQueue
{
    use Queueable;

    public function handle(SensorRollupService $rollups): void
    {
        $rollups->buildCompletedHours();
    }
}
