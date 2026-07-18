<?php

namespace App\Http\Controllers\Web;

use App\Enums\DeviceType;
use App\Models\Device;
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
        $sensors = $environment->latest();
        $extraParameters = collect($sensors)
            ->flatMap(fn (array $sensor): array => array_keys($sensor['extra']))
            ->unique()
            ->values();

        return Inertia::render('environment/index', [
            'series' => $series,
            'filters' => [
                'parameter' => $parameter,
                'device_id' => $deviceId !== null ? (string) $deviceId : '',
                'range' => $range,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'devices' => Device::query()
                ->where('device_type', DeviceType::EnvironmentalSensor)
                ->orderBy('name')
                ->get(['id', 'name', 'reference']),
            'parameters' => collect([
                ['value' => 'temperature_c', 'label' => 'Temperature (°C)'],
                ['value' => 'humidity_pct', 'label' => 'Humidity (%)'],
                ['value' => 'wind_speed_ms', 'label' => 'Wind speed (m/s)'],
            ])->concat($extraParameters->map(
                fn (int|string $key): array => ['value' => (string) $key, 'label' => (string) $key],
            )),
        ]);
    }
}
