<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Zone;

final class ZonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-zones');
    }

    public function view(User $user, Zone $zone): bool
    {
        return $user->can('manage-zones');
    }

    public function create(User $user): bool
    {
        return $user->can('manage-zones');
    }

    public function update(User $user, Zone $zone): bool
    {
        return $user->can('manage-zones');
    }

    public function delete(User $user, Zone $zone): bool
    {
        return $user->can('manage-zones');
    }
}
