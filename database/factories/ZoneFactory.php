<?php

namespace Database\Factories;

use App\Enums\ZoneType;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    protected $model = Zone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'zone_type' => ZoneType::Work,
            'requires_authorization' => false,
            'occupancy_limit' => null,
            'map_x' => null,
            'map_y' => null,
            'map_radius' => null,
            'color' => null,
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    public function gate(): static
    {
        return $this->state(fn (): array => [
            'zone_type' => ZoneType::Gate,
            'name' => 'Main Gate',
        ]);
    }

    public function restricted(): static
    {
        return $this->state(fn (): array => [
            'zone_type' => ZoneType::RestrictedRed,
            'requires_authorization' => true,
        ]);
    }
}
