<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Role;
use App\Support\PermissionCatalogue;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class RoleService
{
    public function __construct(
        private readonly PermissionRegistrar $registrar,
    ) {}

    /**
     * @param  list<string>  $permissionNames
     */
    public function syncPermissions(Role $role, array $permissionNames): Role
    {
        if ($role->is_system) {
            throw new HttpException(403, 'The Super Admin role cannot be edited.');
        }

        $permissionNames = array_values(array_unique($permissionNames));

        if ($role->is_read_only) {
            $viewOnly = PermissionCatalogue::viewOnly();
            $invalid = array_values(array_diff($permissionNames, $viewOnly));

            if ($invalid !== []) {
                throw ValidationException::withMessages([
                    'permissions' => 'Read-only roles may only hold view-* permissions.',
                ]);
            }
        }

        $before = $role->permissions()->pluck('name')->sort()->values()->all();

        $permissions = Permission::query()
            ->where('guard_name', PermissionCatalogue::GUARD)
            ->whereIn('name', $permissionNames)
            ->get();

        $role->syncPermissions($permissions);
        $this->registrar->forgetCachedPermissions();

        $after = $role->fresh()?->permissions()->pluck('name')->sort()->values()->all() ?? [];

        AuditLog::query()->create([
            'event_type' => 'config_changed',
            'user_id' => auth()->id(),
            'route' => request()->path(),
            'payload' => [
                'target' => 'role_permissions',
                'role_id' => $role->id,
                'role_name' => $role->name,
                'before' => $before,
                'after' => $after,
            ],
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);

        return $role->fresh() ?? $role;
    }

    /**
     * @param  array{name: string, description?: string|null, is_read_only?: bool, permissions?: list<string>}  $data
     */
    public function create(array $data): Role
    {
        return DB::transaction(function () use ($data): Role {
            $role = Role::query()->create([
                'name' => $data['name'],
                'guard_name' => PermissionCatalogue::GUARD,
                'description' => $data['description'] ?? null,
                'is_system' => false,
                'is_read_only' => (bool) ($data['is_read_only'] ?? false),
            ]);

            $this->syncPermissions($role, $data['permissions'] ?? []);

            return $role->fresh() ?? $role;
        });
    }

    /**
     * @param  array{name: string, description?: string|null, is_read_only?: bool, permissions?: list<string>}  $data
     */
    public function update(Role $role, array $data): Role
    {
        if ($role->is_system) {
            throw new HttpException(403, 'The Super Admin role cannot be edited.');
        }

        return DB::transaction(function () use ($role, $data): Role {
            $role->forceFill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_read_only' => (bool) ($data['is_read_only'] ?? false),
            ])->save();

            $this->syncPermissions($role, $data['permissions'] ?? []);

            return $role->fresh() ?? $role;
        });
    }

    public function delete(Role $role): void
    {
        if ($role->is_system) {
            throw new HttpException(403, 'The Super Admin role cannot be deleted.');
        }

        if ($role->users()->exists()) {
            throw new HttpException(409, 'Reassign users before deleting this role.');
        }

        $role->delete();
        $this->registrar->forgetCachedPermissions();
    }
}
