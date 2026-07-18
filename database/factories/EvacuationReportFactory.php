<?php

namespace Database\Factories;

use App\Enums\EvacuationStatus;
use App\Models\EvacuationReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvacuationReport>
 */
class EvacuationReportFactory extends Factory
{
    protected $model = EvacuationReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => EvacuationStatus::Open,
            'triggered_at' => now(),
            'triggered_by' => User::factory(),
            'force_closed' => false,
        ];
    }
}
