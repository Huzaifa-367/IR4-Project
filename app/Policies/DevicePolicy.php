<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

final class DevicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-devices');
    }

    public function view(User $user, Device $device): bool
    {
        return $user->can('manage-devices');
    }

    public function create(User $user): bool
    {
        return $user->can('manage-devices');
    }

    public function update(User $user, Device $device): bool
    {
        return $user->can('manage-devices');
    }

    public function delete(User $user, Device $device): bool
    {
        return $user->can('manage-devices');
    }
}
