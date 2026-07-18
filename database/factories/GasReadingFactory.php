<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\GasReading;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GasReading>
 */
class GasReadingFactory extends Factory
{
    protected $model = GasReading::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $now = now();

        return [
            'device_id' => Device::factory()->gasDetector(),
            'asset_id' => null,
            'recorded_at' => $now,
            'received_at' => $now,
            'lel_pct' => 2.5,
            'h2s_ppm' => 1.0,
            'o2_pct' => 20.9,
            'co_ppm' => 5.0,
            'co2_ppm' => null,
            'is_backfill' => false,
            'clock_skew' => false,
            'event_uid' => (string) Str::uuid(),
        ];
    }

    public function live(): static
    {
        return $this->state(fn (): array => [
            'is_backfill' => false,
            'recorded_at' => now(),
            'received_at' => now(),
        ]);
    }

    public function backfill(): static
    {
        return $this->state(fn (): array => [
            'is_backfill' => true,
            'recorded_at' => now()->subHours(2),
            'received_at' => now(),
        ]);
    }
}
