<?php

namespace App\Policies;

use App\Models\RoiViolation;
use App\Models\User;

final class RoiViolationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-roi-violations');
    }

    public function view(User $user, RoiViolation $roiViolation): bool
    {
        return $user->can('view-roi-violations');
    }

    public function review(User $user, RoiViolation $roiViolation): bool
    {
        return $user->can('update-roi-violations');
    }
}
