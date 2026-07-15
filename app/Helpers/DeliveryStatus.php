<?php

namespace App\Helpers;

class DeliveryStatus
{
    public const PENDING = 'pending';

    public const ASSIGNED = 'assigned';

    public const ACCEPTED = 'accepted';

    public const PICKED_UP = 'picked_up';

    public const ON_THE_WAY = 'on_the_way';

    public const DELIVERED = 'delivered';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    /**
     * Get all delivery status values.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::PENDING,
            self::ASSIGNED,
            self::ACCEPTED,
            self::PICKED_UP,
            self::ON_THE_WAY,
            self::DELIVERED,
            self::COMPLETED,
            self::CANCELLED,
        ];
    }

    /**
     * Get human-readable labels for delivery statuses.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::PENDING => 'Pending',
            self::ASSIGNED => 'Assigned',
            self::ACCEPTED => 'Accepted',
            self::PICKED_UP => 'Picked Up',
            self::ON_THE_WAY => 'On The Way',
            self::DELIVERED => 'Delivered',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        ];
    }

    /**
     * Get allowed status transitions from each status.
     *
     * @return array<string, array<int, string>>
     */
    public static function transitions(): array
    {
        return [
            self::PENDING => [self::ASSIGNED, self::CANCELLED],
            self::ASSIGNED => [self::ACCEPTED, self::CANCELLED],
            self::ACCEPTED => [self::PICKED_UP, self::CANCELLED],
            self::PICKED_UP => [self::ON_THE_WAY, self::CANCELLED],
            self::ON_THE_WAY => [self::DELIVERED, self::CANCELLED],
            self::DELIVERED => [self::COMPLETED, self::CANCELLED],
            self::COMPLETED => [],
            self::CANCELLED => [],
        ];
    }

    /**
     * Determine if a status transition is allowed.
     */
    public static function canTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return in_array($to, self::transitions()[$from] ?? [], true);
    }

    /**
     * Determine if a status is terminal.
     */
    public static function isTerminal(string $status): bool
    {
        return in_array($status, [self::COMPLETED, self::CANCELLED], true);
    }

    /**
     * Status options a rider may set from the current delivery status.
     *
     * @return array<int, string>
     */
    public static function riderActionStatuses(string $currentStatus): array
    {
        return match ($currentStatus) {
            self::ASSIGNED => [self::ACCEPTED],
            self::ACCEPTED => [self::PICKED_UP],
            self::PICKED_UP => [self::ON_THE_WAY],
            self::ON_THE_WAY => [self::DELIVERED],
            self::DELIVERED => [self::COMPLETED],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function riderActionLabels(): array
    {
        return [
            self::ACCEPTED => 'Accept Delivery',
            self::PICKED_UP => 'Mark Picked Up',
            self::ON_THE_WAY => 'Mark On The Way',
            self::DELIVERED => 'Mark Delivered',
            self::COMPLETED => 'Complete Delivery',
        ];
    }
}
