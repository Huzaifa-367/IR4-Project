<?php

namespace App\Policies;

use App\Models\HseIncident;
use App\Models\User;

final class HseIncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-incidents');
    }

    public function view(User $user, HseIncident $incident): bool
    {
        return $user->can('view-incidents');
    }

    public function create(User $user): bool
    {
        return $user->can('log-incidents');
    }

    public function classify(User $user, HseIncident $incident): bool
    {
        return $user->can('classify-incidents');
    }

    public function reopen(User $user, HseIncident $incident): bool
    {
        return $user->can('classify-incidents');
    }

    public function close(User $user, HseIncident $incident): bool
    {
        return $user->can('classify-incidents');
    }

    public function addEvidence(User $user, HseIncident $incident): bool
    {
        return $user->can('log-incidents');
    }
}
