<?php

namespace App\Http\Controllers\Web;

use App\Services\Environment\EnvironmentalDataService;
use App\Support\ApiResponse;
use App\Support\TrendRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class EnvironmentController extends BaseController
{
    public function live(Request $request, EnvironmentalDataService $environment): JsonResponse
    {
        abort_unless($request->user()?->can('view-dashboard'), 403);

        return ApiResponse::ok([
            'sensors' => Cache::remember(
                'environment:live',
                now()->addSeconds(5),
                fn (): array => $environment->latest(),
            ),
        ]);
    }

    public function trends(Request $request, EnvironmentalDataService $environment): InertiaResponse|JsonResponse
    {
        abort_unless($request->user()?->can('view-dashboard'), 403);

        [$range, $from, $to] = TrendRange::resolve($request);
        $deviceId = $request->filled('device_id') ? $request->integer('device_id') : null;

        if ($request->wantsJson() || $request->boolean('json')) {
            $payload = $request->filled('parameter')
                ? $environment->trends(
                    $request->string('parameter')->toString(),
                    $deviceId,
                    $from,
                    $to,
                )
                : $environment->coreTrends($deviceId, $from, $to);

            return ApiResponse::ok($payload);
        }

        return Inertia::render('environment/index', [
            'sensors' => $environment->latest(),
            'trends' => $environment->coreTrends($deviceId, $from, $to),
            'filters' => [
                'range' => $range,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]);
    }
}
