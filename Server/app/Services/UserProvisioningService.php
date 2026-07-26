<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalogue;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class UserProvisioningService
{
    public function __construct(
        private readonly AuthLockoutService $lockout,
    ) {}

    /**
     * @param  array{name: string, email: string, password?: string, role: string}  $data
     * @return array{user: User, temporary_password: string|null}
     */
    public function create(array $data): array
    {
        $role = $this->resolveRole($data['role']);
        $plain = $data['password'] ?? Str::password(16);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($plain),
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $user->syncRoles([$role]);

        AuditLog::query()->create([
            'event_type' => 'config_changed',
            'user_id' => auth()->id(),
            'route' => request()->path(),
            'payload' => [
                'target' => 'user_created',
                'user_id' => $user->id,
                'role' => $role->name,
            ],
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);

        return [
            'user' => $user,
            'temporary_password' => $data['password'] ?? $plain,
        ];
    }

    public function assignRole(User $user, Role $role): void
    {
        $this->guardLastSuperAdmin($user, leavingSuperAdmin: $this->holdsSuperAdmin($user) && ! $role->isSuperAdmin());

        $before = $user->getRoleNames()->first();
        $user->syncRoles([$role]);

        AuditLog::query()->create([
            'event_type' => 'config_changed',
            'user_id' => auth()->id(),
            'route' => request()->path(),
            'payload' => [
                'target' => 'user_role',
                'user_id' => $user->id,
                'before' => $before,
                'after' => $role->name,
            ],
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    public function deactivate(User $user): void
    {
        if (auth()->id() === $user->id) {
            throw new HttpException(409, 'You cannot deactivate yourself.');
        }

        $this->guardLastSuperAdmin($user, leavingSuperAdmin: $this->holdsSuperAdmin($user));

        $user->forceFill(['is_active' => false])->save();
    }

    public function resetPassword(User $user): string
    {
        return $this->lockout->resetPassword($user);
    }

    private function resolveRole(string $name): Role
    {
        /** @var Role $role */
        $role = Role::query()
            ->where('name', $name)
            ->where('guard_name', PermissionCatalogue::GUARD)
            ->firstOrFail();

        return $role;
    }

    private function holdsSuperAdmin(User $user): bool
    {
        return $user->roles()
            ->where('name', 'Super Admin')
            ->where('is_system', true)
            ->exists();
    }

    private function guardLastSuperAdmin(User $user, bool $leavingSuperAdmin): void
    {
        if (! $leavingSuperAdmin) {
            return;
        }

        $otherActiveSuperAdmins = User::query()
            ->where('id', '!=', $user->id)
            ->where('is_active', true)
            ->whereHas('roles', function ($query): void {
                $query->where('name', 'Super Admin')->where('is_system', true);
            })
            ->exists();

        if (! $otherActiveSuperAdmins) {
            throw new HttpException(409, 'At least one active Super Admin must remain.');
        }
    }
}
