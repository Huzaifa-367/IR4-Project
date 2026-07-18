<?php

namespace App\Http\Controllers\Web\Gas;

use App\Enums\DeviceType;
use App\Enums\GasType;
use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Web\Gas\UpdateGasThresholdsRequest;
use App\Models\Device;
use App\Models\GasAlarm;
use App\Models\GasThreshold;
use App\Models\User;
use App\Services\GasMonitoringService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class GasDashboardController extends BaseController
{
    public function index(Request $request, GasMonitoringService $gas): InertiaResponse
    {
        abort_unless($request->user()?->can('view-gas'), 403);

        $thresholds = GasThreshold::query()
            ->where('is_active', true)
            ->get()
            ->keyBy(fn (GasThreshold $t): string => $t->gas_type->value)
            ->map(fn (GasThreshold $t): array => [
                'gas_type' => $t->gas_type->value,
                'warning_level' => (float) $t->warning_level,
                'alarm_level' => (float) $t->alarm_level,
                'unit' => $t->unit,
                'direction' => $t->direction->value,
            ]);

        return Inertia::render('gas/index', [
            'panels' => $gas->livePanels(),
            'thresholds' => $thresholds,
            'canManageThresholds' => $request->user()?->can('manage-gas-thresholds') ?? false,
            'canAcknowledge' => $request->user()?->can('acknowledge-alerts') ?? false,
        ]);
    }

    public function live(Request $request, GasMonitoringService $gas): JsonResponse
    {
        abort_unless($request->user()?->can('view-gas'), 403);

        return ApiResponse::ok(['panels' => $gas->livePanels()]);
    }

    public function trends(Request $request, GasMonitoringService $gas): InertiaResponse|JsonResponse
    {
        abort_unless($request->user()?->can('view-gas'), 403);

        $range = $request->string('range', 'day')->toString();
        $gasType = GasType::tryFrom($request->string('gas_type', 'h2s')->toString()) ?? GasType::H2s;
        $deviceId = $request->filled('device_id') ? $request->integer('device_id') : null;

        [$from, $to] = match ($range) {
            'shift' => [now()->subHours(12), now()],
            'week' => [now()->subDays(7), now()],
            'custom' => [
                Carbon::parse($request->string('from')->toString()),
                Carbon::parse($request->string('to')->toString()),
            ],
            default => [now()->subDay(), now()],
        };

        $series = $gas->trends($deviceId, $gasType, $from, $to);

        if ($request->wantsJson() || $request->boolean('json')) {
            return ApiResponse::ok($series);
        }

        return Inertia::render('gas/trends/index', [
            'series' => $series,
            'filters' => [
                'gas_type' => $gasType->value,
                'device_id' => $deviceId !== null ? (string) $deviceId : '',
                'range' => $range,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'devices' => Device::query()
                ->whereIn('device_type', [DeviceType::GasDetector, DeviceType::Co2Sensor])
                ->orderBy('name')
                ->get(['id', 'name', 'reference']),
            'gasTypes' => collect(GasType::cases())->map(fn (GasType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
        ]);
    }

    public function alarms(Request $request, GasMonitoringService $gas): InertiaResponse
    {
        abort_unless($request->user()?->can('view-gas'), 403);

        $query = GasAlarm::query()->with(['device', 'acknowledger']);

        if ($request->filled('gas_type')) {
            $query->where('gas_type', $request->string('gas_type')->toString());
        }
        if ($request->filled('level')) {
            $query->where('level', $request->string('level')->toString());
        }
        if ($request->filled('device_id')) {
            $query->where('device_id', $request->integer('device_id'));
        }
        if ($request->string('resolved')->toString() === 'open') {
            $query->whereNull('resolved_at');
        } elseif ($request->string('resolved')->toString() === 'resolved') {
            $query->whereNotNull('resolved_at');
        }
        if ($request->filled('from')) {
            $query->where('triggered_at', '>=', Carbon::parse($request->string('from')->toString()));
        }
        if ($request->filled('to')) {
            $query->where('triggered_at', '<=', Carbon::parse($request->string('to')->toString()));
        }

        $this->applyListQuery(
            $query,
            $request,
            sortable: ['triggered_at', 'gas_type', 'level'],
            searchable: [],
            defaultSort: 'triggered_at',
            defaultDirection: 'desc',
        );

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        return Inertia::render('gas/alarms/index', [
            'alarms' => [
                'data' => $paginator->getCollection()->map(fn (GasAlarm $a) => $gas->alarmToArray($a))->values(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'filters' => [
                'gas_type' => $request->string('gas_type')->toString(),
                'level' => $request->string('level')->toString(),
                'device_id' => $request->string('device_id')->toString(),
                'resolved' => $request->string('resolved')->toString(),
            ],
            'canAcknowledge' => $request->user()?->can('acknowledge-alerts') ?? false,
        ]);
    }

    public function acknowledge(Request $request, GasAlarm $alarm, GasMonitoringService $gas): RedirectResponse
    {
        abort_unless($request->user()?->can('acknowledge-alerts'), 403);
        /** @var User $user */
        $user = $request->user();
        $gas->acknowledgeAlarm($alarm, $user);

        return redirect()->back();
    }

    public function thresholds(Request $request): InertiaResponse
    {
        abort_unless($request->user()?->can('view-gas'), 403);

        $rows = GasThreshold::query()->with('updater')->orderBy('gas_type')->get();

        return Inertia::render('gas/thresholds/index', [
            'thresholds' => $rows->map(fn (GasThreshold $t): array => [
                'id' => $t->id,
                'gas_type' => $t->gas_type->value,
                'label' => $t->gas_type->label(),
                'warning_level' => (float) $t->warning_level,
                'alarm_level' => (float) $t->alarm_level,
                'unit' => $t->unit,
                'direction' => $t->direction->value,
                'is_active' => $t->is_active,
                'updated_by_name' => $t->updater?->name,
                'updated_at' => $t->updated_at?->toIso8601String(),
            ]),
            'canManage' => $request->user()?->can('manage-gas-thresholds') ?? false,
        ]);
    }

    public function updateThresholds(
        UpdateGasThresholdsRequest $request,
        GasMonitoringService $gas,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $gas->updateThresholds($request->validated('thresholds'), $user);

        return redirect()->back();
    }
}
