<?php

namespace Database\Factories;

use App\Models\Equipment;
use App\Models\EquipmentCheckout;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentCheckout>
 */
class EquipmentCheckoutFactory extends Factory
{
    protected $model = EquipmentCheckout::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'equipment_id' => Equipment::factory()->checkoutable(),
            'worker_id' => Worker::factory(),
            'checked_out_at' => now(),
            'checked_out_by' => User::factory(),
            'reason' => fake()->optional()->sentence(),
            'zone_id' => null,
            'expected_return_at' => null,
            'returned_at' => null,
            'returned_to' => null,
            'condition_out' => null,
            'condition_in' => null,
            'return_status' => null,
            'return_reason' => null,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }

    public function returned(): static
    {
        return $this->state(fn (): array => [
            'returned_at' => now(),
            'returned_to' => User::factory(),
            'return_status' => 'ok',
        ]);
    }
}
