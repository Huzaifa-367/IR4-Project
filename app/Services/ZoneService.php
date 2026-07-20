<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Worker;
use App\Models\Zone;
use App\Models\ZoneAccessListEntry;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ZoneService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Zone
    {
        return Zone::query()->create([
            'name' => $data['name'],
            'zone_type' => $data['zone_type'],
            'requires_authorization' => (bool) ($data['requires_authorization'] ?? false),
            'requires_permit' => (bool) ($data['requires_permit'] ?? false),
            'occupancy_limit' => $data['occupancy_limit'] ?? null,
            'map_x' => $data['map_x'] ?? null,
            'map_y' => $data['map_y'] ?? null,
            'map_radius' => $data['map_radius'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'radius_meters' => $data['radius_meters'] ?? null,
            'color' => $data['color'] ?? null,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Zone $zone, array $data): Zone
    {
        $zone->fill($data)->save();

        return $zone->fresh() ?? $zone;
    }

    public function deactivate(Zone $zone): Zone
    {
        $zone->forceFill(['is_active' => false])->save();

        return $zone;
    }

    public function destroy(Zone $zone): void
    {
        if ($zone->bindings()->exists()) {
            throw new HttpException(409, 'Zone has reader bindings; deactivate it instead of deleting.');
        }

        $zone->delete();
    }

    /**
     * @param  list<int>  $workerIds
     */
    public function syncAccessList(Zone $zone, array $workerIds, ?int $authorizedBy = null): void
    {
        $workerIds = array_values(array_unique(array_map('intval', $workerIds)));

        $existing = Worker::query()->whereIn('id', $workerIds)->pluck('id')->all();
        if (count($existing) !== count($workerIds)) {
            throw new HttpException(422, 'One or more worker ids are invalid.');
        }

        DB::transaction(function () use ($zone, $workerIds, $authorizedBy): void {
            ZoneAccessListEntry::query()
                ->where('zone_id', $zone->id)
                ->whereNotIn('worker_id', $workerIds)
                ->delete();

            $present = ZoneAccessListEntry::query()
                ->where('zone_id', $zone->id)
                ->pluck('worker_id')
                ->all();

            foreach (array_diff($workerIds, $present) as $workerId) {
                ZoneAccessListEntry::query()->create([
                    'zone_id' => $zone->id,
                    'worker_id' => $workerId,
                    'authorized_by' => $authorizedBy ?? auth()->id(),
                    'authorized_at' => now(),
                ]);
            }
        });

        AuditLog::query()->create([
            'event_type' => 'config_changed',
            'user_id' => auth()->id(),
            'route' => request()->path(),
            'payload' => [
                'target' => 'zone_access_list',
                'zone_id' => $zone->id,
                'worker_ids' => $workerIds,
            ],
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array{map_x?: mixed, map_y?: mixed, map_radius?: mixed, latitude?: mixed, longitude?: mixed, radius_meters?: mixed, color?: mixed}  $data
     */
    public function setMapPosition(Zone $zone, array $data): Zone
    {
        $zone->forceFill([
            'map_x' => $data['map_x'] ?? $zone->map_x,
            'map_y' => $data['map_y'] ?? $zone->map_y,
            'map_radius' => $data['map_radius'] ?? $zone->map_radius,
            'latitude' => $data['latitude'] ?? $zone->latitude,
            'longitude' => $data['longitude'] ?? $zone->longitude,
            'radius_meters' => $data['radius_meters'] ?? $zone->radius_meters,
            'color' => $data['color'] ?? $zone->color,
        ])->save();

        return $zone;
    }

    public function workerIsAuthorized(Zone $zone, int $workerId): bool
    {
        if (! $zone->requires_authorization) {
            return true;
        }

        return ZoneAccessListEntry::query()
            ->where('zone_id', $zone->id)
            ->where('worker_id', $workerId)
            ->exists();
    }
}
