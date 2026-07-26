<?php

namespace Database\Factories;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_type' => AssetType::Pole,
            'name' => 'Pole '.fake()->unique()->numberBetween(1, 999),
            'identifier' => 'AST-'.strtoupper(Str::random(8)),
            'status' => AssetStatus::Active,
            'is_mobile' => true,
            'current_location_label' => null,
            'last_heartbeat_at' => null,
            'meta' => null,
        ];
    }

    public function gate(): static
    {
        return $this->state(fn (): array => [
            'asset_type' => AssetType::Gate,
            'name' => 'Main Gate',
            'is_mobile' => false,
        ]);
    }
}
