<?php

namespace App\Services;

use App\Enums\AlertType;
use App\Enums\AssetStatus;
use App\Enums\DeviceType;
use App\Enums\Direction;
use App\Enums\GasType;
use App\Enums\ReportStatus;
use App\Enums\ReviewStatus;
use App\Models\Alert;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\EntryExitLog;
use App\Models\EnvironmentalRollup;
use App\Models\GasAlarm;
use App\Models\GasReadingRollup;
use App\Models\HseIncident;
use App\Models\LsrViolation;
use App\Models\PpeViolation;
use App\Models\User;
use App\Models\VehicleViolation;
use App\Models\WeeklyReport;
use App\Notifications\WeeklyReportReadyNotification;
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
            'viii_environmental',
            'ix_gas',
            'x_co2',
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
            'data' => $report->data,
            'created_at' => optional($report->created_at)?->toIso8601String(),
        ];
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
            'viii_environmental' => $this->itemEnvironmental($start, $end),
            'ix_gas' => $this->itemGas($start, $end, excludeCo2: true),
            'x_co2' => $this->itemCo2($start, $end),
            'completeness' => ['notes' => $completenessNotes],
        ];
    }

    /**
     * @return array{per_day: list<array<string, mixed>>, by_camera: list<array<string, mixed>>, false_positives_excluded: int}
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
            'false_positives_excluded' => (int) $summary['excluded_false_positives'],
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
        $rows = EnvironmentalRollup::query()
            ->whereBetween('bucket_start', [$start, $end])
            ->orderBy('bucket_start')
            ->get();

        $perDay = [];
        foreach ($this->eachDate($start, $end) as $date) {
            $day = $rows->filter(fn (EnvironmentalRollup $r): bool => $r->bucket_start->toDateString() === $date);
            $perDay[] = [
                'date' => $date,
                'temp' => $this->aggMinAvgMax($day, 'temp_min', 'temp_avg', 'temp_max'),
                'humidity' => $this->aggMinAvgMax($day, 'humidity_min', 'humidity_avg', 'humidity_max'),
                'wind' => $this->aggMinAvgMax($day, 'wind_min', 'wind_avg', 'wind_max'),
            ];
        }

        return ['per_day' => $perDay];
    }

    /**
     * @return array{per_day: list<array<string, mixed>>}
     */
    private function itemManpower(Carbon $start, Carbon $end): array
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

        return ['per_day' => $perDay];
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
     * @return array{per_day: list<array<string, mixed>>}
     */
    private function itemEnvironmental(Carbon $start, Carbon $end): array
    {
        $rows = EnvironmentalRollup::query()
            ->whereBetween('bucket_start', [$start, $end])
            ->orderBy('bucket_start')
            ->get();

        $perDay = [];
        foreach ($this->eachDate($start, $end) as $date) {
            $day = $rows->filter(fn (EnvironmentalRollup $r): bool => $r->bucket_start->toDateString() === $date);
            $air = [];
            foreach ($day as $row) {
                foreach (($row->extra_stats ?? []) as $param => $stats) {
                    if (! is_array($stats)) {
                        continue;
                    }
                    $air[$param] ??= ['min' => null, 'avg_sum' => 0.0, 'avg_n' => 0, 'max' => null];
                    if (isset($stats['min'])) {
                        $air[$param]['min'] = $air[$param]['min'] === null
                            ? (float) $stats['min']
                            : min($air[$param]['min'], (float) $stats['min']);
                    }
                    if (isset($stats['max'])) {
                        $air[$param]['max'] = $air[$param]['max'] === null
                            ? (float) $stats['max']
                            : max($air[$param]['max'], (float) $stats['max']);
                    }
                    if (isset($stats['avg'])) {
                        $air[$param]['avg_sum'] += (float) $stats['avg'];
                        $air[$param]['avg_n']++;
                    }
                }
            }

            $airOut = [];
            foreach ($air as $param => $agg) {
                $airOut[$param] = [
                    'min' => $agg['min'],
                    'avg' => $agg['avg_n'] > 0 ? round($agg['avg_sum'] / $agg['avg_n'], 2) : null,
                    'max' => $agg['max'],
                ];
            }

            $perDay[] = [
                'date' => $date,
                'air_quality' => $airOut,
            ];
        }

        return ['per_day' => $perDay];
    }

    /**
     * @return array{per_gas_per_day: list<array<string, mixed>>, alarm_events: list<array<string, mixed>>}
     */
    private function itemGas(Carbon $start, Carbon $end, bool $excludeCo2): array
    {
        $types = collect(GasType::cases())
            ->reject(fn (GasType $t): bool => $excludeCo2 && $t === GasType::Co2)
            ->values();

        $rollups = GasReadingRollup::query()
            ->whereBetween('bucket_start', [$start, $end])
            ->get();

        $perGasPerDay = [];
        foreach ($this->eachDate($start, $end) as $date) {
            $day = $rollups->filter(fn (GasReadingRollup $r): bool => $r->bucket_start->toDateString() === $date);
            foreach ($types as $type) {
                if ($type === GasType::O2High) {
                    continue;
                }
                $prefix = match ($type) {
                    GasType::Lel => 'lel',
                    GasType::H2s => 'h2s',
                    GasType::O2Low => 'o2',
                    GasType::Co => 'co',
                    GasType::Co2 => 'co2',
                    default => null,
                };
                if ($prefix === null) {
                    continue;
                }
                $perGasPerDay[] = [
                    'date' => $date,
                    'gas' => $type === GasType::O2Low ? 'o2' : $type->value,
                    'min' => $this->nullableMin($day->pluck("{$prefix}_min")),
                    'avg' => $this->nullableAvg($day->pluck("{$prefix}_avg")),
                    'max' => $this->nullableMax($day->pluck("{$prefix}_max")),
                ];
            }
        }

        $alarms = GasAlarm::query()
            ->with(['device', 'acknowledger'])
            ->whereBetween('triggered_at', [$start, $end])
            ->when($excludeCo2, fn ($q) => $q->where('gas_type', '!=', GasType::Co2->value))
            ->orderBy('triggered_at')
            ->get()
            ->map(fn (GasAlarm $alarm): array => $this->alarmRow($alarm))
            ->values()
            ->all();

        return [
            'per_gas_per_day' => $perGasPerDay,
            'alarm_events' => $alarms,
        ];
    }

    /**
     * @return array{per_day: list<array<string, mixed>>, alarm_events: list<array<string, mixed>>}
     */
    private function itemCo2(Carbon $start, Carbon $end): array
    {
        $rollups = GasReadingRollup::query()
            ->whereBetween('bucket_start', [$start, $end])
            ->get();

        $perDay = [];
        foreach ($this->eachDate($start, $end) as $date) {
            $day = $rollups->filter(fn (GasReadingRollup $r): bool => $r->bucket_start->toDateString() === $date);
            $perDay[] = [
                'date' => $date,
                'min' => $this->nullableMin($day->pluck('co2_min')),
                'avg' => $this->nullableAvg($day->pluck('co2_avg')),
                'max' => $this->nullableMax($day->pluck('co2_max')),
            ];
        }

        $alarms = GasAlarm::query()
            ->with(['device', 'acknowledger'])
            ->whereBetween('triggered_at', [$start, $end])
            ->where('gas_type', GasType::Co2)
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
     * @return list<array{item: string, message: string}>
     */
    private function completenessNotes(Carbon $start, Carbon $end): array
    {
        $threshold = (float) $this->settings->get('report.completeness_threshold_pct', 20);
        $periodSeconds = max(1, $start->diffInSeconds($end));
        $notes = [];

        $alerts = Alert::query()
            ->where('alert_type', AlertType::DeviceOffline)
            ->where(function ($q) use ($start, $end): void {
                $q->whereBetween('created_at', [$start, $end])
                    ->orWhere(function ($inner) use ($start, $end): void {
                        $inner->where('created_at', '<', $start)
                            ->where(function ($open) use ($start): void {
                                $open->whereNull('resolved_at')->orWhere('resolved_at', '>', $start);
                            });
                    });
            })
            ->get();

        $byDevice = [];
        foreach ($alerts as $alert) {
            $deviceId = (int) ($alert->payload['device_id'] ?? 0);
            if ($deviceId <= 0) {
                continue;
            }
            $outageStart = Carbon::parse($alert->created_at)->max($start);
            $outageEnd = $alert->resolved_at !== null
                ? Carbon::parse($alert->resolved_at)->min($end)
                : $end;
            if ($outageEnd->lte($outageStart)) {
                continue;
            }
            $byDevice[$deviceId] = ($byDevice[$deviceId] ?? 0) + $outageStart->diffInSeconds($outageEnd);
        }

        foreach ($byDevice as $deviceId => $seconds) {
            $pct = round(($seconds / $periodSeconds) * 100, 1);
            if ($pct <= $threshold) {
                continue;
            }

            $device = \App\Models\Device::query()->find($deviceId);
            $item = match ($device?->device_type) {
                DeviceType::GasDetector => 'ix_gas',
                DeviceType::Co2Sensor => 'x_co2',
                DeviceType::EnvironmentalSensor => 'viii_environmental',
                default => 'ix_gas',
            };

            $notes[] = [
                'item' => $item,
                'message' => sprintf(
                    '%s telemetry offline %.1f%% of the period (%s).',
                    $device?->name ?? ('Device #'.$deviceId),
                    $pct,
                    $device?->name ?? ('id '.$deviceId),
                ),
            ];
        }

        return $notes;
    }

    /**
     * @return array{pdf: string, csv: string}
     */
    private function renderArtifacts(WeeklyReport $report): array
    {
        $dir = 'reports/'.$report->id;
        Storage::disk('private')->makeDirectory($dir);

        $pdf = Pdf::loadView('pdf.weekly-report', [
            'report' => $report,
            'data' => $report->data,
            'badges' => $this->automationBadges(),
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
        foreach ($this->csvFiles($report) as $name => $csv) {
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
    private function csvFiles(WeeklyReport $report): array
    {
        $data = $report->data ?? [];
        $files = [
            'summary.csv' => $this->toCsv([
                ['report_number', 'period_start', 'period_end', 'status'],
                [$report->report_number, $data['period']['start'] ?? '', $data['period']['end'] ?? '', $report->status->value],
            ]),
            'i_daily_safety_observations.csv' => $this->rowsToCsv($data['i_daily_safety_observations']['per_day'] ?? []),
            'ii_hse_incidents.csv' => $this->rowsToCsv($data['ii_hse_incidents'] ?? []),
            'iii_lsr_violations.csv' => $this->rowsToCsv($data['iii_lsr_violations']['entries'] ?? []),
            'iv_weather.csv' => $this->rowsToCsv($data['iv_weather']['per_day'] ?? []),
            'v_manpower.csv' => $this->rowsToCsv($data['v_manpower']['per_day'] ?? []),
            'vi_units_monitored.csv' => $this->toCsv([
                ['count', 'note'],
                [$data['vi_units_monitored']['count'] ?? 0, $data['vi_units_monitored']['note'] ?? ''],
            ]),
            'vii_vehicle_violations.csv' => $this->rowsToCsv($data['vii_vehicle_violations'] ?? []),
            'viii_environmental.csv' => $this->rowsToCsv($data['viii_environmental']['per_day'] ?? []),
            'ix_gas.csv' => $this->rowsToCsv($data['ix_gas']['per_gas_per_day'] ?? []),
            'x_co2.csv' => $this->rowsToCsv($data['x_co2']['per_day'] ?? []),
        ];

        return $files;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function rowsToCsv(array $rows): string
    {
        if ($rows === []) {
            return "empty\n";
        }

        $headers = array_keys($this->flattenRow($rows[0]));
        $lines = [$headers];
        foreach ($rows as $row) {
            $flat = $this->flattenRow($row);
            $lines[] = array_map(fn (string $h) => $flat[$h] ?? '', $headers);
        }

        return $this->toCsv($lines);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private function flattenRow(array $row): array
    {
        $out = [];
        foreach ($row as $key => $value) {
            if (is_array($value)) {
                $out[$key] = json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
            } else {
                $out[$key] = (string) ($value ?? '');
            }
        }

        return $out;
    }

    /**
     * @param  list<list<string|int|float|null>>  $lines
     */
    private function toCsv(array $lines): string
    {
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            return '';
        }
        foreach ($lines as $line) {
            fputcsv($fh, $line);
        }
        rewind($fh);
        $csv = stream_get_contents($fh) ?: '';
        fclose($fh);

        return $csv;
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
            'viii_environmental' => 'Automated',
            'ix_gas' => 'Automated',
            'x_co2' => 'Automated',
        ];
    }

    private function nextReportNumber(Carbon $periodStart): string
    {
        $base = 'WR-'.$periodStart->format('Y').'-W'.$periodStart->format('W');
        $existing = WeeklyReport::query()
            ->withTrashed()
            ->where('report_number', 'like', $base.'%')
            ->count();

        return $existing === 0 ? $base : $base.'-'.($existing + 1);
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
     * @param  \Illuminate\Support\Collection<int, EnvironmentalRollup>  $day
     * @return array{min: float|null, avg: float|null, max: float|null}
     */
    private function aggMinAvgMax($day, string $minCol, string $avgCol, string $maxCol): array
    {
        return [
            'min' => $this->nullableMin($day->pluck($minCol)),
            'avg' => $this->nullableAvg($day->pluck($avgCol)),
            'max' => $this->nullableMax($day->pluck($maxCol)),
        ];
    }

    private function nullableMin($values): ?float
    {
        $nums = collect($values)->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v);
        return $nums->isEmpty() ? null : $nums->min();
    }

    private function nullableMax($values): ?float
    {
        $nums = collect($values)->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v);
        return $nums->isEmpty() ? null : $nums->max();
    }

    private function nullableAvg($values): ?float
    {
        $nums = collect($values)->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v);
        return $nums->isEmpty() ? null : round($nums->avg(), 2);
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
