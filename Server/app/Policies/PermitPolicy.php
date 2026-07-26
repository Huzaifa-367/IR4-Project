<?php

namespace App\Policies;

use App\Enums\PermitStatus;
use App\Models\Permit;
use App\Models\User;

final class PermitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-permits');
    }

    public function view(User $user, Permit $permit): bool
    {
        return $user->can('view-permits');
    }

    public function create(User $user): bool
    {
        return $user->can('create-permits');
    }

    public function update(User $user, Permit $permit): bool
    {
        if (! $user->can('create-permits')) {
            return false;
        }

        return in_array($permit->status, [PermitStatus::Draft, PermitStatus::Rejected], true);
    }

    public function issue(User $user, Permit $permit): bool
    {
        return $user->can('update-permits');
    }

    public function suspend(User $user, Permit $permit): bool
    {
        return $user->can('update-permits');
    }

    public function resume(User $user, Permit $permit): bool
    {
        return $user->can('update-permits');
    }

    public function cancel(User $user, Permit $permit): bool
    {
        return $user->can('update-permits');
    }

    public function close(User $user, Permit $permit): bool
    {
        return $user->can('update-permits');
    }

    public function reject(User $user, Permit $permit): bool
    {
        return $user->can('update-permits');
    }

    public function inspect(User $user, Permit $permit): bool
    {
        // Issuer and receiver both co-sign the joint site inspection (GI 2.100).
        return $user->can('update-permits') || $user->can('create-permits');
    }

    public function approve(User $user, Permit $permit): bool
    {
        return $user->can('update-permits');
    }

    public function gasTest(User $user, Permit $permit): bool
    {
        return $user->can('create-permit-gas-tests');
    }
}
