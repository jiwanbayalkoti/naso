<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\User;
use App\Notifications\DeliveryCompletedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class DeliverySettlementNotifier
{
    public function notifyCompleted(Delivery $delivery): void
    {
        $delivery = $delivery->fresh(['shop.user', 'rider.user']) ?? $delivery;
        $delivery->loadMissing(['shop.user', 'rider.user']);

        try {
            $shopUser = $delivery->shop?->user;
            if ($shopUser) {
                $shopUser->notify(new DeliveryCompletedNotification($delivery, 'shop'));
            }

            $riderUser = $delivery->rider?->user;
            if ($riderUser) {
                $riderUser->notify(new DeliveryCompletedNotification($delivery, 'rider'));
            }

            User::role('super_admin')
                ->where('is_active', true)
                ->get()
                ->each(function (User $admin) use ($delivery) {
                    $admin->notify(new DeliveryCompletedNotification($delivery, 'admin'));
                });

            $this->notifyCustomer($delivery);
        } catch (\Throwable $e) {
            Log::warning('Delivery settlement notify failed', [
                'delivery' => $delivery->uuid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function notifyCustomer(Delivery $delivery): void
    {
        $phone = $delivery->customer_phone;
        if (! is_string($phone) || preg_replace('/\D+/', '', $phone) === '') {
            return;
        }

        Notification::route('sms', $phone)
            ->notify(new DeliveryCompletedNotification($delivery, 'customer'));
    }
}
