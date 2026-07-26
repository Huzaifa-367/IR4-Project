<?php

namespace App\Policies;

use App\Models\Camera;
use App\Models\User;

final class CameraPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-devices');
    }

    public function view(User $user, Camera $camera): bool
    {
        return $user->can('view-devices');
    }

    public function create(User $user): bool
    {
        return $user->can('create-devices');
    }

    public function update(User $user, Camera $camera): bool
    {
        return $user->can('update-devices');
    }
}
