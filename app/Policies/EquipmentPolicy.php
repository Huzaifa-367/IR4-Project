<?php

namespace App\Policies;

use App\Models\Equipment;
use App\Models\User;

final class EquipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-equipment');
    }

    public function view(User $user, Equipment $equipment): bool
    {
        return $user->can('view-equipment');
    }

    public function create(User $user): bool
    {
        return $user->can('manage-equipment');
    }

    public function update(User $user, Equipment $equipment): bool
    {
        return $user->can('manage-equipment');
    }

    public function delete(User $user, Equipment $equipment): bool
    {
        return $user->can('manage-equipment');
    }

    public function manage(User $user, ?Equipment $equipment = null): bool
    {
        return $user->can('manage-equipment');
    }

    public function import(User $user): bool
    {
        return $user->can('manage-equipment');
    }

    public function checkout(User $user, Equipment $equipment): bool
    {
        return $user->can('manage-equipment');
    }

    public function print(User $user, ?Equipment $equipment = null): bool
    {
        return $user->can('view-equipment');
    }

    public function printBulk(User $user): bool
    {
        return $user->can('manage-equipment');
    }
}
