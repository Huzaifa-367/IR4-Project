<?php

namespace Database\Factories;

use App\Enums\WorkerType;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Worker>
 */
class WorkerFactory extends Factory
{
    protected $model = Worker::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'employee_code' => fake()->optional()->bothify('EMP-####'),
            'badge_number' => fake()->optional()->bothify('BDG-####'),
            'contractor' => fake()->company(),
            'role_title' => fake()->optional()->jobTitle(),
            'nationality' => fake()->optional(0.7)->country(),
            'date_of_birth' => fake()->optional(0.6)->dateTimeBetween('-60 years', '-18 years'),
            'joined_on' => fake()->optional(0.7)->dateTimeBetween('-5 years', 'now'),
            'government_id_number' => fake()->optional(0.5)->bothify('###########'),
            'worker_type' => fake()->randomElement(WorkerType::cases()),
            'phone' => fake()->optional()->e164PhoneNumber(),
            'photo_path' => null,
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
            'present' => false,
            'last_seen_at' => null,
            'created_by' => User::factory(),
        ];
    }

    public function present(): static
    {
        return $this->state(fn (): array => [
            'present' => true,
            'last_seen_at' => now(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
            'present' => false,
        ]);
    }

    public function visitor(): static
    {
        return $this->state(fn (): array => [
            'worker_type' => WorkerType::Visitor,
        ]);
    }
}
