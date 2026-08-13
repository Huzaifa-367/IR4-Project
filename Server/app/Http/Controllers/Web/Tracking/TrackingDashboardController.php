<?php

namespace App\Http\Controllers\Web\Tracking;

use App\Http\Controllers\Web\BaseController;
use App\Services\TrackingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TrackingDashboardController extends BaseController
{
    public function __invoke(Request $request, TrackingService $tracking): Response
    {
        $user = $request->user();
        abort_unless($user !== null && $user->can('view-tracking'), 403);

        $canSeePositions = $user->can('view-worker-identity')
            || $user->can('update-tags');

        return Inertia::render('tracking/index', [
            'headcount' => $tracking->headcountSnapshot(),
            'zones' => $canSeePositions ? $tracking->liveZones() : [],
            'positions' => $canSeePositions ? $tracking->livePositions($user) : [],
            'coverage' => $canSeePositions ? $tracking->liveCoverage() : [],
            'readings' => $canSeePositions ? $tracking->liveReadings($user) : [],
            'canSeePositions' => $canSeePositions,
            'canTriggerEvacuation' => $user->can('create-evacuation'),
        ]);
    }
}
