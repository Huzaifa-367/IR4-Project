<?php

namespace Database\Factories;

use App\Enums\GasType;
use App\Enums\ThresholdDirection;
use App\Models\GasThreshold;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GasThreshold>
 */
class GasThresholdFactory extends Factory
{
    protected $model = GasThreshold::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gas_type' => GasType::H2s,
            'warning_level' => 5,
            'alarm_level' => 10,
            'unit' => 'ppm',
            'direction' => ThresholdDirection::Above,
            'is_active' => true,
        ];
    }
}
