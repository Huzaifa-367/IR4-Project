<?php

namespace App\Services;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\GasType;
use App\Enums\IncidentStatus;
use App\Enums\LsrCategory;
use App\Enums\LsrStatus;
use App\Enums\ReportStatus;
use App\Enums\ReviewStatus;
use App\Enums\ViolationType;
use App\Enums\ZoneType;
use App\Http\Resources\AlertResource;
use App\Models\Alert;
use App\Models\GasThreshold;
use App\Models\HseIncident;
use App\Models\LsrViolation;
use App\Models\PpeViolation;
use App\Models\User;
use App\Models\WeeklyReport;
use App\Models\WorkerPosition;
use App\Models\Zone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
        private readonly EvacuationService $evacuation,
        private readonly PpeViolationService $ppe,
    ) {}

    /**
     * Permission-filtered dashboard aggregate (DOC-16 §3 + Control Room mockup).
     *
     * @return array<string, mixed>
     */
    public function summary(
        User $user,
        string $range = 'today',
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null,
    ): array {
        $from = Carbon::instance($from ?? now()->startOfDay());
        $to = Carbon::instance($to ?? now());
        $ttl = max(1, (int) $this->settings->get('dashboard.cache_seconds', 8));
        $permKey = implode(',', $user->getAllPermissions()->pluck('name')->sort()->values()->all());
        $cacheKey = 'dashboard.summary.'.$user->id.'.'.md5($permKey.'.'.$range.'.'.$from->timestamp.'.'.$to->timestamp);

        return Cache::remember(
            $cacheKey,
            $ttl,
            fn (): array => $this->buildSummary($user, $range, $from, $to),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSummary(User $user, string $range, Carbon $from, Carbon $to): array
    {
        $out = [];
        $readOnly = (bool) $user->primaryRole()?->is_read_only;
        $windowEnd = $to->lessThan(now()) ? $to : now();

        if ($user->can('view-dashboard')) {
            $headcount = $this->tracking->headcountSnapshot();
            $flow = $this->tracking->headcountFlow($from, $windowEnd, 10);
            $delta = $headcount['total_on_site'] - $flow['shift_start_count'];

            $out['meta'] = [
                'range' => $range,
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'range_label' => $this->rangeLabel($range, $from, $to),
                'as_of' => now()->toIso8601String(),
            ];

            $out['headcount'] = [
                'total_on_site' => $headcount['total_on_site'],
                'by_zone' => ($user->can('view-tracking') && ! $readOnly)
                    ? $headcount['by_zone']
                    : [],
                'range_start_count' => $flow['shift_start_count'],
                'delta_vs_range_start' => $delta,
                'peak' => $flow['peak'],
                'sparkline' => $flow['sparkline'],
                'flow' => $flow['points'],
            ];

            $out['alerts'] = $this->alertsBlock($user);
            $out['weather'] = $this->weatherBlock();
            $out['system_health'] = $this->systemHealthBlock();
        }

        if ($user->can('view-tracking') && ! $readOnly) {
            $out['map'] = $this->mapBlock($user);
            if (! isset($out['headcount'])) {
                $out['headcount'] = $this->tracking->headcountSnapshot();
            } else {
                $out['headcount']['by_zone'] = $this->tracking->headcountSnapshot()['by_zone'];
            }
            $out['evacuation'] = $this->evacuation->readinessSnapshot();
        }

        if ($user->can('view-gas') && ! $readOnly) {
            $out['gas'] = $this->gasBlock($range, $from, $windowEnd);
        }

        if ($user->can('view-ppe') && ! $readOnly) {
            $out['ppe_today'] = $this->ppeTodayBlock(
                $out['headcount']['total_on_site'] ?? 0,
                $from,
                $windowEnd,
            );
        }

        if ($user->can('view-incidents')) {
            $out['incidents'] = [
                'open' => HseIncident::query()->where('status', IncidentStatus::Open)->count(),
                'under_review' => HseIncident::query()->where('status', IncidentStatus::UnderReview)->count(),
            ];
        }

        if ($user->can('view-lsr')) {
            $openAll = $this->lsr->summary();
            $lsrWindow = $this->lsr->summary($from, $windowEnd);
            $out['lsr'] = [
                'open' => $openAll['open'],
                'by_category' => collect($lsrWindow['by_category'])
                    ->map(fn (array $row): array => [
                        'category' => $row['category'],
                        'label' => $row['label'],
                        'open' => $row['open'],
                        'total' => $row['total'] ?? $row['open'],
                    ])
                    ->filter(fn (array $row): bool => ($row['total'] ?? 0) > 0 || ($row['open'] ?? 0) > 0)
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

        if ($user->can('view-dashboard')) {
            $out['safety_score'] = $this->safetyScoreBlock($out);
        }

        if ($user->can('view-incidents') || $user->can('view-lsr')) {
            $out['open_records'] = $this->openRecordsBlock($user);
        }

        return $out;
    }

    private function rangeLabel(string $range, Carbon $from, Carbon $to): string
    {
        return match ($range) {
            'today' => 'Today '.$from->format('H:i').'–'.$to->format('H:i'),
            'yesterday' => 'Yesterday '.$from->format('Y-m-d'),
            'week' => 'Last 7 days',
            'custom' => $from->toDateString().' → '.$to->toDateString(),
            default => $from->toDateString().' → '.$to->toDateString(),
        };
    }

    /**
     * @return array{
     *     open_critical: int,
     *     open_warning: int,
     *     latest: list<array<string, mixed>>,
     *     sparkline: list<int>
     * }
     */
    private function alertsBlock(User $user): array
    {
        $openStatuses = [AlertStatus::Open->value, AlertStatus::Acknowledged->value];
        $base = Alert::query()->whereIn('status', $openStatuses);

        $latest = (clone $base)
            ->orderByDesc('raised_at')
            ->limit(12)
            ->get();

        $sparkline = [];
        for ($i = 11; $i >= 0; $i--) {
            $bucketStart = now()->subHours($i + 1);
            $bucketEnd = now()->subHours($i);
            $sparkline[] = Alert::query()
                ->whereBetween('raised_at', [$bucketStart, $bucketEnd])
                ->where('severity', AlertSeverity::Critical)
                ->count();
        }

        return [
            'open_critical' => (clone $base)->where('severity', AlertSeverity::Critical)->count(),
            'open_warning' => (clone $base)->where('severity', AlertSeverity::Warning)->count(),
            'latest' => $latest->map(function (Alert $alert) use ($user): array {
                $request = request();
                $request->setUserResolver(fn () => $user);

                return (new AlertResource($alert))->toArray($request);
            })->values()->all(),
            'sparkline' => $sparkline,
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
     * @return array{
     *     assets: list<array<string, mixed>>,
     *     online: int,
     *     total: int,
     *     uptime_pct: float,
     *     sparkline: list<float>
     * }
     */
    private function systemHealthBlock(): array
    {
        $assets = $this->health->systemHealthSnapshot();
        $total = count($assets);
        $online = collect($assets)->where('status', 'green')->count();
        $uptime = $total > 0 ? round(($online / $total) * 100, 1) : 100.0;

        return [
            'assets' => $assets,
            'online' => $online,
            'total' => $total,
            'uptime_pct' => $uptime,
            'sparkline' => array_fill(0, 8, $uptime),
        ];
    }

    /**
     * @return array{
     *     panels: list<array<string, mixed>>,
     *     channel_gauges: list<array<string, mixed>>,
     *     thresholds: array{h2s_warn: float|null, h2s_alarm: float|null},
     *     trend: array<string, mixed>
     * }
     */
    private function gasBlock(string $range, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $from = Carbon::instance($from);
        $to = Carbon::instance($to);
        $thresholds = GasThreshold::query()
            ->where('is_active', true)
            ->get()
            ->keyBy(fn (GasThreshold $t) => $t->gas_type->value);

        $h2s = $thresholds->get(GasType::H2s->value);

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
                'device_name' => $panel['device_name'],
                'status' => $status,
                'channels' => [
                    'lel_pct' => $panel['lel_pct'],
                    'h2s_ppm' => $panel['h2s_ppm'],
                    'o2_pct' => $panel['o2_pct'],
                    'co_ppm' => $panel['co_ppm'],
                    'co2_ppm' => $panel['co2_ppm'],
                ],
                'stale' => $panel['is_stale'],
                'open_alarms' => $panel['open_alarms'],
            ];
        })->values()->all();

        $channelGauges = $this->gasChannelGauges($panels, $thresholds);
        $snapshot = $this->gas->dashboardSnapshot($from, $to);

        $series = collect($snapshot['trend']['series'] ?? [])->values()->map(function (array $row, int $index): array {
            return [
                'key' => $row['key'],
                'label' => $row['label'].(isset($row['unit']) && $row['unit'] !== '' ? ' ('.$row['unit'].')' : ''),
                'device_id' => 0,
                'color' => match ($index) {
                    0 => 'var(--viz-1)',
                    1 => 'var(--viz-2)',
                    2 => 'var(--viz-3)',
                    3 => 'var(--viz-4)',
                    default => 'var(--viz-5)',
                },
                'points' => collect($row['points'] ?? [])->map(fn (array $p): array => [
                    'at' => $p['at'],
                    'value' => $p['avg'] ?? $p['value'] ?? null,
                ])->values()->all(),
                'latest' => collect($row['points'] ?? [])->last()['avg']
                    ?? collect($row['points'] ?? [])->last()['value']
                    ?? null,
            ];
        })->all();

        $labels = $this->mergeTrendLabels($series, $range);

        return [
            'panels' => $panels,
            'channel_gauges' => $channelGauges,
            'thresholds' => [
                'h2s_warn' => $h2s !== null ? (float) $h2s->warning_level : 5.0,
                'h2s_alarm' => $h2s !== null ? (float) $h2s->alarm_level : 10.0,
            ],
            'trend' => [
                'range' => $range,
                'labels' => $labels,
                'series' => $series,
                'warn' => $h2s !== null ? (float) $h2s->warning_level : 5.0,
                'alarm' => $h2s !== null ? (float) $h2s->alarm_level : 10.0,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $panels
     * @param  Collection<string, GasThreshold>  $thresholds
     * @return list<array<string, mixed>>
     */
    private function gasChannelGauges(array $panels, $thresholds): array
    {
        $gauges = [];
        $specs = [
            ['key' => 'h2s_ppm', 'type' => GasType::H2s, 'unit' => 'ppm', 'label' => 'H₂S'],
            ['key' => 'lel_pct', 'type' => GasType::Lel, 'unit' => '%', 'label' => 'LEL'],
            ['key' => 'o2_pct', 'type' => GasType::O2Low, 'unit' => '%vol', 'label' => 'O₂'],
            ['key' => 'co_ppm', 'type' => GasType::Co, 'unit' => 'ppm', 'label' => 'CO'],
            ['key' => 'co2_ppm', 'type' => GasType::Co2, 'unit' => 'ppm', 'label' => 'CO₂'],
        ];

        foreach ($panels as $panel) {
            foreach ($specs as $spec) {
                $value = $panel['channels'][$spec['key']] ?? null;
                if ($value === null) {
                    continue;
                }
                $threshold = $thresholds->get($spec['type']->value);
                $warn = $threshold !== null ? (float) $threshold->warning_level : null;
                $alarm = $threshold !== null ? (float) $threshold->alarm_level : null;
                $status = 'ok';
                if ($alarm !== null && $value >= $alarm) {
                    $status = 'crit';
                } elseif ($warn !== null && $value >= $warn) {
                    $status = 'warn';
                }
                $gauges[] = [
                    'label' => $spec['label'],
                    'source' => $panel['asset'] ?? $panel['device_name'],
                    'value' => $value,
                    'unit' => $spec['unit'],
                    'warn' => $warn,
                    'alarm' => $alarm,
                    'status' => $status,
                ];
                if (count($gauges) >= 5) {
                    return $gauges;
                }
            }
        }

        return $gauges;
    }

    /**
     * @param  list<array<string, mixed>>  $series
     * @return list<array<string, mixed>>
     */
    private function mergeTrendLabels(array $series, string $range): array
    {
        $byAt = [];
        foreach ($series as $s) {
            foreach ($s['points'] as $point) {
                $at = (string) $point['at'];
                if (! isset($byAt[$at])) {
                    $byAt[$at] = ['at' => $at, 'label' => $this->formatTrendLabel($at, $range)];
                }
                $byAt[$at][$s['key']] = $point['value'];
            }
        }
        ksort($byAt);

        return array_values($byAt);
    }

    private function formatTrendLabel(string $iso, string $range): string
    {
        $t = Carbon::parse($iso);

        return match ($range) {
            'week', 'custom' => $t->format('D H:i'),
            'yesterday', 'today' => $t->format('H:i'),
            default => $t->format('H:i'),
        };
    }

    /**
     * @return array{
     *     total: int,
     *     by_type: array<string, int>,
     *     trend_delta: int,
     *     compliance_pct: float,
     *     compliance_delta: float,
     *     sparkline: list<float>,
     *     heatmap: array{types: list<array{key: string, label: string}>, hours: list<int>, cells: list<list<int>>}
     * }
     */
    private function ppeTodayBlock(int $headcount, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $from = Carbon::instance($from);
        $to = Carbon::instance($to);
        $seconds = max(1, $from->diffInSeconds($to));
        $prevTo = $from->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subSeconds($seconds);

        $current = $this->ppe->summary($from, $to);
        $previousTotal = PpeViolation::query()
            ->whereBetween('detected_at', [$prevFrom, $prevTo])
            ->where('review_status', '!=', ReviewStatus::FalsePositive->value)
            ->count();

        // PPE events are anonymous (DOC-10) — use worker_count, never worker_id.
        $affectedCurrent = (int) PpeViolation::query()
            ->whereBetween('detected_at', [$from, $to])
            ->where('review_status', '!=', ReviewStatus::FalsePositive->value)
            ->sum('worker_count');
        $affectedPrevious = (int) PpeViolation::query()
            ->whereBetween('detected_at', [$prevFrom, $prevTo])
            ->where('review_status', '!=', ReviewStatus::FalsePositive->value)
            ->sum('worker_count');

        $denom = max(1, $headcount);
        $compliance = round(max(0, min(100, (1 - min(1, $affectedCurrent / $denom)) * 100)), 1);
        $compliancePrevious = round(max(0, min(100, (1 - min(1, $affectedPrevious / $denom)) * 100)), 1);

        $hours = [];
        if ($from->isSameDay($to)) {
            for ($h = (int) $from->format('G'); $h <= (int) $to->format('G'); $h++) {
                $hours[] = $h;
            }
        } else {
            $hours = range(0, 23);
        }
        if ($hours === []) {
            $hours = range(0, 23);
        }

        $types = [];
        foreach (ViolationType::cases() as $type) {
            $types[] = [
                'key' => $type->value,
                'label' => match ($type) {
                    ViolationType::MissingHelmet => 'Helmet',
                    ViolationType::MissingVest => 'Vest',
                    ViolationType::MissingHarness => 'Harness',
                    ViolationType::MissingMask => 'Mask',
                },
            ];
        }
        $cells = $this->ppeHeatmap($types, $hours, $from, $to);

        $sparkline = [];
        for ($d = 9; $d >= 0; $d--) {
            $day = now()->subDays($d);
            $affected = (int) PpeViolation::query()
                ->whereBetween('detected_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
                ->where('review_status', '!=', ReviewStatus::FalsePositive->value)
                ->sum('worker_count');
            $sparkline[] = round(max(0, min(100, (1 - min(1, $affected / $denom)) * 100)), 1);
        }

        return [
            'total' => (int) $current['total'],
            'by_type' => $current['by_type'],
            'trend_delta' => (int) $current['total'] - $previousTotal,
            'compliance_pct' => $compliance,
            'compliance_delta' => round($compliance - $compliancePrevious, 1),
            'sparkline' => $sparkline,
            'heatmap' => [
                'types' => $types,
                'hours' => $hours,
                'cells' => $cells,
            ],
        ];
    }

    /**
     * @param  list<array{key: string, label: string}>  $types
     * @param  list<int>  $hours
     * @return list<list<int>>
     */
    private function ppeHeatmap(array $types, array $hours, \DateTimeInterface $shiftStart, \DateTimeInterface $shiftEnd): array
    {
        $shiftStart = Carbon::instance($shiftStart);
        $shiftEnd = Carbon::instance($shiftEnd);
        $violations = PpeViolation::query()
            ->whereBetween('detected_at', [$shiftStart, min($shiftEnd, now())])
            ->where('review_status', '!=', ReviewStatus::FalsePositive->value)
            ->get(['violation_type', 'detected_at']);

        $cells = [];
        foreach ($types as $type) {
            $row = array_fill(0, count($hours), 0);
            foreach ($violations as $v) {
                if ($v->violation_type->value !== $type['key']) {
                    continue;
                }
                $hour = (int) $v->detected_at->format('G');
                $idx = array_search($hour, $hours, true);
                if ($idx !== false) {
                    $row[$idx]++;
                }
            }
            $cells[] = $row;
        }

        return $cells;
    }

    /**
     * @param  array<string, mixed>  $out
     * @return array<string, mixed>
     */
    private function safetyScoreBlock(array $out): array
    {
        $ppePct = (float) ($out['ppe_today']['compliance_pct'] ?? 100);
        $redZoneIds = Zone::query()
            ->where('zone_type', ZoneType::RestrictedRed)
            ->pluck('id')
            ->all();
        $inRed = 0;
        foreach ($out['headcount']['by_zone'] ?? [] as $zone) {
            if (in_array((int) ($zone['zone_id'] ?? 0), $redZoneIds, true)) {
                $inRed += (int) ($zone['count'] ?? 0);
            }
        }
        $zonePct = $inRed === 0 ? 100.0 : max(40.0, 100.0 - ($inRed * 15));
        $overdue = (int) ($out['equipment']['overdue'] ?? 0);
        $equipPct = $overdue === 0 ? 100.0 : max(40.0, 100.0 - ($overdue * 6));
        $score = (int) round(($ppePct * 0.45) + ($zonePct * 0.3) + ($equipPct * 0.25));

        return [
            'score' => $score,
            'components' => [
                'ppe' => round($ppePct, 1),
                'zone' => round($zonePct, 1),
                'equipment' => round($equipPct, 1),
            ],
            'ppe_today' => (int) ($out['ppe_today']['total'] ?? 0),
            'open_lsr' => (int) ($out['lsr']['open'] ?? 0),
            'overdue_equipment' => $overdue,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function openRecordsBlock(User $user): array
    {
        $rows = [];

        if ($user->can('view-incidents')) {
            HseIncident::query()
                ->with(['zone', 'classifier'])
                ->whereIn('status', [
                    IncidentStatus::Open,
                    IncidentStatus::UnderReview,
                    IncidentStatus::Classified,
                ])
                ->orderByDesc('occurred_at')
                ->limit(8)
                ->get()
                ->each(function (HseIncident $incident) use (&$rows): void {
                    $progress = match ($incident->status) {
                        IncidentStatus::Open => 25,
                        IncidentStatus::UnderReview => 55,
                        IncidentStatus::Classified => 100,
                        default => 0,
                    };
                    $owner = $incident->classifier;
                    $rows[] = [
                        'id' => $incident->id,
                        'uuid' => $incident->uuid,
                        'record' => $incident->incident_number,
                        'type' => $incident->incident_type?->label() ?? 'Incident',
                        'kind' => 'incident',
                        'severity' => $incident->severity?->value ?? 'medium',
                        'severity_label' => $incident->severity?->label() ?? 'Medium',
                        'zone' => $incident->zone?->name ?? '—',
                        'owner' => $owner?->name ?? '—',
                        'owner_initials' => $this->initials($owner?->name),
                        'status' => $incident->status->value,
                        'status_label' => $incident->status->label(),
                        'action_progress' => $progress,
                        'age' => $this->humanAge($incident->occurred_at),
                        'href' => '/incidents/'.$incident->uuid,
                        'occurred_at' => optional($incident->occurred_at)?->toIso8601String(),
                    ];
                });
        }

        if ($user->can('view-lsr')) {
            LsrViolation::query()
                ->with(['zone', 'logger'])
                ->where('status', LsrStatus::Open)
                ->orderByDesc('occurred_at')
                ->limit(8)
                ->get()
                ->each(function (LsrViolation $lsr) use (&$rows): void {
                    $progress = filled($lsr->action_taken) ? 60 : 20;
                    $owner = $lsr->logger;
                    $rows[] = [
                        'id' => $lsr->id,
                        'uuid' => $lsr->uuid,
                        'record' => 'LSR-'.$lsr->id,
                        'type' => $lsr->category->label(),
                        'kind' => 'lsr',
                        'severity' => $lsr->category === LsrCategory::RedZoneIntrusion ? 'critical' : 'medium',
                        'severity_label' => $lsr->category === LsrCategory::RedZoneIntrusion ? 'Critical' : 'Medium',
                        'zone' => $lsr->zone?->name ?? '—',
                        'owner' => $owner?->name ?? '—',
                        'owner_initials' => $this->initials($owner?->name),
                        'status' => $lsr->status->value,
                        'status_label' => $lsr->status->label(),
                        'action_progress' => $progress,
                        'age' => $this->humanAge($lsr->occurred_at),
                        'href' => '/lsr-violations/'.$lsr->uuid,
                        'occurred_at' => optional($lsr->occurred_at)?->toIso8601String(),
                    ];
                });
        }

        usort($rows, function (array $a, array $b): int {
            return strcmp((string) ($b['occurred_at'] ?? ''), (string) ($a['occurred_at'] ?? ''));
        });

        return array_slice($rows, 0, 10);
    }

    private function initials(?string $name): string
    {
        if ($name === null || trim($name) === '') {
            return '—';
        }
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $chars = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $chars .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $chars !== '' ? $chars : '—';
    }

    private function humanAge(?\DateTimeInterface $at): string
    {
        if ($at === null) {
            return '—';
        }
        $minutes = (int) abs(now()->diffInMinutes(Carbon::instance($at)));
        if ($minutes < 60) {
            return $minutes.'m';
        }
        $hours = intdiv($minutes, 60);
        $rem = $minutes % 60;

        return $hours.'h '.str_pad((string) $rem, 2, '0', STR_PAD_LEFT).'m';
    }

    /**
     * @return array{zones: list<array<string, mixed>>, positions: list<array<string, mixed>>, in_red: int, zone_count: int}
     */
    private function mapBlock(User $user): array
    {
        $canIdentity = $user->can('view-worker-identity');
        $zones = Zone::query()
            ->where('is_active', true)
            ->get(['id', 'uuid', 'name', 'zone_type', 'latitude', 'longitude', 'radius_meters', 'color'])
            ->map(fn (Zone $zone): array => [
                'id' => $zone->id,
                'uuid' => $zone->uuid,
                'name' => $zone->name,
                'zone_type' => $zone->zone_type->value,
                'latitude' => $zone->latitude,
                'longitude' => $zone->longitude,
                'radius_meters' => $zone->radius_meters,
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

        $redZoneIds = collect($zones)
            ->filter(fn (array $z): bool => ($z['zone_type'] ?? '') === ZoneType::RestrictedRed->value)
            ->pluck('id')
            ->all();
        $inRed = collect($positions)->whereIn('zone_id', $redZoneIds)->count();

        return [
            'zones' => $zones,
            'positions' => $positions,
            'in_red' => $inRed,
            'zone_count' => count($zones),
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
            'uuid' => $report->uuid,
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
