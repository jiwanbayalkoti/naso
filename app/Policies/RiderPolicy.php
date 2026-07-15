<?php

namespace App\Policies;

use App\Models\Rider;
use App\Models\User;

class RiderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('riders.view');
    }

    public function view(User $user, Rider $rider): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->hasRole('rider')) {
            return $user->rider?->id === $rider->id;
        }

        return $user->can('riders.view');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('riders.create');
    }

    public function update(User $user, Rider $rider): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->hasRole('rider')) {
            return $user->rider?->id === $rider->id;
        }

        return $user->can('riders.update');
    }

    public function delete(User $user, Rider $rider): bool
    {
        return $user->hasRole('super_admin') || $user->can('riders.delete');
    }

    public function export(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function toggleOnline(User $user, Rider $rider): bool
    {
        // Only the rider themselves can go online / offline.
        // Presence also requires an active session heartbeat in the app.
        if ($user->hasRole('rider')) {
            return $user->rider?->id === $rider->id;
        }

        return false;
    }

    /**
     * Live map of rider GPS — admin sees all; shop sees online + riders on their deliveries.
     */
    public function trackLive(User $user): bool
    {
        if ($user->hasRole('super_admin') || $user->can('riders.view')) {
            return true;
        }

        return $user->hasRole('shop') && $user->can('deliveries.view');
    }
}
