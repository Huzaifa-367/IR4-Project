<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Enums\AlertType;
use App\Enums\DeviceType;
use App\Enums\GasAlarmLevel;
use App\Enums\GasType;
use App\Enums\ThresholdDirection;
use App\Events\GasLiveUpdated;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\GasAlarm;
use App\Models\GasReading;
use App\Models\GasReadingRollup;
use App\Models\GasThreshold;
use App\Models\User;
use App\Support\Ingest\IngestEventRejected;
use App\Support\Ingest\IngestTimestamps;
use App\Support\Ingest\ReferenceResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class GasMonitoringService
{
    /** @var array<int, true> */
    private array $pendingLiveBroadcasts = [];

    public function __construct(
        private readonly IngestTimestamps $timestamps,
        private readonly ReferenceResolver $refs,
        private readonly AlertService $alerts,
        private readonly SettingsService $settings,
        private readonly AuditService $audit,
        private readonly SensorRollupService $rollups,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array{accepted: int, duplicates: int, rejected: list<array{index: int, code: string}>}
     */
    public function ingestEvents(Device $caller, array $events): array
    {
        $accepted = 0;
        $duplicates = 0;
        /** @var list<array{index: int, code: string}> $rejected */
        $rejected = [];
        $sawClockSkew = false;

        foreach ($events as $index => $event) {
            if (! is_array($event)) {
                $rejected[] = ['index' => (int) $index, 'code' => 'VALIDATION_FAILED'];

                continue;
            }

            try {
                $result = $this->processOneEvent($caller, $event);
                if ($result === 'duplicate') {
                    $duplicates++;
                } else {
                    $accepted++;
                    if ($result === 'skew') {
                        $sawClockSkew = true;
                    }
                }
            } catch (IngestEventRejected $e) {
                $rejected[] = ['index' => (int) $index, 'code' => $e->rejectionCode];
            }
        }

        if ($sawClockSkew) {
            $day = Carbon::now()->toDateString();
            $this->alerts->raise(
                type: AlertType::ClockSkew,
                title: "Clock skew on device {$caller->name}",
                payload: ['device_id' => $caller->id, 'day' => $day],
                source: $caller,
                dedupeKey: "clock_skew:{$caller->id}:{$day}",
            );
        }

        $this->flushLiveBroadcasts();

        return [
            'accepted' => $accepted,
            'duplicates' => $duplicates,
            'rejected' => $rejected,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function livePanels(): array
    {
        $staleMinutes = (int) $this->settings->get('health.gas_stale_minutes', 5);
        $devices = Device::query()
            ->whereIn('device_type', [DeviceType::GasDetector, DeviceType::Co2Sensor])
            ->with('asset')
            ->orderBy('name')
            ->get();

        $panels = [];
        foreach ($devices as $device) {
            $latest = GasReading::query()
                ->where('device_id', $device->id)
                ->orderByDesc('recorded_at')
                ->first();
            $panels[] = $this->panelPayload($device, $latest, $staleMinutes);
        }

        return $panels;
    }

    /**
     * @return array{points: list<array<string, mixed>>, source: string}
     */
    public function trends(?int $deviceId, GasType $gasType, Carbon $from, Carbon $to): array
    {
        $hours = $from->diffInHours($to);
        $column = $gasType->readingColumn();

        if ($hours <= 24) {
            $query = GasReading::query()
                ->whereBetween('recorded_at', [$from, $to])
                ->whereNotNull($column)
                ->orderBy('recorded_at');
            if ($deviceId !== null) {
                $query->where('device_id', $deviceId);
            }
            $points = $query->get()->map(fn (GasReading $r): array => [
                'at' => $r->recorded_at->toIso8601String(),
                'value' => (float) $r->{$column},
                'min' => (float) $r->{$column},
                'avg' => (float) $r->{$column},
                'max' => (float) $r->{$column},
                'device_id' => $r->device_id,
            ])->all();

            return ['points' => $points, 'source' => 'raw'];
        }

        $prefix = match ($gasType) {
            GasType::Lel => 'lel',
            GasType::H2s => 'h2s',
            GasType::O2Low, GasType::O2High => 'o2',
            GasType::Co => 'co',
            GasType::Co2 => 'co2',
        };

        $query = GasReadingRollup::query()
            ->whereBetween('bucket_start', [$from, $to])
            ->orderBy('bucket_start');
        if ($deviceId !== null) {
            $query->where('device_id', $deviceId);
        }

        $points = $query->get()->map(fn (GasReadingRollup $r): array => [
            'at' => $r->bucket_start->toIso8601String(),
            'value' => $r->{"{$prefix}_avg"} !== null ? (float) $r->{"{$prefix}_avg"} : null,
            'min' => $r->{"{$prefix}_min"} !== null ? (float) $r->{"{$prefix}_min"} : null,
            'avg' => $r->{"{$prefix}_avg"} !== null ? (float) $r->{"{$prefix}_avg"} : null,
            'max' => $r->{"{$prefix}_max"} !== null ? (float) $r->{"{$prefix}_max"} : null,
            'device_id' => $r->device_id,
        ])->filter(fn (array $p): bool => $p['avg'] !== null)->values()->all();

        return ['points' => $points, 'source' => 'rollup'];
    }

    /**
     * @param  list<array{gas_type: string, warning_level: float|int|string, alarm_level: float|int|string}>  $rows
     * @return list<GasThreshold>
     */
    public function updateThresholds(array $rows, User $user): array
    {
        $updated = [];
        foreach ($rows as $row) {
            $type = GasType::from((string) $row['gas_type']);
            /** @var GasThreshold $threshold */
            $threshold = GasThreshold::query()->where('gas_type', $type)->firstOrFail();
            $before = [
                'warning_level' => (float) $threshold->warning_level,
                'alarm_level' => (float) $threshold->alarm_level,
            ];
            $threshold->forceFill([
                'warning_level' => $row['warning_level'],
                'alarm_level' => $row['alarm_level'],
                'updated_by' => $user->id,
            ])->save();

            AuditLog::query()->create([
                'event_type' => 'config_changed',
                'user_id' => $user->id,
                'route' => request()->path(),
                'payload' => [
                    'target' => 'gas_threshold',
                    'gas_type' => $type->value,
                    'before' => $before,
                    'after' => [
                        'warning_level' => (float) $threshold->warning_level,
                        'alarm_level' => (float) $threshold->alarm_level,
                    ],
                ],
                'ip' => request()->ip(),
                'created_at' => now(),
            ]);

            $updated[] = $threshold->fresh() ?? $threshold;
        }

        return $updated;
    }

    public function acknowledgeAlarm(GasAlarm $alarm, User $user): GasAlarm
    {
        if ($alarm->resolved_at !== null) {
            throw new HttpException(409, 'Resolved alarms cannot be acknowledged.');
        }

        $alarm->forceFill([
            'acknowledged_by' => $user->id,
            'acknowledged_at' => now(),
        ])->save();
        $this->audit->record(
            AuditEvent::Acknowledged,
            $alarm,
            'Acknowledged gas alarm.',
            user: $user,
        );

        if ($alarm->alert_id !== null) {
            $alarm->loadMissing('alert');
            if ($alarm->alert !== null) {
                $this->alerts->acknowledge($alarm->alert, $user);
            }
        }

        return $alarm->fresh() ?? $alarm;
    }

    /**
     * @return array<string, mixed>
     */
    public function alarmToArray(GasAlarm $alarm): array
    {
        $alarm->loadMissing(['device', 'acknowledger']);

        return [
            'id' => $alarm->id,
            'device_id' => $alarm->device_id,
            'device_name' => $alarm->device?->name,
            'device_ref' => $alarm->device?->reference,
            'gas_type' => $alarm->gas_type->value,
            'level' => $alarm->level->value,
            'reading_value' => (float) $alarm->reading_value,
            'threshold_value' => (float) $alarm->threshold_value,
            'triggered_at' => $alarm->triggered_at->toIso8601String(),
            'resolved_at' => $alarm->resolved_at?->toIso8601String(),
            'acknowledged_by' => $alarm->acknowledged_by,
            'acknowledged_by_name' => $alarm->acknowledger?->name,
            'acknowledged_at' => $alarm->acknowledged_at?->toIso8601String(),
            'during_outage' => $alarm->during_outage,
            'alert_id' => $alarm->alert_id,
            'is_open' => $alarm->isOpen(),
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     * @return 'accepted'|'duplicate'|'skew'
     */
    private function processOneEvent(Device $caller, array $event): string
    {
        $device = $caller;
        if (isset($event['device_ref']) && is_string($event['device_ref']) && $event['device_ref'] !== '') {
            $resolved = $this->refs->resolveDevice($event['device_ref']);
            if ($resolved === null) {
                throw new IngestEventRejected('UNKNOWN_REFERENCE');
            }
            if ($resolved->id !== $caller->id) {
                throw new IngestEventRejected('FORBIDDEN_REFERENCE');
            }
            $device = $resolved;
        }

        $eventUid = (string) ($event['event_uid'] ?? '');
        $normalized = $this->timestamps->normalize(Carbon::parse((string) $event['recorded_at']));

        if (GasReading::query()
            ->where('device_id', $device->id)
            ->where('event_uid', $eventUid)
            ->exists()) {
            return 'duplicate';
        }

        $channels = [
            'lel_pct' => isset($event['lel_pct']) ? (float) $event['lel_pct'] : null,
            'h2s_ppm' => isset($event['h2s_ppm']) ? (float) $event['h2s_ppm'] : null,
            'o2_pct' => isset($event['o2_pct']) ? (float) $event['o2_pct'] : null,
            'co_ppm' => isset($event['co_ppm']) ? (float) $event['co_ppm'] : null,
            'co2_ppm' => isset($event['co2_ppm']) ? (float) $event['co2_ppm'] : null,
        ];

        if (
            $channels['lel_pct'] === null
            && $channels['h2s_ppm'] === null
            && $channels['o2_pct'] === null
            && $channels['co_ppm'] === null
            && $channels['co2_ppm'] === null
        ) {
            throw new IngestEventRejected('VALIDATION_FAILED');
        }

        try {
            $reading = GasReading::query()->create([
                'device_id' => $device->id,
                'asset_id' => $device->asset_id,
                'recorded_at' => $normalized['recorded_at'],
                'received_at' => $normalized['received_at'],
                'lel_pct' => $channels['lel_pct'],
                'h2s_ppm' => $channels['h2s_ppm'],
                'o2_pct' => $channels['o2_pct'],
                'co_ppm' => $channels['co_ppm'],
                'co2_ppm' => $channels['co2_ppm'],
                'is_backfill' => $normalized['is_backfill'],
                'clock_skew' => $normalized['clock_skew'],
                'event_uid' => $eventUid,
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return 'duplicate';
            }
            throw $e;
        }

        $this->rollups->rebuildGasBucket($reading->device_id, $reading->recorded_at->copy()->startOfHour());

        if (! $normalized['is_backfill']) {
            $this->evaluate($reading, $device);
            $this->pendingLiveBroadcasts[$device->id] = true;
        }

        return $normalized['clock_skew'] ? 'skew' : 'accepted';
    }

    private function evaluate(GasReading $reading, Device $device): void
    {
        $thresholds = GasThreshold::query()->where('is_active', true)->get()->keyBy(
            fn (GasThreshold $t): string => $t->gas_type->value,
        );

        foreach (GasType::cases() as $gasType) {
            $column = $gasType->readingColumn();
            $value = $reading->{$column};
            if ($value === null) {
                continue;
            }
            $value = (float) $value;

            /** @var GasThreshold|null $threshold */
            $threshold = $thresholds->get($gasType->value);
            if ($threshold === null) {
                continue;
            }

            $level = $this->crossingLevel($value, $threshold);
            if ($level !== null) {
                $this->raiseOrEscalate($device, $reading, $gasType, $level, $value, $threshold);
                Cache::forget($this->cleanKey($device->id, $gasType));
            } else {
                $this->trackHysteresis($device, $gasType, $value, $threshold);
            }
        }
    }

    private function crossingLevel(float $value, GasThreshold $threshold): ?GasAlarmLevel
    {
        $warn = (float) $threshold->warning_level;
        $alarm = (float) $threshold->alarm_level;

        if ($threshold->direction === ThresholdDirection::Below) {
            if ($value <= $alarm) {
                return GasAlarmLevel::Alarm;
            }
            if ($value <= $warn) {
                return GasAlarmLevel::Warning;
            }

            return null;
        }

        if ($value >= $alarm) {
            return GasAlarmLevel::Alarm;
        }
        if ($value >= $warn) {
            return GasAlarmLevel::Warning;
        }

        return null;
    }

    private function raiseOrEscalate(
        Device $device,
        GasReading $reading,
        GasType $gasType,
        GasAlarmLevel $level,
        float $value,
        GasThreshold $threshold,
    ): void {
        $openSame = GasAlarm::query()
            ->where('device_id', $device->id)
            ->where('gas_type', $gasType)
            ->where('level', $level)
            ->whereNull('resolved_at')
            ->first();

        if ($openSame !== null) {
            return;
        }

        if ($level === GasAlarmLevel::Alarm) {
            $openWarn = GasAlarm::query()
                ->where('device_id', $device->id)
                ->where('gas_type', $gasType)
                ->where('level', GasAlarmLevel::Warning)
                ->whereNull('resolved_at')
                ->first();
            if ($openWarn !== null) {
                $this->resolveAlarm($openWarn);
            }
        }

        $thresholdValue = $level === GasAlarmLevel::Alarm
            ? (float) $threshold->alarm_level
            : (float) $threshold->warning_level;

        $alertType = $level === GasAlarmLevel::Alarm ? AlertType::GasAlarm : AlertType::GasWarning;
        $dedupe = "gas:{$device->id}:{$gasType->value}:{$level->value}";

        $alert = $this->alerts->raise(
            type: $alertType,
            title: "{$gasType->label()} {$level->label()} — {$device->name}",
            payload: [
                'device_id' => $device->id,
                'device_ref' => $device->reference,
                'gas_type' => $gasType->value,
                'level' => $level->value,
                'reading_value' => $value,
                'threshold_value' => $thresholdValue,
            ],
            source: $device,
            dedupeKey: $dedupe,
        );

        GasAlarm::query()->create([
            'device_id' => $device->id,
            'asset_id' => $device->asset_id,
            'gas_type' => $gasType,
            'level' => $level,
            'reading_value' => $value,
            'threshold_value' => $thresholdValue,
            'triggered_at' => $reading->recorded_at,
            'alert_id' => $alert->id,
            'during_outage' => false,
        ]);
    }

    private function trackHysteresis(Device $device, GasType $gasType, float $value, GasThreshold $threshold): void
    {
        $open = GasAlarm::query()
            ->where('device_id', $device->id)
            ->where('gas_type', $gasType)
            ->whereNull('resolved_at')
            ->get();

        if ($open->isEmpty()) {
            Cache::forget($this->cleanKey($device->id, $gasType));

            return;
        }

        if (! $this->isUnderClearLine($value, $threshold)) {
            Cache::forget($this->cleanKey($device->id, $gasType));

            return;
        }

        $key = $this->cleanKey($device->id, $gasType);
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, now()->addHour());

        if ($count < 2) {
            return;
        }

        foreach ($open as $alarm) {
            $this->resolveAlarm($alarm);
        }
        Cache::forget($key);
    }

    private function isUnderClearLine(float $value, GasThreshold $threshold): bool
    {
        $warn = (float) $threshold->warning_level;
        $alarm = (float) $threshold->alarm_level;
        $marginPct = (float) $this->settings->get('gas.hysteresis_margin_pct', 5);
        $span = abs($alarm - $warn);
        $margin = $span * ($marginPct / 100);

        if ($threshold->direction === ThresholdDirection::Below) {
            return $value > ($warn + $margin);
        }

        return $value < ($warn - $margin);
    }

    private function resolveAlarm(GasAlarm $alarm): void
    {
        if ($alarm->resolved_at !== null) {
            return;
        }

        $alarm->forceFill(['resolved_at' => now()])->save();
        $dedupe = "gas:{$alarm->device_id}:{$alarm->gas_type->value}:{$alarm->level->value}";
        $this->alerts->resolveByDedupeKey($dedupe);
    }

    private function flushLiveBroadcasts(): void
    {
        $throttle = (int) $this->settings->get('realtime.gas_throttle_seconds', 5);
        $staleMinutes = (int) $this->settings->get('health.gas_stale_minutes', 5);

        foreach (array_keys($this->pendingLiveBroadcasts) as $deviceId) {
            $cacheKey = "gas:live:broadcast:{$deviceId}";
            if (Cache::has($cacheKey)) {
                continue;
            }
            Cache::put($cacheKey, true, now()->addSeconds($throttle));

            $device = Device::query()->with('asset')->find($deviceId);
            if ($device === null) {
                continue;
            }
            $latest = GasReading::query()
                ->where('device_id', $deviceId)
                ->orderByDesc('recorded_at')
                ->first();

            broadcast(new GasLiveUpdated($this->panelPayload($device, $latest, $staleMinutes)));
        }

        $this->pendingLiveBroadcasts = [];
    }

    /**
     * @return array<string, mixed>
     */
    private function panelPayload(Device $device, ?GasReading $latest, int $staleMinutes): array
    {
        $openAlarms = GasAlarm::query()
            ->where('device_id', $device->id)
            ->whereNull('resolved_at')
            ->get()
            ->map(fn (GasAlarm $a): array => [
                'gas_type' => $a->gas_type->value,
                'level' => $a->level->value,
            ])
            ->values()
            ->all();

        $recordedAt = $latest?->recorded_at;
        $isStale = $recordedAt === null || $recordedAt->lessThan(now()->subMinutes($staleMinutes));

        return [
            'device_id' => $device->id,
            'device_name' => $device->name,
            'device_ref' => $device->reference,
            'device_type' => $device->device_type->value,
            'asset_label' => $device->asset?->current_location_label,
            'recorded_at' => $recordedAt?->toIso8601String(),
            'is_stale' => $isStale,
            'lel_pct' => $latest?->lel_pct !== null ? (float) $latest->lel_pct : null,
            'h2s_ppm' => $latest?->h2s_ppm !== null ? (float) $latest->h2s_ppm : null,
            'o2_pct' => $latest?->o2_pct !== null ? (float) $latest->o2_pct : null,
            'co_ppm' => $latest?->co_ppm !== null ? (float) $latest->co_ppm : null,
            'co2_ppm' => $latest?->co2_ppm !== null ? (float) $latest->co2_ppm : null,
            'open_alarms' => $openAlarms,
        ];
    }

    private function cleanKey(int $deviceId, GasType $gasType): string
    {
        return "gas:clean:{$deviceId}:{$gasType->value}";
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? '';
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000' || $driverCode === 1062 || str_contains($e->getMessage(), 'UNIQUE');
    }
}
