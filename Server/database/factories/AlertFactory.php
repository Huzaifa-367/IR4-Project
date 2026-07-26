<?php

namespace Database\Factories;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Models\Alert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    protected $model = Alert::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'alert_type' => AlertType::System,
            'severity' => AlertSeverity::Warning,
            'title' => fake()->sentence(4),
            'payload' => [],
            'status' => AlertStatus::Open,
            'raised_at' => now(),
            'audible' => false,
            'dedupe_key' => null,
            'occurrences' => 1,
        ];
    }

    public function critical(): static
    {
        return $this->state(fn (): array => [
            'severity' => AlertSeverity::Critical,
            'audible' => true,
            'alert_type' => AlertType::GasAlarm,
        ]);
    }

    public function acknowledged(): static
    {
        return $this->state(fn (): array => [
            'status' => AlertStatus::Acknowledged,
            'acknowledged_at' => now(),
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'status' => AlertStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }

    public function ofType(AlertType $type): static
    {
        return $this->state(fn (): array => [
            'alert_type' => $type,
        ]);
    }
}
