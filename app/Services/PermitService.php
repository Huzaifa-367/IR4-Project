<?php

namespace App\Services;

use App\Enums\DeviceType;
use App\Enums\GasTestPhase;
use App\Enums\GasTestResult;
use App\Enums\GasTestSource;
use App\Enums\PermitApprovalAction;
use App\Enums\PermitStatus;
use App\Models\GasReading;
use App\Models\Permit;
use App\Models\PermitGasTest;
use App\Models\PermitPersonnel;
use App\Models\PermitType;
use App\Models\PermitTypeGasChannel;
use App\Models\ReaderZoneBinding;
use App\Models\User;
use App\Models\WorkerDocument;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PermitService
{
    public function __construct(
        private readonly WorkerDocumentReadinessService $readiness,
    ) {}

    /**
     * @param  array{
     *     permit_type_id: int,
     *     zone_id?: int|null,
     *     task_description: string,
     *     work_order_id?: int|null,
     *     checklist?: array<string, mixed>|null,
     *     personnel?: list<array{worker_id: int, role_code: string}>,
     *     is_extended?: bool
     * }  $data
     */
    public function createDraft(User $receiver, array $data): Permit
    {
        return DB::transaction(function () use ($receiver, $data): Permit {
            $type = PermitType::query()->findOrFail((int) $data['permit_type_id']);

            if (! $type->is_active) {
                throw ValidationException::withMessages([
                    'permit_type_id' => ['The selected permit type is not active.'],
                ]);
            }

            $personnel = $data['personnel'] ?? [];

            $permit = Permit::query()->create([
                'permit_number' => $this->generateNumber(),
                'permit_type_id' => $type->id,
                'work_order_id' => $data['work_order_id'] ?? null,
                'zone_id' => $data['zone_id'] ?? null,
                'task_description' => $data['task_description'],
                'receiver_id' => $receiver->id,
                'status' => PermitStatus::Draft,
                'is_extended' => (bool) ($data['is_extended'] ?? false),
                'checklist' => $data['checklist'] ?? null,
                'gas_test_required' => $type->requires_gas_test,
                'created_by' => $receiver->id,
            ]);

            foreach ($personnel as $row) {
                $workerId = (int) $row['worker_id'];
                $roleCode = (string) $row['role_code'];

                $this->assertWorkerDocuments($permit, $workerId, $roleCode);

                PermitPersonnel::query()->create([
                    'permit_id' => $permit->id,
                    'worker_id' => $workerId,
                    'role_code' => $roleCode,
                    'documents_verified_at' => now(),
                ]);
            }

            $this->recordEvent($permit, 'draft_created', [
                'permit_type_id' => $type->id,
                'permit_type_code' => $type->code,
                'personnel_count' => count($personnel),
            ], $receiver);

            return $permit->fresh([
                'type',
                'zone',
                'personnel.worker',
                'receiver',
            ]) ?? $permit;
        });
    }

    /**
     * @param  array{
     *     zone_id?: int|null,
     *     task_description: string,
     *     checklist?: array<string, mixed>|null,
     *     controls?: array<string, mixed>|null,
     *     personnel?: list<array{worker_id: int, role_code: string}>,
     *     is_extended?: bool
     * }  $data
     */
    public function updateDraft(Permit $permit, User $actor, array $data): Permit
    {
        return DB::transaction(function () use ($permit, $actor, $data): Permit {
            $permit = $this->reload($permit);

            if (! in_array($permit->status, [PermitStatus::Draft, PermitStatus::Rejected], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only draft or rejected permits can be updated.'],
                ]);
            }

            $permit->update([
                'zone_id' => $data['zone_id'] ?? null,
                'task_description' => $data['task_description'],
                'checklist' => $data['checklist'] ?? null,
                'controls' => $data['controls'] ?? null,
                'is_extended' => (bool) ($data['is_extended'] ?? false),
            ]);

            if (array_key_exists('personnel', $data)) {
                $permit->personnel()->delete();

                foreach ($data['personnel'] ?? [] as $row) {
                    $workerId = (int) $row['worker_id'];
                    $roleCode = (string) $row['role_code'];

                    $this->assertWorkerDocuments($permit, $workerId, $roleCode);

                    PermitPersonnel::query()->create([
                        'permit_id' => $permit->id,
                        'worker_id' => $workerId,
                        'role_code' => $roleCode,
                        'documents_verified_at' => now(),
                    ]);
                }
            }

            $this->recordEvent($permit, 'draft_updated', [
                'personnel_count' => array_key_exists('personnel', $data)
                    ? count($data['personnel'] ?? [])
                    : $permit->personnel()->count(),
            ], $actor);

            return $permit->fresh([
                'type',
                'zone',
                'personnel.worker',
                'receiver',
            ]) ?? $permit;
        });
    }

    public function submit(Permit $permit, User $actor): Permit
    {
        return DB::transaction(function () use ($permit, $actor): Permit {
            $permit = $this->reload($permit);

            if (! in_array($permit->status, [PermitStatus::Draft, PermitStatus::Rejected], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only draft or rejected permits can be submitted.'],
                ]);
            }

            $this->assertMandatoryChecklist($permit);
            $this->assertMandatoryRoles($permit);
            $this->assertAllPersonnelDocuments($permit);

            $nextStatus = $this->statusAfterSubmit($permit);

            $permit->update(['status' => $nextStatus]);

            $this->recordEvent($permit, 'submitted', [
                'from' => PermitStatus::Draft->value,
                'to' => $nextStatus->value,
            ], $actor);

            return $permit->fresh(['type', 'zone', 'personnel.worker', 'receiver']) ?? $permit;
        });
    }

    public function recordJointInspection(Permit $permit, User $actor, string $as): Permit
    {
        return DB::transaction(function () use ($permit, $actor, $as): Permit {
            $permit = $this->reload($permit);

            if ($permit->status !== PermitStatus::PendingInspection) {
                throw ValidationException::withMessages([
                    'status' => ['Joint inspection can only be recorded while pending inspection.'],
                ]);
            }

            if (! in_array($as, ['issuer', 'receiver'], true)) {
                throw ValidationException::withMessages([
                    'as' => ['Inspection role must be issuer or receiver.'],
                ]);
            }

            if ($as === 'issuer') {
                $permit->joint_inspection_by_issuer = $actor->id;
            } else {
                $permit->joint_inspection_by_receiver = $actor->id;
            }

            if ($permit->hasJointInspectionComplete()) {
                $permit->joint_inspection_at = now();
                $permit->status = $this->nextStatusAfterInspection($permit);
            }

            $permit->save();

            $this->recordEvent($permit, 'joint_inspection_signed', [
                'as' => $as,
                'complete' => $permit->hasJointInspectionComplete(),
                'status' => $permit->status->value,
            ], $actor);

            return $permit->fresh(['type', 'zone', 'personnel.worker']) ?? $permit;
        });
    }

    /**
     * @param  array<string, float|int|string|null>  $readings
     */
    public function recordGasTest(
        Permit $permit,
        User $tester,
        array $readings,
        GasTestSource $source,
        ?int $deviceId,
        GasTestPhase $phase,
    ): PermitGasTest {
        return DB::transaction(function () use ($permit, $tester, $readings, $source, $deviceId, $phase): PermitGasTest {
            $permit = $this->reload($permit);
            $type = $permit->type ?? PermitType::query()->findOrFail($permit->permit_type_id);

            $passed = $this->evaluateGasPass($type, $readings);
            $result = $passed ? GasTestResult::Pass : GasTestResult::Fail;

            $gasTest = PermitGasTest::query()->create([
                'permit_id' => $permit->id,
                'tested_at' => now(),
                'readings' => $readings,
                'result' => $result,
                'source' => $source,
                'device_id' => $deviceId,
                'tested_by' => $tester->id,
                'phase' => $phase,
            ]);

            $this->recordEvent($permit, 'gas_test_recorded', [
                'result' => $result->value,
                'phase' => $phase->value,
                'source' => $source->value,
                'readings' => $readings,
            ], $tester);

            if ($passed && $permit->status === PermitStatus::PendingGasTest) {
                $permit->update([
                    'status' => $permit->needsApprover()
                        ? PermitStatus::PendingApproval
                        : PermitStatus::PendingIssue,
                ]);
            }

            return $gasTest->fresh(['tester', 'device']) ?? $gasTest;
        });
    }

    public function approve(Permit $permit, User $approver, ?string $note): Permit
    {
        return DB::transaction(function () use ($permit, $approver, $note): Permit {
            $permit = $this->reload($permit);

            if ($permit->status !== PermitStatus::PendingApproval) {
                throw ValidationException::withMessages([
                    'status' => ['Only permits pending approval can be approved.'],
                ]);
            }

            $permit->update([
                'approver_id' => $approver->id,
                'status' => PermitStatus::PendingIssue,
            ]);

            $this->recordApproval($permit, $approver, PermitApprovalAction::Approved, $note);
            $this->recordEvent($permit, 'approved', ['note' => $note], $approver);

            return $permit->fresh(['type', 'zone', 'approver']) ?? $permit;
        });
    }

    public function issue(Permit $permit, User $issuer, ?string $note): Permit
    {
        return DB::transaction(function () use ($permit, $issuer, $note): Permit {
            $permit = $this->reload($permit);
            $type = $permit->type ?? PermitType::query()->findOrFail($permit->permit_type_id);

            if ($permit->status !== PermitStatus::PendingIssue) {
                throw ValidationException::withMessages([
                    'status' => ['Only permits pending issue can be issued.'],
                ]);
            }

            $this->assertMandatoryChecklist($permit);
            $this->assertMandatoryRoles($permit);
            $this->assertAllPersonnelDocuments($permit);

            $validFrom = now();
            $validTo = $validFrom->copy()->addMinutes($type->default_validity_minutes);

            $permit->update([
                'issuer_id' => $issuer->id,
                'issued_at' => $validFrom,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
                'status' => PermitStatus::Active,
            ]);

            $this->recordApproval($permit, $issuer, PermitApprovalAction::Issued, $note);
            $this->recordEvent($permit, 'issued', [
                'valid_from' => $validFrom->toIso8601String(),
                'valid_to' => $validTo->toIso8601String(),
                'note' => $note,
            ], $issuer);

            return $permit->fresh(['type', 'zone', 'personnel.worker', 'issuer']) ?? $permit;
        });
    }

    public function suspend(Permit $permit, ?User $actor, string $reason): Permit
    {
        return DB::transaction(function () use ($permit, $actor, $reason): Permit {
            $permit = $this->reload($permit);

            if (! in_array($permit->status, [PermitStatus::Active, PermitStatus::Suspended], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only active permits can be suspended.'],
                ]);
            }

            if ($permit->status === PermitStatus::Suspended) {
                return $permit;
            }

            $permit->update(['status' => PermitStatus::Suspended]);

            if ($actor !== null) {
                $this->recordApproval($permit, $actor, PermitApprovalAction::Suspended, $reason);
            }

            $this->recordEvent($permit, 'suspended', ['reason' => $reason], $actor);

            return $permit->fresh(['type', 'zone']) ?? $permit;
        });
    }

    public function resume(Permit $permit, User $actor): Permit
    {
        return DB::transaction(function () use ($permit, $actor): Permit {
            $permit = $this->reload($permit);

            if ($permit->status !== PermitStatus::Suspended) {
                throw ValidationException::withMessages([
                    'status' => ['Only suspended permits can be resumed.'],
                ]);
            }

            if ($permit->gas_test_required) {
                $latestPass = $permit->gasTests()
                    ->where('result', GasTestResult::Pass)
                    ->orderByDesc('tested_at')
                    ->first();

                if ($latestPass === null) {
                    throw ValidationException::withMessages([
                        'gas_test' => ['A passing gas test is required before resuming work.'],
                    ]);
                }

                $retestMinutes = $permit->type?->retest_interval_minutes;
                if ($retestMinutes !== null && $latestPass->tested_at->lt(now()->subMinutes($retestMinutes))) {
                    throw ValidationException::withMessages([
                        'gas_test' => ['The most recent passing gas test is outside the allowed retest interval.'],
                    ]);
                }
            }

            $permit->update(['status' => PermitStatus::Active]);

            $this->recordApproval($permit, $actor, PermitApprovalAction::Resumed, null);
            $this->recordEvent($permit, 'resumed', [], $actor);

            return $permit->fresh(['type', 'zone']) ?? $permit;
        });
    }

    public function renew(Permit $permit, User $issuer): Permit
    {
        return DB::transaction(function () use ($permit, $issuer): Permit {
            $permit = $this->reload($permit);
            $type = $permit->type ?? PermitType::query()->findOrFail($permit->permit_type_id);

            if ($permit->status !== PermitStatus::Active) {
                throw ValidationException::withMessages([
                    'status' => ['Only active permits can be renewed.'],
                ]);
            }

            if ($permit->renewal_count >= $type->max_renewals) {
                throw ValidationException::withMessages([
                    'renewal_count' => ['This permit has reached the maximum number of renewals.'],
                ]);
            }

            $validFrom = $permit->valid_from ?? now();
            $proposedValidTo = now()->addMinutes($type->default_validity_minutes);
            $totalMinutes = $validFrom->diffInMinutes($proposedValidTo);

            if ($totalMinutes > $type->max_total_minutes && ! $permit->is_extended) {
                throw ValidationException::withMessages([
                    'valid_to' => ['Renewal would exceed the maximum total permit duration.'],
                ]);
            }

            $this->assertAllPersonnelDocuments($permit);

            $permit->update([
                'renewal_count' => $permit->renewal_count + 1,
                'valid_to' => $proposedValidTo,
                'issuer_id' => $issuer->id,
            ]);

            $this->recordApproval($permit, $issuer, PermitApprovalAction::Renewed, null);
            $this->recordEvent($permit, 'renewed', [
                'renewal_count' => $permit->renewal_count,
                'valid_to' => $proposedValidTo->toIso8601String(),
            ], $issuer);

            return $permit->fresh(['type', 'zone', 'personnel.worker']) ?? $permit;
        });
    }

    public function cancel(Permit $permit, User $actor, string $reason): Permit
    {
        return DB::transaction(function () use ($permit, $actor, $reason): Permit {
            $permit = $this->reload($permit);

            if (! in_array($permit->status, [PermitStatus::Active, PermitStatus::Suspended], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only active or suspended permits can be cancelled.'],
                ]);
            }

            $permit->update([
                'status' => PermitStatus::Cancelled,
                'cancel_reason' => $reason,
            ]);

            $this->recordApproval($permit, $actor, PermitApprovalAction::Cancelled, $reason);
            $this->recordEvent($permit, 'cancelled', ['reason' => $reason], $actor);

            return $permit->fresh(['type', 'zone']) ?? $permit;
        });
    }

    public function close(Permit $permit, User $actor, string $note): Permit
    {
        return DB::transaction(function () use ($permit, $actor, $note): Permit {
            $permit = $this->reload($permit);

            if (! in_array($permit->status, [PermitStatus::Active, PermitStatus::Expired, PermitStatus::Suspended], true)) {
                throw ValidationException::withMessages([
                    'status' => ['This permit cannot be closed from its current status.'],
                ]);
            }

            $permit->update([
                'status' => PermitStatus::Closed,
                'closed_at' => now(),
                'close_note' => $note,
            ]);

            $this->recordApproval($permit, $actor, PermitApprovalAction::Closed, $note);
            $this->recordEvent($permit, 'closed', ['note' => $note], $actor);

            return $permit->fresh(['type', 'zone']) ?? $permit;
        });
    }

    public function reject(Permit $permit, User $actor, string $note): Permit
    {
        return DB::transaction(function () use ($permit, $actor, $note): Permit {
            $permit = $this->reload($permit);

            if ($permit->status === PermitStatus::Rejected) {
                return $permit;
            }

            if (in_array($permit->status, [PermitStatus::Active, PermitStatus::Closed, PermitStatus::Cancelled], true)) {
                throw ValidationException::withMessages([
                    'status' => ['This permit cannot be rejected from its current status.'],
                ]);
            }

            $permit->update(['status' => PermitStatus::Rejected]);

            $this->recordApproval($permit, $actor, PermitApprovalAction::Rejected, $note);
            $this->recordEvent($permit, 'rejected', ['note' => $note], $actor);

            return $permit->fresh(['type', 'zone']) ?? $permit;
        });
    }

    public function assertWorkerDocuments(Permit $permit, int $workerId, string $roleCode): void
    {
        $type = $permit->type ?? PermitType::query()->findOrFail($permit->permit_type_id);

        $requirements = $type->documentRequirements()
            ->where('is_mandatory', true)
            ->where(function ($query) use ($roleCode): void {
                $query->whereNull('role_code')
                    ->orWhere('role_code', $roleCode);
            })
            ->with('workerDocumentType')
            ->get();

        /** @var list<string> $missing */
        $missing = [];

        foreach ($requirements as $requirement) {
            if (! $this->readiness->workerSatisfiesRequirement($workerId, $requirement)) {
                $missing[] = $requirement->workerDocumentType?->code ?? (string) $requirement->worker_document_type_id;
            }
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'personnel' => [
                    'Worker #'.$workerId.' is missing required documents: '.implode(', ', $missing).'. Upload and verify them on the worker profile before assigning this role.',
                ],
            ]);
        }
    }

    /**
     * @return array{
     *     is_clear: bool,
     *     total_permits: int,
     *     active_permits: int,
     *     pending_permits: int,
     *     permits: list<array<string, mixed>>
     * }
     */
    public function workOrderClearance(WorkOrder $workOrder): array
    {
        $workOrder->load([
            'permits.type:id,code,name,colour_token',
            'permits.zone:id,name',
        ]);

        $permits = $workOrder->permits;

        $terminal = [
            PermitStatus::Closed,
            PermitStatus::Cancelled,
            PermitStatus::Expired,
        ];

        $activeCount = $permits->where('status', PermitStatus::Active)->count();
        $pendingCount = $permits
            ->filter(fn (Permit $permit): bool => ! in_array($permit->status, $terminal, true)
                && $permit->status !== PermitStatus::Active)
            ->count();

        $isClear = $permits->isEmpty()
            || $permits->every(fn (Permit $permit): bool => $permit->status === PermitStatus::Active);

        return [
            'is_clear' => $isClear,
            'total_permits' => $permits->count(),
            'active_permits' => $activeCount,
            'pending_permits' => $pendingCount,
            'permits' => $permits->map(fn (Permit $permit): array => [
                'id' => $permit->id,
                'permit_number' => $permit->permit_number,
                'status' => $permit->status->value,
                'status_label' => $permit->status->label(),
                'type' => $permit->type === null ? null : [
                    'id' => $permit->type->id,
                    'code' => $permit->type->code,
                    'name' => $permit->type->name,
                    'colour_token' => $permit->type->colour_token,
                ],
                'zone' => $permit->zone === null ? null : [
                    'id' => $permit->zone->id,
                    'name' => $permit->zone->name,
                ],
            ])->values()->all(),
        ];
    }

    public function expireOverdue(): int
    {
        $count = 0;

        Permit::query()
            ->where('status', PermitStatus::Active)
            ->whereNotNull('valid_to')
            ->where('valid_to', '<', now())
            ->orderBy('id')
            ->each(function (Permit $permit) use (&$count): void {
                DB::transaction(function () use ($permit, &$count): void {
                    $permit->update(['status' => PermitStatus::Expired]);
                    $this->recordEvent($permit, 'expired', [
                        'reason' => 'valid_to_passed',
                        'valid_to' => $permit->valid_to?->toIso8601String(),
                    ], null);
                    $count++;
                });
            });

        return $count;
    }

    public function suspendStaleGasTests(): int
    {
        $count = 0;

        Permit::query()
            ->where('status', PermitStatus::Active)
            ->where('gas_test_required', true)
            ->with(['type', 'gasTests'])
            ->orderBy('id')
            ->each(function (Permit $permit) use (&$count): void {
                $retestMinutes = $permit->type?->retest_interval_minutes;
                if ($retestMinutes === null) {
                    return;
                }

                $latestPass = $permit->gasTests
                    ->where('result', GasTestResult::Pass)
                    ->sortByDesc('tested_at')
                    ->first();

                if ($latestPass !== null && $latestPass->tested_at->gte(now()->subMinutes($retestMinutes))) {
                    return;
                }

                $this->suspend($permit, null, 'Gas retest interval exceeded (automated).');
                $count++;
            });

        return $count;
    }

    /**
     * @return array<string, float|int|string|null>
     */
    public function suggestGasReadings(Permit $permit): array
    {
        $permit = $this->reload($permit);

        if ($permit->zone_id === null) {
            return [];
        }

        $type = $permit->type ?? PermitType::query()->with('gasChannels')->findOrFail($permit->permit_type_id);
        $channels = $type->gasChannels;

        if ($channels->isEmpty()) {
            return [];
        }

        $deviceIds = ReaderZoneBinding::query()
            ->where('zone_id', $permit->zone_id)
            ->whereNull('bound_until')
            ->whereHas('reader', function ($query): void {
                $query->whereIn('device_type', [DeviceType::GasDetector, DeviceType::Co2Sensor]);
            })
            ->pluck('device_id');

        if ($deviceIds->isEmpty()) {
            return [];
        }

        $reading = GasReading::query()
            ->whereIn('device_id', $deviceIds)
            ->orderByDesc('recorded_at')
            ->first();

        if ($reading === null) {
            return [];
        }

        /** @var array<string, float|int|string|null> $suggested */
        $suggested = [];

        foreach ($channels as $channel) {
            $column = $this->channelToReadingColumn($channel->channel_code);
            if ($column === null) {
                continue;
            }

            $value = $reading->{$column};
            if ($value !== null) {
                $suggested[$channel->channel_code] = (float) $value;
            }
        }

        return $suggested;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Permit $permit): array
    {
        $permit = $this->reload($permit);
        $canSeeIdentity = auth()->user()?->can('view-worker-identity') ?? false;

        return [
            'id' => $permit->id,
            'permit_number' => $permit->permit_number,
            'status' => $permit->status->value,
            'status_label' => $permit->status->label(),
            'task_description' => $permit->task_description,
            'is_extended' => $permit->is_extended,
            'renewal_count' => $permit->renewal_count,
            'gas_test_required' => $permit->gas_test_required,
            'valid_from' => optional($permit->valid_from)?->toIso8601String(),
            'valid_to' => optional($permit->valid_to)?->toIso8601String(),
            'issued_at' => optional($permit->issued_at)?->toIso8601String(),
            'closed_at' => optional($permit->closed_at)?->toIso8601String(),
            'close_note' => $permit->close_note,
            'cancel_reason' => $permit->cancel_reason,
            'joint_inspection_at' => optional($permit->joint_inspection_at)?->toIso8601String(),
            'checklist' => $permit->checklist,
            'controls' => $permit->controls,
            'source' => $permit->source,
            'type' => $permit->type === null ? null : [
                'id' => $permit->type->id,
                'code' => $permit->type->code,
                'name' => $permit->type->name,
                'colour_token' => $permit->type->colour_token,
                'sa_form_code' => $permit->type->sa_form_code,
            ],
            'zone' => $permit->zone === null ? null : [
                'id' => $permit->zone->id,
                'name' => $permit->zone->name,
                'requires_permit' => $permit->zone->requires_permit,
            ],
            'work_order_id' => $permit->work_order_id,
            'receiver' => $permit->receiver === null ? null : [
                'id' => $permit->receiver->id,
                'name' => $permit->receiver->name,
            ],
            'issuer' => $permit->issuer === null ? null : [
                'id' => $permit->issuer->id,
                'name' => $permit->issuer->name,
            ],
            'approver' => $permit->approver === null ? null : [
                'id' => $permit->approver->id,
                'name' => $permit->approver->name,
            ],
            'personnel' => $permit->personnel->map(function (PermitPersonnel $row) use ($permit, $canSeeIdentity): array {
                $worker = $row->worker;

                return [
                    'id' => $row->id,
                    'worker_id' => $row->worker_id,
                    'worker_label' => $worker === null
                        ? null
                        : ($canSeeIdentity ? $worker->name : $worker->anonymizedLabel()),
                    'employee_code' => $canSeeIdentity ? $worker?->employee_code : null,
                    'role_code' => $row->role_code,
                    'documents_verified_at' => optional($row->documents_verified_at)?->toIso8601String(),
                    'document_status' => $this->documentStatusForWorker($permit, $row->worker_id, $row->role_code),
                ];
            })->values()->all(),
            'gas_tests' => $permit->gasTests->map(fn (PermitGasTest $test): array => [
                'id' => $test->id,
                'tested_at' => $test->tested_at->toIso8601String(),
                'readings' => $test->readings,
                'result' => $test->result->value,
                'result_label' => $test->result->label(),
                'source' => $test->source->value,
                'source_label' => $test->source->label(),
                'phase' => $test->phase->value,
                'phase_label' => $test->phase->label(),
                'device_id' => $test->device_id,
                'tested_by_name' => $test->tester?->name,
            ])->values()->all(),
            'approvals' => $permit->approvals->map(fn ($approval): array => [
                'id' => $approval->id,
                'action' => $approval->action->value,
                'action_label' => $approval->action->label(),
                'note' => $approval->note,
                'signed_at' => $approval->signed_at->toIso8601String(),
                'user_name' => $approval->user?->name,
            ])->values()->all(),
            'events' => $permit->events->map(fn ($event): array => [
                'id' => $event->id,
                'event' => $event->event,
                'payload' => $event->payload,
                'occurred_at' => $event->occurred_at->toIso8601String(),
                'user_name' => $event->user?->name,
            ])->values()->all(),
        ];
    }

    private function reload(Permit $permit): Permit
    {
        return Permit::query()->with([
            'type.gasChannels',
            'type.checklistItems',
            'type.roles',
            'type.documentRequirements.workerDocumentType',
            'zone',
            'personnel.worker',
            'gasTests.tester',
            'approvals.user',
            'events.user',
            'receiver',
            'issuer',
            'approver',
        ])->findOrFail($permit->id);
    }

    private function assertAllPersonnelDocuments(Permit $permit): void
    {
        foreach ($permit->personnel as $person) {
            $this->assertWorkerDocuments($permit, $person->worker_id, $person->role_code);
            $person->update(['documents_verified_at' => now()]);
        }
    }

    private function assertMandatoryChecklist(Permit $permit): void
    {
        $type = $permit->type ?? PermitType::query()->findOrFail($permit->permit_type_id);

        $items = $type->relationLoaded('checklistItems')
            ? $type->checklistItems->where('is_mandatory', true)->where('is_active', true)
            : $type->checklistItems()
                ->where('is_mandatory', true)
                ->where('is_active', true)
                ->get();

        if ($items->isEmpty()) {
            return;
        }

        /** @var array<string, mixed> $answers */
        $answers = $permit->checklist ?? [];

        /** @var list<string> $missing */
        $missing = [];

        foreach ($items as $item) {
            $value = $answers[$item->code] ?? $answers[(string) $item->id] ?? null;

            if (! $this->checklistItemAnswered($value)) {
                $missing[] = $item->code;
            }
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'checklist' => [
                    'Mandatory checklist items not completed: '.implode(', ', $missing).'.',
                ],
            ]);
        }
    }

    private function assertMandatoryRoles(Permit $permit): void
    {
        $type = $permit->type ?? PermitType::query()->findOrFail($permit->permit_type_id);

        $roles = $type->relationLoaded('roles')
            ? $type->roles->where('is_mandatory', true)
            : $type->roles()->where('is_mandatory', true)->get();

        if ($roles->isEmpty()) {
            return;
        }

        /** @var array<string, int> $counts */
        $counts = [];

        foreach ($permit->personnel as $person) {
            $counts[$person->role_code] = ($counts[$person->role_code] ?? 0) + 1;
        }

        /** @var list<string> $shortfalls */
        $shortfalls = [];

        foreach ($roles as $role) {
            $assigned = $counts[$role->role_code] ?? 0;

            if ($assigned < $role->min_count) {
                $shortfalls[] = $role->role_code.' (need '.$role->min_count.', have '.$assigned.')';
            }
        }

        if ($shortfalls !== []) {
            throw ValidationException::withMessages([
                'personnel' => [
                    'Mandatory crew roles not satisfied: '.implode(', ', $shortfalls).'.',
                ],
            ]);
        }
    }

    private function checklistItemAnswered(mixed $value): bool
    {
        if ($value === true || $value === 1) {
            return true;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    private function statusAfterSubmit(Permit $permit): PermitStatus
    {
        $type = $permit->type ?? PermitType::query()->findOrFail($permit->permit_type_id);

        if ($type->requires_joint_inspection) {
            return PermitStatus::PendingInspection;
        }

        if ($permit->gas_test_required) {
            return PermitStatus::PendingGasTest;
        }

        if ($permit->needsApprover()) {
            return PermitStatus::PendingApproval;
        }

        return PermitStatus::PendingIssue;
    }

    private function nextStatusAfterInspection(Permit $permit): PermitStatus
    {
        if ($permit->gas_test_required) {
            return PermitStatus::PendingGasTest;
        }

        if ($permit->needsApprover()) {
            return PermitStatus::PendingApproval;
        }

        return PermitStatus::PendingIssue;
    }

    /**
     * @param  array<string, float|int|string|null>  $readings
     */
    private function evaluateGasPass(PermitType $type, array $readings): bool
    {
        $channels = $type->relationLoaded('gasChannels')
            ? $type->gasChannels
            : $type->gasChannels()->get();

        if ($channels->isEmpty()) {
            return true;
        }

        foreach ($channels as $channel) {
            if (! $this->channelReadingPasses($channel, $readings)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, float|int|string|null>  $readings
     */
    private function channelReadingPasses(PermitTypeGasChannel $channel, array $readings): bool
    {
        if (! array_key_exists($channel->channel_code, $readings)) {
            return false;
        }

        $value = $readings[$channel->channel_code];
        if ($value === null || ! is_numeric($value)) {
            return false;
        }

        $numeric = (float) $value;

        if ($channel->alarm_below !== null && $numeric < (float) $channel->alarm_below) {
            return false;
        }

        if ($channel->alarm_above !== null && $numeric > (float) $channel->alarm_above) {
            return false;
        }

        return true;
    }

    /**
     * @return array{status: string, missing: list<string>, expiring_soon: list<string>}
     */
    private function documentStatusForWorker(Permit $permit, int $workerId, string $roleCode): array
    {
        $type = $permit->type ?? PermitType::query()->findOrFail($permit->permit_type_id);

        $requirements = $type->documentRequirements()
            ->where('is_mandatory', true)
            ->where(function ($query) use ($roleCode): void {
                $query->whereNull('role_code')
                    ->orWhere('role_code', $roleCode);
            })
            ->with('workerDocumentType')
            ->get();

        /** @var list<string> $missing */
        $missing = [];
        /** @var list<string> $expiringSoon */
        $expiringSoon = [];

        foreach ($requirements as $requirement) {
            $code = $requirement->workerDocumentType?->code ?? (string) $requirement->worker_document_type_id;

            if (! $this->readiness->workerSatisfiesRequirement($workerId, $requirement)) {
                $missing[] = $code;

                continue;
            }

            $document = WorkerDocument::query()
                ->where('worker_id', $workerId)
                ->where('worker_document_type_id', $requirement->worker_document_type_id)
                ->orderByDesc('expires_at')
                ->first();

            if ($document?->expires_at !== null && $document->expires_at->lte(now()->addDays(30))) {
                $expiringSoon[] = $code;
            }
        }

        $status = 'green';
        if ($missing !== []) {
            $status = 'red';
        } elseif ($expiringSoon !== []) {
            $status = 'amber';
        }

        return [
            'status' => $status,
            'missing' => $missing,
            'expiring_soon' => $expiringSoon,
        ];
    }

    private function generateNumber(): string
    {
        $year = now()->format('Y');
        $prefix = 'PTW-'.$year.'-';

        $latest = Permit::query()
            ->withTrashed()
            ->where('permit_number', 'like', $prefix.'%')
            ->orderByDesc('permit_number')
            ->value('permit_number');

        $seq = 1;
        if ($latest !== null && preg_match('/-(\d+)$/', $latest, $matches)) {
            $seq = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordEvent(Permit $permit, string $event, array $payload, ?User $user): void
    {
        $permit->events()->create([
            'event' => $event,
            'payload' => $payload,
            'user_id' => $user?->id,
            'occurred_at' => now(),
        ]);
    }

    private function recordApproval(
        Permit $permit,
        User $user,
        PermitApprovalAction $action,
        ?string $note,
    ): void {
        $permit->approvals()->create([
            'user_id' => $user->id,
            'action' => $action,
            'note' => $note,
            'signed_at' => now(),
        ]);
    }

    private function channelToReadingColumn(string $channelCode): ?string
    {
        return match ($channelCode) {
            'lel_pct' => 'lel_pct',
            'h2s_ppm' => 'h2s_ppm',
            'o2_pct' => 'o2_pct',
            'co_ppm' => 'co_ppm',
            'co2_ppm' => 'co2_ppm',
            default => null,
        };
    }
}
