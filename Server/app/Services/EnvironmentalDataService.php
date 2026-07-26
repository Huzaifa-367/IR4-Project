<?php

namespace App\Services;

use App\Enums\AlertType;
use App\Enums\DeviceType;
use App\Events\EnvironmentUpdated;
use App\Models\Device;
use App\Models\EnvironmentalReading;
use App\Support\Ingest\IngestEventRejected;
use App\Support\Ingest\IngestTimestamps;
use App\Support\Ingest\ReferenceResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

final class EnvironmentalDataService
{
    /** @var array<int, true> */
    private array $pendingBroadcasts = [];

    public function __construct(
        private readonly IngestTimestamps $timestamps,
        private readonly ReferenceResolver $references,
        private readonly AlertService $alerts,
        private readonly SettingsService $settings,
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
            try {
                $result = $this->processEvent($caller, $event);
                if ($result === 'duplicate') {
                    $duplicates++;

                    continue;
                }
                $accepted++;
                $sawClockSkew = $sawClockSkew || $result === 'skew';
            } catch (IngestEventRejected $exception) {
                $rejected[] = ['index' => (int) $index, 'code' => $exception->rejectionCode];
            }
        }
        if ($sawClockSkew) {
            $day = now()->toDateString();
            $this->alerts->raise(
                type: AlertType::ClockSkew,
                title: "Clock skew on device {$caller->name}",
                payload: ['device_id' => $caller->id, 'day' => $day],
                source: $caller,
                dedupeKey: "clock_skew:{$caller->id}:{$day}",
            );
        }
        $this->flushBroadcasts();

        return compact('accepted', 'duplicates', 'rejected');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function latest(): array
    {
        $staleMinutes = (int) $this->settings->get('health.sensor_stale_minutes', 5);

        return array_values(Device::query()
            ->where('device_type', DeviceType::EnvironmentalSensor)
            ->with('asset')
            ->orderBy('name')
            ->get()
            ->map(function (Device $device) use ($staleMinutes): array {
                $reading = EnvironmentalReading::query()
                    ->where('device_id', $device->id)
                    ->latest('recorded_at')
                    ->first();

                return $this->sensorPayload($device, $reading, $staleMinutes);
            })
            ->all());
    }

    /**
     * @return array{points: list<array<string, mixed>>, source: string}
     */
    public function trends(
        string $parameter,
        ?int $deviceId,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): array {
        $from = Carbon::instance($from);
        $to = Carbon::instance($to);
        if ($from->diffInHours($to) <= 24) {
            $query = EnvironmentalReading::query()
                ->whereBetween('recorded_at', [$from, $to])
                ->orderBy('recorded_at');
            if ($deviceId !== null) {
                $query->where('device_id', $deviceId);
            }
            $points = array_values($query->get()
                ->map(fn (EnvironmentalReading $reading): array => $this->rawPoint($reading, $parameter))
                ->filter(fn (array $point): bool => $point['avg'] !== null)
                ->all());

            return ['points' => $points, 'source' => 'raw'];
        }

        return $this->trendsFromRawHourly($parameter, $deviceId, $from, $to);
    }

    /**
     * Build hourly trend points from raw readings for windows longer than 24 hours.
     *
     * @return array{points: list<array<string, mixed>>, source: string}
     */
    private function trendsFromRawHourly(
        string $parameter,
        ?int $deviceId,
        Carbon $from,
        Carbon $to,
    ): array {
        $query = EnvironmentalReading::query()
            ->whereBetween('recorded_at', [$from, $to]);
        if ($deviceId !== null) {
            $query->where('device_id', $deviceId);
        }
        $readings = $query->get();
        if ($readings->isEmpty()) {
            return ['points' => [], 'source' => 'raw-hourly'];
        }

        $points = $readings
            ->groupBy(fn (EnvironmentalReading $reading): string => $reading->device_id.'|'.$reading->recorded_at->copy()->startOfHour()->toIso8601String())
            ->map(function ($group) use ($parameter): ?array {
                /** @var EnvironmentalReading $first */
                $first = $group->first();
                $values = $group
                    ->map(fn (EnvironmentalReading $reading): ?float => $this->metricValue($reading, $parameter))
                    ->filter(fn (?float $value): bool => $value !== null);
                if ($values->isEmpty()) {
                    return null;
                }

                return [
                    'at' => $first->recorded_at->copy()->startOfHour()->toIso8601String(),
                    'value' => (float) $values->avg(),
                    'min' => (float) $values->min(),
                    'avg' => (float) $values->avg(),
                    'max' => (float) $values->max(),
                    'device_id' => $first->device_id,
                ];
            })
            ->filter()
            ->sortBy('at')
            ->values()
            ->all();

        return ['points' => array_values($points), 'source' => 'raw-hourly'];
    }

    private function metricValue(EnvironmentalReading $reading, string $parameter): ?float
    {
        $column = match ($parameter) {
            'temperature_c' => $reading->temperature_c,
            'humidity_pct' => $reading->humidity_pct,
            'wind_speed_ms' => $reading->wind_speed_ms,
            default => $reading->extra[$parameter] ?? null,
        };

        return is_numeric($column) ? (float) $column : null;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return 'accepted'|'duplicate'|'skew'
     */
    private function processEvent(Device $caller, array $event): string
    {
        $device = $this->resolveDevice($caller, $event);
        $eventUid = (string) ($event['event_uid'] ?? '');
        if (EnvironmentalReading::query()
            ->where('device_id', $device->id)
            ->where('event_uid', $eventUid)
            ->exists()) {
            return 'duplicate';
        }
        $metrics = $this->metrics($event);
        if ($metrics['temperature_c'] === null
            && $metrics['humidity_pct'] === null
            && $metrics['wind_speed_ms'] === null
            && $metrics['extra'] === null) {
            throw new IngestEventRejected('VALIDATION_FAILED');
        }
        $normalized = $this->timestamps->normalize(Carbon::parse((string) $event['recorded_at']));
        try {
            EnvironmentalReading::query()->create([
                'device_id' => $device->id,
                'asset_id' => $device->asset_id,
                'recorded_at' => $normalized['recorded_at'],
                'received_at' => $normalized['received_at'],
                ...$metrics,
                'is_backfill' => $normalized['is_backfill'],
                'clock_skew' => $normalized['clock_skew'],
                'event_uid' => $eventUid,
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                return 'duplicate';
            }
            throw $exception;
        }
        if (! $normalized['is_backfill']) {
            $this->pendingBroadcasts[$device->id] = true;
        }

        return $normalized['clock_skew'] ? 'skew' : 'accepted';
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function resolveDevice(Device $caller, array $event): Device
    {
        $reference = $event['device_ref'] ?? null;
        if (! is_string($reference) || $reference === '') {
            return $caller;
        }
        $device = $this->references->resolveDevice($reference);
        if ($device === null) {
            throw new IngestEventRejected('UNKNOWN_REFERENCE');
        }
        if ($device->id !== $caller->id) {
            throw new IngestEventRejected('FORBIDDEN_REFERENCE');
        }

        return $device;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array{temperature_c: ?float, humidity_pct: ?float, wind_speed_ms: ?float, extra: ?array<string, float>}
     */
    private function metrics(array $event): array
    {
        $extra = [];
        foreach (($event['extra'] ?? []) as $key => $value) {
            if (is_string($key) && is_numeric($value)) {
                $extra[$key] = (float) $value;
            }
        }

        return [
            'temperature_c' => isset($event['temperature_c']) ? (float) $event['temperature_c'] : null,
            'humidity_pct' => isset($event['humidity_pct']) ? (float) $event['humidity_pct'] : null,
            'wind_speed_ms' => isset($event['wind_speed_ms']) ? (float) $event['wind_speed_ms'] : null,
            'extra' => $extra !== [] ? $extra : null,
        ];
    }

    private function flushBroadcasts(): void
    {
        $seconds = (int) $this->settings->get('realtime.gas_throttle_seconds', 5);
        $staleMinutes = (int) $this->settings->get('health.sensor_stale_minutes', 5);
        foreach (array_keys($this->pendingBroadcasts) as $deviceId) {
            $cacheKey = "environment:broadcast:{$deviceId}";
            if (Cache::has($cacheKey)) {
                continue;
            }
            Cache::put($cacheKey, true, now()->addSeconds($seconds));
            $device = Device::query()->with('asset')->find($deviceId);
            $reading = EnvironmentalReading::query()->where('device_id', $deviceId)->latest('recorded_at')->first();
            if ($device !== null) {
                broadcast(new EnvironmentUpdated($this->sensorPayload($device, $reading, $staleMinutes)));
            }
        }
        $this->pendingBroadcasts = [];
    }

    /**
     * @return array<string, mixed>
     */
    private function sensorPayload(Device $device, ?EnvironmentalReading $reading, int $staleMinutes): array
    {
        return [
            'device_id' => $device->id,
            'device_name' => $device->name,
            'device_ref' => $device->reference,
            'asset_label' => $device->asset?->current_location_label,
            'recorded_at' => $reading?->recorded_at->toIso8601String(),
            'is_stale' => $reading === null || $reading->recorded_at->lessThan(now()->subMinutes($staleMinutes)),
            'temperature_c' => $reading?->temperature_c !== null ? (float) $reading->temperature_c : null,
            'humidity_pct' => $reading?->humidity_pct !== null ? (float) $reading->humidity_pct : null,
            'wind_speed_ms' => $reading?->wind_speed_ms !== null ? (float) $reading->wind_speed_ms : null,
            'extra' => $reading !== null ? ($reading->extra ?? []) : [],
        ];
    }

    /** @return array<string, mixed> */
    private function rawPoint(EnvironmentalReading $reading, string $parameter): array
    {
        $value = $this->metricValue($reading, $parameter);

        return [
            'at' => $reading->recorded_at->toIso8601String(),
            'value' => $value,
            'min' => $value,
            'avg' => $value,
            'max' => $value,
            'device_id' => $reading->device_id,
        ];
    }

    /**
     * Control-room snapshot: all sensors, all core (+ extra) metrics for the window.
     *
     * @return array<string, mixed>
     */
    public function dashboardSnapshot(
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): array {
        $sensors = $this->latest();
        $coreMetrics = [
            ['key' => 'temperature_c', 'label' => 'Temperature', 'unit' => '°C'],
            ['key' => 'humidity_pct', 'label' => 'Humidity', 'unit' => '%'],
            ['key' => 'wind_speed_ms', 'label' => 'Wind speed', 'unit' => 'm/s'],
        ];
        $extraKeys = collect($sensors)
            ->flatMap(fn (array $sensor): array => array_keys($sensor['extra']))
            ->unique()
            ->values();
        $metricDefs = collect($coreMetrics)->concat(
            $extraKeys->map(fn (int|string $key): array => [
                'key' => (string) $key,
                'label' => str((string) $key)->replace(['_', '-'], ' ')->title()->toString(),
                'unit' => '',
            ]),
        );
        $trendSeries = $metricDefs->map(function (array $metric) use ($from, $to): array {
            $trend = $this->trends($metric['key'], null, $from, $to);
            $points = $this->aggregateTrendPoints($trend['points']);

            return [
                'key' => $metric['key'],
                'label' => $metric['label'],
                'unit' => $metric['unit'],
                'source' => $trend['source'],
                'points' => $points,
            ];
        })->all();
        $metrics = collect($coreMetrics)->map(function (array $metric) use ($trendSeries, $sensors): array {
            $series = collect($trendSeries)->firstWhere('key', $metric['key']);
            $values = collect($series['points'] ?? [])
                ->pluck('avg')
                ->filter(fn (mixed $value): bool => is_numeric($value))
                ->map(fn (mixed $value): float => (float) $value);
            $current = collect($sensors)
                ->pluck($metric['key'])
                ->filter(fn (mixed $value): bool => is_numeric($value))
                ->map(fn (mixed $value): float => (float) $value);

            return [
                ...$metric,
                'current' => $current->isNotEmpty() ? round($current->avg(), 2) : null,
                'min' => $values->isNotEmpty() ? round($values->min(), 2) : null,
                'avg' => $values->isNotEmpty() ? round($values->avg(), 2) : null,
                'max' => $values->isNotEmpty() ? round($values->max(), 2) : null,
                'sparkline' => $values->take(-20)->values()->all(),
            ];
        })->all();
        $extraMetrics = collect($sensors)
            ->flatMap(fn (array $sensor): array => collect($sensor['extra'])
                ->map(fn (float|int $value, string $key): array => [
                    'key' => $key,
                    'value' => (float) $value,
                    'device_id' => $sensor['device_id'],
                ])
                ->values()
                ->all())
            ->groupBy('key')
            ->map(function ($rows, string $key): array {
                $values = $rows->pluck('value');

                return [
                    'key' => $key,
                    'label' => str($key)->replace(['_', '-'], ' ')->title()->toString(),
                    'current' => round($values->avg(), 2),
                    'sensor_count' => $rows->pluck('device_id')->unique()->count(),
                ];
            })
            ->values()
            ->all();

        return [
            'as_of' => now()->toIso8601String(),
            'sensors' => $sensors,
            'sensor_health' => [
                'total' => count($sensors),
                'current' => collect($sensors)->where('is_stale', false)->count(),
                'stale' => collect($sensors)->where('is_stale', true)->count(),
            ],
            'metrics' => $metrics,
            'extra_metrics' => $extraMetrics,
            'trend' => [
                'series' => $trendSeries,
                'source' => collect($trendSeries)->contains(
                    fn (array $series): bool => $series['source'] === 'raw-hourly',
                ) ? 'raw-hourly' : 'raw',
            ],
        ];
    }

    /**
     * Collapse multi-device samples at the same timestamp into site-wide stats.
     *
     * @param  list<array<string, mixed>>  $points
     * @return list<array<string, mixed>>
     */
    private function aggregateTrendPoints(array $points): array
    {
        return collect($points)
            ->groupBy('at')
            ->map(function ($group, string $at): array {
                $avgs = $group->pluck('avg')->filter(fn (mixed $value): bool => is_numeric($value));
                $mins = $group->pluck('min')->filter(fn (mixed $value): bool => is_numeric($value));
                $maxes = $group->pluck('max')->filter(fn (mixed $value): bool => is_numeric($value));
                $avg = $avgs->isNotEmpty() ? round((float) $avgs->avg(), 2) : null;

                return [
                    'at' => $at,
                    'value' => $avg,
                    'min' => $mins->isNotEmpty() ? round((float) $mins->min(), 2) : null,
                    'avg' => $avg,
                    'max' => $maxes->isNotEmpty() ? round((float) $maxes->max(), 2) : null,
                    'device_id' => null,
                ];
            })
            ->sortKeys()
            ->values()
            ->all();
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? '';
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return $sqlState === '23000' || $driverCode === 1062 || str_contains($exception->getMessage(), 'UNIQUE');
    }
}
