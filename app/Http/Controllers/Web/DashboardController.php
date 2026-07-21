<?php

namespace App\Http\Controllers\Web;

use App\Services\DashboardService;
use App\Services\SettingsService;
use App\Support\ApiResponse;
use App\Support\TrendRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class DashboardController extends BaseController
{
    public function index(
        Request $request,
        DashboardService $dashboard,
        SettingsService $settings,
    ): InertiaResponse {
        abort_unless($request->user()?->can('view-dashboard'), 403);
        $user = $request->user();
        assert($user !== null);

        [$range, $from, $to] = TrendRange::resolveDashboard($request);

        return Inertia::render('dashboard/index', [
            'summary' => $dashboard->summary($user, $range, $from, $to),
            'filters' => [
                'range' => $range,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'cycleSeconds' => (int) $settings->get('display.cycle_seconds', 20),
            'permissions' => [
                'view_tracking' => $user->can('view-tracking'),
                'view_gas' => $user->can('view-gas'),
                'view_ppe' => $user->can('view-ppe'),
                'view_incidents' => $user->can('view-incidents'),
                'view_lsr' => $user->can('view-lsr'),
                'view_equipment' => $user->can('view-equipment'),
                'view_reports' => $user->can('view-reports'),
                'trigger_evacuation' => $user->can('create-evacuation'),
            ],
        ]);
    }

    public function summary(Request $request, DashboardService $dashboard): JsonResponse
    {
        abort_unless($request->user()?->can('view-dashboard'), 403);
        $user = $request->user();
        assert($user !== null);

        [$range, $from, $to] = TrendRange::resolveDashboard($request);

        return ApiResponse::ok($dashboard->summary($user, $range, $from, $to));
    }
}
