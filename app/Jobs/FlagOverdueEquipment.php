<?php

namespace App\Jobs;

use App\Services\EquipmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class FlagOverdueEquipment implements ShouldQueue
{
    use Queueable;

    public function handle(EquipmentService $equipment): void
    {
        $equipment->flagOverdue();
    }
}
