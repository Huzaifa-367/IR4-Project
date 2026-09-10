<?php

namespace App\Jobs;

use App\Models\WorkerImport;
use App\Services\Worker\WorkerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Legacy queue path. Prefer the synchronous HTTP import (no retained file).
 * Still deletes any leftover stored_path after processing.
 */
final class ImportWorkersJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $importId,
    ) {}

    public function handle(WorkerService $workers): void
    {
        $import = WorkerImport::query()->findOrFail($this->importId);
        if (in_array($import->status, ['completed', 'failed'], true)) {
            return;
        }

        $workers->processImport($import);
    }
}
