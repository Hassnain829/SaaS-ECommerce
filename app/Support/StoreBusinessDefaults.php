<?php

namespace App\Support;

final class StoreBusinessDefaults
{
    /**
     * Supported store currencies for merchant settings.
     *
     * @var list<string>
     */
    public const CURRENCIES = ['USD', 'EUR', 'GBP', 'PKR', 'AED'];

    /**
     * Supported store timezones for merchant settings.
     *
     * @var list<string>
     */
    public const TIMEZONES = [
        'UTC',
        'Asia/Karachi',
        'America/New_York',
        'Europe/London',
        'Asia/Dubai',
    ];

    /**
     * @return list<string>
     */
    public static function currencies(): array
    {
        return self::CURRENCIES;
    }

    /**
     * @return list<string>
     */
    public static function timezones(): array
    {
        return self::TIMEZONES;
    }
}
