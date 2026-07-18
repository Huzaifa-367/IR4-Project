<?php

namespace Database\Factories;

use App\Enums\DeviceType;
use App\Models\Device;
use App\Models\EnvironmentalReading;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EnvironmentalReading>
 */
final class EnvironmentalReadingFactory extends Factory
{
    protected $model = EnvironmentalReading::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $recordedAt = now();

        return [
            'device_id' => Device::factory()->state([
                'device_type' => DeviceType::EnvironmentalSensor,
            ]),
            'asset_id' => null,
            'recorded_at' => $recordedAt,
            'received_at' => $recordedAt,
            'temperature_c' => 28.5,
            'humidity_pct' => 55,
            'wind_speed_ms' => 3.2,
            'extra' => null,
            'is_backfill' => false,
            'clock_skew' => false,
            'event_uid' => (string) Str::uuid(),
        ];
    }
}
