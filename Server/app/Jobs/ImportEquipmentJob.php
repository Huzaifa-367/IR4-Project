<?php

namespace App\Jobs;

use App\Models\EquipmentImport;
use App\Services\EquipmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ImportEquipmentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $importId,
    ) {}

    public function handle(EquipmentService $equipment): void
    {
        $import = EquipmentImport::query()->findOrFail($this->importId);
        $equipment->processImport($import);
    }
}
