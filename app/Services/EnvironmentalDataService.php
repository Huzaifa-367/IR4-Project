<?php

namespace App\Services;

use App\Enums\AlertType;
use App\Enums\DeviceType;
use App\Events\EnvironmentUpdated;
use App\Models\Device;
use App\Models\EnvironmentalReading;
use App\Models\EnvironmentalRollup;
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
        $query = EnvironmentalRollup::query()
            ->whereBetween('bucket_start', [$from, $to])
            ->orderBy('bucket_start');
        if ($deviceId !== null) {
            $query->where('device_id', $deviceId);
        }
        $points = array_values($query->get()
            ->map(fn (EnvironmentalRollup $rollup): array => $this->rollupPoint($rollup, $parameter))
            ->filter(fn (array $point): bool => $point['avg'] !== null)
            ->all());

        return ['points' => $points, 'source' => 'rollup'];
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
            $reading = EnvironmentalReading::query()->create([
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
        $this->rollups->rebuildEnvBucket($reading->device_id, $reading->recorded_at->copy()->startOfHour());
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
        $column = match ($parameter) {
            'temperature_c' => $reading->temperature_c,
            'humidity_pct' => $reading->humidity_pct,
            'wind_speed_ms' => $reading->wind_speed_ms,
            default => $reading->extra[$parameter] ?? null,
        };
        $value = is_numeric($column) ? (float) $column : null;

        return [
            'at' => $reading->recorded_at->toIso8601String(),
            'value' => $value,
            'min' => $value,
            'avg' => $value,
            'max' => $value,
            'device_id' => $reading->device_id,
        ];
    }

    /** @return array<string, mixed> */
    private function rollupPoint(EnvironmentalRollup $rollup, string $parameter): array
    {
        $prefix = match ($parameter) {
            'temperature_c' => 'temp',
            'humidity_pct' => 'humidity',
            'wind_speed_ms' => 'wind',
            default => null,
        };
        $stats = $prefix === null
            ? ($rollup->extra_stats[$parameter] ?? null)
            : [
                'min' => $rollup->{"{$prefix}_min"},
                'avg' => $rollup->{"{$prefix}_avg"},
                'max' => $rollup->{"{$prefix}_max"},
            ];

        return [
            'at' => $rollup->bucket_start->toIso8601String(),
            'value' => isset($stats['avg']) ? (float) $stats['avg'] : null,
            'min' => isset($stats['min']) ? (float) $stats['min'] : null,
            'avg' => isset($stats['avg']) ? (float) $stats['avg'] : null,
            'max' => isset($stats['max']) ? (float) $stats['max'] : null,
            'device_id' => $rollup->device_id,
        ];
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? '';
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return $sqlState === '23000' || $driverCode === 1062 || str_contains($exception->getMessage(), 'UNIQUE');
    }
}
