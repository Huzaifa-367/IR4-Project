<?php

namespace Database\Factories;

use App\Enums\LsrCategory;
use App\Enums\LsrStatus;
use App\Models\LsrViolation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LsrViolation>
 */
class LsrViolationFactory extends Factory
{
    protected $model = LsrViolation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => LsrCategory::MissingPpe,
            'occurred_at' => now()->subHour(),
            'worker_id' => null,
            'zone_id' => null,
            'camera_id' => null,
            'alert_id' => null,
            'ppe_violation_id' => null,
            'description' => fake()->optional()->sentence(),
            'action_taken' => null,
            'status' => LsrStatus::Open,
            'closed_by' => null,
            'closed_at' => null,
            'logged_by' => User::factory(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'status' => LsrStatus::Closed,
            'action_taken' => 'Work stopped and PPE issued before restart.',
            'closed_by' => User::factory(),
            'closed_at' => now(),
        ]);
    }
}
