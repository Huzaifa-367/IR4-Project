<?php

namespace App\Jobs;

use App\Models\WorkerImport;
use App\Services\WorkerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ImportWorkersJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $importId,
    ) {}

    public function handle(WorkerService $workers): void
    {
        $import = WorkerImport::query()->findOrFail($this->importId);
        $workers->processImport($import);
    }
}
