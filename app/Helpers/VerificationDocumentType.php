<?php

namespace App\Helpers;

class VerificationDocumentType
{
    public const PAN = 'pan';

    public const LICENSE = 'license';

    public const BLUE_BOOK = 'blue_book';

    public const CITIZENSHIP = 'citizenship';

    public const NID = 'nid';

    /**
     * @return array<int, string>
     */
    public static function shopTypes(): array
    {
        return [
            self::PAN,
            self::CITIZENSHIP,
            self::NID,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function riderTypes(): array
    {
        return [
            self::LICENSE,
            self::BLUE_BOOK,
            self::CITIZENSHIP,
            self::NID,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::PAN => 'PAN Card',
            self::LICENSE => 'Driving License',
            self::BLUE_BOOK => 'Blue Book',
            self::CITIZENSHIP => 'Citizenship',
            self::NID => 'National ID (NID)',
        ];
    }

    public static function label(string $type): string
    {
        return self::labels()[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }
}
