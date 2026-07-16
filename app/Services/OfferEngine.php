<?php

namespace App\Services;

use App\Helpers\DeliveryStatus;
use App\Helpers\OfferType;
use App\Helpers\WalletTransactionType;
use App\Models\Delivery;
use App\Models\Offer;
use App\Models\OfferRedemption;
use App\Models\Rider;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OfferEngine
{
    /**
     * Apply the best shop fee offer to a base fee.
     *
     * @return array{
     *   delivery_fee: float,
     *   base_delivery_fee: float,
     *   applied_offer_ids: list<int>,
     *   offer_notes: ?string,
     *   offer: ?array
     * }
     */
    public function resolveShopFee(?Shop $shop, float $baseFee, ?float $codAmount = null): array
    {
        $baseFee = max(0, round($baseFee, 2));
        $result = [
            'delivery_fee' => $baseFee,
            'base_delivery_fee' => $baseFee,
            'applied_offer_ids' => [],
            'offer_notes' => null,
            'offer' => null,
        ];

        if (! $shop || $baseFee <= 0) {
            return $result;
        }

        $offers = $this->activeOffers('shop', OfferType::shopFeeTypes());
        $best = null;
        $bestFee = $baseFee;
        $bestNote = null;

        foreach ($offers as $offer) {
            $count = $this->completedCountForShop($shop, $offer);
            $resolved = $this->evaluateShopFeeOffer($offer, $baseFee, $count, $codAmount);
            if ($resolved === null) {
                continue;
            }

            if ($resolved['fee'] < $bestFee || ($resolved['fee'] === $bestFee && ($best === null || $offer->priority < $best->priority))) {
                $best = $offer;
                $bestFee = $resolved['fee'];
                $bestNote = $resolved['note'];
            }
        }

        if ($best) {
            $result['delivery_fee'] = $bestFee;
            $result['applied_offer_ids'] = [$best->id];
            $result['offer_notes'] = $bestNote;
            $result['offer'] = $this->serializeOfferHint($best, $bestNote, $baseFee, $bestFee);
        }

        return $result;
    }

    /**
     * @return array{commission_percent: float, applied_offer_ids: list<int>, offer_notes: ?string}
     */
    public function resolveRiderCommission(?Rider $rider, float $basePercent, ?Delivery $delivery = null): array
    {
        $basePercent = max(0, min(100, $basePercent));
        $result = [
            'commission_percent' => $basePercent,
            'applied_offer_ids' => [],
            'offer_notes' => null,
        ];

        if (! $rider) {
            return $result;
        }

        $offers = $this->activeOffers('rider', OfferType::riderCommissionTypes());
        $best = null;
        $bestPercent = $basePercent;
        $bestNote = null;

        $at = $delivery?->completed_at
            ?? $delivery?->delivered_at
            ?? now();

        foreach ($offers as $offer) {
            $count = $this->completedCountForRider($rider, $offer);
            $resolved = $this->evaluateRiderCommissionOffer($offer, $basePercent, $count, $at);
            if ($resolved === null) {
                continue;
            }

            if ($resolved['percent'] < $bestPercent || ($resolved['percent'] === $bestPercent && ($best === null || $offer->priority < $best->priority))) {
                $best = $offer;
                $bestPercent = $resolved['percent'];
                $bestNote = $resolved['note'];
            }
        }

        if ($best) {
            $result['commission_percent'] = $bestPercent;
            $result['applied_offer_ids'] = [$best->id];
            $result['offer_notes'] = $bestNote;
        }

        return $result;
    }

    /**
     * Record fee/commission redemptions and credit milestone bonuses after settle.
     *
     * @param  list<int>  $appliedOfferIds
     */
    public function applyPostSettleRewards(Delivery $delivery, array $appliedOfferIds = [], ?int $actorId = null): void
    {
        $delivery->loadMissing(['shop', 'rider']);
        $ids = array_values(array_unique(array_filter(array_merge(
            $appliedOfferIds,
            $delivery->applied_offer_ids ?? []
        ))));

        foreach ($ids as $offerId) {
            $offer = Offer::query()->find($offerId);
            if (! $offer) {
                continue;
            }

            $exists = OfferRedemption::query()
                ->where('offer_id', $offer->id)
                ->where('delivery_id', $delivery->id)
                ->exists();
            if ($exists) {
                continue;
            }

            OfferRedemption::create([
                'offer_id' => $offer->id,
                'shop_id' => $delivery->shop_id,
                'rider_id' => $delivery->rider_id,
                'delivery_id' => $delivery->id,
                'benefit' => [
                    'type' => $offer->type,
                    'delivery_fee' => (float) $delivery->delivery_fee,
                    'base_delivery_fee' => (float) ($delivery->base_delivery_fee ?? $delivery->delivery_fee),
                    'platform_commission' => (float) ($delivery->platform_commission ?? 0),
                    'rider_earning' => (float) ($delivery->rider_earning ?? 0),
                    'note' => $delivery->offer_notes,
                ],
            ]);
        }

        if ($delivery->rider) {
            $this->maybeCreditMilestoneBonus($delivery->rider, $delivery, $actorId);
        }
    }

    /**
     * Progress payloads for shop/rider wallet UI.
     *
     * @return list<array<string, mixed>>
     */
    public function progressForShop(Shop $shop): array
    {
        return $this->activeOffers('shop', OfferType::shopTypes())
            ->map(fn (Offer $offer) => $this->progressPayload($offer, $this->completedCountForShop($shop, $offer)))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function progressForRider(Rider $rider): array
    {
        return $this->activeOffers('rider', OfferType::riderTypes())
            ->map(fn (Offer $offer) => $this->progressPayload($offer, $this->completedCountForRider($rider, $offer)))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $types
     * @return Collection<int, Offer>
     */
    protected function activeOffers(string $audience, array $types): Collection
    {
        return Offer::query()
            ->where('audience', $audience)
            ->where('is_active', true)
            ->whereIn('type', $types)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }

    protected function completedCountForShop(Shop $shop, Offer $offer): int
    {
        $query = Delivery::query()
            ->where('shop_id', $shop->id)
            ->whereIn('status', [DeliveryStatus::COMPLETED, DeliveryStatus::DELIVERED]);

        $this->applyWindow($query, $offer);

        return (int) $query->count();
    }

    protected function completedCountForRider(Rider $rider, Offer $offer): int
    {
        $query = Delivery::query()
            ->where('rider_id', $rider->id)
            ->whereIn('status', [DeliveryStatus::COMPLETED, DeliveryStatus::DELIVERED]);

        $this->applyWindow($query, $offer);

        return (int) $query->count();
    }

    protected function applyWindow($query, Offer $offer): void
    {
        if ($offer->window === 'calendar_month') {
            $query->whereBetween('created_at', [
                now()->startOfMonth()->toDateTimeString(),
                now()->endOfMonth()->toDateTimeString(),
            ]);
        }
    }

    /**
     * @return array{fee: float, note: string}|null
     */
    protected function evaluateShopFeeOffer(Offer $offer, float $baseFee, int $count, ?float $codAmount): ?array
    {
        return match ($offer->type) {
            OfferType::SHOP_NTH_FREE => $this->shopNthFree($offer, $baseFee, $count),
            OfferType::SHOP_FEE_PERCENT_OFF => $this->shopFeePercentOff($offer, $baseFee, $count),
            OfferType::SHOP_FIRST_N_DISCOUNT => $this->shopFirstNDiscount($offer, $baseFee, $count),
            default => null,
        };
    }

    /**
     * @return array{fee: float, note: string}|null
     */
    protected function shopNthFree(Offer $offer, float $baseFee, int $count): ?array
    {
        $everyN = max(1, (int) $offer->configValue('every_n', 5));
        // Next delivery index in window (1-based after this create) = count + 1
        $nextIndex = $count + 1;
        if ($nextIndex % $everyN !== 0) {
            return null;
        }

        return [
            'fee' => 0.0,
            'note' => sprintf('%s: delivery #%d is free (every %d)', $offer->name, $nextIndex, $everyN),
        ];
    }

    /**
     * @return array{fee: float, note: string}|null
     */
    protected function shopFeePercentOff(Offer $offer, float $baseFee, int $count): ?array
    {
        $min = max(0, (int) $offer->configValue('min_completed', 5));
        if ($count < $min) {
            return null;
        }

        $off = max(0, min(100, (float) $offer->configValue('fee_percent_off', 50)));
        $fee = round($baseFee * (1 - ($off / 100)), 2);

        return [
            'fee' => $fee,
            'note' => sprintf('%s: %.0f%% fee off after %d orders', $offer->name, $off, $min),
        ];
    }

    /**
     * @return array{fee: float, note: string}|null
     */
    protected function shopFirstNDiscount(Offer $offer, float $baseFee, int $count): ?array
    {
        $firstN = max(1, (int) $offer->configValue('first_n', 5));
        // Applies while completed count is still within first N (this delivery is count+1)
        if (($count + 1) > $firstN) {
            return null;
        }

        $off = max(0, min(100, (float) $offer->configValue('fee_percent_off', 50)));
        $fee = round($baseFee * (1 - ($off / 100)), 2);

        return [
            'fee' => $fee,
            'note' => sprintf('%s: first %d deliveries get %.0f%% fee off', $offer->name, $firstN, $off),
        ];
    }

    /**
     * @return array{percent: float, note: string}|null
     */
    protected function evaluateRiderCommissionOffer(Offer $offer, float $basePercent, int $count, Carbon $at): ?array
    {
        return match ($offer->type) {
            OfferType::RIDER_COMMISSION_REDUCE => $this->riderCommissionReduce($offer, $basePercent, $count),
            OfferType::RIDER_PEAK_BONUS => $this->riderPeakBonus($offer, $basePercent, $at),
            default => null,
        };
    }

    /**
     * @return array{percent: float, note: string}|null
     */
    protected function riderCommissionReduce(Offer $offer, float $basePercent, int $count): ?array
    {
        $min = max(0, (int) $offer->configValue('min_completed', 5));
        // After completing min rides, next settlements get reduced commission
        if ($count < $min) {
            return null;
        }

        $percent = max(0, min(100, (float) $offer->configValue('commission_percent', 10)));

        return [
            'percent' => $percent,
            'note' => sprintf('%s: commission %.1f%% after %d rides', $offer->name, $percent, $min),
        ];
    }

    /**
     * @return array{percent: float, note: string}|null
     */
    protected function riderPeakBonus(Offer $offer, float $basePercent, Carbon $at): ?array
    {
        $weekdays = $offer->configValue('weekdays', [5, 6]);
        if (! is_array($weekdays)) {
            $weekdays = [5, 6];
        }
        $weekdays = array_map('intval', $weekdays);
        // Carbon iso weekday: 1=Mon ... 7=Sun
        if (! in_array((int) $at->dayOfWeekIso, $weekdays, true)) {
            return null;
        }

        $startHour = (int) $offer->configValue('start_hour', 17);
        $endHour = (int) $offer->configValue('end_hour', 22);
        $hour = (int) $at->format('G');
        if ($hour < $startHour || $hour >= $endHour) {
            return null;
        }

        $percent = max(0, min(100, (float) $offer->configValue('commission_percent', 5)));

        return [
            'percent' => $percent,
            'note' => sprintf('%s: peak commission %.1f%%', $offer->name, $percent),
        ];
    }

    protected function maybeCreditMilestoneBonus(Rider $rider, Delivery $delivery, ?int $actorId): void
    {
        $offers = $this->activeOffers('rider', [OfferType::RIDER_MILESTONE_BONUS]);

        foreach ($offers as $offer) {
            $min = max(1, (int) $offer->configValue('min_completed', 10));
            $bonus = max(0, (float) $offer->configValue('bonus_amount', 0));
            if ($bonus <= 0) {
                continue;
            }

            $count = $this->completedCountForRider($rider, $offer);
            if ($count < $min) {
                continue;
            }

            // Credit once per window when threshold first reached (or once per delivery hitting exact min)
            $already = OfferRedemption::query()
                ->where('offer_id', $offer->id)
                ->where('rider_id', $rider->id)
                ->where('benefit->type', OfferType::RIDER_MILESTONE_BONUS)
                ->when($offer->window === 'calendar_month', function ($q) {
                    $q->whereBetween('created_at', [
                        now()->startOfMonth(),
                        now()->endOfMonth(),
                    ]);
                })
                ->exists();

            if ($already) {
                continue;
            }

            // Only when this completion crosses the threshold
            if ($count !== $min) {
                continue;
            }

            $this->wallet()->creditRider(
                $rider,
                $bonus,
                WalletTransactionType::OFFER_BONUS,
                $delivery,
                $actorId,
                sprintf('Milestone bonus: %s (Rs %.2f after %d rides)', $offer->name, $bonus, $min)
            );

            OfferRedemption::create([
                'offer_id' => $offer->id,
                'rider_id' => $rider->id,
                'delivery_id' => $delivery->id,
                'benefit' => [
                    'type' => OfferType::RIDER_MILESTONE_BONUS,
                    'bonus_amount' => $bonus,
                    'min_completed' => $min,
                ],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function progressPayload(Offer $offer, int $count): array
    {
        $target = match ($offer->type) {
            OfferType::SHOP_NTH_FREE => max(1, (int) $offer->configValue('every_n', 5)),
            OfferType::SHOP_FEE_PERCENT_OFF,
            OfferType::RIDER_COMMISSION_REDUCE,
            OfferType::RIDER_MILESTONE_BONUS => max(1, (int) $offer->configValue('min_completed', 5)),
            OfferType::SHOP_FIRST_N_DISCOUNT => max(1, (int) $offer->configValue('first_n', 5)),
            default => 0,
        };

        $nextAt = null;
        if ($offer->type === OfferType::SHOP_NTH_FREE && $target > 0) {
            $mod = $count % $target;
            $nextAt = $mod === 0 ? $target : ($target - $mod);
        } elseif (in_array($offer->type, [
            OfferType::SHOP_FEE_PERCENT_OFF,
            OfferType::RIDER_COMMISSION_REDUCE,
            OfferType::RIDER_MILESTONE_BONUS,
        ], true)) {
            $nextAt = max(0, $target - $count);
        } elseif ($offer->type === OfferType::SHOP_FIRST_N_DISCOUNT) {
            $nextAt = max(0, $target - $count);
        }

        return [
            'uuid' => $offer->uuid,
            'name' => $offer->name,
            'type' => $offer->type,
            'type_label' => OfferType::label($offer->type),
            'audience' => $offer->audience,
            'description' => $offer->description,
            'window' => $offer->window,
            'config' => $offer->config ?? [],
            'current_count' => $count,
            'target' => $target,
            'next_reward_in' => $nextAt,
            'unlocked' => $target > 0 && $nextAt === 0 && $offer->type !== OfferType::SHOP_NTH_FREE
                ? true
                : ($offer->type === OfferType::SHOP_NTH_FREE && $count > 0 && ($count % max(1, $target)) === 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeOfferHint(Offer $offer, ?string $note, float $baseFee, float $finalFee): array
    {
        return [
            'uuid' => $offer->uuid,
            'name' => $offer->name,
            'type' => $offer->type,
            'note' => $note,
            'base_fee' => $baseFee,
            'final_fee' => $finalFee,
            'saved' => round(max(0, $baseFee - $finalFee), 2),
        ];
    }

    protected function wallet(): WalletService
    {
        return app(WalletService::class);
    }
}
