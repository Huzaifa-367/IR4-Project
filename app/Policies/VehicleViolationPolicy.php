<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleViolation;

final class VehicleViolationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('log-vehicle-violations') || $user->can('view-reports');
    }

    public function create(User $user): bool
    {
        return $user->can('log-vehicle-violations');
    }

    public function delete(User $user, VehicleViolation $violation): bool
    {
        return $user->can('log-vehicle-violations');
    }
}
