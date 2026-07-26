<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VehicleViolation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleViolation>
 */
class VehicleViolationFactory extends Factory
{
    protected $model = VehicleViolation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'observed_at' => now()->subDay(),
            'vehicle_description' => fake()->bothify('Plate ####??'),
            'violation_type' => fake()->randomElement([
                'speeding',
                'seatbelt',
                'unauthorized_parking',
                'reckless_driving',
            ]),
            'description' => fake()->optional()->sentence(),
            'action_taken' => 'Driver briefed and warning issued on site.',
            'camera_id' => null,
            'logged_by' => User::factory(),
        ];
    }
}
