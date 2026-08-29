<?php

namespace App\Services\Report;

use App\Enums\AlertType;
use App\Enums\AssetStatus;
use App\Enums\DeviceType;
use App\Enums\Direction;
use App\Enums\GasAlarmLevel;
use App\Enums\HeadcountSource;
use App\Enums\ReportStatus;
use App\Enums\ReviewStatus;
use App\Models\Alert;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\EntryExitLog;
use App\Models\EnvironmentalReading;
use App\Models\GasAlarm;
use App\Models\GasReading;
use App\Models\HseIncident;
use App\Models\LsrViolation;
use App\Models\PpeViolation;
use App\Models\User;
use App\Models\VehicleViolation;
use App\Models\WeeklyReport;
use App\Notifications\WeeklyReportReadyNotification;
use App\Services\Hse\LsrService;
use App\Services\Ppe\PpeViolationService;
use App\Services\Settings\SettingsService;
use App\Services\Storage\SignedStorageUrlService;
use App\Services\Tracking\HeadcountIngestService;
use App\Support\SqlTimeBucket;
use App\Support\WeatherSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use ZipArchive;

final class WeeklyReportService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly SignedStorageUrlService $signedUrls,
        private readonly PpeViolationService $ppe,
        private readonly LsrService $lsr,
        private readonly WeatherSettings $weather,
        private readonly HeadcountIngestService $cameraHeadcounts,
    ) {}

    /**
     * @return list<string>
     */
    public static function dataKeys(): array
    {
        return [
            'period',
            'i_daily_safety_observations',
            'ii_hse_incidents',
            'iii_lsr_violations',
            'iv_weather',
            'v_manpower',
            'vi_units_monitored',
            'vii_vehicle_violations',
            'ix_gas',
            'completeness',
        ];
    }

    /**
     * Assemble, freeze, render artifacts, and mark generated.
     */
    public function generate(
        Carbon|string $start,
        Carbon|string $end,
        ?User $by = null,
        bool $auto = false,
        ?WeeklyReport $supersedes = null,
    ): WeeklyReport {
        $periodStart = Carbon::parse($start)->startOfDay();
        $periodEnd = Carbon::parse($end)->endOfDay();

        if ($periodEnd->lt($periodStart)) {
            throw ValidationException::withMessages([
                'period_end' => ['period_end must be on or after period_start.'],
            ]);
        }

        $canSeeIdentity = $by?->can('view-worker-identity') ?? true;
        $data = $this->assembleData($periodStart, $periodEnd, $canSeeIdentity);

        return DB::transaction(function () use ($periodStart, $periodEnd, $by, $auto, $supersedes, $data): WeeklyReport {
            if ($supersedes === null) {
                $supersedes = WeeklyReport::query()
                    ->whereDate('period_start', $periodStart->toDateString())
                    ->whereDate('period_end', $periodEnd->toDateString())
                    ->where('status', ReportStatus::Published)
                    ->orderByDesc('id')
                    ->first();
            }

            $report = WeeklyReport::query()->create([
                'report_number' => $this->nextReportNumber($periodStart),
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'status' => ReportStatus::Generated,
                'generated_at' => now(),
                'generated_by' => $by?->id,
                'data' => $data,
                'supersedes_report_id' => $supersedes?->id,
            ]);

            $paths = $this->renderArtifacts($report);
            $report->forceFill([
                'pdf_path' => $paths['pdf'],
                'csv_path' => $paths['csv'],
            ])->save();

            $this->audit('config_changed', [
                'target' => 'weekly_report_generated',
                'report_id' => $report->id,
                'auto' => $auto,
            ]);

            $this->notifyPublishHolders($report);

            $autoPublish = (bool) $this->settings->get('report.auto_publish', false);
            if ($auto && $autoPublish) {
                $publisher = $by ?? User::permission('update-reports')->first();
                if ($publisher !== null) {
                    return $this->publish($report, $publisher);
                }
            }

            return $report->fresh(['generator', 'supersedes']) ?? $report;
        });
    }

    public function publish(WeeklyReport $report, User $by): WeeklyReport
    {
        if ($report->status !== ReportStatus::Generated) {
            throw new HttpException(422, 'Only generated reports can be published.');
        }

        $report->forceFill([
            'status' => ReportStatus::Published,
            'published_at' => now(),
            'published_by' => $by->id,
        ])->save();

        $this->audit('report_published', [
            'target' => 'weekly_report',
            'report_id' => $report->id,
            'report_number' => $report->report_number,
        ]);

        return $report->fresh(['publisher', 'supersedes']) ?? $report;
    }

    /**
     * Re-render PDF/CSV from frozen data (generated reports only — published artifacts are immutable).
     */
    public function rerenderArtifacts(WeeklyReport $report): WeeklyReport
    {
        if ($report->status === ReportStatus::Published) {
            throw new HttpException(409, 'Published reports are immutable.');
        }

        $paths = $this->renderArtifacts($report);
        $report->forceFill([
            'pdf_path' => $paths['pdf'],
            'csv_path' => $paths['csv'],
        ])->save();

        return $report->fresh() ?? $report;
    }

    /**
     * @return array{url: string, format: string}
     */
    public function downloadUrl(WeeklyReport $report, string $format): array
    {
        $format = strtolower($format);
        $path = match ($format) {
            'pdf' => $report->pdf_path,
            'csv' => $report->csv_path,
            default => null,
        };

        if ($path === null || $path === '') {
            throw new HttpException(404, 'Report artifact not found.');
        }

        return [
            'url' => $this->signedUrls->temporaryUrl($path, 15),
            'format' => $format,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(WeeklyReport $report): array
    {
        $report->loadMissing(['generator', 'publisher', 'supersedes', 'supersededBy']);

        return [
            'id' => $report->id,
            'uuid' => $report->uuid,
            'report_number' => $report->report_number,
            'period_start' => optional($report->period_start)?->toDateString(),
            'period_end' => optional($report->period_end)?->toDateString(),
            'status' => $report->status->value,
            'status_label' => $report->status->label(),
            'generated_at' => optional($report->generated_at)?->toIso8601String(),
            'generated_by_name' => $report->generator?->name,
            'published_at' => optional($report->published_at)?->toIso8601String(),
            'published_by_name' => $report->publisher?->name,
            'has_pdf' => $report->pdf_path !== null,
            'has_csv' => $report->csv_path !== null,
            'supersedes_report_id' => $report->supersedes_report_id,
            'supersedes_report_number' => $report->supersedes?->report_number,
            'superseded_by_report_numbers' => $report->supersededBy->pluck('report_number')->values()->all(),
            'data' => $this->operatorFacingData($report->data),
            'created_at' => optional($report->created_at)?->toIso8601String(),
        ];
    }

    /**
     * Strip fields that must not appear on operator-facing report payloads.
     *
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>|null
     */
    private function operatorFacingData(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        if (isset($data['v_manpower']) && is_array($data['v_manpower'])) {
            unset($data['v_manpower']['source']);
        }

        return $data;
    }

    /**
     * Sunday–Saturday week just completed (DOC-15 default).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function previousReportingWeek(?Carbon $now = null): array
    {
        $now = $now ?? now();
        $weekStart = $this->weekStartConstant();
        $end = $now->copy()->startOfWeek($weekStart)->subDay()->endOfDay();
        $start = $end->copy()->startOfWeek($weekStart)->startOfDay();

        return [$start, $end];
    }

    private function weekStartConstant(): int
    {
        $day = strtolower((string) $this->settings->get('report.week_start', 'sunday'));

        return match ($day) {
            'monday' => Carbon::MONDAY,
            'tuesday' => Carbon::TUESDAY,
            'wednesday' => Carbon::WEDNESDAY,
            'thursday' => Carbon::THURSDAY,
            'friday' => Carbon::FRIDAY,
            'saturday' => Carbon::SATURDAY,
            default => Carbon::SUNDAY,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function assembleData(Carbon $start, Carbon $end, bool $canSeeIdentity = true): array
    {
        $completenessNotes = $this->completenessNotes($start, $end);

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'i_daily_safety_observations' => $this->itemDailySafety($start, $end),
            'ii_hse_incidents' => $this->itemIncidents($start, $end),
            'iii_lsr_violations' => $this->itemLsr($start, $end, $canSeeIdentity),
            'iv_weather' => $this->itemWeather($start, $end),
            'v_manpower' => $this->itemManpower($start, $end),
            'vi_units_monitored' => $this->itemUnitsMonitored(),
            'vii_vehicle_violations' => $this->itemVehicleViolations($start, $end),
            'ix_gas' => $this->itemGas($start, $end),
            'completeness' => ['notes' => $completenessNotes],
        ];
    }

    /**
     * @return array{per_day: list<array<string, mixed>>, by_camera: list<array<string, mixed>>}
     */
    private function itemDailySafety(Carbon $start, Carbon $end): array
    {
        $summary = $this->ppe->summary($start, $end);
        $included = PpeViolation::query()
            ->whereBetween('detected_at', [$start, $end])
            ->where('review_status', '!=', ReviewStatus::FalsePositive->value)
            ->get(['detected_at', 'violation_type']);

        $perDay = [];
        foreach ($this->eachDate($start, $end) as $date) {
            $dayRows = $included->filter(fn (PpeViolation $v): bool => $v->detected_at->toDateString() === $date);
            $byType = [];
            foreach ($dayRows as $row) {
                $key = $row->violation_type->value;
                $byType[$key] = ($byType[$key] ?? 0) + 1;
            }
            $perDay[] = [
                'date' => $date,
                'by_type' => $byType,
                'total' => $dayRows->count(),
            ];
        }

        return [
            'per_day' => $perDay,
            'by_camera' => collect($summary['by_camera'])
                ->map(fn (array $row): array => [
                    'camera' => $row['camera_ref'] !== '' ? $row['camera_ref'] : ('Camera #'.$row['camera_id']),
                    'total' => $row['count'],
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function itemIncidents(Carbon $start, Carbon $end): array
    {
        return HseIncident::query()
            ->withCount(['personnel', 'evidence'])
            ->with('evidence')
            ->whereBetween('occurred_at', [$start, $end])
            ->whereNotNull('classified_at')
            ->orderBy('occurred_at')
            ->get()
            ->map(function (HseIncident $incident): array {
                $evidenceCounts = [];
                foreach ($incident->evidence as $row) {
                    $key = $row->evidence_type->value;
                    $evidenceCounts[$key] = ($evidenceCounts[$key] ?? 0) + 1;
                }

                return [
                    'incident_number' => $incident->incident_number,
                    'occurred_at' => optional($incident->occurred_at)?->toIso8601String(),
                    'type' => $incident->incident_type?->value,
                    'severity' => $incident->severity?->value,
                    'status' => $incident->status->value,
                    'nature' => $incident->nature_of_incident,
                    'immediate_action' => $incident->immediate_action,
                    'corrective_action' => $incident->corrective_action,
                    'personnel_count' => (int) $incident->personnel_count,
                    'evidence_counts' => $evidenceCounts,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{summary_by_category: list<array{category: string, count: int}>, entries: list<array<string, mixed>>}
     */
    private function itemLsr(Carbon $start, Carbon $end, bool $canSeeIdentity): array
    {
        $summary = $this->lsr->summary($start, $end);
        $summaryByCategory = collect($summary['by_category'])
            ->filter(fn (array $row): bool => $row['total'] > 0)
            ->map(fn (array $row): array => [
                'category' => $row['category'],
                'count' => $row['total'],
            ])
            ->values()
            ->all();

        $entries = LsrViolation::query()
            ->with(['worker', 'zone'])
            ->whereBetween('occurred_at', [$start, $end])
            ->orderBy('occurred_at')
            ->get()
            ->map(function (LsrViolation $lsr) use ($canSeeIdentity): array {
                $workerLabel = null;
                if ($lsr->worker !== null) {
                    $workerLabel = $canSeeIdentity
                        ? $lsr->worker->name
                        : $lsr->worker->anonymizedLabel();
                }

                return [
                    'category' => $lsr->category->value,
                    'occurred_at' => optional($lsr->occurred_at)?->toIso8601String(),
                    'worker' => $workerLabel ?? '—',
                    'zone' => $lsr->zone?->name,
                    'action_taken' => $lsr->action_taken,
                    'status' => $lsr->status->value,
                ];
            })
            ->values()
            ->all();

        return [
            'summary_by_category' => $summaryByCategory,
            'entries' => $entries,
        ];
    }

    /**
     * @return array{per_day: list<array<string, mixed>>}
     */
    private function itemWeather(Carbon $start, Carbon $end): array
    {
        $dayExpr = SqlTimeBucket::day('recorded_at');
        $query = EnvironmentalReading::query()
            ->whereBetween('recorded_at', [$start, $end]);

        $systemDeviceId = $this->weather->systemDeviceId();
        if ($this->weather->usesApi()) {
            if ($systemDeviceId === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('device_id', $systemDeviceId);
            }
        } elseif ($systemDeviceId !== null) {
            $query->where('device_id', '!=', $systemDeviceId);
        }

        $byDay = $query
            ->selectRaw(implode(', ', [
                "{$dayExpr} as day",
                'MIN(temperature_c) as temp_min',
                'AVG(temperature_c) as temp_avg',
                'MAX(temperature_c) as temp_max',
                'MIN(humidity_pct) as humidity_min',
                'AVG(humidity_pct) as humidity_avg',
                'MAX(humidity_pct) as humidity_max',
            ]))
            ->groupByRaw($dayExpr)
            ->orderBy('day')
            ->get()
            ->keyBy(fn (object $row): string => Carbon::parse((string) $row->day)->toDateString());

        $perDay = [];
        foreach ($this->eachDate($start, $end) as $date) {
            $row = $byDay->get($date);
            $perDay[] = [
                'date' => $date,
                'temp' => [
                    'min' => $row?->temp_min !== null ? (float) $row->temp_min : null,
                    'avg' => $row?->temp_avg !== null ? round((float) $row->temp_avg, 2) : null,
                    'max' => $row?->temp_max !== null ? (float) $row->temp_max : null,
                ],
                'humidity' => [
                    'min' => $row?->humidity_min !== null ? (float) $row->humidity_min : null,
                    'avg' => $row?->humidity_avg !== null ? round((float) $row->humidity_avg, 2) : null,
                    'max' => $row?->humidity_max !== null ? (float) $row->humidity_max : null,
                ],
            ];
        }

        return ['per_day' => $perDay];
    }

    /**
     * @return array{source: string, per_day: list<array<string, mixed>>}
     */
    private function itemManpower(Carbon $start, Carbon $end): array
    {
        $source = $this->cameraHeadcounts->configuredSource();

        if ($source === HeadcountSource::Camera) {
            return [
                'source' => HeadcountSource::Camera->value,
                'per_day' => $this->cameraHeadcounts->manpowerPerDay($start, $end),
            ];
        }

        return [
            'source' => HeadcountSource::Rfid->value,
            'per_day' => $this->itemManpowerFromRfid($start, $end),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function itemManpowerFromRfid(Carbon $start, Carbon $end): array
    {
        $logs = EntryExitLog::query()
            ->whereBetween('occurred_at', [$start->copy()->subDays(14), $end])
            ->orderBy('occurred_at')
            ->get(['direction', 'occurred_at']);

        $opening = 0;
        foreach ($logs as $log) {
            if ($log->occurred_at->lt($start)) {
                $opening += $log->direction === Direction::In ? 1 : -1;
            }
        }
        $opening = max(0, $opening);

        $perDay = [];
        foreach ($this->eachDate($start, $end) as $date) {
            $dayStart = Carbon::parse($date)->startOfDay();
            $dayEnd = Carbon::parse($date)->endOfDay();
            $dayLogs = $logs->filter(fn ($log): bool => $log->occurred_at->betweenIncluded($dayStart, $dayEnd));

            $headcount = $opening;
            $peak = $opening;
            $entries = 0;
            $exits = 0;
            $weighted = 0.0;
            $prevAt = $dayStart;

            foreach ($dayLogs->sortBy('occurred_at') as $log) {
                $seconds = max(0, $prevAt->diffInSeconds($log->occurred_at));
                $weighted += $headcount * $seconds;
                if ($log->direction === Direction::In) {
                    $headcount++;
                    $entries++;
                } else {
                    $headcount = max(0, $headcount - 1);
                    $exits++;
                }
                $peak = max($peak, $headcount);
                $prevAt = $log->occurred_at;
            }

            $seconds = max(0, $prevAt->diffInSeconds($dayEnd));
            $weighted += $headcount * $seconds;
            $average = $dayStart->diffInSeconds($dayEnd) > 0
                ? round($weighted / $dayStart->diffInSeconds($dayEnd), 1)
                : (float) $opening;

            $perDay[] = [
                'date' => $date,
                'peak' => $peak,
                'average' => $average,
                'entries' => $entries,
                'exits' => $exits,
            ];

            $opening = $headcount;
        }

        return $perDay;
    }

    /**
     * @return array{count: int, note: string}
     */
    private function itemUnitsMonitored(): array
    {
        $count = Asset::query()
            ->where('status', AssetStatus::Active)
            ->where(function ($q): void {
                $q->whereHas('devices')->orWhereHas('cameras');
            })
            ->count();

        return [
            'count' => $count,
            'note' => 'active field units with monitoring devices',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function itemVehicleViolations(Carbon $start, Carbon $end): array
    {
        return VehicleViolation::query()
            ->with('logger')
            ->whereBetween('observed_at', [$start, $end])
            ->orderBy('observed_at')
            ->get()
            ->map(fn (VehicleViolation $row): array => [
                'observed_at' => optional($row->observed_at)?->toIso8601String(),
                'vehicle_description' => $row->vehicle_description,
                'violation_type' => $row->violation_type,
                'description' => $row->description,
                'action_taken' => $row->action_taken,
                'logged_by' => $row->logger?->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{per_day: list<array<string, mixed>>, alarm_events: list<array<string, mixed>>}
     */
    private function itemGas(Carbon $start, Carbon $end): array
    {
        $channels = [
            'lel' => 'lel_pct',
            'h2s' => 'h2s_ppm',
            'o2' => 'o2_pct',
            'co' => 'co_ppm',
            'co2' => 'co2_ppm',
        ];
        $dayExpr = SqlTimeBucket::day('recorded_at');
        $selects = ["{$dayExpr} as day"];
        foreach ($channels as $gas => $column) {
            $selects[] = "MIN({$column}) as {$gas}_min";
            $selects[] = "AVG({$column}) as {$gas}_avg";
            $selects[] = "MAX({$column}) as {$gas}_max";
        }

        $byDay = GasReading::query()
            ->whereBetween('recorded_at', [$start, $end])
            ->selectRaw(implode(', ', $selects))
            ->groupByRaw($dayExpr)
            ->orderBy('day')
            ->get()
            ->keyBy(fn (object $row): string => Carbon::parse((string) $row->day)->toDateString());

        $perDay = [];
        foreach ($this->eachDate($start, $end) as $date) {
            $row = $byDay->get($date);
            $day = ['date' => $date];
            foreach (array_keys($channels) as $gas) {
                $day[$gas] = [
                    'min' => $row !== null && $row->{"{$gas}_min"} !== null ? (float) $row->{"{$gas}_min"} : null,
                    'avg' => $row !== null && $row->{"{$gas}_avg"} !== null ? round((float) $row->{"{$gas}_avg"}, 2) : null,
                    'max' => $row !== null && $row->{"{$gas}_max"} !== null ? (float) $row->{"{$gas}_max"} : null,
                ];
            }
            $perDay[] = $day;
        }

        // Weekly report shows Alarm-level events only — Warning stays in live gas UI.
        $alarms = GasAlarm::query()
            ->with(['device', 'acknowledger'])
            ->where('level', GasAlarmLevel::Alarm)
            ->whereBetween('triggered_at', [$start, $end])
            ->orderBy('triggered_at')
            ->get()
            ->map(fn (GasAlarm $alarm): array => $this->alarmRow($alarm))
            ->values()
            ->all();

        return [
            'per_day' => $perDay,
            'alarm_events' => $alarms,
        ];
    }

    /**
     * Outage honesty (DOC-15 §4.5): one note per affected report item —
     * never a per-camera / per-device wall. Uses worst offline % among
     * devices over report.completeness_threshold_pct.
     *
     * @return list<array{item: string, message: string}>
     */
    private function completenessNotes(Carbon $start, Carbon $end): array
    {
        $threshold = (float) $this->settings->get('report.completeness_threshold_pct', 20);
        $periodSeconds = max(1, $start->diffInSeconds($end));

        $alerts = Alert::query()
            ->whereIn('alert_type', [AlertType::DeviceOffline, AlertType::CameraOffline])
            ->where(function ($q) use ($start, $end): void {
                $q->whereBetween('created_at', [$start, $end])
                    ->orWhere(function ($inner) use ($start): void {
                        $inner->where('created_at', '<', $start)
                            ->where(function ($open) use ($start): void {
                                $open->whereNull('resolved_at')->orWhere('resolved_at', '>', $start);
                            });
                    });
            })
            ->get();

        /** @var array<string, array{seconds: float, item: string, label: string}> $buckets */
        $buckets = [];

        foreach ($alerts as $alert) {
            $outageStart = Carbon::parse($alert->created_at)->max($start);
            $outageEnd = $alert->resolved_at !== null
                ? Carbon::parse($alert->resolved_at)->min($end)
                : $end;
            if ($outageEnd->lte($outageStart)) {
                continue;
            }
            $seconds = (float) $outageStart->diffInSeconds($outageEnd);

            if ($alert->alert_type === AlertType::CameraOffline) {
                $cameraId = (int) ($alert->payload['camera_id'] ?? 0);
                $label = (string) ($alert->payload['camera_name'] ?? ('Camera #'.$cameraId));
                if ($cameraId <= 0 && $label === 'Camera #0') {
                    continue;
                }
                $key = 'camera:'.($cameraId > 0 ? $cameraId : $label);
                $buckets[$key] = [
                    'seconds' => ($buckets[$key]['seconds'] ?? 0) + $seconds,
                    'item' => 'vi_units_monitored',
                    'label' => $label,
                ];

                continue;
            }

            $deviceId = (int) ($alert->payload['device_id'] ?? 0);
            if ($deviceId <= 0) {
                continue;
            }
            $device = Device::query()->find($deviceId);
            $fromPayload = trim((string) ($alert->payload['device_name'] ?? ''));
            $label = $device?->name ?? ($fromPayload !== '' ? $fromPayload : 'Device #'.$deviceId);
            // Unknown / deleted device id: still declare the gap (DOC-15 honesty)
            // under units-monitored — never default cameras/RFID into ix_gas.
            $item = match ($device?->device_type) {
                DeviceType::GasDetector => 'ix_gas',
                DeviceType::EnvironmentalSensor => 'iv_weather',
                DeviceType::RfidReader => 'v_manpower',
                DeviceType::EdgeCompute => 'vi_units_monitored',
                default => 'vi_units_monitored',
            };
            $key = 'device:'.$deviceId;
            $buckets[$key] = [
                'seconds' => ($buckets[$key]['seconds'] ?? 0) + $seconds,
                'item' => $item,
                'label' => $label,
            ];
        }

        /** @var array<string, list<array{label: string, pct: float}>> $byItem */
        $byItem = [];

        foreach ($buckets as $bucket) {
            $pct = min(100.0, round(($bucket['seconds'] / $periodSeconds) * 100, 1));
            if ($pct <= $threshold) {
                continue;
            }

            $byItem[$bucket['item']][] = [
                'label' => $bucket['label'],
                'pct' => $pct,
            ];
        }

        $itemTitles = [
            'i_daily_safety_observations' => 'PPE camera coverage',
            'iv_weather' => 'Weather / environmental',
            'v_manpower' => 'Site headcount',
            'vi_units_monitored' => 'Field unit monitoring',
            'ix_gas' => 'Gas telemetry',
        ];

        $notes = [];

        foreach ($byItem as $item => $devices) {
            usort($devices, static fn (array $a, array $b): int => $b['pct'] <=> $a['pct']);
            $count = count($devices);
            $worst = $devices[0]['pct'];
            $title = $itemTitles[$item] ?? 'Sensor coverage';

            if ($count === 1) {
                $notes[] = [
                    'item' => $item,
                    'message' => sprintf(
                        '%s incomplete — %s offline %.0f%% of the period.',
                        $title,
                        $devices[0]['label'],
                        $worst,
                    ),
                ];

                continue;
            }

            $notes[] = [
                'item' => $item,
                'message' => sprintf(
                    '%s incomplete — %d units offline more than %.0f%% of the period (worst %.0f%%).',
                    $title,
                    $count,
                    $threshold,
                    $worst,
                ),
            ];
        }

        usort($notes, static fn (array $a, array $b): int => strcmp($a['item'], $b['item']));

        return $notes;
    }

    /**
     * @return array{pdf: string, csv: string}
     */
    private function renderArtifacts(WeeklyReport $report): array
    {
        $report->loadMissing('supersedes');
        $badges = $this->automationBadges();
        $view = WeeklyReportPresenter::for($report, $badges);

        $dir = 'reports/'.$report->id;
        Storage::disk('private')->makeDirectory($dir);

        $pdf = Pdf::loadView('pdf.weekly-report', [
            'report' => $report,
            'data' => $report->data,
            'badges' => $badges,
            'view' => $view,
        ]);
        $pdfPath = $dir.'/report.pdf';
        Storage::disk('private')->put($pdfPath, $pdf->output());

        $zipPath = $dir.'/report-csvs.zip';
        $tmpZip = tempnam(sys_get_temp_dir(), 'wrzip');
        if ($tmpZip === false) {
            throw new HttpException(500, 'Unable to create CSV zip.');
        }

        $zip = new ZipArchive;
        $zip->open($tmpZip, ZipArchive::OVERWRITE);
        foreach ($view->csvFiles() as $name => $csv) {
            $zip->addFromString($name, $csv);
        }
        $zip->close();
        Storage::disk('private')->put($zipPath, (string) file_get_contents($tmpZip));
        @unlink($tmpZip);

        return ['pdf' => $pdfPath, 'csv' => $zipPath];
    }

    /**
     * @return array<string, string>
     */
    public function automationBadges(): array
    {
        return [
            'i_daily_safety_observations' => 'Automated',
            'ii_hse_incidents' => 'Auto-detect + Manual',
            'iii_lsr_violations' => 'Automated + Manual',
            'iv_weather' => 'Automated',
            'v_manpower' => 'Automated',
            'vi_units_monitored' => 'Automated (partial)',
            'vii_vehicle_violations' => 'Manual',
            'ix_gas' => 'Automated',
        ];
    }

    private function nextReportNumber(Carbon $periodStart): string
    {
        // ISO week number is defined by the Thursday of the reporting week (ISO-8601),
        // so Ww stays correct for whatever report.week_start is configured.
        $weekStart = $this->weekStartConstant();
        $thursday = $periodStart->copy()->startOfDay()->startOfWeek($weekStart)->addDays(3);
        $base = 'WR-'.$thursday->format('o').'-W'.$thursday->format('W');

        if (! WeeklyReport::query()->withTrashed()->where('report_number', $base)->exists()) {
            return $base;
        }

        $suffix = 1;
        while (WeeklyReport::query()->withTrashed()->where('report_number', $base.'-'.$suffix)->exists()) {
            $suffix++;
        }

        return $base.'-'.$suffix;
    }

    private function notifyPublishHolders(WeeklyReport $report): void
    {
        User::permission('update-reports')
            ->where('is_active', true)
            ->get()
            ->each(fn (User $user) => $user->notify(new WeeklyReportReadyNotification($report)));
    }

    /**
     * @return list<string>
     */
    private function eachDate(Carbon $start, Carbon $end): array
    {
        $dates = [];
        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();
        while ($cursor->lte($last)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }

    /**
     * @return array<string, mixed>
     */
    private function alarmRow(GasAlarm $alarm): array
    {
        $duration = $alarm->resolved_at !== null
            ? $alarm->triggered_at->diffInSeconds($alarm->resolved_at)
            : null;

        return [
            'triggered_at' => optional($alarm->triggered_at)?->toIso8601String(),
            'device' => $alarm->device?->name,
            'gas' => $alarm->gas_type->value,
            'level' => $alarm->level->value,
            'peak' => (float) $alarm->reading_value,
            'duration_s' => $duration,
            'acknowledged_by' => $alarm->acknowledger?->name,
            'during_outage' => $alarm->during_outage,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function audit(string $eventType, array $payload): void
    {
        AuditLog::query()->create([
            'event_type' => $eventType,
            'user_id' => auth()->id(),
            'route' => request()->path(),
            'payload' => $payload,
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
