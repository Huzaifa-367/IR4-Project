<?php

namespace App\Policies;

use App\Models\Camera;
use App\Models\User;

final class CameraPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-devices');
    }

    public function create(User $user): bool
    {
        return $user->can('manage-devices');
    }

    public function update(User $user, Camera $camera): bool
    {
        return $user->can('manage-devices');
    }
}
