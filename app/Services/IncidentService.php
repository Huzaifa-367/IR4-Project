<?php

namespace App\Services;

use App\Enums\AlertType;
use App\Enums\EvidenceType;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\Involvement;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\HseIncident;
use App\Models\IncidentEvidence;
use App\Models\IncidentPersonnel;
use App\Models\PpeViolation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class IncidentService
{
    public function __construct(
        private readonly SignedStorageUrlService $signedUrls,
        private readonly TrackingService $tracking,
    ) {}

    /**
     * Prefill payload for the create form — never persists a row.
     *
     * @return array<string, mixed>
     */
    public function prefillFromAlert(Alert $alert): array
    {
        $payload = $alert->payload ?? [];

        return [
            'source' => IncidentSource::FromAlert->value,
            'alert_id' => $alert->id,
            'occurred_at' => $payload['detected_at']
                ?? $payload['occurred_at']
                ?? optional($alert->raised_at)?->toIso8601String(),
            'zone_id' => $payload['zone_id'] ?? null,
            'camera_id' => $payload['camera_id'] ?? null,
            'nature_of_incident' => $alert->title,
            'suggested_action' => $payload['suggested_action'] ?? null,
            'snapshot_path' => $payload['snapshot_path'] ?? null,
            'ppe_violation_id' => $payload['ppe_violation_id'] ?? null,
            'alert' => [
                'id' => $alert->id,
                'uuid' => $alert->uuid,
                'alert_type' => $alert->alert_type->value,
                'title' => $alert->title,
                'raised_at' => optional($alert->raised_at)?->toIso8601String(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $author): HseIncident
    {
        return DB::transaction(function () use ($data, $author): HseIncident {
            $alert = null;
            $source = IncidentSource::Manual;

            if (! empty($data['alert_id'])) {
                $alert = Alert::query()->findOrFail((int) $data['alert_id']);
                $source = IncidentSource::FromAlert;
            }

            $status = $source === IncidentSource::FromAlert
                ? IncidentStatus::UnderReview
                : IncidentStatus::Open;

            $incident = HseIncident::query()->create([
                'incident_number' => $this->nextIncidentNumber(),
                'source' => $source,
                'alert_id' => $alert?->id,
                'zone_id' => $data['zone_id'] ?? $alert?->payload['zone_id'] ?? null,
                'camera_id' => $data['camera_id'] ?? $alert?->payload['camera_id'] ?? null,
                'occurred_at' => $data['occurred_at'],
                'status' => $status,
                'nature_of_incident' => $data['nature_of_incident'] ?? null,
                'created_by' => $author->id,
            ]);

            if ($alert !== null) {
                $this->attachAlertEvidence($incident, $alert);
            }

            if (! empty($data['ppe_violation_id'])) {
                $this->linkPpeEvidence($incident, (int) $data['ppe_violation_id'], $author->id);
            }

            $zoneId = $incident->zone_id;
            if ($zoneId !== null && ($source === IncidentSource::FromAlert || ! empty($data['capture_roster']))) {
                $this->captureZoneRoster($incident, $zoneId, $incident->occurred_at);
            }

            $this->audit('config_changed', [
                'target' => 'incident_create',
                'incident_id' => $incident->id,
                'source' => $source->value,
                'alert_id' => $alert?->id,
            ]);

            return $incident->fresh(['personnel.worker', 'evidence', 'zone', 'camera', 'alert']) ?? $incident;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function classify(HseIncident $incident, array $data, User $classifier): HseIncident
    {
        $this->assertTransition($incident, IncidentStatus::Classified);

        return DB::transaction(function () use ($incident, $data, $classifier): HseIncident {
            $incident->forceFill([
                'incident_type' => IncidentType::from((string) $data['incident_type']),
                'severity' => IncidentSeverity::from((string) $data['severity']),
                'occurred_at' => $data['occurred_at'] ?? $incident->occurred_at,
                'nature_of_incident' => $data['nature_of_incident'],
                'immediate_action' => $data['immediate_action'],
                'corrective_action' => $data['corrective_action'],
                'status' => IncidentStatus::Classified,
                'classified_by' => $classifier->id,
                'classified_at' => now(),
            ])->save();

            if (isset($data['personnel']) && is_array($data['personnel'])) {
                $this->syncCuratedPersonnel($incident, $data['personnel']);
            }

            $this->audit('config_changed', [
                'target' => 'incident_classify',
                'incident_id' => $incident->id,
            ]);

            return $incident->fresh(['personnel.worker', 'evidence', 'classifier']) ?? $incident;
        });
    }

    public function reopen(HseIncident $incident, User $actor): HseIncident
    {
        if ($incident->status !== IncidentStatus::Classified) {
            throw new HttpException(422, 'Only classified incidents can be reopened.');
        }

        $incident->forceFill([
            'status' => IncidentStatus::UnderReview,
        ])->save();

        $this->audit('config_changed', [
            'target' => 'incident_reopen',
            'incident_id' => $incident->id,
            'actor_id' => $actor->id,
        ]);

        return $incident->fresh() ?? $incident;
    }

    /**
     * @param  array{close_note?: string|null}  $data
     */
    public function close(HseIncident $incident, array $data, User $closer): HseIncident
    {
        if ($incident->status === IncidentStatus::Closed) {
            return $incident;
        }

        if ($incident->status === IncidentStatus::Classified) {
            $incident->forceFill([
                'status' => IncidentStatus::Closed,
                'closed_by' => $closer->id,
                'closed_at' => now(),
                'close_note' => $data['close_note'] ?? null,
            ])->save();
        } elseif (in_array($incident->status, [IncidentStatus::Open, IncidentStatus::UnderReview], true)) {
            $note = trim((string) ($data['close_note'] ?? ''));
            if (strlen($note) < 10) {
                throw ValidationException::withMessages([
                    'close_note' => ['A close note (min 10 characters) is required to close without classification.'],
                ]);
            }

            $incident->forceFill([
                'status' => IncidentStatus::Closed,
                'closed_by' => $closer->id,
                'closed_at' => now(),
                'close_note' => $note,
            ])->save();
        } else {
            throw new HttpException(422, 'Invalid incident close transition.');
        }

        $this->audit('config_changed', [
            'target' => 'incident_close',
            'incident_id' => $incident->id,
        ]);

        return $incident->fresh() ?? $incident;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addEvidence(HseIncident $incident, array $data, User $user): IncidentEvidence
    {
        if ($incident->status === IncidentStatus::Closed) {
            throw new HttpException(422, 'Cannot add evidence to a closed incident.');
        }

        $type = EvidenceType::from((string) $data['evidence_type']);
        $filePath = null;

        if (($data['file'] ?? null) instanceof UploadedFile) {
            $filePath = $data['file']->storeAs(
                'incidents/'.$incident->id,
                (string) Str::uuid().'.'.($data['file']->getClientOriginalExtension() ?: 'bin'),
                'private',
            );
        }

        $evidence = $incident->evidence()->create([
            'evidence_type' => $type,
            'file_path' => $filePath,
            'payload' => $data['payload'] ?? (isset($data['note']) ? ['text' => $data['note']] : null),
            'ppe_violation_id' => $data['ppe_violation_id'] ?? null,
            'camera_id' => $data['camera_id'] ?? null,
            'captured_at' => $data['captured_at'] ?? now(),
            'added_by' => $user->id,
        ]);

        return $evidence;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(HseIncident $incident, bool $canSeeIdentity = false): array
    {
        $incident->loadMissing([
            'personnel.worker',
            'evidence.ppeViolation',
            'evidence.addedByUser',
            'zone',
            'camera',
            'alert',
            'classifier',
            'closer',
            'creator',
        ]);

        return [
            'id' => $incident->id,
            'uuid' => $incident->uuid,
            'incident_number' => $incident->incident_number,
            'source' => $incident->source->value,
            'source_label' => $incident->source->label(),
            'status' => $incident->status->value,
            'status_label' => $incident->status->label(),
            'alert_id' => $incident->alert_id,
            'zone_id' => $incident->zone_id,
            'zone_name' => $incident->zone?->name,
            'camera_id' => $incident->camera_id,
            'camera_name' => $incident->camera?->name,
            'occurred_at' => optional($incident->occurred_at)?->toIso8601String(),
            'incident_type' => $incident->incident_type?->value,
            'incident_type_label' => $incident->incident_type?->label(),
            'severity' => $incident->severity?->value,
            'severity_label' => $incident->severity?->label(),
            'nature_of_incident' => $incident->nature_of_incident,
            'immediate_action' => $incident->immediate_action,
            'corrective_action' => $incident->corrective_action,
            'classified_at' => optional($incident->classified_at)?->toIso8601String(),
            'classified_by_name' => $incident->classifier?->name,
            'closed_at' => optional($incident->closed_at)?->toIso8601String(),
            'closed_by_name' => $incident->closer?->name,
            'close_note' => $incident->close_note,
            'created_by_name' => $incident->creator?->name,
            'created_at' => optional($incident->created_at)?->toIso8601String(),
            'personnel' => $incident->personnel->map(function (IncidentPersonnel $row) use ($canSeeIdentity): array {
                $worker = $row->worker;

                return [
                    'id' => $row->id,
                    'worker_id' => $row->worker_id,
                    'worker_label' => $worker === null
                        ? null
                        : ($canSeeIdentity ? $worker->name : $worker->anonymizedLabel()),
                    'involvement' => $row->involvement->value,
                    'involvement_label' => $row->involvement->label(),
                ];
            })->values()->all(),
            'evidence' => $incident->evidence->map(function (IncidentEvidence $row): array {
                return [
                    'id' => $row->id,
                    'evidence_type' => $row->evidence_type->value,
                    'evidence_type_label' => $row->evidence_type->label(),
                    'download_url' => $row->file_path !== null
                        ? $this->signedUrls->temporaryUrl($row->file_path)
                        : null,
                    'payload' => $row->payload,
                    'ppe_violation_id' => $row->ppe_violation_id,
                    'camera_id' => $row->camera_id,
                    'captured_at' => optional($row->captured_at)?->toIso8601String(),
                    'auto_captured' => $row->isAutoCaptured(),
                    'added_by_name' => $row->addedByUser?->name,
                ];
            })->values()->all(),
        ];
    }

    private function nextIncidentNumber(): string
    {
        $year = now()->format('Y');
        $prefix = 'INC-'.$year.'-';

        $latest = HseIncident::query()
            ->withTrashed()
            ->where('incident_number', 'like', $prefix.'%')
            ->orderByDesc('incident_number')
            ->value('incident_number');

        $seq = 1;
        if (is_string($latest) && preg_match('/INC-\d{4}-(\d+)$/', $latest, $matches) === 1) {
            $seq = (int) $matches[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    private function attachAlertEvidence(HseIncident $incident, Alert $alert): void
    {
        $payload = $alert->payload ?? [];

        if (! empty($payload['snapshot_path']) && is_string($payload['snapshot_path'])) {
            $incident->evidence()->create([
                'evidence_type' => EvidenceType::Snapshot,
                'file_path' => $payload['snapshot_path'],
                'camera_id' => $payload['camera_id'] ?? $incident->camera_id,
                'captured_at' => $incident->occurred_at,
                'added_by' => null,
            ]);
        }

        if (! empty($payload['ppe_violation_id'])) {
            $this->linkPpeEvidence($incident, (int) $payload['ppe_violation_id'], null);
        }
    }

    private function linkPpeEvidence(HseIncident $incident, int $ppeViolationId, ?int $addedBy): void
    {
        $violation = PpeViolation::query()->find($ppeViolationId);
        if ($violation === null) {
            return;
        }

        $incident->evidence()->create([
            'evidence_type' => EvidenceType::PpeViolation,
            'ppe_violation_id' => $violation->id,
            'camera_id' => $violation->camera_id,
            'captured_at' => $violation->detected_at,
            'file_path' => $violation->snapshot_path,
            'added_by' => $addedBy,
            'payload' => [
                'violation_type' => $violation->violation_type->value ?? null,
            ],
        ]);
    }

    private function captureZoneRoster(HseIncident $incident, int $zoneId, mixed $occurredAt): void
    {
        $roster = $this->tracking->zoneRosterAt($zoneId, $occurredAt);

        foreach ($roster as $row) {
            IncidentPersonnel::query()->firstOrCreate(
                [
                    'hse_incident_id' => $incident->id,
                    'worker_id' => $row['worker_id'],
                ],
                [
                    'involvement' => Involvement::PresentInZone,
                ],
            );
        }

        $incident->evidence()->create([
            'evidence_type' => EvidenceType::RfidZoneSnapshot,
            'payload' => [
                'zone_id' => $zoneId,
                'captured_for' => optional(\Illuminate\Support\Carbon::parse($occurredAt))->toIso8601String(),
                'workers' => $roster,
                'note' => 'Frozen RFID zone roster from tag readings at or before occurred_at.',
            ],
            'captured_at' => now(),
            'added_by' => null,
        ]);
    }

    /**
     * @param  list<array{worker_id: int, involvement: string}>  $personnel
     */
    private function syncCuratedPersonnel(HseIncident $incident, array $personnel): void
    {
        $keepWorkerIds = [];

        foreach ($personnel as $row) {
            if (empty($row['worker_id'])) {
                continue;
            }

            $involvement = Involvement::from((string) $row['involvement']);
            if ($involvement === Involvement::PresentInZone) {
                continue;
            }

            $keepWorkerIds[] = (int) $row['worker_id'];

            IncidentPersonnel::query()->updateOrCreate(
                [
                    'hse_incident_id' => $incident->id,
                    'worker_id' => (int) $row['worker_id'],
                ],
                [
                    'involvement' => $involvement,
                ],
            );
        }

        IncidentPersonnel::query()
            ->where('hse_incident_id', $incident->id)
            ->whereIn('involvement', [Involvement::Involved->value, Involvement::Witness->value])
            ->when($keepWorkerIds !== [], fn ($q) => $q->whereNotIn('worker_id', $keepWorkerIds))
            ->when($keepWorkerIds === [], fn ($q) => $q)
            ->delete();
    }

    private function assertTransition(HseIncident $incident, IncidentStatus $to): void
    {
        $allowed = match ($incident->status) {
            IncidentStatus::Open, IncidentStatus::UnderReview => [IncidentStatus::Classified, IncidentStatus::Closed, IncidentStatus::UnderReview],
            IncidentStatus::Classified => [IncidentStatus::Closed, IncidentStatus::UnderReview],
            IncidentStatus::Closed => [],
        };

        if (! in_array($to, $allowed, true) && $to !== $incident->status) {
            throw new HttpException(422, 'Invalid incident status transition.');
        }
    }

    /**
     * Map alert types that suggest incidents.
     */
    public function alertSuggestsIncident(Alert $alert): bool
    {
        return in_array($alert->alert_type, [
            AlertType::FallDetection,
            AlertType::StationaryTag,
            AlertType::WorkerDown,
        ], true)
            || (($alert->payload['suggested_action'] ?? null) === 'create_incident');
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
