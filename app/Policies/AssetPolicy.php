<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

final class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-devices');
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->can('manage-devices');
    }

    public function create(User $user): bool
    {
        return $user->can('manage-devices');
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->can('manage-devices');
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->can('manage-devices');
    }
}
