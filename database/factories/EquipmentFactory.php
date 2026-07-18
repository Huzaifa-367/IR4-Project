<?php

namespace Database\Factories;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
class EquipmentFactory extends Factory
{
    protected $model = Equipment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'equipment_code' => fake()->unique()->bothify('EQ-#####'),
            'qr_token' => fake()->unique()->uuid(),
            'name' => fake()->words(3, true),
            'equipment_type' => fake()->randomElement(['fire extinguisher', 'safety harness', 'generator']),
            'status' => EquipmentStatus::InService,
            'is_checkoutable' => false,
            'location_label' => fake()->optional()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'next_inspection_due' => null,
            'next_service_due' => null,
            'created_by' => User::factory(),
        ];
    }

    public function checkoutable(): static
    {
        return $this->state(fn (): array => [
            'is_checkoutable' => true,
        ]);
    }
}
