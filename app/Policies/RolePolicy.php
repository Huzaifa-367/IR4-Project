<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

final class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-roles');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('view-roles');
    }

    public function create(User $user): bool
    {
        return $user->can('create-roles');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('update-roles') && ! $role->is_system;
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('delete-roles') && ! $role->is_system;
    }
}
