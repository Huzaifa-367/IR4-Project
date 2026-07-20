<?php

namespace App\Http\Controllers\Web\Permit;

use App\Http\Controllers\Web\BaseController;
use App\Models\PermitType;
use App\Models\PermitTypeRole;
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
        ]);

        return Inertia::render('workforce/permit-types/show', [
            'permitType' => [
                'id' => $permitType->id,
                'code' => $permitType->code,
                'name' => $permitType->name,
                'description' => $permitType->description,
                'colour_token' => $permitType->colour_token,
                'sa_form_code' => $permitType->sa_form_code,
                'requires_gas_test' => $permitType->requires_gas_test,
                'requires_approver' => $permitType->requires_approver,
                'requires_joint_inspection' => $permitType->requires_joint_inspection,
                'default_validity_minutes' => $permitType->default_validity_minutes,
                'is_active' => $permitType->is_active,
                'roles' => $permitType->roles->map(fn (PermitTypeRole $role): array => [
                    'id' => $role->id,
                    'role_code' => $role->role_code,
                    'label' => $role->label,
                    'min_count' => $role->min_count,
                    'is_mandatory' => $role->is_mandatory,
                    'sort_order' => $role->sort_order,
                ])->values()->all(),
            ],
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
            'default_validity_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

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
            'default_validity_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $permitType->update($validated);

        return back()->with('flash', ['success' => 'Permit type updated.']);
    }
}
