<?php

namespace Database\Factories;

use App\Enums\ScheduleType;
use App\Models\Equipment;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceSchedule>
 */
class MaintenanceScheduleFactory extends Factory
{
    protected $model = MaintenanceSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'equipment_id' => Equipment::factory(),
            'schedule_type' => ScheduleType::Inspection,
            'interval_days' => 30,
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function service(): static
    {
        return $this->state(fn (): array => [
            'schedule_type' => ScheduleType::Service,
            'interval_days' => 90,
        ]);
    }
}
