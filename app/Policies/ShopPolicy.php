<?php

namespace App\Policies;

use App\Models\Shop;
use App\Models\User;

class ShopPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('shops.view');
    }

    public function view(User $user, Shop $shop): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->hasRole('shop')) {
            return $user->shop?->id === $shop->id;
        }

        return $user->can('shops.view');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('shops.create');
    }

    public function update(User $user, Shop $shop): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->hasRole('shop')) {
            return $user->shop?->id === $shop->id;
        }

        return $user->can('shops.update');
    }

    public function delete(User $user, Shop $shop): bool
    {
        return $user->hasRole('super_admin') || $user->can('shops.delete');
    }

    public function export(User $user): bool
    {
        return $this->viewAny($user);
    }
}
