<?php

namespace App\Policies;

use App\Enums\CameraType;
use App\Models\Device;
use App\Models\User;

final class DevicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-devices');
    }

    public function view(User $user, Device $device): bool
    {
        return $user->can('view-devices');
    }

    public function create(User $user): bool
    {
        return $user->can('create-devices');
    }

    public function update(User $user, Device $device): bool
    {
        return $user->can('update-devices');
    }

    public function delete(User $user, Device $device): bool
    {
        return $user->can('delete-devices');
    }

    public function controlPtz(User $user, Device $device): bool
    {
        return $user->can('control-ptz-cameras')
            && $device->isCamera()
            && $device->camera_type === CameraType::Ptz
            && $device->status->isOperational();
    }
}
