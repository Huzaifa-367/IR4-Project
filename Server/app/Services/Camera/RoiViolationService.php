<?php

namespace App\Services\Camera;

use App\Enums\AlertType;
use App\Enums\CameraRoiSetStatus;
use App\Enums\ReviewStatus;
use App\Enums\RoiViolationType;
use App\Events\RoiViolationDetected;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\CameraRoi;
use App\Models\Device;
use App\Models\RoiViolation;
use App\Models\User;
use App\Services\Alert\AlertService;
use App\Services\Hardware\HardwareRegistryService;
use App\Support\Ingest\IngestEventRejected;
use App\Support\Ingest\IngestSnapshotStore;
use App\Support\Ingest\IngestTimestamps;
use App\Support\Ingest\ReferenceResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class RoiViolationService
{
    public function __construct(
        private readonly IngestTimestamps $timestamps,
        private readonly ReferenceResolver $refs,
        private readonly AlertService $alerts,
        private readonly IngestSnapshotStore $snapshots,
        private readonly HardwareRegistryService $hardware,
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
    public function review(RoiViolation $violation, User $user, array $data): RoiViolation
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
                $this->alerts->resolve($alert, 'Marked false positive during ROI review');
            }
        }

        AuditLog::query()->create([
            'event_type' => 'roi_reviewed',
            'user_id' => $user->id,
            'route' => request()->path(),
            'payload' => [
                'roi_violation_id' => $violation->id,
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
     * @return list<RoiViolation>
     */
    public function bulkReview(array $ids, User $user, array $data): array
    {
        $reviewed = [];
        foreach ($ids as $id) {
            $violation = RoiViolation::query()->find($id);
            if ($violation === null) {
                continue;
            }
            $reviewed[] = $this->review($violation, $user, $data);
        }

        return $reviewed;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(RoiViolation $violation): array
    {
        $violation->loadMissing(['camera', 'reviewer', 'alert', 'cameraRoi']);

        return [
            'id' => $violation->id,
            'uuid' => $violation->uuid,
            'camera_id' => $violation->camera_id,
            'device_id' => $violation->device_id,
            'camera_ref' => $violation->camera?->reference,
            'camera_name' => $violation->camera?->name,
            'camera_roi_id' => $violation->camera_roi_id,
            'roi_reference' => $violation->roi_reference,
            'roi_name' => $violation->cameraRoi?->name,
            'event_type' => $violation->event_type->value,
            'detected_at' => $violation->detected_at->toIso8601String(),
            'confidence' => $violation->confidence !== null ? (float) $violation->confidence : null,
            'location_label' => $violation->location_label,
            'alert_id' => $violation->alert_id,
            'review_status' => $violation->review_status->value,
            'reviewed_by' => $violation->reviewed_by,
            'reviewed_by_name' => $violation->reviewer?->name,
            'reviewed_at' => $violation->reviewed_at?->toIso8601String(),
            'review_note' => $violation->review_note,
            'is_backfill' => $violation->is_backfill,
            'snapshot_url' => $this->snapshots->temporaryUrl($violation->snapshot_path),
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
        $eventType = RoiViolationType::tryFrom((string) ($event['event_type'] ?? ''));
        if ($eventType === null) {
            throw new IngestEventRejected('VALIDATION_FAILED');
        }

        $roiReference = trim((string) ($event['roi_reference'] ?? ''));
        if ($roiReference === '') {
            throw new IngestEventRejected('VALIDATION_FAILED');
        }

        $roi = $this->resolveActiveRoi($camera, $roiReference);
        if ($roi === null) {
            throw new IngestEventRejected('UNKNOWN_ROI');
        }

        $normalized = $this->timestamps->normalize(Carbon::parse((string) $event['detected_at']));
        $detectedAt = $normalized['recorded_at'];
        $camera = $this->hardware->touchCameraPresence($camera, $normalized['received_at']);

        if (RoiViolation::query()
            ->where('camera_id', $camera->id)
            ->where('event_uid', $eventUid)
            ->exists()) {
            return 'duplicate';
        }

        $snapshotPath = $this->snapshots->store(isset($event['snapshot']) ? (string) $event['snapshot'] : null);
        $camera->loadMissing('asset');

        try {
            $violation = RoiViolation::query()->create([
                'camera_id' => $camera->id,
                'device_id' => $caller->id,
                'camera_roi_id' => $roi->id,
                'roi_reference' => $roi->reference,
                'event_type' => $eventType,
                'detected_at' => $detectedAt,
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
                type: AlertType::RoiViolation,
                title: $eventType->label().': '.$roi->name,
                payload: [
                    'roi_violation_id' => $violation->id,
                    'event_uid' => $eventUid,
                    'camera_id' => $camera->id,
                    'camera_ref' => $camera->reference,
                    'roi_reference' => $roi->reference,
                    'roi_name' => $roi->name,
                    'event_type' => $eventType->value,
                    'detected_at' => $detectedAt->toIso8601String(),
                    'snapshot_path' => $snapshotPath,
                    'snapshot_url' => $this->snapshots->temporaryUrl($snapshotPath),
                ],
                source: $violation,
            );
            $violation->forceFill(['alert_id' => $alert->id])->save();

            broadcast(new RoiViolationDetected([
                'id' => $violation->id,
                'uuid' => $violation->uuid,
                'event_type' => $eventType->value,
                'camera_ref' => $camera->reference,
                'roi_reference' => $roi->reference,
                'snapshot_url' => $this->snapshots->temporaryUrl($snapshotPath),
                'detected_at' => $detectedAt->toIso8601String(),
            ]));
        }

        return $normalized['clock_skew'] ? 'skew' : 'accepted';
    }

    private function resolveActiveRoi(Device $camera, string $roiReference): ?CameraRoi
    {
        $camera->loadMissing('roiSet.rois');
        $set = $camera->roiSet;
        if ($set === null || $set->status !== CameraRoiSetStatus::Active) {
            return null;
        }

        return $set->rois->first(
            fn (CameraRoi $roi): bool => $roi->is_enabled
                && $roi->reference === $roiReference
                && count($roi->polygon) >= 3,
        );
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $code = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $code === '23000' || $driverCode === 1062 || $driverCode === 19
            || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
