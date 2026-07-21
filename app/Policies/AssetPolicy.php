<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

final class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-devices');
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->can('view-devices');
    }

    public function create(User $user): bool
    {
        return $user->can('create-devices');
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->can('update-devices');
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->can('delete-devices');
    }
}
