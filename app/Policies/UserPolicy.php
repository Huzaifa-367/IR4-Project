<?php

namespace App\Policies;

use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-users');
    }

    public function view(User $actor, User $model): bool
    {
        return $actor->can('manage-users');
    }

    public function create(User $user): bool
    {
        return $user->can('manage-users');
    }

    public function update(User $actor, User $model): bool
    {
        return $actor->can('manage-users');
    }

    public function delete(User $actor, User $model): bool
    {
        return $actor->can('manage-users') && $actor->id !== $model->id;
    }
}
