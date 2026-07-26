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
        abort_unless($request->user()?->can('view-tracking'), 403);

        return Inertia::render('tracking/index', [
            'headcount' => $tracking->headcountSnapshot(),
            'canSeePositions' => $request->user()->can('view-worker-identity')
                || $request->user()->can('update-tags'),
            'canTriggerEvacuation' => $request->user()->can('create-evacuation'),
        ]);
    }
}
