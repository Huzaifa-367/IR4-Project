<?php

namespace App\Http\Controllers\Web\Tracking;

use App\Http\Controllers\Web\BaseController;
use App\Models\TagReading;
use App\Models\WorkerPosition;
use App\Models\Zone;
use App\Services\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TrackingApiController extends BaseController
{
    public function headcount(TrackingService $tracking): JsonResponse
    {
        abort_unless(request()->user()?->can('view-tracking'), 403);

        return response()->json(['data' => $tracking->headcountSnapshot()]);
    }

    public function positions(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user?->can('view-tracking')
            && ($user->can('view-worker-identity') || $user->can('update-tags')),
            403,
        );

        $canIdentity = $user->can('view-worker-identity');

        $rows = WorkerPosition::query()
            ->with(['worker', 'zone', 'tag'])
            ->where('is_on_site', true)
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(function (WorkerPosition $position) use ($canIdentity): array {
                $worker = $position->worker;
                $label = $canIdentity && $worker !== null
                    ? $worker->name
                    : ($worker?->anonymizedLabel() ?? 'Worker');

                return [
                    'tag_id' => $position->tag_id,
                    'tag_uid' => $position->tag?->tag_uid,
                    'worker_id' => $position->worker_id,
                    'worker_label' => $label,
                    'zone_id' => $position->zone_id,
                    'zone_name' => $position->zone?->name,
                    'last_seen_at' => $position->last_seen_at->toIso8601String(),
                    'is_on_site' => $position->is_on_site,
                ];
            })
            ->values()
            ->all();

        $zones = Zone::query()
            ->where('is_active', true)
            ->withCount('currentBindings')
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'zone_type', 'color', 'occupancy_limit'])
            ->map(fn (Zone $z) => [
                'id' => $z->id,
                'uuid' => $z->uuid,
                'name' => $z->name,
                'zone_type' => $z->zone_type->value,
                'color' => $z->color,
                'occupancy_limit' => $z->occupancy_limit,
                'reader_count' => $z->current_bindings_count,
            ]);

        return response()->json([
            'data' => [
                'positions' => $rows,
                'zones' => $zones,
            ],
        ]);
    }

    public function readings(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->can('view-tracking'), 403);

        $canIdentity = $user->can('view-worker-identity');
        $zoneId = $request->filled('zone_id') ? $request->integer('zone_id') : null;
        $limit = min(200, max(1, $request->integer('limit', 100)));

        $query = TagReading::query()
            ->with(['tag.worker', 'zone:id,name', 'reader:id,name,reference'])
            ->orderByDesc('recorded_at')
            ->limit($limit);

        if ($zoneId !== null && $zoneId > 0) {
            $query->where('zone_id', $zoneId);
        }

        $rows = $query->get()->map(function (TagReading $reading) use ($canIdentity): array {
            $worker = $reading->tag?->worker;
            $label = $canIdentity && $worker !== null
                ? $worker->name
                : ($worker?->anonymizedLabel() ?? null);

            return [
                'id' => $reading->id,
                'recorded_at' => $reading->recorded_at->toIso8601String(),
                'zone_id' => $reading->zone_id,
                'zone_name' => $reading->zone?->name,
                'reader_ref' => $reading->reader?->reference,
                'reader_name' => $reading->reader?->name,
                'tag_uid' => $reading->tag?->tag_uid,
                'worker_label' => $label,
                'rssi' => $reading->rssi,
                'antenna' => $reading->antenna,
                'proximity' => $reading->proximity()?->value,
                'is_backfill' => $reading->is_backfill,
            ];
        })->values()->all();

        return response()->json(['data' => $rows]);
    }
}
