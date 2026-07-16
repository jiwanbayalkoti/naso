<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\Rider;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\PayoutPaidNotification;
use App\Notifications\PayoutRequestedNotification;
use Illuminate\Support\Facades\Log;

class PayoutRequestNotifier
{
    public function notifyAdmins(Payout $payout): void
    {
        try {
            $payout->loadMissing(['payable.user', 'requestedBy']);

            User::role('super_admin')
                ->where('is_active', true)
                ->get()
                ->each(function (User $admin) use ($payout) {
                    $admin->notify(new PayoutRequestedNotification($payout));
                });
        } catch (\Throwable $e) {
            Log::warning('Payout request notify failed', [
                'payout' => $payout->uuid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function notifyPaid(
        Payout $payout,
        bool $wasPartial = false,
        ?float $remainderAmount = null
    ): void {
        try {
            $payout->loadMissing(['payable.user', 'processedBy']);
            $user = $this->payableUser($payout);
            if (! $user) {
                return;
            }

            $user->notify(new PayoutPaidNotification($payout, $wasPartial, $remainderAmount));
        } catch (\Throwable $e) {
            Log::warning('Payout paid notify failed', [
                'payout' => $payout->uuid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function payableUser(Payout $payout): ?User
    {
        $payable = $payout->payable;

        if ($payable instanceof Shop) {
            return $payable->user;
        }

        if ($payable instanceof Rider) {
            return $payable->user;
        }

        return null;
    }
}
