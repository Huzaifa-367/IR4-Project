<?php

namespace App\Http\Controllers\Web;

use App\Services\DashboardService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DisplayController extends BaseController
{
    public function __invoke(
        Request $request,
        DashboardService $dashboard,
        SettingsService $settings,
    ): Response {
        abort_unless($request->user()?->can('view-dashboard'), 403);
        $user = $request->user();
        assert($user !== null);

        return Inertia::render('display/index', [
            'summary' => $dashboard->summary($user),
            'cycleSeconds' => (int) $settings->get('display.cycle_seconds', 20),
            'permissions' => [
                'view_tracking' => $user->can('view-tracking'),
                'view_gas' => $user->can('view-gas'),
                'view_ppe' => $user->can('view-ppe'),
                'view_incidents' => $user->can('view-incidents'),
                'view_lsr' => $user->can('view-lsr'),
                'view_equipment' => $user->can('view-equipment'),
                'view_reports' => $user->can('view-reports'),
            ],
        ]);
    }
}
