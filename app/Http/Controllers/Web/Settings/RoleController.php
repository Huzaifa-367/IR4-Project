<?php

namespace App\Http\Controllers\Web\Settings;

use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Settings\StoreRoleRequest;
use App\Http\Requests\Settings\UpdateRoleRequest;
use App\Models\Role;
use App\Services\RoleService;
use App\Support\PermissionCatalogue;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class RoleController extends BaseController
{
    public function index(): Response
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()
            ->where('guard_name', PermissionCatalogue::GUARD)
            ->withCount('users')
            ->with('permissions:id,name')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'is_read_only' => $role->is_read_only,
                'users_count' => $role->users_count,
                'permissions' => $role->permissions->pluck('name')->values()->all(),
            ]);

        return Inertia::render('settings/roles/index', [
            'roles' => $roles,
            'catalogue' => PermissionCatalogue::grouped(),
        ]);
    }

    public function store(StoreRoleRequest $request, RoleService $roles): RedirectResponse
    {
        $roles->create($request->validated());

        return redirect()->route('settings.roles.index');
    }

    public function update(UpdateRoleRequest $request, Role $role, RoleService $roles): RedirectResponse
    {
        $this->authorize('update', $role);

        $data = $request->validated();

        $role->forceFill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_read_only' => (bool) ($data['is_read_only'] ?? false),
        ])->save();

        $roles->syncPermissions($role, $data['permissions'] ?? []);

        return redirect()->route('settings.roles.index');
    }

    public function destroy(Role $role, RoleService $roles): RedirectResponse
    {
        $this->authorize('delete', $role);
        $roles->delete($role);

        return redirect()->route('settings.roles.index');
    }
}
