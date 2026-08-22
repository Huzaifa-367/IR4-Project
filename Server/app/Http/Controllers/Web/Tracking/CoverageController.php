<?php

namespace App\Http\Controllers\Web\Tracking;

use App\Http\Controllers\Web\BaseController;
use App\Services\Tracking\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CoverageController extends BaseController
{
    public function __invoke(Request $request, TrackingService $tracking): JsonResponse
    {
        abort_unless($request->user()?->can('view-tracking'), 403);

        return response()->json(['data' => $tracking->liveCoverage()]);
    }
}
