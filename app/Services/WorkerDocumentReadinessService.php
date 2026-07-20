<?php

namespace App\Services;

use App\Enums\WorkerDocumentVerificationStatus;
use App\Models\PermitType;
use App\Models\PermitTypeDocumentRequirement;
use App\Models\PermitTypeRole;
use App\Models\Worker;
use App\Models\WorkerDocument;
use App\Models\WorkerDocumentType;
use Illuminate\Support\Collection;

/**
 * DOC-22 §4.6 — documents live on the worker; permit assignment only checks readiness.
 */
final class WorkerDocumentReadinessService
{
    /**
     * @return list<array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     category: string|null,
     *     requires_file: bool,
     *     requires_expiry: bool,
     *     status: 'missing'|'pending'|'verified'|'rejected'|'expired',
     *     status_label: string,
     *     document_id: int|null,
     *     expires_at: string|null,
     *     used_by_roles: list<array{permit_type: string, role_code: string|null, role_label: string}>
     * }>
     */
    public function checklist(Worker $worker): array
    {
        $worker->loadMissing(['documents.documentType']);

        $usage = $this->documentTypeUsage();

        $types = WorkerDocumentType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $byTypeId = $worker->documents->groupBy('worker_document_type_id');

        return $types->map(function (WorkerDocumentType $type) use ($byTypeId, $usage): array {
            /** @var Collection<int, WorkerDocument> $docs */
            $docs = $byTypeId->get($type->id, collect());
            $best = $this->bestDocument($docs);
            $status = $this->statusForDocument($best, $type);

            return [
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
                'category' => $type->category,
                'requires_file' => $type->requires_file,
                'requires_expiry' => $type->requires_expiry,
                'status' => $status,
                'status_label' => match ($status) {
                    'verified' => 'Verified',
                    'pending' => 'Pending verification',
                    'rejected' => 'Rejected',
                    'expired' => 'Expired',
                    default => 'Not on file',
                },
                'document_id' => $best?->id,
                'expires_at' => $best?->expires_at?->toDateString(),
                'used_by_roles' => $usage[$type->id] ?? [],
            ];
        })->values()->all();
    }

    /**
     * Role eligibility across active permit types (for worker onboarding / show).
     *
     * @return list<array{
     *     permit_type_id: int,
     *     permit_type_code: string,
     *     permit_type_name: string,
     *     role_code: string,
     *     role_label: string,
     *     is_mandatory: bool,
     *     ready: bool,
     *     missing: list<string>,
     *     missing_labels: list<string>
     * }>
     */
    public function roleMatrix(Worker $worker): array
    {
        $types = PermitType::query()
            ->where('is_active', true)
            ->with([
                'roles' => fn ($query) => $query->orderBy('sort_order'),
                'documentRequirements.workerDocumentType',
            ])
            ->orderBy('sort_order')
            ->get();

        $rows = [];

        foreach ($types as $type) {
            foreach ($type->roles as $role) {
                $assessment = $this->assessRole($worker->id, $type, $role->role_code);
                $rows[] = [
                    'permit_type_id' => $type->id,
                    'permit_type_code' => $type->code,
                    'permit_type_name' => $type->name,
                    'role_code' => $role->role_code,
                    'role_label' => $role->label,
                    'is_mandatory' => $role->is_mandatory,
                    'ready' => $assessment['ready'],
                    'missing' => $assessment['missing'],
                    'missing_labels' => $assessment['missing_labels'],
                ];
            }
        }

        return $rows;
    }

    /**
     * @return array{ready: bool, missing: list<string>, missing_labels: list<string>}
     */
    public function assessRole(int $workerId, PermitType $type, string $roleCode): array
    {
        $requirements = $type->relationLoaded('documentRequirements')
            ? $type->documentRequirements
            : $type->documentRequirements()->with('workerDocumentType')->get();

        $applicable = $requirements
            ->where('is_mandatory', true)
            ->filter(fn (PermitTypeDocumentRequirement $req): bool => $req->role_code === null || $req->role_code === $roleCode);

        /** @var list<string> $missing */
        $missing = [];
        /** @var list<string> $missingLabels */
        $missingLabels = [];

        foreach ($applicable as $requirement) {
            if ($this->workerSatisfiesRequirement($workerId, $requirement)) {
                continue;
            }

            $code = $requirement->workerDocumentType?->code ?? (string) $requirement->worker_document_type_id;
            $missing[] = $code;
            $missingLabels[] = $requirement->workerDocumentType?->name ?? $code;
        }

        return [
            'ready' => $missing === [],
            'missing' => $missing,
            'missing_labels' => $missingLabels,
        ];
    }

    /**
     * Codes that satisfy permit gates (verified + not expired when verification required).
     *
     * @return list<string>
     */
    public function gateSatisfyingCodes(Worker $worker): array
    {
        $worker->loadMissing(['documents.documentType']);

        return $worker->documents
            ->filter(fn (WorkerDocument $document): bool => $document->isVerifiedAndValid()
                && ! ($document->documentType?->requires_file && ($document->file_path === null || $document->file_path === '')))
            ->map(fn (WorkerDocument $document) => $document->documentType?->code)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function workerSatisfiesRequirement(int $workerId, PermitTypeDocumentRequirement $requirement): bool
    {
        $document = WorkerDocument::query()
            ->where('worker_id', $workerId)
            ->where('worker_document_type_id', $requirement->worker_document_type_id)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->with('documentType')
            ->orderByDesc('expires_at')
            ->first();

        if ($document === null) {
            return false;
        }

        if ($document->isExpired()) {
            return false;
        }

        if ($document->documentType?->requires_file && ($document->file_path === null || $document->file_path === '')) {
            return false;
        }

        if ($requirement->must_be_verified) {
            return $document->verification_status === WorkerDocumentVerificationStatus::Verified;
        }

        return ! in_array($document->verification_status, [
            WorkerDocumentVerificationStatus::Rejected,
            WorkerDocumentVerificationStatus::Expired,
        ], true);
    }

    /**
     * Summary counts for onboarding UI.
     *
     * @return array{ready_roles: int, blocked_roles: int, verified_docs: int, pending_docs: int, missing_recommended: int}
     */
    public function summary(Worker $worker): array
    {
        $checklist = $this->checklist($worker);
        $matrix = $this->roleMatrix($worker);

        $recommended = array_values(array_filter(
            $checklist,
            fn (array $row): bool => $row['used_by_roles'] !== [],
        ));

        return [
            'ready_roles' => count(array_filter($matrix, fn (array $row): bool => $row['ready'])),
            'blocked_roles' => count(array_filter($matrix, fn (array $row): bool => ! $row['ready'])),
            'verified_docs' => count(array_filter($checklist, fn (array $row): bool => $row['status'] === 'verified')),
            'pending_docs' => count(array_filter($checklist, fn (array $row): bool => $row['status'] === 'pending')),
            'missing_recommended' => count(array_filter(
                $recommended,
                fn (array $row): bool => in_array($row['status'], ['missing', 'rejected', 'expired'], true),
            )),
        ];
    }

    /**
     * @return array<int, list<array{permit_type: string, role_code: string|null, role_label: string}>>
     */
    private function documentTypeUsage(): array
    {
        $requirements = PermitTypeDocumentRequirement::query()
            ->where('is_mandatory', true)
            ->with([
                'permitType:id,code,name,is_active',
                'workerDocumentType:id,code,name',
            ])
            ->get()
            ->filter(fn (PermitTypeDocumentRequirement $req): bool => $req->permitType?->is_active === true);

        $roleLabels = PermitTypeRole::query()
            ->get(['permit_type_id', 'role_code', 'label'])
            ->keyBy(fn (PermitTypeRole $role): string => $role->permit_type_id.':'.$role->role_code);

        /** @var array<int, list<array{permit_type: string, role_code: string|null, role_label: string}>> $usage */
        $usage = [];

        foreach ($requirements as $requirement) {
            $typeId = (int) $requirement->worker_document_type_id;
            $permitName = $requirement->permitType?->name ?? 'Permit';
            $roleCode = $requirement->role_code;
            $roleLabel = $roleCode === null
                ? 'All crew'
                : ($roleLabels->get($requirement->permit_type_id.':'.$roleCode)?->label ?? $roleCode);

            $usage[$typeId] ??= [];
            $usage[$typeId][] = [
                'permit_type' => $permitName,
                'role_code' => $roleCode,
                'role_label' => $roleLabel,
            ];
        }

        return $usage;
    }

    /**
     * @param  Collection<int, WorkerDocument>  $docs
     */
    private function bestDocument(Collection $docs): ?WorkerDocument
    {
        if ($docs->isEmpty()) {
            return null;
        }

        return $docs
            ->sortByDesc(function (WorkerDocument $document): int {
                return match ($document->verification_status) {
                    WorkerDocumentVerificationStatus::Verified => 40,
                    WorkerDocumentVerificationStatus::Pending => 30,
                    WorkerDocumentVerificationStatus::Rejected => 10,
                    WorkerDocumentVerificationStatus::Expired => 5,
                } + ($document->isExpired() ? -50 : 0);
            })
            ->first();
    }

    private function statusForDocument(?WorkerDocument $document, WorkerDocumentType $type): string
    {
        if ($document === null) {
            return 'missing';
        }

        if ($document->isExpired() || $document->verification_status === WorkerDocumentVerificationStatus::Expired) {
            return 'expired';
        }

        if ($document->verification_status === WorkerDocumentVerificationStatus::Rejected) {
            return 'rejected';
        }

        if ($type->requires_file && ($document->file_path === null || $document->file_path === '')) {
            return 'pending';
        }

        if ($document->verification_status === WorkerDocumentVerificationStatus::Verified) {
            return 'verified';
        }

        return 'pending';
    }
}
