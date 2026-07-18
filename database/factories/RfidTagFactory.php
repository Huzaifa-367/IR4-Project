<?php

namespace Database\Factories;

use App\Enums\TagStatus;
use App\Models\RfidTag;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RfidTag>
 */
class RfidTagFactory extends Factory
{
    protected $model = RfidTag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tag_uid' => strtoupper(Str::random(24)),
            'worker_id' => null,
            'status' => TagStatus::InStock,
            'notes' => null,
        ];
    }

    public function assigned(?Worker $worker = null): static
    {
        return $this->state(fn (): array => [
            'worker_id' => $worker?->id ?? Worker::factory(),
            'status' => TagStatus::Assigned,
            'assigned_at' => now(),
        ]);
    }

    public function inStock(): static
    {
        return $this->state(fn (): array => [
            'worker_id' => null,
            'status' => TagStatus::InStock,
            'assigned_at' => null,
            'assigned_by' => null,
        ]);
    }

    public function lost(): static
    {
        return $this->state(fn (): array => [
            'worker_id' => null,
            'status' => TagStatus::Lost,
            'assigned_at' => null,
            'assigned_by' => null,
        ]);
    }
}
