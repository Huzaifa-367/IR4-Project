<?php

namespace App\Http\Controllers\Web;

use App\Services\DashboardService;
use App\Services\SettingsService;
use App\Support\ApiResponse;
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

        $gasRange = $this->resolveGasRange($request);

        return Inertia::render('dashboard/index', [
            'summary' => $dashboard->summary($user, $gasRange),
            'gasRange' => $gasRange,
            'cycleSeconds' => (int) $settings->get('display.cycle_seconds', 20),
            'permissions' => [
                'view_tracking' => $user->can('view-tracking'),
                'view_gas' => $user->can('view-gas'),
                'view_ppe' => $user->can('view-ppe'),
                'view_incidents' => $user->can('view-incidents'),
                'view_lsr' => $user->can('view-lsr'),
                'view_equipment' => $user->can('view-equipment'),
                'view_reports' => $user->can('view-reports'),
                'trigger_evacuation' => $user->can('trigger-evacuation'),
            ],
        ]);
    }

    public function summary(Request $request, DashboardService $dashboard): JsonResponse
    {
        abort_unless($request->user()?->can('view-dashboard'), 403);
        $user = $request->user();
        assert($user !== null);

        return ApiResponse::ok($dashboard->summary($user, $this->resolveGasRange($request)));
    }

    private function resolveGasRange(Request $request): string
    {
        $range = $request->string('gas_range', 'shift')->toString();

        return in_array($range, ['shift', 'day', 'week'], true) ? $range : 'shift';
    }
}
