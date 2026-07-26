<?php

namespace App\Policies;

use App\Models\PpeViolation;
use App\Models\User;

final class PpeViolationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-ppe');
    }

    public function view(User $user, PpeViolation $ppeViolation): bool
    {
        return $user->can('view-ppe');
    }

    public function review(User $user, PpeViolation $ppeViolation): bool
    {
        return $user->can('update-ppe-violations');
    }

    public function export(User $user): bool
    {
        return $user->can('export-ppe-violations');
    }
}
