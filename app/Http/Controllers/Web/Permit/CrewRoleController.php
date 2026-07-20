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

final class CrewRoleController extends BaseController
{
    public function index(): InertiaResponse
    {
        $roles = PermitTypeRole::query()
            ->with('permitType:id,code,name,is_active')
            ->orderBy('permit_type_id')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->map(fn (PermitTypeRole $role): array => $this->toArray($role));

        $permitTypes = PermitType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (PermitType $type): array => [
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
            ]);

        return Inertia::render('access/crew-roles/index', [
            'roles' => $roles->values()->all(),
            'permitTypes' => $permitTypes->values()->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        PermitTypeRole::query()->create($validated);

        return back()->with('flash', ['success' => 'Crew role created.']);
    }

    public function update(Request $request, PermitTypeRole $crewRole): RedirectResponse
    {
        $validated = $this->validated($request, $crewRole);

        $crewRole->update($validated);

        return back()->with('flash', ['success' => 'Crew role updated.']);
    }

    public function destroy(PermitTypeRole $crewRole): RedirectResponse
    {
        $crewRole->delete();

        return back()->with('flash', ['success' => 'Crew role removed.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?PermitTypeRole $existing = null): array
    {
        $permitTypeId = (int) $request->input('permit_type_id', $existing?->permit_type_id);

        $validated = $request->validate([
            'permit_type_id' => [
                $existing === null ? 'required' : 'sometimes',
                'integer',
                Rule::exists('permit_types', 'id'),
            ],
            'role_code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('permit_type_roles', 'role_code')
                    ->where(fn ($query) => $query->where('permit_type_id', $permitTypeId))
                    ->ignore($existing?->id),
            ],
            'label' => ['required', 'string', 'max:255'],
            'min_count' => ['required', 'integer', 'min:0', 'max:50'],
            'is_mandatory' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ]);

        $validated['is_mandatory'] = $request->boolean('is_mandatory', true);
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(PermitTypeRole $role): array
    {
        return [
            'id' => $role->id,
            'permit_type_id' => $role->permit_type_id,
            'role_code' => $role->role_code,
            'label' => $role->label,
            'min_count' => $role->min_count,
            'is_mandatory' => $role->is_mandatory,
            'sort_order' => $role->sort_order,
            'permit_type' => $role->permitType === null ? null : [
                'id' => $role->permitType->id,
                'code' => $role->permitType->code,
                'name' => $role->permitType->name,
                'is_active' => $role->permitType->is_active,
            ],
        ];
    }
}
