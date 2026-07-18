<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Enums\ViolationType;
use App\Models\Camera;
use App\Models\PpeViolation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PpeViolation>
 */
class PpeViolationFactory extends Factory
{
    protected $model = PpeViolation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'camera_id' => Camera::factory(),
            'violation_type' => ViolationType::MissingHelmet,
            'detected_at' => now(),
            'worker_count' => 1,
            'snapshot_path' => 'snapshots/'.now()->format('Y/m/d').'/'.Str::uuid().'.jpg',
            'confidence' => 0.85,
            'location_label' => null,
            'review_status' => ReviewStatus::Unreviewed,
            'is_backfill' => false,
            'event_uid' => (string) Str::uuid(),
        ];
    }

    public function falsePositive(): static
    {
        return $this->state(fn (): array => [
            'review_status' => ReviewStatus::FalsePositive,
            'reviewed_at' => now(),
            'review_note' => 'Dust glare false positive',
        ]);
    }
}
