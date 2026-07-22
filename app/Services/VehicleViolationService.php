<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\VehicleViolation;
use Illuminate\Validation\ValidationException;

final class VehicleViolationService
{
    /**
     * Seeded types (DOC-15 default).
     *
     * @return list<string>
     */
    public static function violationTypes(): array
    {
        return [
            'speeding',
            'seatbelt',
            'unauthorized_parking',
            'reckless_driving',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $logger): VehicleViolation
    {
        $action = trim((string) ($data['action_taken'] ?? ''));
        if (strlen($action) < 10) {
            throw ValidationException::withMessages([
                'action_taken' => ['Action taken is required (min 10 characters).'],
            ]);
        }

        $violation = VehicleViolation::query()->create([
            'observed_at' => $data['observed_at'],
            'vehicle_description' => $data['vehicle_description'],
            'violation_type' => $data['violation_type'],
            'description' => $data['description'] ?? null,
            'action_taken' => $action,
            'camera_id' => $data['camera_id'] ?? null,
            'logged_by' => $logger->id,
        ]);

        AuditLog::query()->create([
            'event_type' => 'config_changed',
            'user_id' => $logger->id,
            'route' => request()->path(),
            'payload' => [
                'target' => 'vehicle_violation_create',
                'vehicle_violation_id' => $violation->id,
            ],
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);

        return $violation->fresh(['camera', 'logger']) ?? $violation;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(VehicleViolation $violation): array
    {
        $violation->loadMissing(['camera', 'logger']);

        return [
            'id' => $violation->id,
            'uuid' => $violation->uuid,
            'observed_at' => optional($violation->observed_at)?->toIso8601String(),
            'vehicle_description' => $violation->vehicle_description,
            'violation_type' => $violation->violation_type,
            'description' => $violation->description,
            'action_taken' => $violation->action_taken,
            'camera_id' => $violation->camera_id,
            'camera_name' => $violation->camera?->name ?? $violation->camera?->reference,
            'logged_by_name' => $violation->logger?->name,
            'created_at' => optional($violation->created_at)?->toIso8601String(),
        ];
    }
}
