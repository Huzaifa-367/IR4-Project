<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Worker;

final class WorkerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-tracking');
    }

    public function view(User $user, Worker $worker): bool
    {
        return $user->can('view-tracking');
    }

    public function create(User $user): bool
    {
        return $user->can('create-workers');
    }

    public function update(User $user, Worker $worker): bool
    {
        return $user->can('update-workers');
    }

    public function delete(User $user, Worker $worker): bool
    {
        return $user->can('delete-workers');
    }

    public function import(User $user): bool
    {
        return $user->can('create-workers');
    }
}
