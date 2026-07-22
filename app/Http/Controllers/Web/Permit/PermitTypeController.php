<?php

namespace App\Http\Controllers\Web\Permit;

use App\Http\Controllers\Web\BaseController;
use App\Models\PermitType;
use App\Models\PermitTypeChecklistItem;
use App\Models\PermitTypeConflict;
use App\Models\PermitTypeDocumentRequirement;
use App\Models\PermitTypeGasChannel;
use App\Models\PermitTypeRole;
use App\Models\WorkerDocumentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class PermitTypeController extends BaseController
{
    public function index(): InertiaResponse
    {
        $types = PermitType::query()
            ->withCount(['roles', 'gasChannels', 'checklistItems', 'documentRequirements'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PermitType $type): array => [
                'id' => $type->id,
                'uuid' => $type->uuid,
                'code' => $type->code,
                'name' => $type->name,
                'description' => $type->description,
                'colour_token' => $type->colour_token,
                'sa_form_code' => $type->sa_form_code,
                'requires_gas_test' => $type->requires_gas_test,
                'requires_approver' => $type->requires_approver,
                'requires_joint_inspection' => $type->requires_joint_inspection,
                'default_validity_minutes' => $type->default_validity_minutes,
                'is_active' => $type->is_active,
                'roles_count' => $type->roles_count,
                'gas_channels_count' => $type->gas_channels_count,
                'checklist_items_count' => $type->checklist_items_count,
                'document_requirements_count' => $type->document_requirements_count,
            ]);

        return Inertia::render('workforce/permit-types/index', [
            'permitTypes' => $types->values()->all(),
        ]);
    }

    public function show(PermitType $permitType): InertiaResponse
    {
        $permitType->load([
            'roles' => fn ($query) => $query->orderBy('sort_order')->orderBy('label'),
            'checklistItems' => fn ($query) => $query->orderBy('sort_order')->orderBy('label'),
            'gasChannels' => fn ($query) => $query->orderBy('sort_order')->orderBy('label'),
            'conflicts.conflictsWithType:id,uuid,code,name',
            'documentRequirements.workerDocumentType:id,uuid,code,name',
        ]);

        $otherTypes = PermitType::query()
            ->whereKeyNot($permitType->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'uuid', 'code', 'name']);

        $documentTypes = WorkerDocumentType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'uuid', 'code', 'name']);

        return Inertia::render('workforce/permit-types/show', [
            'permitType' => [
                'id' => $permitType->id,
                'uuid' => $permitType->uuid,
                'code' => $permitType->code,
                'name' => $permitType->name,
                'description' => $permitType->description,
                'colour_token' => $permitType->colour_token,
                'sa_form_code' => $permitType->sa_form_code,
                'requires_gas_test' => $permitType->requires_gas_test,
                'requires_approver' => $permitType->requires_approver,
                'requires_joint_inspection' => $permitType->requires_joint_inspection,
                'default_validity_minutes' => $permitType->default_validity_minutes,
                'max_renewals' => $permitType->max_renewals,
                'max_total_minutes' => $permitType->max_total_minutes,
                'allows_extended' => $permitType->allows_extended,
                'retest_interval_minutes' => $permitType->retest_interval_minutes,
                'sort_order' => $permitType->sort_order,
                'is_active' => $permitType->is_active,
                'roles' => $permitType->roles->map(fn (PermitTypeRole $role): array => [
                    'id' => $role->id,
                    'uuid' => $role->uuid,
                    'role_code' => $role->role_code,
                    'label' => $role->label,
                    'min_count' => $role->min_count,
                    'is_mandatory' => $role->is_mandatory,
                    'sort_order' => $role->sort_order,
                ])->values()->all(),
                'checklist_items' => $permitType->checklistItems->map(fn (PermitTypeChecklistItem $item): array => [
                    'id' => $item->id,
                    'uuid' => $item->uuid,
                    'code' => $item->code,
                    'label' => $item->label,
                    'is_mandatory' => $item->is_mandatory,
                    'is_active' => $item->is_active,
                    'sort_order' => $item->sort_order,
                ])->values()->all(),
                'gas_channels' => $permitType->gasChannels->map(fn (PermitTypeGasChannel $channel): array => [
                    'id' => $channel->id,
                    'uuid' => $channel->uuid,
                    'channel_code' => $channel->channel_code,
                    'label' => $channel->label,
                    'unit' => $channel->unit,
                    'warn_below' => $channel->warn_below !== null ? (float) $channel->warn_below : null,
                    'warn_above' => $channel->warn_above !== null ? (float) $channel->warn_above : null,
                    'alarm_below' => $channel->alarm_below !== null ? (float) $channel->alarm_below : null,
                    'alarm_above' => $channel->alarm_above !== null ? (float) $channel->alarm_above : null,
                    'sort_order' => $channel->sort_order,
                ])->values()->all(),
                'conflicts' => $permitType->conflicts->map(fn (PermitTypeConflict $conflict): array => [
                    'id' => $conflict->id,
                    'uuid' => $conflict->uuid,
                    'conflicts_with_type_id' => $conflict->conflicts_with_type_id,
                    'conflicts_with' => $conflict->conflictsWithType === null ? null : [
                        'id' => $conflict->conflictsWithType->id,
                        'uuid' => $conflict->conflictsWithType->uuid,
                        'code' => $conflict->conflictsWithType->code,
                        'name' => $conflict->conflictsWithType->name,
                    ],
                    'scope' => $conflict->scope,
                    'severity' => $conflict->severity,
                    'note' => $conflict->note,
                ])->values()->all(),
                'document_requirements' => $permitType->documentRequirements->map(fn (PermitTypeDocumentRequirement $req): array => [
                    'id' => $req->id,
                    'uuid' => $req->uuid,
                    'worker_document_type_id' => $req->worker_document_type_id,
                    'role_code' => $req->role_code,
                    'is_mandatory' => $req->is_mandatory,
                    'must_be_verified' => $req->must_be_verified,
                    'worker_document_type' => $req->workerDocumentType === null ? null : [
                        'id' => $req->workerDocumentType->id,
                        'uuid' => $req->workerDocumentType->uuid,
                        'code' => $req->workerDocumentType->code,
                        'name' => $req->workerDocumentType->name,
                    ],
                ])->values()->all(),
            ],
            'otherTypes' => $otherTypes->map(fn (PermitType $type): array => [
                'id' => $type->id,
                'uuid' => $type->uuid,
                'code' => $type->code,
                'name' => $type->name,
            ])->values()->all(),
            'documentTypes' => $documentTypes->map(fn (WorkerDocumentType $type): array => [
                'id' => $type->id,
                'uuid' => $type->uuid,
                'code' => $type->code,
                'name' => $type->name,
            ])->values()->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64', Rule::unique('permit_types', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'colour_token' => ['nullable', 'string', 'max:32'],
            'sa_form_code' => ['nullable', 'string', 'max:32'],
            'requires_gas_test' => ['sometimes', 'boolean'],
            'requires_approver' => ['sometimes', 'boolean'],
            'requires_joint_inspection' => ['sometimes', 'boolean'],
            'default_validity_minutes' => ['sometimes', 'integer', 'min:1', 'max:43200'],
            'max_renewals' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'max_total_minutes' => ['sometimes', 'integer', 'min:1', 'max:43200'],
            'allows_extended' => ['sometimes', 'boolean'],
            'retest_interval_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['requires_gas_test'] = $request->boolean('requires_gas_test');
        $validated['requires_approver'] = $request->boolean('requires_approver');
        $validated['requires_joint_inspection'] = $request->boolean(
            'requires_joint_inspection',
            true,
        );
        $validated['allows_extended'] = $request->boolean('allows_extended');
        $validated['is_active'] = $request->boolean('is_active', true);

        PermitType::query()->create($validated);

        return back()->with('flash', ['success' => 'Permit type created.']);
    }

    public function update(Request $request, PermitType $permitType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'colour_token' => ['nullable', 'string', 'max:32'],
            'sa_form_code' => ['nullable', 'string', 'max:32'],
            'requires_gas_test' => ['sometimes', 'boolean'],
            'requires_approver' => ['sometimes', 'boolean'],
            'requires_joint_inspection' => ['sometimes', 'boolean'],
            'default_validity_minutes' => ['sometimes', 'integer', 'min:1', 'max:43200'],
            'max_renewals' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'max_total_minutes' => ['sometimes', 'integer', 'min:1', 'max:43200'],
            'allows_extended' => ['sometimes', 'boolean'],
            'retest_interval_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        foreach ([
            'requires_gas_test',
            'requires_approver',
            'requires_joint_inspection',
            'allows_extended',
            'is_active',
        ] as $flag) {
            if ($request->has($flag)) {
                $validated[$flag] = $request->boolean($flag);
            }
        }

        $permitType->update($validated);

        return back()->with('flash', ['success' => 'Permit type updated.']);
    }

    public function storeChecklistItem(Request $request, PermitType $permitType): RedirectResponse
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('permit_type_checklist_items', 'code')
                    ->where(fn ($query) => $query->where('permit_type_id', $permitType->id)),
            ],
            'label' => ['required', 'string', 'max:255'],
            'is_mandatory' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ]);

        $permitType->checklistItems()->create([
            ...$validated,
            'is_mandatory' => $request->boolean('is_mandatory', true),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        return back()->with('flash', ['success' => 'Checklist item added.']);
    }

    public function updateChecklistItem(
        Request $request,
        PermitType $permitType,
        PermitTypeChecklistItem $checklistItem,
    ): RedirectResponse {
        abort_unless($checklistItem->permit_type_id === $permitType->id, 404);

        $validated = $request->validate([
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:64',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('permit_type_checklist_items', 'code')
                    ->where(fn ($query) => $query->where('permit_type_id', $permitType->id))
                    ->ignore($checklistItem->id),
            ],
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'is_mandatory' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ]);

        foreach (['is_mandatory', 'is_active'] as $flag) {
            if ($request->has($flag)) {
                $validated[$flag] = $request->boolean($flag);
            }
        }

        $checklistItem->update($validated);

        return back()->with('flash', ['success' => 'Checklist item updated.']);
    }

    public function destroyChecklistItem(
        PermitType $permitType,
        PermitTypeChecklistItem $checklistItem,
    ): RedirectResponse {
        abort_unless($checklistItem->permit_type_id === $permitType->id, 404);
        $checklistItem->delete();

        return back()->with('flash', ['success' => 'Checklist item removed.']);
    }

    public function storeGasChannel(Request $request, PermitType $permitType): RedirectResponse
    {
        $validated = $request->validate([
            'channel_code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('permit_type_gas_channels', 'channel_code')
                    ->where(fn ($query) => $query->where('permit_type_id', $permitType->id)),
            ],
            'label' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:32'],
            'warn_below' => ['nullable', 'numeric'],
            'warn_above' => ['nullable', 'numeric'],
            'alarm_below' => ['nullable', 'numeric'],
            'alarm_above' => ['nullable', 'numeric'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ]);

        $permitType->gasChannels()->create([
            ...$validated,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        return back()->with('flash', ['success' => 'Gas channel added.']);
    }

    public function updateGasChannel(
        Request $request,
        PermitType $permitType,
        PermitTypeGasChannel $gasChannel,
    ): RedirectResponse {
        abort_unless($gasChannel->permit_type_id === $permitType->id, 404);

        $validated = $request->validate([
            'channel_code' => [
                'sometimes',
                'required',
                'string',
                'max:64',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('permit_type_gas_channels', 'channel_code')
                    ->where(fn ($query) => $query->where('permit_type_id', $permitType->id))
                    ->ignore($gasChannel->id),
            ],
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:32'],
            'warn_below' => ['nullable', 'numeric'],
            'warn_above' => ['nullable', 'numeric'],
            'alarm_below' => ['nullable', 'numeric'],
            'alarm_above' => ['nullable', 'numeric'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ]);

        $gasChannel->update($validated);

        return back()->with('flash', ['success' => 'Gas channel updated.']);
    }

    public function destroyGasChannel(
        PermitType $permitType,
        PermitTypeGasChannel $gasChannel,
    ): RedirectResponse {
        abort_unless($gasChannel->permit_type_id === $permitType->id, 404);
        $gasChannel->delete();

        return back()->with('flash', ['success' => 'Gas channel removed.']);
    }

    public function storeConflict(Request $request, PermitType $permitType): RedirectResponse
    {
        $validated = $request->validate([
            'conflicts_with_type_id' => [
                'required',
                'integer',
                Rule::exists('permit_types', 'id'),
                Rule::notIn([$permitType->id]),
            ],
            'scope' => ['required', 'string', Rule::in(['same_zone', 'adjacent_zone'])],
            'severity' => ['required', 'string', Rule::in(['block', 'warn'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $exists = PermitTypeConflict::query()
            ->where('permit_type_id', $permitType->id)
            ->where('conflicts_with_type_id', $validated['conflicts_with_type_id'])
            ->where('scope', $validated['scope'])
            ->exists();

        abort_if($exists, 409, 'That SIMOPS conflict already exists for this scope.');

        $permitType->conflicts()->create($validated);

        return back()->with('flash', ['success' => 'SIMOPS conflict added.']);
    }

    public function updateConflict(
        Request $request,
        PermitType $permitType,
        PermitTypeConflict $conflict,
    ): RedirectResponse {
        abort_unless($conflict->permit_type_id === $permitType->id, 404);

        $validated = $request->validate([
            'conflicts_with_type_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('permit_types', 'id'),
                Rule::notIn([$permitType->id]),
            ],
            'scope' => ['sometimes', 'required', 'string', Rule::in(['same_zone', 'adjacent_zone'])],
            'severity' => ['sometimes', 'required', 'string', Rule::in(['block', 'warn'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $conflict->update($validated);

        return back()->with('flash', ['success' => 'SIMOPS conflict updated.']);
    }

    public function destroyConflict(
        PermitType $permitType,
        PermitTypeConflict $conflict,
    ): RedirectResponse {
        abort_unless($conflict->permit_type_id === $permitType->id, 404);
        $conflict->delete();

        return back()->with('flash', ['success' => 'SIMOPS conflict removed.']);
    }

    public function storeDocumentRequirement(
        Request $request,
        PermitType $permitType,
    ): RedirectResponse {
        $validated = $request->validate([
            'worker_document_type_id' => [
                'required',
                'integer',
                Rule::exists('worker_document_types', 'id'),
            ],
            'role_code' => ['nullable', 'string', 'max:64'],
            'is_mandatory' => ['sometimes', 'boolean'],
            'must_be_verified' => ['sometimes', 'boolean'],
        ]);

        $permitType->documentRequirements()->create([
            ...$validated,
            'role_code' => $validated['role_code'] !== null && $validated['role_code'] !== ''
                ? $validated['role_code']
                : null,
            'is_mandatory' => $request->boolean('is_mandatory', true),
            'must_be_verified' => $request->boolean('must_be_verified', true),
        ]);

        return back()->with('flash', ['success' => 'Document requirement added.']);
    }

    public function updateDocumentRequirement(
        Request $request,
        PermitType $permitType,
        PermitTypeDocumentRequirement $documentRequirement,
    ): RedirectResponse {
        abort_unless($documentRequirement->permit_type_id === $permitType->id, 404);

        $validated = $request->validate([
            'worker_document_type_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('worker_document_types', 'id'),
            ],
            'role_code' => ['nullable', 'string', 'max:64'],
            'is_mandatory' => ['sometimes', 'boolean'],
            'must_be_verified' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('role_code', $validated) && ($validated['role_code'] === '' || $validated['role_code'] === null)) {
            $validated['role_code'] = null;
        }

        foreach (['is_mandatory', 'must_be_verified'] as $flag) {
            if ($request->has($flag)) {
                $validated[$flag] = $request->boolean($flag);
            }
        }

        $documentRequirement->update($validated);

        return back()->with('flash', ['success' => 'Document requirement updated.']);
    }

    public function destroyDocumentRequirement(
        PermitType $permitType,
        PermitTypeDocumentRequirement $documentRequirement,
    ): RedirectResponse {
        abort_unless($documentRequirement->permit_type_id === $permitType->id, 404);
        $documentRequirement->delete();

        return back()->with('flash', ['success' => 'Document requirement removed.']);
    }
}
