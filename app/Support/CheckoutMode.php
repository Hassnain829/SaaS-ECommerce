<?php

namespace App\Support;

use App\Models\Store;
use App\Services\Channels\ChannelOwnershipService;

final class CheckoutMode
{
    /**
     * Historical order source only. Not a selectable runtime checkout mode.
     */
    public const EXTERNAL = 'external_checkout';

    public const PLATFORM = 'platform_checkout';

    public const ALL = [
        self::PLATFORM,
    ];

    public static function forStore(Store $store): string
    {
        return self::PLATFORM;
    }

    public static function label(string $mode): string
    {
        return match ($mode) {
            self::EXTERNAL => 'External checkout (historical)',
            default => 'Platform checkout',
        };
    }

    public static function setForStore(Store $store, string $mode): Store
    {
        return app(ChannelOwnershipService::class)->syncActiveCheckoutMode($store, self::PLATFORM);
    }

    public static function isHistoricalExternal(?string $orderSource): bool
    {
        return $orderSource === self::EXTERNAL;
    }
}
