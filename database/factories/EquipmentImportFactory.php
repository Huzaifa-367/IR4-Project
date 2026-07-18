<?php

namespace Database\Factories;

use App\Models\EquipmentImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentImport>
 */
class EquipmentImportFactory extends Factory
{
    protected $model = EquipmentImport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'original_filename' => 'equipment.csv',
            'stored_path' => 'imports/equipment/example.csv',
            'status' => 'pending',
            'summary' => null,
        ];
    }
}
