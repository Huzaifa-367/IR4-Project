<?php

namespace Database\Factories;

use App\Enums\PortableDeviceStatus;
use App\Models\PortableDevice;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortableDevice>
 */
class PortableDeviceFactory extends Factory
{
    protected $model = PortableDevice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'worker_id' => Worker::factory(),
            'device_type' => 'phone',
            'make_model' => fake()->optional()->word(),
            'serial_number' => fake()->optional()->bothify('SN-####'),
            'approval_reference' => null,
            'status' => PortableDeviceStatus::Approved,
            'approved_at' => now(),
        ];
    }
}
