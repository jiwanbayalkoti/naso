<?php

namespace App\Helpers;

class ApprovalStatus
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::PENDING,
            self::APPROVED,
            self::REJECTED,
        ];
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            default => ucfirst($status),
        };
    }
}
