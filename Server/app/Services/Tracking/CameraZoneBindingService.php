<?php

namespace App\Services\Tracking;

use App\Models\AuditLog;
use App\Models\Camera;
use App\Models\CameraZoneBinding;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Time-aware camera → zone bindings (DOC-09 headcount by board/zone).
 */
final class CameraZoneBindingService
{
    /**
     * @return array{binding: CameraZoneBinding}
     */
    public function bind(
        Camera $camera,
        Zone $zone,
        \DateTimeInterface $effectiveAt,
        ?User $by = null,
        ?string $note = null,
    ): array {
        $effectiveAt = Carbon::instance($effectiveAt);

        if (! $zone->is_active) {
            throw ValidationException::withMessages([
                'zone_id' => 'Cannot bind to an inactive zone.',
            ]);
        }

        if ($effectiveAt->greaterThan(now()->addMinutes(5))) {
            throw ValidationException::withMessages([
                'effective_at' => 'effective_at cannot be more than 5 minutes in the future.',
            ]);
        }

        $binding = DB::transaction(function () use ($camera, $zone, $effectiveAt, $by, $note): CameraZoneBinding {
            /** @var CameraZoneBinding|null $current */
            $current = CameraZoneBinding::query()
                ->where('camera_id', $camera->id)
                ->whereNull('bound_until')
                ->lockForUpdate()
                ->first();

            if ($current !== null) {
                if ($effectiveAt->lessThan($current->bound_from)) {
                    throw ValidationException::withMessages([
                        'effective_at' => 'effective_at cannot be before the current binding start.',
                    ]);
                }

                $current->forceFill(['bound_until' => $effectiveAt])->save();
            }

            $created = CameraZoneBinding::query()->create([
                'camera_id' => $camera->id,
                'zone_id' => $zone->id,
                'bound_from' => $effectiveAt,
                'bound_until' => null,
                'bound_by' => $by?->id ?? auth()->id(),
                'note' => $note,
            ]);

            AuditLog::query()->create([
                'event_type' => 'config_changed',
                'user_id' => $by?->id ?? auth()->id(),
                'route' => request()->path(),
                'payload' => [
                    'target' => 'camera_zone_binding',
                    'camera_id' => $camera->id,
                    'zone_id' => $zone->id,
                    'bound_from' => $effectiveAt->toIso8601String(),
                    'previous_binding_id' => $current?->id,
                ],
                'ip' => request()->ip(),
                'created_at' => now(),
            ]);

            return $created;
        });

        return ['binding' => $binding->load('zone')];
    }

    public function resolveZoneAt(Camera $camera, \DateTimeInterface $recordedAt): ?Zone
    {
        $recordedAt = Carbon::instance($recordedAt);
        /** @var CameraZoneBinding|null $binding */
        $binding = CameraZoneBinding::query()
            ->where('camera_id', $camera->id)
            ->where('bound_from', '<=', $recordedAt)
            ->where(function ($query) use ($recordedAt): void {
                $query->whereNull('bound_until')
                    ->orWhere('bound_until', '>', $recordedAt);
            })
            ->with('zone')
            ->first();

        return $binding?->zone;
    }
}
