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
        protected DeliveryFeeCalculatorService $feeCalculator,
        protected OfferEngine $offerEngine
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
            $shopFee = (float) ($delivery->delivery_fee ?? 0);
            // Rider pay uses pre-offer fee so free/promo deliveries still compensate riders.
            $feeForRider = (float) ($delivery->base_delivery_fee ?? $delivery->delivery_fee ?? 0);
            $cod = (float) ($delivery->cod_amount ?? 0);

            $rider = $delivery->rider_id
                ? Rider::query()->lockForUpdate()->find($delivery->rider_id)
                : null;

            $commissionResolved = $this->offerEngine->resolveRiderCommission(
                $rider,
                (float) $rates['commission_percent'],
                $delivery
            );
            $commissionPercent = $commissionResolved['commission_percent'];
            $commission = round($feeForRider * ($commissionPercent / 100), 2);
            $riderEarning = round(max(0, $feeForRider - $commission), 2);

            $appliedIds = array_values(array_unique(array_filter(array_merge(
                $delivery->applied_offer_ids ?? [],
                $commissionResolved['applied_offer_ids'] ?? []
            ))));
            $notes = array_filter([
                $delivery->offer_notes,
                $commissionResolved['offer_notes'] ?? null,
            ]);

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

                    if ($shopFee > 0) {
                        $this->debitShop(
                            $shop,
                            $shopFee,
                            WalletTransactionType::FEE_DEBIT,
                            $delivery,
                            $actorId,
                            'Delivery fee for '.$delivery->tracking_number
                        );
                    }
                }
            }

            if ($rider && $riderEarning > 0) {
                $this->creditRider(
                    $rider,
                    $riderEarning,
                    WalletTransactionType::RIDE_EARNING,
                    $delivery,
                    $actorId,
                    'Ride earning for '.$delivery->tracking_number
                    .' (base fee '.$feeForRider.', commission '.$commission.' @ '.$commissionPercent.'%)'
                );
            }

            $delivery->rider_earning = $riderEarning;
            $delivery->platform_commission = $commission;
            $delivery->applied_offer_ids = $appliedIds;
            $delivery->offer_notes = $notes !== [] ? implode(' | ', $notes) : $delivery->offer_notes;
            $delivery->settled_at = now();
            $delivery->save();

            $this->offerEngine->applyPostSettleRewards($delivery, $appliedIds, $actorId);

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
     * Balance minus amounts already locked in pending payout requests.
     *
     * @param  Shop|Rider  $payable
     */
    public function availableForPayout($payable, ?int $excludePayoutId = null): float
    {
        $balance = (float) ($payable->balance ?? 0);
        $pendingQuery = Payout::query()
            ->where('payable_type', $payable::class)
            ->where('payable_id', $payable->id)
            ->where('status', Payout::STATUS_PENDING);

        if ($excludePayoutId) {
            $pendingQuery->where('id', '!=', $excludePayoutId);
        }

        $pending = (float) $pendingQuery->sum('amount');

        return round(max(0, $balance - $pending), 2);
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

        $available = $this->availableForPayout($payable);
        if ($amount > $available) {
            throw ValidationException::withMessages([
                'amount' => ['Payout cannot exceed available amount (Rs '.$available.'). Pending requests already reserved part of your balance.'],
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

    /**
     * Mark a payout paid. Pass a smaller $paidAmount for partial payment;
     * remainder stays as a new pending request.
     *
     * @return array{paid: Payout, remainder: ?Payout, was_partial: bool}
     */
    public function markPayoutPaid(
        Payout $payout,
        ?int $processedBy = null,
        ?string $reference = null,
        ?float $paidAmount = null
    ): array {
        if ($payout->status === Payout::STATUS_PAID) {
            return ['paid' => $payout, 'remainder' => null, 'was_partial' => false];
        }

        return $this->transaction(function () use ($payout, $processedBy, $reference, $paidAmount) {
            $payout = Payout::query()->lockForUpdate()->findOrFail($payout->id);
            if ($payout->status === Payout::STATUS_PAID) {
                return ['paid' => $payout, 'remainder' => null, 'was_partial' => false];
            }

            $requested = round((float) $payout->amount, 2);
            $toPay = $paidAmount !== null ? round($paidAmount, 2) : $requested;

            if ($toPay <= 0) {
                throw ValidationException::withMessages(['amount' => ['Payment amount must be greater than zero.']]);
            }
            if ($toPay > $requested) {
                throw ValidationException::withMessages([
                    'amount' => ['Payment amount cannot exceed requested amount (Rs '.$requested.').'],
                ]);
            }

            $payable = $payout->payable;
            if (! $payable) {
                throw ValidationException::withMessages(['payout' => ['Payable account not found.']]);
            }

            if ($payable instanceof Shop) {
                $shop = Shop::query()->lockForUpdate()->findOrFail($payable->id);
                if ($toPay > (float) $shop->balance) {
                    throw ValidationException::withMessages(['amount' => ['Insufficient shop balance.']]);
                }
                $this->debitShop(
                    $shop,
                    $toPay,
                    WalletTransactionType::PAYOUT_DEBIT,
                    null,
                    $processedBy,
                    'Payout '.$payout->uuid.($reference ? ' / '.$reference : '')
                );
            } elseif ($payable instanceof Rider) {
                $rider = Rider::query()->lockForUpdate()->findOrFail($payable->id);
                if ($toPay > (float) $rider->balance) {
                    throw ValidationException::withMessages(['amount' => ['Insufficient rider balance.']]);
                }
                $this->debitRider(
                    $rider,
                    $toPay,
                    WalletTransactionType::PAYOUT_DEBIT,
                    null,
                    $processedBy,
                    'Payout '.$payout->uuid.($reference ? ' / '.$reference : '')
                );
            }

            $wasPartial = $toPay < $requested;
            $remainderAmount = $wasPartial ? round($requested - $toPay, 2) : 0.0;
            $remainder = null;

            $payout->amount = $toPay;
            $payout->status = Payout::STATUS_PAID;
            $payout->paid_at = now();
            $payout->processed_by = $processedBy;
            if ($reference) {
                $payout->reference = $reference;
            }
            if ($wasPartial) {
                $payout->note = trim(($payout->note ? $payout->note.' · ' : '').'Partial payment');
            }
            $payout->save();

            if ($wasPartial && $remainderAmount > 0) {
                $remainder = Payout::create([
                    'payable_type' => $payout->payable_type,
                    'payable_id' => $payout->payable_id,
                    'amount' => $remainderAmount,
                    'status' => Payout::STATUS_PENDING,
                    'note' => 'Remainder after partial payment of '.$payout->uuid,
                    'requested_by' => $payout->requested_by,
                ]);
            }

            return [
                'paid' => $payout->fresh(['payable.user', 'processedBy', 'requestedBy']),
                'remainder' => $remainder,
                'was_partial' => $wasPartial,
            ];
        });
    }
}
