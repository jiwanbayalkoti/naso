<?php

namespace App\Services;

use App\Helpers\WalletTransactionType;
use App\Models\Delivery;
use App\Models\Payout;
use App\Models\Rider;
use App\Models\Shop;
use App\Models\WalletTransaction;
use Illuminate\Validation\ValidationException;

class WalletService extends BaseService
{
    public function __construct(
        protected DeliveryFeeCalculatorService $feeCalculator
    ) {}

    /**
     * Settle shop COD + fee and rider earning when goods are delivered.
     */
    public function settleCompletedDelivery(Delivery $delivery, ?int $actorId = null): Delivery
    {
        if ($delivery->settled_at) {
            return $delivery;
        }

        return $this->transaction(function () use ($delivery, $actorId) {
            $delivery = Delivery::query()->lockForUpdate()->find($delivery->id);
            if (! $delivery || $delivery->settled_at) {
                return $delivery;
            }

            $rates = $this->feeCalculator->rates();
            $fee = (float) ($delivery->delivery_fee ?? 0);
            $cod = (float) ($delivery->cod_amount ?? 0);
            $commissionPercent = max(0, min(100, $rates['commission_percent']));
            $commission = round($fee * ($commissionPercent / 100), 2);
            $riderEarning = round(max(0, $fee - $commission), 2);

            if ($delivery->shop_id) {
                $shop = Shop::query()->lockForUpdate()->find($delivery->shop_id);
                if ($shop) {
                    if ($cod > 0) {
                        $this->creditShop(
                            $shop,
                            $cod,
                            WalletTransactionType::COD_CREDIT,
                            $delivery,
                            $actorId,
                            'COD collected for '.$delivery->tracking_number
                        );
                        $delivery->cod_collected_at = now();
                    }

                    if ($fee > 0) {
                        $this->debitShop(
                            $shop,
                            $fee,
                            WalletTransactionType::FEE_DEBIT,
                            $delivery,
                            $actorId,
                            'Delivery fee for '.$delivery->tracking_number
                        );
                    }
                }
            }

            if ($delivery->rider_id && $riderEarning > 0) {
                $rider = Rider::query()->lockForUpdate()->find($delivery->rider_id);
                if ($rider) {
                    $this->creditRider(
                        $rider,
                        $riderEarning,
                        WalletTransactionType::RIDE_EARNING,
                        $delivery,
                        $actorId,
                        'Ride earning for '.$delivery->tracking_number
                        .' (fee '.$fee.', commission '.$commission.')'
                    );
                }
            }

            $delivery->rider_earning = $riderEarning;
            $delivery->platform_commission = $commission;
            $delivery->settled_at = now();
            $delivery->save();

            return $delivery->fresh(['shop', 'rider.user']);
        });
    }

    public function creditShop(
        Shop $shop,
        float $amount,
        string $type,
        ?Delivery $delivery = null,
        ?int $actorId = null,
        ?string $note = null
    ): WalletTransaction {
        $shop->balance = round((float) $shop->balance + $amount, 2);
        $shop->save();

        return WalletTransaction::create([
            'shop_id' => $shop->id,
            'delivery_id' => $delivery?->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $shop->balance,
            'note' => $note,
            'created_by' => $actorId,
        ]);
    }

    public function debitShop(
        Shop $shop,
        float $amount,
        string $type,
        ?Delivery $delivery = null,
        ?int $actorId = null,
        ?string $note = null
    ): WalletTransaction {
        $shop->balance = round((float) $shop->balance - $amount, 2);
        $shop->save();

        return WalletTransaction::create([
            'shop_id' => $shop->id,
            'delivery_id' => $delivery?->id,
            'type' => $type,
            'amount' => -abs($amount),
            'balance_after' => $shop->balance,
            'note' => $note,
            'created_by' => $actorId,
        ]);
    }

    public function creditRider(
        Rider $rider,
        float $amount,
        string $type,
        ?Delivery $delivery = null,
        ?int $actorId = null,
        ?string $note = null
    ): WalletTransaction {
        $rider->balance = round((float) $rider->balance + $amount, 2);
        $rider->save();

        return WalletTransaction::create([
            'rider_id' => $rider->id,
            'delivery_id' => $delivery?->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $rider->balance,
            'note' => $note,
            'created_by' => $actorId,
        ]);
    }

    public function debitRider(
        Rider $rider,
        float $amount,
        string $type,
        ?Delivery $delivery = null,
        ?int $actorId = null,
        ?string $note = null
    ): WalletTransaction {
        $rider->balance = round((float) $rider->balance - $amount, 2);
        $rider->save();

        return WalletTransaction::create([
            'rider_id' => $rider->id,
            'delivery_id' => $delivery?->id,
            'type' => $type,
            'amount' => -abs($amount),
            'balance_after' => $rider->balance,
            'note' => $note,
            'created_by' => $actorId,
        ]);
    }

    /**
     * @param  Shop|Rider  $payable
     */
    public function createPayout(
        $payable,
        float $amount,
        ?int $requestedBy = null,
        ?string $note = null
    ): Payout {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => ['Payout amount must be greater than zero.']]);
        }

        $balance = (float) ($payable->balance ?? 0);
        if ($amount > $balance) {
            throw ValidationException::withMessages([
                'amount' => ['Payout cannot exceed available balance ('.$balance.').'],
            ]);
        }

        return Payout::create([
            'payable_type' => $payable::class,
            'payable_id' => $payable->id,
            'amount' => $amount,
            'status' => Payout::STATUS_PENDING,
            'note' => $note,
            'requested_by' => $requestedBy,
        ]);
    }

    public function markPayoutPaid(Payout $payout, ?int $processedBy = null, ?string $reference = null): Payout
    {
        if ($payout->status === Payout::STATUS_PAID) {
            return $payout;
        }

        return $this->transaction(function () use ($payout, $processedBy, $reference) {
            $payout = Payout::query()->lockForUpdate()->findOrFail($payout->id);
            if ($payout->status === Payout::STATUS_PAID) {
                return $payout;
            }

            $payable = $payout->payable;
            if (! $payable) {
                throw ValidationException::withMessages(['payout' => ['Payable account not found.']]);
            }

            if ($payable instanceof Shop) {
                $shop = Shop::query()->lockForUpdate()->findOrFail($payable->id);
                if ((float) $payout->amount > (float) $shop->balance) {
                    throw ValidationException::withMessages(['amount' => ['Insufficient shop balance.']]);
                }
                $this->debitShop(
                    $shop,
                    (float) $payout->amount,
                    WalletTransactionType::PAYOUT_DEBIT,
                    null,
                    $processedBy,
                    'Payout '.$payout->uuid.($reference ? ' / '.$reference : '')
                );
            } elseif ($payable instanceof Rider) {
                $rider = Rider::query()->lockForUpdate()->findOrFail($payable->id);
                if ((float) $payout->amount > (float) $rider->balance) {
                    throw ValidationException::withMessages(['amount' => ['Insufficient rider balance.']]);
                }
                $this->debitRider(
                    $rider,
                    (float) $payout->amount,
                    WalletTransactionType::PAYOUT_DEBIT,
                    null,
                    $processedBy,
                    'Payout '.$payout->uuid.($reference ? ' / '.$reference : '')
                );
            }

            $payout->status = Payout::STATUS_PAID;
            $payout->paid_at = now();
            $payout->processed_by = $processedBy;
            if ($reference) {
                $payout->reference = $reference;
            }
            $payout->save();

            return $payout->fresh(['payable', 'processedBy', 'requestedBy']);
        });
    }
}
