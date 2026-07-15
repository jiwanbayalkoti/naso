<?php

namespace App\Helpers;

class ActivityType
{
    public const DELIVERY_CREATED = 'delivery_created';

    public const DELIVERY_ASSIGNED = 'delivery_assigned';

    public const DELIVERY_ACCEPTED = 'delivery_accepted';

    public const DELIVERY_PICKED_UP = 'delivery_picked_up';

    public const DELIVERY_ON_THE_WAY = 'delivery_on_the_way';

    public const DELIVERY_DELIVERED = 'delivery_delivered';

    public const DELIVERY_COMPLETED = 'delivery_completed';

    public const DELIVERY_CANCELLED = 'delivery_cancelled';

    public const DELIVERY_STATUS_CHANGED = 'delivery_status_changed';

    public const RIDER_ONLINE = 'rider_online';

    public const RIDER_OFFLINE = 'rider_offline';

    public const RIDER_LOCATION_UPDATED = 'rider_location_updated';

    public const SHOP_CREATED = 'shop_created';

    public const SHOP_REGISTERED = 'shop_registered';

    public const SHOP_REGISTRATION_APPROVED = 'shop_registration_approved';

    public const SHOP_REGISTRATION_REJECTED = 'shop_registration_rejected';

    public const SHOP_UPDATED = 'shop_updated';

    public const RIDER_REGISTERED = 'rider_registered';

    public const RIDER_REGISTRATION_APPROVED = 'rider_registration_approved';

    public const RIDER_REGISTRATION_REJECTED = 'rider_registration_rejected';

    public const USER_LOGIN = 'user_login';

    public const USER_LOGOUT = 'user_logout';

    public const USER_CREATED = 'user_created';

    public const USER_UPDATED = 'user_updated';

    /**
     * Get all activity type values.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::DELIVERY_CREATED,
            self::DELIVERY_ASSIGNED,
            self::DELIVERY_ACCEPTED,
            self::DELIVERY_PICKED_UP,
            self::DELIVERY_ON_THE_WAY,
            self::DELIVERY_DELIVERED,
            self::DELIVERY_COMPLETED,
            self::DELIVERY_CANCELLED,
            self::DELIVERY_STATUS_CHANGED,
            self::RIDER_ONLINE,
            self::RIDER_OFFLINE,
            self::RIDER_LOCATION_UPDATED,
            self::SHOP_CREATED,
            self::SHOP_REGISTERED,
            self::SHOP_REGISTRATION_APPROVED,
            self::SHOP_REGISTRATION_REJECTED,
            self::SHOP_UPDATED,
            self::RIDER_REGISTERED,
            self::RIDER_REGISTRATION_APPROVED,
            self::RIDER_REGISTRATION_REJECTED,
            self::USER_LOGIN,
            self::USER_LOGOUT,
            self::USER_CREATED,
            self::USER_UPDATED,
        ];
    }
}
