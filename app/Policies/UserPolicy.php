<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('users.view');
    }

    public function view(User $user, User $model): bool
    {
        if ($user->hasRole('super_admin') || $user->can('users.view')) {
            return true;
        }

        return $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('users.create');
    }

    public function update(User $user, User $model): bool
    {
        if ($user->hasRole('super_admin') || $user->can('users.update')) {
            return true;
        }

        return $user->id === $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        return $user->hasRole('super_admin') || $user->can('users.delete');
    }

    public function export(User $user): bool
    {
        return $this->viewAny($user);
    }
}
