<?php

namespace Database\Factories;

use App\Enums\MusterStatus;
use App\Models\EvacuationReport;
use App\Models\EvacuationReportEntry;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvacuationReportEntry>
 */
class EvacuationReportEntryFactory extends Factory
{
    protected $model = EvacuationReportEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'evacuation_report_id' => EvacuationReport::factory(),
            'worker_id' => Worker::factory(),
            'last_zone_id' => null,
            'last_seen_at' => now(),
            'muster_status' => MusterStatus::Unaccounted,
        ];
    }
}
