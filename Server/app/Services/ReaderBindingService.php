<?php

namespace App\Services;

use App\Enums\DeviceType;
use App\Enums\ZoneType;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\ReaderZoneBinding;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReaderBindingService
{
    /**
     * @return array{binding: ReaderZoneBinding, gate_warning: bool}
     */
    public function bind(
        Device $reader,
        Zone $zone,
        \DateTimeInterface $effectiveAt,
        ?User $by = null,
        ?string $note = null,
    ): array {
        $effectiveAt = Carbon::instance($effectiveAt);
        if ($reader->device_type !== DeviceType::RfidReader) {
            throw ValidationException::withMessages([
                'device_id' => 'Only RFID readers can be bound to zones.',
            ]);
        }

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

        $gateWarning = false;

        $binding = DB::transaction(function () use ($reader, $zone, $effectiveAt, $by, $note, &$gateWarning): ReaderZoneBinding {
            /** @var ReaderZoneBinding|null $current */
            $current = ReaderZoneBinding::query()
                ->where('device_id', $reader->id)
                ->whereNull('bound_until')
                ->with('zone')
                ->lockForUpdate()
                ->first();

            if ($current !== null) {
                if ($effectiveAt->lessThan($current->bound_from)) {
                    throw ValidationException::withMessages([
                        'effective_at' => 'effective_at cannot be before the current binding start.',
                    ]);
                }

                if ($current->zone?->zone_type === ZoneType::Gate) {
                    $gateWarning = true;
                }

                $current->forceFill(['bound_until' => $effectiveAt])->save();
            }

            $created = ReaderZoneBinding::query()->create([
                'device_id' => $reader->id,
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
                    'target' => 'reader_zone_binding',
                    'device_id' => $reader->id,
                    'zone_id' => $zone->id,
                    'bound_from' => $effectiveAt->toIso8601String(),
                    'previous_binding_id' => $current?->id,
                ],
                'ip' => request()->ip(),
                'created_at' => now(),
            ]);

            return $created;
        });

        return [
            'binding' => $binding->load('zone'),
            'gate_warning' => $gateWarning,
        ];
    }

    public function resolveZoneAt(Device $reader, \DateTimeInterface $recordedAt): ?Zone
    {
        $recordedAt = Carbon::instance($recordedAt);
        /** @var ReaderZoneBinding|null $binding */
        $binding = ReaderZoneBinding::query()
            ->where('device_id', $reader->id)
            ->where('bound_from', '<=', $recordedAt)
            ->where(function ($query) use ($recordedAt): void {
                $query->whereNull('bound_until')
                    ->orWhere('bound_until', '>', $recordedAt);
            })
            ->with('zone')
            ->first();

        return $binding?->zone;
    }

    public function currentZone(Device $reader): ?Zone
    {
        /** @var ReaderZoneBinding|null $binding */
        $binding = ReaderZoneBinding::query()
            ->where('device_id', $reader->id)
            ->whereNull('bound_until')
            ->with('zone')
            ->first();

        return $binding?->zone;
    }
}
