<?php

namespace Database\Factories;

use App\Enums\Involvement;
use App\Models\HseIncident;
use App\Models\IncidentPersonnel;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncidentPersonnel>
 */
class IncidentPersonnelFactory extends Factory
{
    protected $model = IncidentPersonnel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hse_incident_id' => HseIncident::factory(),
            'worker_id' => Worker::factory(),
            'involvement' => Involvement::Involved,
        ];
    }
}
