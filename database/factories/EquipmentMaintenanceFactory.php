<?php

namespace Database\Factories;

use App\Enums\MaintenanceType;
use App\Models\Equipment;
use App\Models\EquipmentMaintenance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentMaintenance>
 */
class EquipmentMaintenanceFactory extends Factory
{
    protected $model = EquipmentMaintenance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'equipment_id' => Equipment::factory(),
            'performed_at' => today(),
            'maintenance_type' => MaintenanceType::Preventive,
            'description' => fake()->sentence(),
            'performed_by_name' => fake()->optional()->name(),
            'recorded_by' => User::factory(),
            'next_due' => null,
            'created_by' => User::factory(),
        ];
    }
}
