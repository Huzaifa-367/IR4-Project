<?php

namespace Database\Factories;

use App\Enums\EvidenceType;
use App\Models\HseIncident;
use App\Models\IncidentEvidence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncidentEvidence>
 */
class IncidentEvidenceFactory extends Factory
{
    protected $model = IncidentEvidence::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hse_incident_id' => HseIncident::factory(),
            'evidence_type' => EvidenceType::Note,
            'file_path' => null,
            'payload' => ['text' => fake()->sentence()],
            'ppe_violation_id' => null,
            'camera_id' => null,
            'captured_at' => now(),
            'added_by' => User::factory(),
        ];
    }

    public function autoCaptured(): static
    {
        return $this->state(fn (): array => [
            'added_by' => null,
            'evidence_type' => EvidenceType::RfidZoneSnapshot,
            'payload' => ['workers' => []],
        ]);
    }
}
