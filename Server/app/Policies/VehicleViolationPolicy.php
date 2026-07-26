<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleViolation;

final class VehicleViolationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-vehicle-violations') || $user->can('view-reports');
    }

    public function create(User $user): bool
    {
        return $user->can('create-vehicle-violations');
    }

    public function delete(User $user, VehicleViolation $vehicleViolation): bool
    {
        return $user->can('delete-vehicle-violations');
    }
}
