<?php

namespace App\Policies;

use App\Helpers\ApprovalStatus;
use App\Helpers\DeliveryStatus;
use App\Models\Delivery;
use App\Models\User;

class DeliveryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'shop', 'rider']) || $user->can('deliveries.view');
    }

    public function view(User $user, Delivery $delivery): bool
    {
        if ($user->hasRole('super_admin') || $user->can('deliveries.view')) {
            return true;
        }

        if ($user->hasRole('shop')) {
            return $user->shop?->id === $delivery->shop_id;
        }

        if ($user->hasRole('rider')) {
            return $user->rider?->id === $delivery->rider_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'shop']) || $user->can('deliveries.create');
    }

    public function update(User $user, Delivery $delivery): bool
    {
        if ($user->hasRole('super_admin') || $user->can('deliveries.update')) {
            return true;
        }

        if ($user->hasRole('shop')) {
            return $user->shop?->id === $delivery->shop_id;
        }

        if ($user->hasRole('rider')) {
            return $user->rider?->id === $delivery->rider_id;
        }

        return false;
    }

    public function delete(User $user, Delivery $delivery): bool
    {
        if ($user->hasRole('super_admin') || $user->can('deliveries.delete')) {
            return true;
        }

        return $user->hasRole('shop') && $user->shop?->id === $delivery->shop_id;
    }

    public function assign(User $user, Delivery $delivery): bool
    {
        if ($user->hasRole('super_admin') || $user->can('deliveries.assign')) {
            return true;
        }

        return $user->hasRole('shop') && $user->shop?->id === $delivery->shop_id;
    }

    public function updateStatus(User $user, Delivery $delivery): bool
    {
        if ($user->hasRole('super_admin') || $user->can('deliveries.update_status')) {
            return true;
        }

        if ($user->hasRole('shop')) {
            return $user->shop?->id === $delivery->shop_id;
        }

        if ($user->hasRole('rider')) {
            return $user->rider?->id === $delivery->rider_id;
        }

        return false;
    }

    public function rejectAssignment(User $user, Delivery $delivery): bool
    {
        if ($delivery->status !== DeliveryStatus::ASSIGNED) {
            return false;
        }

        return $user->hasRole('rider') && $user->rider?->id === $delivery->rider_id;
    }

    public function claim(User $user, Delivery $delivery): bool
    {
        if (! $user->hasRole('rider') || ! $user->rider) {
            return false;
        }

        if ($delivery->status !== DeliveryStatus::PENDING || $delivery->rider_id !== null) {
            return false;
        }

        $rider = $user->rider;

        if (! $rider->isPresentlyOnline() || ! $rider->is_available) {
            return false;
        }

        if ($rider->approval_status && $rider->approval_status !== ApprovalStatus::APPROVED) {
            return false;
        }

        return true;
    }

    public function export(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function track(?User $user, Delivery $delivery): bool
    {
        return true;
    }
}
