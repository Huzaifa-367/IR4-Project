<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkerImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkerImport>
 */
class WorkerImportFactory extends Factory
{
    protected $model = WorkerImport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'original_filename' => 'workers.csv',
            'stored_path' => 'imports/workers/example.csv',
            'status' => 'pending',
            'summary' => null,
        ];
    }
}
