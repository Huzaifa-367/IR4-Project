<?php

namespace App\Services;

use App\Enums\AccountedSource;
use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\Direction;
use App\Enums\EntryExitSource;
use App\Enums\EvacuationStatus;
use App\Enums\MusterStatus;
use App\Enums\TagStatus;
use App\Enums\ZoneType;
use App\Events\EvacuationEntryUpdated;
use App\Events\HeadcountUpdated;
use App\Events\PositionsUpdated;
use App\Models\Alert;
use App\Models\Camera;
use App\Models\Device;
use App\Models\EntryExitLog;
use App\Models\EvacuationReport;
use App\Models\EvacuationReportEntry;
use App\Models\RfidTag;
use App\Models\TagReading;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerPosition;
use App\Models\Zone;
use App\Support\Ingest\IngestEventRejected;
use App\Support\Ingest\IngestTimestamps;
use App\Support\Ingest\LiveState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class TrackingService
{
    /** @var list<array<string, mixed>> */
    private array $pendingPositionDeltas = [];

    private bool $headcountDirty = false;

    public function __construct(
        private readonly ReaderBindingService $bindings,
        private readonly IngestTimestamps $timestamps,
        private readonly AlertService $alerts,
        private readonly SettingsService $settings,
        private readonly WorkerService $workers,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array{accepted: int, duplicates: int, rejected: list<array{index: int, code: string}>}
     */
    public function ingestReadings(Device $authenticatingDevice, array $events): array
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
                $result = $this->processOneEvent($authenticatingDevice, $event);
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
                title: "Clock skew on device {$authenticatingDevice->name}",
                payload: ['device_id' => $authenticatingDevice->id, 'day' => $day],
                source: $authenticatingDevice,
                dedupeKey: "clock_skew:{$authenticatingDevice->id}:{$day}",
            );
        }

        $this->flushBroadcasts();

        return [
            'accepted' => $accepted,
            'duplicates' => $duplicates,
            'rejected' => $rejected,
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     * @return 'accepted'|'duplicate'|'skew'
     */
    private function processOneEvent(Device $caller, array $event): string
    {
        $readerRef = (string) ($event['reader_ref'] ?? '');
        $reader = Device::query()->where('reference', $readerRef)->first();
        if ($reader === null) {
            throw new IngestEventRejected('UNKNOWN_REFERENCE');
        }
        if ($reader->id !== $caller->id) {
            throw new IngestEventRejected('FORBIDDEN_REFERENCE');
        }

        $tagUid = strtoupper((string) ($event['tag_uid'] ?? ''));
        $eventUid = (string) ($event['event_uid'] ?? '');
        $recordedRaw = Carbon::parse((string) $event['recorded_at']);
        $normalized = $this->timestamps->normalize($recordedRaw);
        $recordedAt = $normalized['recorded_at'];

        if (TagReading::query()
            ->where('reader_device_id', $reader->id)
            ->where('event_uid', $eventUid)
            ->exists()) {
            return 'duplicate';
        }

        $tag = RfidTag::query()->where('tag_uid', $tagUid)->first();
        if ($tag === null) {
            throw new IngestEventRejected('UNKNOWN_TAG');
        }

        $zone = $this->bindings->resolveZoneAt($reader, $recordedAt);

        TagReading::query()->create([
            'tag_id' => $tag->id,
            'reader_device_id' => $reader->id,
            'zone_id' => $zone?->id,
            'recorded_at' => $recordedAt,
            'received_at' => $normalized['received_at'],
            'rssi' => isset($event['rssi']) ? (int) $event['rssi'] : null,
            'is_backfill' => $normalized['is_backfill'],
            'clock_skew' => $normalized['clock_skew'],
            'event_uid' => $eventUid,
        ]);

        if (in_array($tag->status, [TagStatus::Lost, TagStatus::Retired], true)) {
            $this->alerts->raise(
                type: AlertType::System,
                severity: AlertSeverity::Warning,
                title: "Retired/lost tag {$tag->tag_uid} seen",
                payload: [
                    'tag_uid' => $tag->tag_uid,
                    'tag_id' => $tag->id,
                    'zone_id' => $zone?->id,
                    'zone_name' => $zone?->name,
                ],
                source: $tag,
                dedupeKey: "lost_tag_seen:{$tag->id}:".Carbon::now()->toDateString(),
            );

            return $normalized['clock_skew'] ? 'skew' : 'accepted';
        }

        if ($normalized['is_backfill'] || $tag->status !== TagStatus::Assigned || $tag->worker_id === null) {
            return $normalized['clock_skew'] ? 'skew' : 'accepted';
        }

        if ($zone === null) {
            return $normalized['clock_skew'] ? 'skew' : 'accepted';
        }

        $this->advanceLiveState($tag, $reader, $zone, $recordedAt);

        return $normalized['clock_skew'] ? 'skew' : 'accepted';
    }

    private function advanceLiveState(RfidTag $tag, Device $reader, Zone $zone, Carbon $recordedAt): void
    {
        /** @var WorkerPosition|null $position */
        $position = WorkerPosition::query()->where('tag_id', $tag->id)->first();
        if ($position === null) {
            $position = WorkerPosition::query()->create([
                'tag_id' => $tag->id,
                'worker_id' => $tag->worker_id,
                'zone_id' => null,
                'last_seen_at' => $recordedAt->copy()->subSecond(),
                'is_on_site' => false,
            ]);
        }

        if (! LiveState::shouldAdvance($position->last_seen_at, $recordedAt)) {
            return;
        }

        $previousZoneId = $position->zone_id;
        $wasOnSite = $position->is_on_site;

        if ($zone->zone_type === ZoneType::Gate) {
            $this->applyGateLogic($position, $tag, $zone, $recordedAt);
            $position->refresh();
        } else {
            $position->forceFill([
                'zone_id' => $zone->id,
                'last_seen_at' => $recordedAt,
            ])->save();
        }

        $worker = Worker::query()->find($tag->worker_id);
        if ($worker !== null) {
            $this->workers->syncPresenceMirror(
                $worker,
                $position->is_on_site,
                $recordedAt,
            );
        }

        if ($previousZoneId !== $position->zone_id && $position->zone_id !== null && $position->is_on_site) {
            $newZone = Zone::query()->find($position->zone_id);
            if ($newZone !== null && $worker !== null) {
                $this->evaluateZoneRules($worker, $newZone, $previousZoneId);
            }
        }

        if ($wasOnSite !== $position->is_on_site || $previousZoneId !== $position->zone_id) {
            $this->headcountDirty = true;
        }

        $this->pendingPositionDeltas[] = [
            'tag_id' => $tag->id,
            'worker_id' => $tag->worker_id,
            'zone_id' => $position->zone_id,
            'last_seen_at' => $position->last_seen_at->toIso8601String(),
            'is_on_site' => $position->is_on_site,
        ];

        $this->autoAccountEvacuation($tag, $zone, $position, $recordedAt);
    }

    private function applyGateLogic(
        WorkerPosition $position,
        RfidTag $tag,
        Zone $gate,
        Carbon $recordedAt,
    ): void {
        $debounce = (int) $this->settings->get('tracking.gate_debounce_seconds', 60);
        $lastGate = EntryExitLog::query()
            ->where('worker_id', $tag->worker_id)
            ->where('source', EntryExitSource::GateReader)
            ->orderByDesc('occurred_at')
            ->first();

        if (
            $lastGate !== null
            && $lastGate->occurred_at->greaterThan($recordedAt->copy()->subSeconds($debounce))
        ) {
            $position->forceFill([
                'zone_id' => $gate->id,
                'last_seen_at' => $recordedAt,
            ])->save();

            return;
        }

        $goingIn = ! $position->is_on_site;
        EntryExitLog::query()->create([
            'worker_id' => $tag->worker_id,
            'tag_id' => $tag->id,
            'gate_zone_id' => $gate->id,
            'direction' => $goingIn ? Direction::In : Direction::Out,
            'occurred_at' => $recordedAt,
            'source' => EntryExitSource::GateReader,
        ]);

        $position->forceFill([
            'zone_id' => $gate->id,
            'last_seen_at' => $recordedAt,
            'is_on_site' => $goingIn,
        ])->save();
    }

    private function evaluateZoneRules(Worker $worker, Zone $zone, ?int $previousZoneId): void
    {
        if ($zone->zone_type === ZoneType::RestrictedRed) {
            $this->alerts->raise(
                type: AlertType::RedZoneIntrusion,
                title: "Red zone intrusion: {$zone->name}",
                payload: [
                    'worker_id' => $worker->id,
                    'worker_name' => $worker->name,
                    'zone_id' => $zone->id,
                    'zone_name' => $zone->name,
                    'suggested_action' => 'log_lsr',
                ],
                source: $worker,
                dedupeKey: "redzone:{$worker->id}:{$zone->id}",
            );
        }

        if ($zone->requires_authorization) {
            $listed = $zone->authorizedWorkers()->where('workers.id', $worker->id)->exists();
            if (! $listed) {
                $this->alerts->raise(
                    type: AlertType::UnauthorizedZoneAccess,
                    title: "Unauthorized zone access: {$zone->name}",
                    payload: [
                        'worker_id' => $worker->id,
                        'worker_name' => $worker->name,
                        'zone_id' => $zone->id,
                        'zone_name' => $zone->name,
                        'suggested_action' => 'log_lsr',
                    ],
                    source: $worker,
                    dedupeKey: "unauthorized:{$worker->id}:{$zone->id}",
                );
            }
        }

        if ($zone->occupancy_limit !== null) {
            $count = WorkerPosition::query()
                ->where('zone_id', $zone->id)
                ->where('is_on_site', true)
                ->count();

            if ($count > $zone->occupancy_limit) {
                $this->alerts->raise(
                    type: AlertType::ZoneOccupancyExceeded,
                    title: "Occupancy exceeded: {$zone->name}",
                    payload: [
                        'zone_id' => $zone->id,
                        'zone_name' => $zone->name,
                        'count' => $count,
                        'limit' => $zone->occupancy_limit,
                        'suggested_action' => 'log_lsr',
                    ],
                    source: $zone,
                    dedupeKey: "occupancy:{$zone->id}",
                );
            } else {
                $this->alerts->resolveByDedupeKey("occupancy:{$zone->id}");
            }
        }

        // Resolve occupancy for previous zone if under limit now
        if ($previousZoneId !== null) {
            $prev = Zone::query()->find($previousZoneId);
            if ($prev?->occupancy_limit !== null) {
                $prevCount = WorkerPosition::query()
                    ->where('zone_id', $prev->id)
                    ->where('is_on_site', true)
                    ->count();
                if ($prevCount <= $prev->occupancy_limit) {
                    $this->alerts->resolveByDedupeKey("occupancy:{$prev->id}");
                }
            }
        }
    }

    private function autoAccountEvacuation(
        RfidTag $tag,
        Zone $zone,
        WorkerPosition $position,
        Carbon $recordedAt,
    ): void {
        $open = EvacuationReport::query()
            ->where('status', EvacuationStatus::Open)
            ->latest('id')
            ->first();

        if ($open === null || $tag->worker_id === null) {
            return;
        }

        /** @var EvacuationReportEntry|null $entry */
        $entry = EvacuationReportEntry::query()
            ->where('evacuation_report_id', $open->id)
            ->where('worker_id', $tag->worker_id)
            ->where('muster_status', MusterStatus::Unaccounted)
            ->first();

        if ($entry === null) {
            return;
        }

        $source = null;
        if ($zone->zone_type === ZoneType::MusterPoint) {
            $source = AccountedSource::MusterReader;
        } elseif ($zone->zone_type === ZoneType::Gate && ! $position->is_on_site) {
            $source = AccountedSource::GateExit;
        }

        if ($source === null) {
            return;
        }

        $entry->forceFill([
            'muster_status' => MusterStatus::Accounted,
            'accounted_at' => $recordedAt,
            'accounted_source' => $source,
        ])->save();

        broadcast(new EvacuationEntryUpdated($entry->fresh() ?? $entry));
    }

    public function checkStationaryTags(?\DateTimeInterface $now = null): void
    {
        $now = Carbon::instance($now ?? now());
        $minutes = (int) $this->settings->get('tracking.stationary_tag_minutes', 15);
        $threshold = $now->copy()->subMinutes($minutes);

        WorkerPosition::query()
            ->where('is_on_site', true)
            ->where('last_seen_at', '<=', $threshold)
            ->with(['tag', 'zone', 'worker'])
            ->each(function (WorkerPosition $position) use ($now): void {
                $zone = $position->zone;
                if ($zone === null) {
                    return;
                }

                if (in_array($zone->zone_type, [ZoneType::Gate, ZoneType::MusterPoint], true)) {
                    return;
                }

                $camera = $this->nearestCameraForTag($position->tag_id);
                $this->alerts->raise(
                    type: AlertType::StationaryTag,
                    title: "Stationary tag in {$zone->name}",
                    payload: [
                        'worker_id' => $position->worker_id,
                        'worker_name' => $position->worker?->name,
                        'zone_id' => $zone->id,
                        'zone_name' => $zone->name,
                        'tag_id' => $position->tag_id,
                        'camera_id' => $camera?->id,
                        'camera_name' => $camera?->name,
                        'suggested_action' => 'create_incident',
                    ],
                    source: $position->tag,
                    dedupeKey: "stationary:{$position->tag_id}",
                );

                $this->correlateWorkerDown($zone->id, $now);
            });
    }

    public function correlateWorkerDown(int $zoneId, ?\DateTimeInterface $now = null): void
    {
        $now = Carbon::instance($now ?? now());
        $window = (int) $this->settings->get('tracking.worker_down_window_minutes', 10);
        $since = $now->copy()->subMinutes($window);

        $hasStationary = Alert::query()
            ->where('alert_type', AlertType::StationaryTag)
            ->whereIn('status', [AlertStatus::Open, AlertStatus::Acknowledged])
            ->where('payload->zone_id', $zoneId)
            ->where('raised_at', '>=', $since)
            ->exists();

        $hasFall = Alert::query()
            ->where('alert_type', AlertType::FallDetection)
            ->where('raised_at', '>=', $since)
            ->where(function ($q) use ($zoneId): void {
                $q->where('payload->zone_id', $zoneId)
                    ->orWhere('payload->zone_id', (string) $zoneId);
            })
            ->exists();

        if (! $hasStationary || ! $hasFall) {
            return;
        }

        $this->alerts->raise(
            type: AlertType::WorkerDown,
            title: 'Worker down correlation',
            payload: [
                'zone_id' => $zoneId,
                'suggested_action' => 'create_incident',
                'signals' => ['stationary_tag', 'fall_detection'],
            ],
            dedupeKey: "worker_down:{$zoneId}",
        );
    }

    public function sweepOffsiteTags(?\DateTimeInterface $now = null): void
    {
        $now = Carbon::instance($now ?? now());
        $hours = (int) $this->settings->get('tracking.tag_offsite_after_hours', 14);
        $threshold = $now->copy()->subHours($hours);
        $affected = [];

        WorkerPosition::query()
            ->where('is_on_site', true)
            ->where('last_seen_at', '<=', $threshold)
            ->with('tag')
            ->each(function (WorkerPosition $position) use ($now, &$affected): void {
                $position->forceFill(['is_on_site' => false])->save();
                EntryExitLog::query()->create([
                    'worker_id' => $position->worker_id,
                    'tag_id' => $position->tag_id,
                    'gate_zone_id' => null,
                    'direction' => Direction::Out,
                    'occurred_at' => $now,
                    'source' => EntryExitSource::AutoSweep,
                    'correction_note' => 'auto: tag unseen',
                ]);

                $worker = Worker::query()->find($position->worker_id);
                if ($worker !== null) {
                    $this->workers->syncPresenceMirror($worker, false, $now);
                    $affected[] = $worker->id;
                }
            });

        if ($affected !== []) {
            $this->alerts->raise(
                type: AlertType::System,
                severity: AlertSeverity::Info,
                title: 'Absence sweep marked workers off-site',
                payload: ['worker_ids' => $affected],
                dedupeKey: 'absence_sweep:'.Carbon::now()->toDateString(),
            );
            $this->headcountDirty = true;
            $this->flushBroadcasts();
        }
    }

    /**
     * @return array{total_on_site: int, by_zone: list<array{zone_id: int, count: int, zone_name: string}>}
     */
    public function headcountSnapshot(): array
    {
        $ttl = max(1, (int) $this->settings->get('tracking.headcount_cache_seconds', 5));

        return Cache::remember('tracking.headcount', $ttl, function (): array {
            $total = WorkerPosition::query()->where('is_on_site', true)->count();
            $byZone = WorkerPosition::query()
                ->selectRaw('zone_id, count(*) as count')
                ->where('is_on_site', true)
                ->whereNotNull('zone_id')
                ->groupBy('zone_id')
                ->get()
                ->map(function ($row): array {
                    $zone = Zone::query()->find($row->zone_id);

                    return [
                        'zone_id' => (int) $row->zone_id,
                        'count' => (int) $row->count,
                        'zone_name' => $zone?->name ?? 'Zone',
                    ];
                })
                ->values()
                ->all();

            return [
                'total_on_site' => $total,
                'by_zone' => $byZone,
            ];
        });
    }

    /**
     * Frozen RFID roster for a zone at a point in time (DOC-09 history → DOC-14 evidence).
     * Uses each tag's latest reading at or before $at; retains only tags whose zone equals $zoneId.
     *
     * @return list<array{worker_id: int, tag_id: int, last_seen_at: string|null}>
     */
    public function zoneRosterAt(int $zoneId, \DateTimeInterface|string $at): array
    {
        $at = Carbon::parse($at);

        $latestPerTag = DB::table('tag_readings')
            ->select('tag_id', DB::raw('MAX(recorded_at) as max_recorded_at'))
            ->where('recorded_at', '<=', $at)
            ->groupBy('tag_id');

        $rows = DB::table('tag_readings as tr')
            ->joinSub($latestPerTag, 'latest', function ($join): void {
                $join->on('tr.tag_id', '=', 'latest.tag_id')
                    ->on('tr.recorded_at', '=', 'latest.max_recorded_at');
            })
            ->where('tr.zone_id', $zoneId)
            ->select('tr.tag_id', 'tr.recorded_at')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $tags = RfidTag::query()
            ->whereIn('id', $rows->pluck('tag_id'))
            ->whereNotNull('worker_id')
            ->get()
            ->keyBy('id');

        $roster = [];
        $seenWorkers = [];

        foreach ($rows as $row) {
            $tag = $tags->get((int) $row->tag_id);
            if ($tag === null) {
                continue;
            }

            $workerId = (int) $tag->worker_id;
            if (isset($seenWorkers[$workerId])) {
                continue;
            }

            $seenWorkers[$workerId] = true;
            $roster[] = [
                'worker_id' => $workerId,
                'tag_id' => (int) $row->tag_id,
                'last_seen_at' => Carbon::parse($row->recorded_at)->toIso8601String(),
            ];
        }

        return $roster;
    }

    public function correctEntryExit(
        Worker $worker,
        Direction $direction,
        \DateTimeInterface|string $occurredAt,
        string $note,
        User $by,
    ): EntryExitLog {
        $occurred = Carbon::parse($occurredAt);

        $log = EntryExitLog::query()->create([
            'worker_id' => $worker->id,
            'tag_id' => RfidTag::query()
                ->where('worker_id', $worker->id)
                ->where('status', TagStatus::Assigned)
                ->value('id'),
            'direction' => $direction,
            'occurred_at' => $occurred,
            'source' => EntryExitSource::ManualCorrection,
            'corrected_by' => $by->id,
            'correction_note' => $note,
        ]);

        $onSite = $direction === Direction::In;
        WorkerPosition::query()
            ->where('worker_id', $worker->id)
            ->update(['is_on_site' => $onSite]);

        $this->workers->syncPresenceMirror($worker, $onSite, $occurred);
        Cache::forget('tracking.headcount');
        $this->headcountDirty = true;
        $this->flushBroadcasts();

        return $log;
    }

    /**
     * Reconstruct on-site headcount and gate flow across a window (DOC-09 gate logic).
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

        $baselineLogs = EntryExitLog::query()
            ->where('occurred_at', '<', $from)
            ->orderByDesc('occurred_at')
            ->get(['worker_id', 'direction', 'occurred_at'])
            ->unique('worker_id');

        $onSite = $baselineLogs
            ->filter(fn (EntryExitLog $log): bool => $log->direction === Direction::In)
            ->count();
        $shiftStartCount = $onSite;

        $logs = EntryExitLog::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->orderBy('occurred_at')
            ->get(['direction', 'occurred_at']);

        $points = [];
        $peak = $onSite;
        $cursor = $from->copy();
        $logIndex = 0;
        $logCount = $logs->count();

        while ($cursor->lt($to)) {
            $bucketEnd = $cursor->copy()->addMinutes($bucketMinutes);
            $entries = 0;
            $exits = 0;

            while ($logIndex < $logCount) {
                $log = $logs[$logIndex];
                if ($log->occurred_at->gte($bucketEnd)) {
                    break;
                }
                if ($log->occurred_at->gte($cursor)) {
                    if ($log->direction === Direction::In) {
                        $entries++;
                    } else {
                        $exits++;
                    }
                }
                $logIndex++;
            }

            $onSite = max(0, $onSite + $entries - $exits);
            $peak = max($peak, $onSite);
            $points[] = [
                'at' => $cursor->toIso8601String(),
                'label' => $cursor->format('H:i'),
                'on_site' => $onSite,
                'entries' => $entries,
                'exits' => $exits,
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

    private function nearestCameraForTag(int $tagId): ?Camera
    {
        $last = TagReading::query()
            ->where('tag_id', $tagId)
            ->orderByDesc('recorded_at')
            ->first();

        if ($last === null) {
            return null;
        }

        $reader = Device::query()->find($last->reader_device_id);
        if ($reader?->asset_id === null) {
            return null;
        }

        return Camera::query()->where('asset_id', $reader->asset_id)->first();
    }

    private function flushBroadcasts(): void
    {
        $headcountSeconds = (int) $this->settings->get('realtime.headcount_throttle_seconds', 5);
        $positionsSeconds = (int) $this->settings->get('realtime.positions_throttle_seconds', 5);

        if ($this->headcountDirty) {
            Cache::forget('tracking.headcount');
            $key = 'broadcast:headcount';
            if (Cache::add($key, true, $headcountSeconds)) {
                broadcast(new HeadcountUpdated($this->headcountSnapshot()));
            }
            $this->headcountDirty = false;
        }

        if ($this->pendingPositionDeltas !== []) {
            $key = 'broadcast:positions';
            $deltas = $this->pendingPositionDeltas;
            $this->pendingPositionDeltas = [];
            if (Cache::add($key, true, $positionsSeconds)) {
                broadcast(new PositionsUpdated($deltas));
            }
        }
    }
}
