<?php

namespace Database\Factories;

use App\Models\Equipment;
use App\Models\EquipmentDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EquipmentDocument>
 */
class EquipmentDocumentFactory extends Factory
{
    protected $model = EquipmentDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'equipment_id' => Equipment::factory(),
            'title' => fake()->words(3, true),
            'file_path' => 'equipment-docs/1/'.$uuid.'.pdf',
            'mime' => 'application/pdf',
            'uploaded_by' => User::factory(),
            'created_by' => User::factory(),
        ];
    }
}
