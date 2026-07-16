<?php

namespace App\Helpers;

final class OfferType
{
    public const RIDER_COMMISSION_REDUCE = 'rider_commission_reduce';

    public const RIDER_MILESTONE_BONUS = 'rider_milestone_bonus';

    public const RIDER_PEAK_BONUS = 'rider_peak_bonus';

    public const SHOP_NTH_FREE = 'shop_nth_free';

    public const SHOP_FEE_PERCENT_OFF = 'shop_fee_percent_off';

    public const SHOP_FIRST_N_DISCOUNT = 'shop_first_n_discount';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::RIDER_COMMISSION_REDUCE,
            self::RIDER_MILESTONE_BONUS,
            self::RIDER_PEAK_BONUS,
            self::SHOP_NTH_FREE,
            self::SHOP_FEE_PERCENT_OFF,
            self::SHOP_FIRST_N_DISCOUNT,
        ];
    }

    /**
     * @return list<string>
     */
    public static function shopTypes(): array
    {
        return [
            self::SHOP_NTH_FREE,
            self::SHOP_FEE_PERCENT_OFF,
            self::SHOP_FIRST_N_DISCOUNT,
        ];
    }

    /**
     * @return list<string>
     */
    public static function riderTypes(): array
    {
        return [
            self::RIDER_COMMISSION_REDUCE,
            self::RIDER_MILESTONE_BONUS,
            self::RIDER_PEAK_BONUS,
        ];
    }

    /**
     * @return list<string>
     */
    public static function shopFeeTypes(): array
    {
        return self::shopTypes();
    }

    /**
     * @return list<string>
     */
    public static function riderCommissionTypes(): array
    {
        return [
            self::RIDER_COMMISSION_REDUCE,
            self::RIDER_PEAK_BONUS,
        ];
    }

    public static function label(string $type): string
    {
        return match ($type) {
            self::RIDER_COMMISSION_REDUCE => 'Rider: lower commission after N rides',
            self::RIDER_MILESTONE_BONUS => 'Rider: milestone bonus',
            self::RIDER_PEAK_BONUS => 'Rider: peak hours commission',
            self::SHOP_NTH_FREE => 'Shop: every Nth delivery free',
            self::SHOP_FEE_PERCENT_OFF => 'Shop: fee % off after N orders',
            self::SHOP_FIRST_N_DISCOUNT => 'Shop: first N deliveries discount',
            default => $type,
        };
    }
}
