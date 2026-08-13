<?php

namespace App\Http\Controllers\Web\Tracking;

use App\Http\Controllers\Web\BaseController;
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

    public function positions(Request $request, TrackingService $tracking): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null
            && $user->can('view-tracking')
            && ($user->can('view-worker-identity') || $user->can('update-tags')),
            403,
        );

        return response()->json([
            'data' => [
                'positions' => $tracking->livePositions($user),
                'zones' => $tracking->liveZones(),
            ],
        ]);
    }

    public function readings(Request $request, TrackingService $tracking): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->can('view-tracking'), 403);

        $zoneId = $request->filled('zone_id') ? $request->integer('zone_id') : null;
        $limit = $request->integer('limit', 100);

        return response()->json([
            'data' => $tracking->liveReadings($user, $zoneId, $limit),
        ]);
    }
}
