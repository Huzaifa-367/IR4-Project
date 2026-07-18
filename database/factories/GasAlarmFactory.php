<?php

namespace Database\Factories;

use App\Enums\GasAlarmLevel;
use App\Enums\GasType;
use App\Models\Device;
use App\Models\GasAlarm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GasAlarm>
 */
class GasAlarmFactory extends Factory
{
    protected $model = GasAlarm::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => Device::factory()->gasDetector(),
            'asset_id' => null,
            'gas_type' => GasType::H2s,
            'level' => GasAlarmLevel::Alarm,
            'reading_value' => 12,
            'threshold_value' => 10,
            'triggered_at' => now(),
            'during_outage' => false,
        ];
    }
}
