<?php

namespace App\Http\Controllers\Web\Tracking;

use App\Http\Controllers\Web\BaseController;
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
            ->get(['id', 'uuid', 'name', 'zone_type', 'latitude', 'longitude', 'radius_meters', 'color'])
            ->map(fn (Zone $z) => [
                'id' => $z->id,
                'uuid' => $z->uuid,
                'name' => $z->name,
                'zone_type' => $z->zone_type->value,
                'latitude' => $z->latitude,
                'longitude' => $z->longitude,
                'radius_meters' => $z->radius_meters,
                'color' => $z->color,
            ]);

        return response()->json([
            'data' => [
                'positions' => $rows,
                'zones' => $zones,
            ],
        ]);
    }
}
