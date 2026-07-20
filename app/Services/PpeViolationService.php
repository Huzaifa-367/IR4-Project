<?php

namespace App\Services;

use App\Enums\AlertType;
use App\Enums\IngestStream;
use App\Enums\ReviewStatus;
use App\Enums\ViolationType;
use App\Events\PpeViolationDetected;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Camera;
use App\Models\Device;
use App\Models\IngestEvent;
use App\Models\PpeViolation;
use App\Models\User;
use App\Models\Zone;
use App\Support\Ingest\IngestEventRejected;
use App\Support\Ingest\IngestTimestamps;
use App\Support\Ingest\ReferenceResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class PpeViolationService
{
    private const MINIMAL_JPEG = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAGfAP/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAQUCf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQMBAT8Bf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQIBAT8Bf//Z';

    public function __construct(
        private readonly IngestTimestamps $timestamps,
        private readonly ReferenceResolver $refs,
        private readonly AlertService $alerts,
        private readonly TrackingService $tracking,
        private readonly SignedStorageUrlService $signedUrls,
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

        return [
            'accepted' => $accepted,
            'duplicates' => $duplicates,
            'rejected' => $rejected,
        ];
    }

    /**
     * @param  array{status: string, note?: string|null}  $data
     */
    public function review(PpeViolation $violation, User $user, array $data): PpeViolation
    {
        $status = $data['status'] instanceof ReviewStatus
            ? $data['status']
            : ReviewStatus::from((string) $data['status']);
        if ($status === ReviewStatus::Unreviewed) {
            throw new HttpException(422, 'Review status must be confirmed or false_positive.');
        }

        $violation->forceFill([
            'review_status' => $status,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_note' => $data['note'] ?? null,
        ])->save();

        if ($status === ReviewStatus::FalsePositive && $violation->alert_id !== null) {
            $alert = Alert::query()->find($violation->alert_id);
            if ($alert !== null) {
                $this->alerts->resolve($alert, 'Marked false positive during PPE review');
            }
        }

        AuditLog::query()->create([
            'event_type' => 'ppe_reviewed',
            'user_id' => $user->id,
            'route' => request()->path(),
            'payload' => [
                'ppe_violation_id' => $violation->id,
                'review_status' => $status->value,
                'note' => $data['note'] ?? null,
            ],
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);

        return $violation->fresh() ?? $violation;
    }

    /**
     * @param  list<int>  $ids
     * @param  array{status: string, note?: string|null}  $data
     * @return list<PpeViolation>
     */
    public function bulkReview(array $ids, User $user, array $data): array
    {
        $reviewed = [];
        foreach ($ids as $id) {
            $violation = PpeViolation::query()->find($id);
            if ($violation === null) {
                continue;
            }
            $reviewed[] = $this->review($violation, $user, $data);
        }

        return $reviewed;
    }

    /**
     * @return array{
     *     by_type: array<string, int>,
     *     by_camera: list<array{camera_id: int, camera_ref: string, count: int}>,
     *     by_hour: array<int, int>,
     *     false_positive_rate: float,
     *     excluded_false_positives: int,
     *     total: int
     * }
     */
    public function summary(\DateTimeInterface $from, \DateTimeInterface $to, string $groupBy = 'type'): array
    {
        $base = PpeViolation::query()
            ->whereBetween('detected_at', [$from, $to]);

        $excluded = (clone $base)->where('review_status', ReviewStatus::FalsePositive)->count();
        $included = (clone $base)->where('review_status', '!=', ReviewStatus::FalsePositive->value);
        $total = (clone $included)->count();
        $fpTotal = $excluded;
        $allInRange = (clone $base)->count();
        $fpRate = $allInRange > 0 ? round($fpTotal / $allInRange, 4) : 0.0;

        $byType = [];
        foreach (ViolationType::cases() as $type) {
            $byType[$type->value] = (clone $included)->where('violation_type', $type)->count();
        }

        $byCameraRows = (clone $included)
            ->selectRaw('camera_id, count(*) as aggregate')
            ->groupBy('camera_id')
            ->get();
        $cameraRefs = Camera::query()
            ->whereIn('id', $byCameraRows->pluck('camera_id'))
            ->pluck('reference', 'id');
        $byCamera = $byCameraRows
            ->map(fn ($row): array => [
                'camera_id' => (int) $row->camera_id,
                'camera_ref' => (string) ($cameraRefs[$row->camera_id] ?? ''),
                'count' => (int) $row->aggregate,
            ])
            ->values()
            ->all();

        $byHour = array_fill(0, 24, 0);
        (clone $included)->get(['detected_at'])->each(function (PpeViolation $v) use (&$byHour): void {
            $hour = (int) $v->detected_at->format('G');
            $byHour[$hour]++;
        });

        return [
            'by_type' => $byType,
            'by_camera' => $byCamera,
            'by_hour' => $byHour,
            'false_positive_rate' => $fpRate,
            'excluded_false_positives' => $excluded,
            'total' => $total,
            'group_by' => $groupBy,
        ];
    }

    /**
     * Control-room snapshot for PPE trends: counts over time plus summary breakdowns.
     *
     * @return array<string, mixed>
     */
    public function dashboardSnapshot(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $from = Carbon::instance($from);
        $to = Carbon::instance($to);
        $summary = $this->summary($from, $to);
        $points = $this->violationTrendPoints($from, $to);
        $values = collect($points)
            ->pluck('avg')
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): float => (float) $value);
        $unreviewedInRange = PpeViolation::query()
            ->whereBetween('detected_at', [$from, $to])
            ->where('review_status', ReviewStatus::Unreviewed)
            ->count();

        return [
            'as_of' => now()->toIso8601String(),
            'total' => $summary['total'],
            'unreviewed_in_range' => $unreviewedInRange,
            'false_positive_rate' => $summary['false_positive_rate'],
            'excluded_false_positives' => $summary['excluded_false_positives'],
            'by_type' => $summary['by_type'],
            'by_camera' => $summary['by_camera'],
            'by_hour' => $summary['by_hour'],
            'metrics' => [[
                'key' => 'violations',
                'label' => 'Violations',
                'unit' => '',
                'current' => $summary['total'],
                'min' => $values->isNotEmpty() ? (int) $values->min() : null,
                'avg' => $values->isNotEmpty() ? round($values->avg(), 1) : null,
                'max' => $values->isNotEmpty() ? (int) $values->max() : null,
                'sparkline' => $values->take(-20)->values()->all(),
            ]],
            'trend' => [
                'series' => [[
                    'key' => 'violations',
                    'label' => 'Violations',
                    'unit' => '',
                    'source' => 'raw',
                    'points' => $points,
                ]],
                'source' => 'raw',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function violationTrendPoints(Carbon $from, Carbon $to): array
    {
        $useDaily = $from->diffInHours($to) > 24;
        $buckets = [];

        PpeViolation::query()
            ->whereBetween('detected_at', [$from, $to])
            ->where('review_status', '!=', ReviewStatus::FalsePositive)
            ->orderBy('detected_at')
            ->get(['detected_at'])
            ->each(function (PpeViolation $violation) use (&$buckets, $useDaily): void {
                $at = $useDaily
                    ? $violation->detected_at->copy()->startOfDay()->toIso8601String()
                    : $violation->detected_at->copy()->startOfHour()->toIso8601String();
                $buckets[$at] = ($buckets[$at] ?? 0) + 1;
            });

        return collect($buckets)
            ->map(fn (int $count, string $at): array => [
                'at' => $at,
                'value' => $count,
                'min' => $count,
                'avg' => $count,
                'max' => $count,
                'device_id' => null,
            ])
            ->sortKeys()
            ->values()
            ->all();
    }

    /**
     * @return StreamedResponse|\Illuminate\Http\Response
     */
    public function export(string $format, \DateTimeInterface $from, \DateTimeInterface $to)
    {
        $rows = PpeViolation::query()
            ->with('camera')
            ->whereBetween('detected_at', [$from, $to])
            ->where('review_status', '!=', ReviewStatus::FalsePositive->value)
            ->orderByDesc('detected_at')
            ->get();

        $excluded = PpeViolation::query()
            ->whereBetween('detected_at', [$from, $to])
            ->where('review_status', ReviewStatus::FalsePositive)
            ->count();

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('pdf.ppe-trend-export', [
                'rows' => $rows,
                'from' => Carbon::instance($from),
                'to' => Carbon::instance($to),
                'excluded' => $excluded,
            ]);

            return $pdf->download('ppe-violations.pdf');
        }

        return response()->streamDownload(function () use ($rows, $excluded): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['id', 'violation_type', 'camera_ref', 'detected_at', 'worker_count', 'confidence', 'review_status', 'location_label']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->id,
                    $row->violation_type->value,
                    $row->camera?->reference,
                    $row->detected_at->toIso8601String(),
                    $row->worker_count,
                    $row->confidence,
                    $row->review_status->value,
                    $row->location_label,
                ]);
            }
            fputcsv($out, []);
            fputcsv($out, ['excluded_false_positives', $excluded]);
            fclose($out);
        }, 'ppe-violations.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(PpeViolation $violation): array
    {
        $violation->loadMissing(['camera', 'reviewer', 'alert']);

        return [
            'id' => $violation->id,
            'camera_id' => $violation->camera_id,
            'camera_ref' => $violation->camera?->reference,
            'camera_name' => $violation->camera?->name,
            'violation_type' => $violation->violation_type->value,
            'detected_at' => $violation->detected_at->toIso8601String(),
            'worker_count' => $violation->worker_count,
            'confidence' => $violation->confidence !== null ? (float) $violation->confidence : null,
            'location_label' => $violation->location_label,
            'alert_id' => $violation->alert_id,
            'review_status' => $violation->review_status->value,
            'reviewed_by' => $violation->reviewed_by,
            'reviewed_by_name' => $violation->reviewer?->name,
            'reviewed_at' => $violation->reviewed_at?->toIso8601String(),
            'review_note' => $violation->review_note,
            'is_backfill' => $violation->is_backfill,
            'snapshot_url' => $this->signedUrls->temporaryUrl($violation->snapshot_path),
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     * @return 'accepted'|'duplicate'|'skew'
     */
    private function processOneEvent(Device $caller, array $event): string
    {
        $cameraRef = (string) ($event['camera_ref'] ?? '');
        $camera = $this->refs->resolveCamera($cameraRef);
        if ($camera === null) {
            throw new IngestEventRejected('UNKNOWN_REFERENCE');
        }

        $eventUid = (string) ($event['event_uid'] ?? '');
        $eventType = (string) ($event['event_type'] ?? '');
        $normalized = $this->timestamps->normalize(Carbon::parse((string) $event['detected_at']));
        $detectedAt = $normalized['recorded_at'];

        $camera->forceFill(['last_frame_at' => $normalized['received_at']])->save();

        if ($eventType === 'fall') {
            return $this->processFall($caller, $camera, $event, $eventUid, $normalized, $detectedAt);
        }

        $violationType = ViolationType::tryFrom($eventType);
        if ($violationType === null) {
            throw new IngestEventRejected('VALIDATION_FAILED');
        }

        if (PpeViolation::query()
            ->where('camera_id', $camera->id)
            ->where('event_uid', $eventUid)
            ->exists()) {
            return 'duplicate';
        }

        $snapshotPath = $this->storeSnapshot(isset($event['snapshot']) ? (string) $event['snapshot'] : null);
        $camera->loadMissing('asset');

        try {
            $violation = PpeViolation::query()->create([
                'camera_id' => $camera->id,
                'violation_type' => $violationType,
                'detected_at' => $detectedAt,
                'worker_count' => (int) ($event['worker_count'] ?? 1),
                'snapshot_path' => $snapshotPath,
                'confidence' => isset($event['confidence']) ? (float) $event['confidence'] : null,
                'location_label' => $camera->asset?->current_location_label,
                'review_status' => ReviewStatus::Unreviewed,
                'is_backfill' => $normalized['is_backfill'],
                'event_uid' => $eventUid,
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return 'duplicate';
            }
            throw $e;
        }

        if (! $normalized['is_backfill']) {
            $alert = $this->alerts->raise(
                type: AlertType::PpeViolation,
                title: $violationType->label(),
                payload: [
                    'ppe_violation_id' => $violation->id,
                    'camera_ref' => $camera->reference,
                    'violation_type' => $violationType->value,
                    'detected_at' => $detectedAt->toIso8601String(),
                    'snapshot_path' => $snapshotPath,
                    'snapshot_url' => $this->signedUrls->temporaryUrl($snapshotPath),
                    'suggested_action' => 'log_lsr',
                ],
                source: $violation,
            );
            $violation->forceFill(['alert_id' => $alert->id])->save();

            broadcast(new PpeViolationDetected([
                'id' => $violation->id,
                'violation_type' => $violationType->value,
                'camera_ref' => $camera->reference,
                'snapshot_url' => $this->signedUrls->temporaryUrl($snapshotPath),
                'detected_at' => $detectedAt->toIso8601String(),
            ]));
        }

        return $normalized['clock_skew'] ? 'skew' : 'accepted';
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  array{recorded_at: Carbon, received_at: Carbon, is_backfill: bool, clock_skew: bool}  $normalized
     * @return 'accepted'|'duplicate'|'skew'
     */
    private function processFall(
        Device $caller,
        Camera $camera,
        array $event,
        string $eventUid,
        array $normalized,
        Carbon $detectedAt,
    ): string {
        if (Alert::query()
            ->where('alert_type', AlertType::FallDetection)
            ->where('payload->event_uid', $eventUid)
            ->where('payload->camera_ref', $camera->reference)
            ->exists()) {
            return 'duplicate';
        }

        if (IngestEvent::query()
            ->where('device_id', $caller->id)
            ->where('event_uid', $eventUid)
            ->where('stream', IngestStream::PpeViolations)
            ->exists()) {
            return 'duplicate';
        }

        $snapshotPath = $this->storeSnapshot(isset($event['snapshot']) ? (string) $event['snapshot'] : null);
        $zoneId = $this->resolveZoneId($camera);
        $zone = $zoneId !== null ? Zone::query()->find($zoneId) : null;

        if ($normalized['is_backfill']) {
            IngestEvent::query()->create([
                'device_id' => $caller->id,
                'stream' => IngestStream::PpeViolations,
                'event_uid' => $eventUid,
                'recorded_at' => $detectedAt,
                'received_at' => $normalized['received_at'],
                'is_backfill' => true,
                'clock_skew' => $normalized['clock_skew'],
                'payload' => [
                    'event_type' => 'fall',
                    'camera_ref' => $camera->reference,
                    'snapshot_path' => $snapshotPath,
                ],
            ]);

            return $normalized['clock_skew'] ? 'skew' : 'accepted';
        }

        $this->alerts->raise(
            type: AlertType::FallDetection,
            title: 'Fall detection',
            payload: [
                'event_uid' => $eventUid,
                'camera_ref' => $camera->reference,
                'detected_at' => $detectedAt->toIso8601String(),
                'snapshot_path' => $snapshotPath,
                'snapshot_url' => $this->signedUrls->temporaryUrl($snapshotPath),
                'zone_id' => $zoneId,
                'zone_name' => $zone?->name,
                'suggested_action' => 'create_incident',
            ],
            source: $camera,
        );

        if ($zoneId !== null) {
            $this->tracking->correlateWorkerDown($zoneId, $detectedAt);
        }

        return $normalized['clock_skew'] ? 'skew' : 'accepted';
    }

    private function storeSnapshot(?string $base64): string
    {
        $path = 'snapshots/'.now()->format('Y/m/d').'/'.Str::uuid().'.jpg';
        $binary = '';
        if ($base64 !== null && $base64 !== '') {
            $decoded = base64_decode($base64, true);
            $binary = $decoded !== false ? $decoded : '';
        }
        if ($binary === '') {
            $binary = (string) base64_decode(self::MINIMAL_JPEG, true);
        }
        Storage::disk('private')->put($path, $binary);

        return $path;
    }

    private function resolveZoneId(Camera $camera): ?int
    {
        $camera->loadMissing('asset');
        $meta = $camera->meta ?? [];
        if (isset($meta['zone_id']) && is_numeric($meta['zone_id'])) {
            return (int) $meta['zone_id'];
        }
        $assetMeta = $camera->asset?->meta ?? [];
        if (isset($assetMeta['zone_id']) && is_numeric($assetMeta['zone_id'])) {
            return (int) $assetMeta['zone_id'];
        }
        $label = $camera->asset?->current_location_label;
        if ($label === null || $label === '') {
            return null;
        }

        $id = Zone::query()->where('name', $label)->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? '';
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000' || $driverCode === 1062 || str_contains($e->getMessage(), 'UNIQUE');
    }
}
