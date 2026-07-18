<?php

namespace App\Http\Controllers\Web;

use App\Services\EnvironmentalDataService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class EnvironmentController extends BaseController
{
    public function dashboard(Request $request, EnvironmentalDataService $environment): InertiaResponse
    {
        abort_unless($request->user()?->can('view-dashboard'), 403);

        return Inertia::render('dashboard', [
            'environmentSensors' => $environment->latest(),
        ]);
    }

    public function live(Request $request, EnvironmentalDataService $environment): JsonResponse
    {
        abort_unless($request->user()?->can('view-dashboard'), 403);

        return ApiResponse::ok(['sensors' => $environment->latest()]);
    }

    public function trends(Request $request, EnvironmentalDataService $environment): InertiaResponse|JsonResponse
    {
        abort_unless($request->user()?->can('view-dashboard'), 403);
        $range = $request->string('range', 'day')->toString();
        $parameter = $request->string('parameter', 'temperature_c')->toString();
        $deviceId = $request->filled('device_id') ? $request->integer('device_id') : null;
        $now = now();
        [$from, $to] = match ($range) {
            'week' => [$now->copy()->subDays(7), $now],
            'custom' => [
                Carbon::parse($request->string('from')->toString())->startOfDay(),
                Carbon::parse($request->string('to')->toString())->endOfDay(),
            ],
            default => [$now->copy()->subDay(), $now],
        };
        $series = $environment->trends($parameter, $deviceId, $from, $to);
        if ($request->wantsJson() || $request->boolean('json')) {
            return ApiResponse::ok($series);
        }

        return Inertia::render('environment/index', [
            'snapshot' => $environment->dashboardSnapshot($from, $to),
            'filters' => [
                'range' => $range,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]);
    }
}
