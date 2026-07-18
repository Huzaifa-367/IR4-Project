<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleViolation;
use App\Models\WeeklyReport;

final class WeeklyReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-reports');
    }

    public function view(User $user, WeeklyReport $report): bool
    {
        if (! $user->can('view-reports')) {
            return false;
        }

        if ($user->primaryRole()?->is_read_only) {
            return $report->isPublished();
        }

        return true;
    }

    public function generate(User $user): bool
    {
        return $user->can('generate-reports');
    }

    public function publish(User $user, WeeklyReport $report): bool
    {
        return $user->can('publish-reports');
    }
}
