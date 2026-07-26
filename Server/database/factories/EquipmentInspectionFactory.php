<?php

namespace Database\Factories;

use App\Enums\InspectionOutcome;
use App\Models\Equipment;
use App\Models\EquipmentInspection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentInspection>
 */
class EquipmentInspectionFactory extends Factory
{
    protected $model = EquipmentInspection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'equipment_id' => Equipment::factory(),
            'inspected_at' => today(),
            'outcome' => InspectionOutcome::Pass,
            'notes' => null,
            'inspector_id' => User::factory(),
            'next_due' => null,
            'created_by' => User::factory(),
        ];
    }
}
