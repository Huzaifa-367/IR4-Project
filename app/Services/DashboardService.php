<?php

namespace App\Services;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\AssetStatus;
use App\Enums\HardwareStatus;
use App\Enums\IncidentStatus;
use App\Enums\ReportStatus;
use App\Enums\ReviewStatus;
use App\Http\Resources\AlertResource;
use App\Models\Alert;
use App\Models\Asset;
use App\Models\HseIncident;
use App\Models\PpeViolation;
use App\Models\User;
use App\Models\WeeklyReport;
use App\Models\WorkerPosition;
use App\Models\Zone;
use Illuminate\Support\Facades\Cache;

final class DashboardService
{
    public function __construct(
        private readonly TrackingService $tracking,
        private readonly GasMonitoringService $gas,
        private readonly EnvironmentalDataService $environment,
        private readonly LsrService $lsr,
        private readonly EquipmentService $equipment,
        private readonly SettingsService $settings,
        private readonly AssetHealthService $health,
    ) {}

    /**
     * Permission-filtered dashboard aggregate (DOC-16 §3).
     *
     * @return array<string, mixed>
     */
    public function summary(User $user): array
    {
        $ttl = max(1, (int) $this->settings->get('dashboard.cache_seconds', 8));
        $permKey = implode(',', $user->getAllPermissions()->pluck('name')->sort()->values()->all());
        $cacheKey = 'dashboard.summary.'.$user->id.'.'.md5($permKey);

        return Cache::remember($cacheKey, $ttl, fn (): array => $this->buildSummary($user));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSummary(User $user): array
    {
        $out = [];
        $readOnly = (bool) $user->primaryRole()?->is_read_only;

        if ($user->can('view-dashboard')) {
            $headcount = $this->tracking->headcountSnapshot();
            $out['headcount'] = [
                'total_on_site' => $headcount['total_on_site'],
                'by_zone' => ($user->can('view-tracking') && ! $readOnly)
                    ? $headcount['by_zone']
                    : [],
            ];
            $out['alerts'] = $this->alertsBlock($user);
            $out['weather'] = $this->weatherBlock();
            $out['system_health'] = $this->health->systemHealthSnapshot();
        }

        if ($user->can('view-tracking') && ! $readOnly) {
            $out['map'] = $this->mapBlock($user);
            if (! isset($out['headcount'])) {
                $out['headcount'] = $this->tracking->headcountSnapshot();
            } else {
                $out['headcount']['by_zone'] = $this->tracking->headcountSnapshot()['by_zone'];
            }
        }

        if ($user->can('view-gas') && ! $readOnly) {
            $out['gas'] = $this->gasBlock();
        }

        if ($user->can('view-ppe') && ! $readOnly) {
            $out['ppe_today'] = $this->ppeTodayBlock();
        }

        if ($user->can('view-incidents')) {
            $out['incidents'] = [
                'open' => HseIncident::query()->where('status', IncidentStatus::Open)->count(),
                'under_review' => HseIncident::query()->where('status', IncidentStatus::UnderReview)->count(),
            ];
        }

        if ($user->can('view-lsr')) {
            $lsr = $this->lsr->summary();
            $out['lsr'] = [
                'open' => $lsr['open'],
                'by_category' => collect($lsr['by_category'])
                    ->map(fn (array $row): array => [
                        'category' => $row['category'],
                        'label' => $row['label'],
                        'open' => $row['open'],
                    ])
                    ->values()
                    ->all(),
            ];
        }

        if ($user->can('view-equipment')) {
            $out['equipment'] = $this->equipment->summaryCounts();
        }

        if ($user->can('view-reports')) {
            $out['last_report'] = $this->lastReportBlock($user);
        }

        return $out;
    }

    /**
     * @return array{open_critical: int, open_warning: int, latest: list<array<string, mixed>>}
     */
    private function alertsBlock(User $user): array
    {
        $openStatuses = [AlertStatus::Open->value, AlertStatus::Acknowledged->value];
        $base = Alert::query()->whereIn('status', $openStatuses);

        $latest = (clone $base)
            ->orderByDesc('raised_at')
            ->limit(8)
            ->get();

        return [
            'open_critical' => (clone $base)->where('severity', AlertSeverity::Critical)->count(),
            'open_warning' => (clone $base)->where('severity', AlertSeverity::Warning)->count(),
            'latest' => $latest->map(function (Alert $alert) use ($user): array {
                $request = request();
                $request->setUserResolver(fn () => $user);

                return (new AlertResource($alert))->toArray($request);
            })->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function weatherBlock(): array
    {
        $sensors = $this->environment->latest();
        $first = $sensors[0] ?? null;
        if ($first === null) {
            return [
                'temperature_c' => null,
                'humidity_pct' => null,
                'wind_speed_ms' => null,
                'updated_at' => null,
                'stale' => false,
            ];
        }

        return [
            'temperature_c' => $first['temperature_c'] ?? null,
            'humidity_pct' => $first['humidity_pct'] ?? null,
            'wind_speed_ms' => $first['wind_speed_ms'] ?? null,
            'updated_at' => $first['recorded_at'] ?? null,
            'stale' => (bool) ($first['is_stale'] ?? true),
        ];
    }

    /**
     * @return array{panels: list<array<string, mixed>>}
     */
    private function gasBlock(): array
    {
        $panels = collect($this->gas->livePanels())->map(function (array $panel): array {
            $status = 'ok';
            if ($panel['is_stale'] || $panel['open_alarms'] !== []) {
                $hasCritical = collect($panel['open_alarms'])->contains(
                    fn (array $a): bool => ($a['level'] ?? '') === 'alarm' || ($a['level'] ?? '') === 'critical',
                );
                $status = $hasCritical || $panel['is_stale'] ? 'crit' : 'warn';
            }

            return [
                'device_id' => $panel['device_id'],
                'asset' => $panel['asset_label'] ?? $panel['device_name'],
                'status' => $status,
                'channels' => [
                    'lel_pct' => $panel['lel_pct'],
                    'h2s_ppm' => $panel['h2s_ppm'],
                    'o2_pct' => $panel['o2_pct'],
                    'co_ppm' => $panel['co_ppm'],
                ],
                'co2_ppm' => $panel['co2_ppm'],
                'stale' => $panel['is_stale'],
            ];
        })->values()->all();

        return ['panels' => $panels];
    }

    /**
     * @return array{total: int, by_type: array<string, int>, trend_delta: int}
     */
    private function ppeTodayBlock(): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $yesterdayStart = now()->subDay()->startOfDay();
        $yesterdayEnd = now()->subDay()->endOfDay();

        $today = app(PpeViolationService::class)->summary($todayStart, $todayEnd);
        $yesterdayTotal = PpeViolation::query()
            ->whereBetween('detected_at', [$yesterdayStart, $yesterdayEnd])
            ->where('review_status', '!=', ReviewStatus::FalsePositive->value)
            ->count();

        return [
            'total' => (int) $today['total'],
            'by_type' => $today['by_type'],
            'trend_delta' => (int) $today['total'] - $yesterdayTotal,
        ];
    }

    /**
     * @return array{zones: list<array<string, mixed>>, positions: list<array<string, mixed>>}
     */
    private function mapBlock(User $user): array
    {
        $canIdentity = $user->can('view-worker-identity');
        $zones = Zone::query()
            ->where('is_active', true)
            ->get(['id', 'name', 'zone_type', 'map_x', 'map_y', 'map_radius', 'color'])
            ->map(fn (Zone $zone): array => [
                'id' => $zone->id,
                'name' => $zone->name,
                'zone_type' => $zone->zone_type->value,
                'map_x' => $zone->map_x,
                'map_y' => $zone->map_y,
                'map_radius' => $zone->map_radius,
                'color' => $zone->color,
            ])
            ->values()
            ->all();

        $positions = WorkerPosition::query()
            ->with('worker')
            ->where('is_on_site', true)
            ->whereNotNull('zone_id')
            ->get()
            ->map(function (WorkerPosition $position) use ($canIdentity): array {
                $worker = $position->worker;

                return [
                    'tag_id' => $position->tag_id,
                    'worker_id' => $position->worker_id,
                    'worker_label' => $canIdentity && $worker !== null
                        ? $worker->name
                        : ($worker?->anonymizedLabel() ?? ('Worker #'.$position->worker_id)),
                    'zone_id' => $position->zone_id,
                    'last_seen_at' => optional($position->last_seen_at)?->toIso8601String(),
                    'is_on_site' => $position->is_on_site,
                ];
            })
            ->values()
            ->all();

        return [
            'zones' => $zones,
            'positions' => $positions,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lastReportBlock(User $user): ?array
    {
        $query = WeeklyReport::query()->orderByDesc('id');
        if ($user->primaryRole()?->is_read_only) {
            $query->where('status', ReportStatus::Published);
        }

        $report = $query->first();
        if ($report === null) {
            return null;
        }

        return [
            'id' => $report->id,
            'report_number' => $report->report_number,
            'period' => [
                'start' => optional($report->period_start)?->toDateString(),
                'end' => optional($report->period_end)?->toDateString(),
            ],
            'status' => $report->status->value,
            'generated_at' => optional($report->generated_at)?->toIso8601String(),
        ];
    }
}
