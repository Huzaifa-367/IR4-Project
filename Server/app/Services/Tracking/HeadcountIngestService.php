<?php

namespace App\Services\Tracking;

use App\Enums\AlertType;
use App\Enums\DeviceType;
use App\Enums\HeadcountSource;
use App\Events\HeadcountUpdated;
use App\Models\CameraHeadcountReading;
use App\Models\Device;
use App\Models\Zone;
use App\Services\Alert\AlertService;
use App\Services\Settings\SettingsService;
use App\Support\Ingest\IngestEventRejected;
use App\Support\Ingest\IngestTimestamps;
use App\Support\Ingest\ReferenceResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class HeadcountIngestService
{
    public const LIVE_CACHE_KEY = 'tracking.camera_headcount';

    public function __construct(
        private readonly IngestTimestamps $timestamps,
        private readonly ReferenceResolver $refs,
        private readonly AlertService $alerts,
        private readonly SettingsService $settings,
        private readonly CameraZoneBindingService $cameraZones,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array{accepted: int, duplicates: int, rejected: list<array{index: int, code: string}>}
     */
    public function ingestEvents(Device $caller, array $events): array
    {
        if ($caller->device_type !== DeviceType::EdgeCompute) {
            $rejected = [];
            foreach (array_keys($events) as $index) {
                $rejected[] = ['index' => (int) $index, 'code' => 'WRONG_DEVICE_TYPE'];
            }

            return [
                'accepted' => 0,
                'duplicates' => 0,
                'rejected' => $rejected,
            ];
        }

        $accepted = 0;
        $duplicates = 0;
        /** @var list<array{index: int, code: string}> $rejected */
        $rejected = [];
        $sawClockSkew = false;
        $liveAdvanced = false;

        foreach ($events as $index => $event) {
            if (! is_array($event)) {
                $rejected[] = ['index' => (int) $index, 'code' => 'VALIDATION_FAILED'];

                continue;
            }

            try {
                $result = $this->processOneEvent($caller, $event);
                if ($result['outcome'] === 'duplicate') {
                    $duplicates++;

                    continue;
                }

                $accepted++;
                $sawClockSkew = $sawClockSkew || $result['clock_skew'];
                $liveAdvanced = $liveAdvanced || $result['live_advanced'];
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

        if ($liveAdvanced) {
            Cache::forget(self::LIVE_CACHE_KEY);
            Cache::forget('tracking.headcount');
            $this->maybeBroadcastCameraHeadcount();
        }

        return [
            'accepted' => $accepted,
            'duplicates' => $duplicates,
            'rejected' => $rejected,
        ];
    }

    /**
     * Latest non-backfill count per bound camera, rolled up by zone binding snapshot.
     *
     * @return array{
     *     total_on_site: int,
     *     recorded_at: string|null,
     *     by_zone: list<array{zone_id: int, count: int, zone_name: string}>
     * }
     */
    public function liveState(): array
    {
        $ttl = max(1, (int) $this->settings->get('tracking.headcount_cache_seconds', 5));

        return Cache::remember(self::LIVE_CACHE_KEY, $ttl, function (): array {
            $byCamera = $this->latestBoundReadings();
            /** @var array<int, int> $byZoneCounts */
            $byZoneCounts = [];
            $asOf = null;
            $total = 0;

            foreach ($byCamera as $reading) {
                $zoneId = (int) $reading->zone_id;
                $count = (int) $reading->count;
                $byZoneCounts[$zoneId] = ($byZoneCounts[$zoneId] ?? 0) + $count;
                $total += $count;
                if ($asOf === null || $reading->recorded_at->gt($asOf)) {
                    $asOf = $reading->recorded_at;
                }
            }

            $zoneNames = Zone::query()
                ->whereIn('id', array_keys($byZoneCounts))
                ->pluck('name', 'id');

            $byZone = [];
            foreach ($byZoneCounts as $zoneId => $count) {
                $byZone[] = [
                    'zone_id' => $zoneId,
                    'count' => $count,
                    'zone_name' => (string) ($zoneNames[$zoneId] ?? 'Zone'),
                ];
            }

            usort($byZone, fn (array $a, array $b): int => strcmp($a['zone_name'], $b['zone_name']));

            return [
                'total_on_site' => $total,
                'recorded_at' => $asOf?->toIso8601String(),
                'by_zone' => $byZone,
            ];
        });
    }

    /**
     * Recent camera headcount samples for the live Tracking wall.
     *
     * @return list<array{
     *     id: int,
     *     recorded_at: string,
     *     zone_id: int|null,
     *     zone_name: string|null,
     *     camera_id: int|null,
     *     camera_ref: string|null,
     *     camera_name: string|null,
     *     count: int,
     *     is_backfill: bool
     * }>
     */
    public function liveReadings(?int $zoneId = null, int $limit = 25): array
    {
        $limit = min(200, max(1, $limit));

        $query = CameraHeadcountReading::query()
            ->with(['camera:id,name,reference', 'zone:id,name'])
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->limit($limit);

        if ($zoneId !== null && $zoneId > 0) {
            $query->where('zone_id', $zoneId);
        }

        return $query->get()->map(fn (CameraHeadcountReading $reading): array => [
            'id' => $reading->id,
            'recorded_at' => $reading->recorded_at->toIso8601String(),
            'zone_id' => $reading->zone_id,
            'zone_name' => $reading->zone?->name,
            'camera_id' => $reading->camera_id,
            'camera_ref' => $reading->camera?->reference,
            'camera_name' => $reading->camera?->name,
            'count' => (int) $reading->count,
            'is_backfill' => $reading->is_backfill,
        ])->values()->all();
    }

    public function configuredSource(): HeadcountSource
    {
        $raw = (string) $this->settings->get('tracking.headcount_source', HeadcountSource::Camera->value);

        return HeadcountSource::tryFrom($raw) ?? HeadcountSource::Camera;
    }

    /**
     * Snapshot shaped for HeadcountUpdated / TrackingService when source=camera.
     *
     * @return array{
     *     total_on_site: int,
     *     by_zone: list<array{zone_id: int, count: int, zone_name: string}>,
     *     source: string,
     *     as_of: string|null
     * }
     */
    public function cameraSnapshot(): array
    {
        $live = $this->liveState();

        return [
            'total_on_site' => $live['total_on_site'],
            'by_zone' => $live['by_zone'],
            'source' => HeadcountSource::Camera->value,
            'as_of' => $live['recorded_at'],
        ];
    }

    /**
     * Site total over time: sum of latest bound count per camera in each bucket.
     *
     * @return array{
     *     shift_start_count: int,
     *     peak: int,
     *     points: list<array{at: string, label: string, on_site: int, entries: int, exits: int}>,
     *     sparkline: list<int>
     * }
     */
    public function headcountFlow(\DateTimeInterface $from, \DateTimeInterface $to, int $bucketMinutes = 10): array
    {
        $from = Carbon::instance($from);
        $to = Carbon::instance($to);
        $bucketMinutes = max(5, min(60, $bucketMinutes));

        $byCamera = $this->latestBoundCountsBefore($from);
        $onSite = $this->sumCounts($byCamera);
        $shiftStartCount = $onSite;

        $samples = $this->boundSamplesBetween($from, $to);
        $points = [];
        $peak = $onSite;
        $cursor = $from->copy();
        $sampleIndex = 0;
        $sampleCount = $samples->count();

        while ($cursor->lt($to)) {
            $bucketEnd = $cursor->copy()->addMinutes($bucketMinutes);

            while ($sampleIndex < $sampleCount) {
                $sample = $samples[$sampleIndex];
                if ($sample->recorded_at->gte($bucketEnd)) {
                    break;
                }
                if ($sample->recorded_at->gte($cursor)) {
                    $byCamera[(int) $sample->camera_id] = (int) $sample->count;
                }
                $sampleIndex++;
            }

            $onSite = $this->sumCounts($byCamera);
            $peak = max($peak, $onSite);
            $points[] = [
                'at' => $cursor->toIso8601String(),
                'label' => $cursor->format('H:i'),
                'on_site' => $onSite,
                'entries' => 0,
                'exits' => 0,
            ];
            $cursor = $bucketEnd;
        }

        $sparkline = [];
        $step = max(1, (int) floor(count($points) / 12));
        foreach ($points as $i => $point) {
            if ($i % $step === 0) {
                $sparkline[] = $point['on_site'];
            }
        }

        return [
            'shift_start_count' => $shiftStartCount,
            'peak' => $peak,
            'points' => $points,
            'sparkline' => $sparkline !== [] ? $sparkline : [$shiftStartCount],
        ];
    }

    /**
     * Daily peak / time-weighted average from multi-camera bound samples.
     *
     * @return list<array{date: string, peak: int, average: float, entries: null, exits: null, samples: int}>
     */
    public function manpowerPerDay(Carbon $start, Carbon $end): array
    {
        $byCamera = $this->latestBoundCountsBefore($start);
        $opening = $this->sumCounts($byCamera);

        $samples = $this->boundSamplesBetween($start->copy()->subDays(1), $end);
        $perDay = [];

        foreach ($this->eachDate($start, $end) as $date) {
            $dayStart = Carbon::parse($date)->startOfDay();
            $dayEnd = Carbon::parse($date)->endOfDay();
            $daySamples = $samples->filter(
                fn (CameraHeadcountReading $row): bool => $row->recorded_at->betweenIncluded($dayStart, $dayEnd),
            )->sortBy([
                ['recorded_at', 'asc'],
                ['id', 'asc'],
            ]);

            $headcount = $opening;
            $peak = $opening;
            $weighted = 0.0;
            $prevAt = $dayStart;
            $sampleCount = 0;

            foreach ($daySamples as $sample) {
                $seconds = max(0, $prevAt->diffInSeconds($sample->recorded_at));
                $weighted += $headcount * $seconds;
                $byCamera[(int) $sample->camera_id] = (int) $sample->count;
                $headcount = $this->sumCounts($byCamera);
                $peak = max($peak, $headcount);
                $prevAt = $sample->recorded_at;
                $sampleCount++;
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
                'entries' => null,
                'exits' => null,
                'samples' => $sampleCount,
            ];

            $opening = $headcount;
        }

        return $perDay;
    }

    /**
     * @return array{outcome: 'duplicate'|'accepted', clock_skew: bool, live_advanced: bool}
     */
    private function processOneEvent(Device $caller, array $event): array
    {
        $normalized = $this->timestamps->normalize(Carbon::parse((string) $event['recorded_at']));
        $cameraRef = (string) ($event['camera_ref'] ?? '');
        if ($cameraRef === '') {
            throw new IngestEventRejected('VALIDATION_FAILED');
        }

        $camera = $this->refs->resolveCamera($cameraRef);
        if ($camera === null) {
            throw new IngestEventRejected('UNKNOWN_REFERENCE');
        }

        $zone = $this->cameraZones->resolveZoneAt($camera, $normalized['recorded_at']);

        try {
            $reading = CameraHeadcountReading::query()->create([
                'device_id' => $caller->id,
                'camera_id' => $camera->id,
                'zone_id' => $zone?->id,
                'recorded_at' => $normalized['recorded_at'],
                'received_at' => $normalized['received_at'],
                'count' => (int) $event['count'],
                'is_backfill' => $normalized['is_backfill'],
                'clock_skew' => $normalized['clock_skew'],
                'event_uid' => (string) $event['event_uid'],
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return [
                    'outcome' => 'duplicate',
                    'clock_skew' => false,
                    'live_advanced' => false,
                ];
            }

            throw $e;
        }

        $liveAdvanced = false;
        if (! $normalized['is_backfill']) {
            $previous = CameraHeadcountReading::query()
                ->where('camera_id', $camera->id)
                ->where('is_backfill', false)
                ->where('id', '!=', $reading->id)
                ->orderByDesc('recorded_at')
                ->orderByDesc('id')
                ->first();

            $liveAdvanced = $previous === null
                || $reading->recorded_at->gt($previous->recorded_at)
                || (
                    $reading->recorded_at->eq($previous->recorded_at)
                    && $reading->id > $previous->id
                );
        }

        return [
            'outcome' => 'accepted',
            'clock_skew' => $normalized['clock_skew'],
            'live_advanced' => $liveAdvanced,
        ];
    }

    private function maybeBroadcastCameraHeadcount(): void
    {
        if ($this->configuredSource() !== HeadcountSource::Camera) {
            return;
        }

        $seconds = max(1, (int) $this->settings->get('realtime.headcount_throttle_seconds', 5));
        if (! Cache::add('broadcast:headcount', true, $seconds)) {
            return;
        }

        broadcast(new HeadcountUpdated($this->cameraSnapshot()));
    }

    /**
     * @return array<int, CameraHeadcountReading>
     */
    private function latestBoundReadings(): array
    {
        $readings = CameraHeadcountReading::query()
            ->where('is_backfill', false)
            ->whereNotNull('camera_id')
            ->whereNotNull('zone_id')
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->get(['camera_id', 'zone_id', 'count', 'recorded_at']);

        /** @var array<int, CameraHeadcountReading> $latestByCamera */
        $latestByCamera = [];
        foreach ($readings as $reading) {
            $cameraId = (int) $reading->camera_id;
            if (! isset($latestByCamera[$cameraId])) {
                $latestByCamera[$cameraId] = $reading;
            }
        }

        return $latestByCamera;
    }

    /**
     * @return array<int, int> camera_id => count
     */
    private function latestBoundCountsBefore(Carbon $at): array
    {
        $readings = CameraHeadcountReading::query()
            ->where('is_backfill', false)
            ->whereNotNull('camera_id')
            ->whereNotNull('zone_id')
            ->where('recorded_at', '<', $at)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->get(['camera_id', 'count']);

        /** @var array<int, int> $byCamera */
        $byCamera = [];
        foreach ($readings as $reading) {
            $cameraId = (int) $reading->camera_id;
            if (! isset($byCamera[$cameraId])) {
                $byCamera[$cameraId] = (int) $reading->count;
            }
        }

        return $byCamera;
    }

    /**
     * @return Collection<int, CameraHeadcountReading>
     */
    private function boundSamplesBetween(Carbon $from, Carbon $to): Collection
    {
        return CameraHeadcountReading::query()
            ->where('is_backfill', false)
            ->whereNotNull('camera_id')
            ->whereNotNull('zone_id')
            ->whereBetween('recorded_at', [$from, $to])
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get(['id', 'camera_id', 'count', 'recorded_at']);
    }

    /**
     * @param  array<int, int>  $byCamera
     */
    private function sumCounts(array $byCamera): int
    {
        return array_sum($byCamera);
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

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? '';
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000' || $driverCode === 1062 || $driverCode === 19;
    }
}
