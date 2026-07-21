<?php

namespace App\Policies;

use App\Models\LsrViolation;
use App\Models\User;

final class LsrViolationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-lsr');
    }

    public function view(User $user, LsrViolation $lsr): bool
    {
        return $user->can('view-lsr');
    }

    public function create(User $user): bool
    {
        return $user->can('create-lsr');
    }

    public function close(User $user, ?LsrViolation $lsr = null): bool
    {
        return $user->can('update-lsr');
    }
}
