<?php

namespace App\Policies;

use App\Models\Alert;
use App\Models\User;

final class AlertPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Alert $alert): bool
    {
        return true;
    }

    public function acknowledge(User $user, Alert $alert): bool
    {
        return $user->can('acknowledge-alerts');
    }

    public function resolve(User $user, Alert $alert): bool
    {
        return $user->can('resolve-alerts');
    }
}
