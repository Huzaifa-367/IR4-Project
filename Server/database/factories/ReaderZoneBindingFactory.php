<?php

namespace Database\Factories;

use App\Enums\DeviceType;
use App\Models\Device;
use App\Models\ReaderZoneBinding;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReaderZoneBinding>
 */
class ReaderZoneBindingFactory extends Factory
{
    protected $model = ReaderZoneBinding::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => Device::factory()->state(['device_type' => DeviceType::RfidReader]),
            'zone_id' => Zone::factory(),
            'bound_from' => now()->subDay(),
            'bound_until' => null,
            'bound_by' => null,
            'note' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'bound_until' => now(),
        ]);
    }
}
