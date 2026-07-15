<?php

namespace App\Policies;

use App\Models\Menu;
use App\Models\User;

class MenuPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('menus.view');
    }

    public function view(User $user, Menu $menu): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('menus.create');
    }

    public function update(User $user, Menu $menu): bool
    {
        return $user->hasRole('super_admin') || $user->can('menus.update');
    }

    public function delete(User $user, Menu $menu): bool
    {
        return $user->hasRole('super_admin') || $user->can('menus.delete');
    }

    public function export(User $user): bool
    {
        return $this->viewAny($user);
    }
}
