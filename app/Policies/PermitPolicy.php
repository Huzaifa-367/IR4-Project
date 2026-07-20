<?php

namespace App\Policies;

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
        return $user->can('request-permit');
    }

    public function update(User $user, Permit $permit): bool
    {
        return $user->can('request-permit');
    }

    public function issue(User $user, Permit $permit): bool
    {
        return $user->can('issue-permit');
    }

    public function suspend(User $user, Permit $permit): bool
    {
        return $user->can('issue-permit');
    }

    public function resume(User $user, Permit $permit): bool
    {
        return $user->can('issue-permit');
    }

    public function cancel(User $user, Permit $permit): bool
    {
        return $user->can('issue-permit');
    }

    public function close(User $user, Permit $permit): bool
    {
        return $user->can('issue-permit');
    }

    public function reject(User $user, Permit $permit): bool
    {
        return $user->can('issue-permit');
    }

    public function inspect(User $user, Permit $permit): bool
    {
        return $user->can('issue-permit');
    }

    public function approve(User $user, Permit $permit): bool
    {
        return $user->can('approve-permit');
    }

    public function gasTest(User $user, Permit $permit): bool
    {
        return $user->can('perform-gas-test');
    }
}
