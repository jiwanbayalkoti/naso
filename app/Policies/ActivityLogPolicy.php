<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('activity-logs.view');
    }

    public function view(User $user, ActivityLog $activityLog): bool
    {
        return $this->viewAny($user);
    }
}
