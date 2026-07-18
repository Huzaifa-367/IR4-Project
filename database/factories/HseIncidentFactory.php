<?php

namespace Database\Factories;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\HseIncident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HseIncident>
 */
class HseIncidentFactory extends Factory
{
    protected $model = HseIncident::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $seq = 0;
        $seq++;

        return [
            'incident_number' => 'INC-'.now()->format('Y').'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT).'-'.fake()->unique()->numerify('##'),
            'source' => IncidentSource::Manual,
            'alert_id' => null,
            'zone_id' => null,
            'camera_id' => null,
            'occurred_at' => now()->subHour(),
            'status' => IncidentStatus::Open,
            'incident_type' => null,
            'severity' => null,
            'nature_of_incident' => null,
            'immediate_action' => null,
            'corrective_action' => null,
            'classified_by' => null,
            'classified_at' => null,
            'closed_by' => null,
            'closed_at' => null,
            'close_note' => null,
            'created_by' => User::factory(),
        ];
    }

    public function classified(): static
    {
        return $this->state(fn (): array => [
            'status' => IncidentStatus::Classified,
            'incident_type' => IncidentType::NearMiss,
            'severity' => IncidentSeverity::Medium,
            'nature_of_incident' => 'Worker slipped near scaffolding but was unharmed.',
            'immediate_action' => 'Area cordoned and surface dried.',
            'corrective_action' => 'Added anti-slip mats and toolbox talk.',
            'classified_by' => User::factory(),
            'classified_at' => now(),
        ]);
    }

    public function fromAlert(): static
    {
        return $this->state(fn (): array => [
            'source' => IncidentSource::FromAlert,
            'status' => IncidentStatus::UnderReview,
        ]);
    }
}
