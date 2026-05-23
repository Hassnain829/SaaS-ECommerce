<?php

namespace App\Support;

use App\Models\Store;
use App\Services\Channels\ChannelOwnershipService;

final class CheckoutMode
{
    public const EXTERNAL = 'external_checkout';

    public const PLATFORM = 'platform_checkout';

    public const ALL = [
        self::EXTERNAL,
        self::PLATFORM,
    ];

    public static function forStore(Store $store): string
    {
        $mode = (string) data_get($store->settings ?? [], 'checkout_mode', self::EXTERNAL);

        return in_array($mode, self::ALL, true) ? $mode : self::EXTERNAL;
    }

    public static function label(string $mode): string
    {
        return match ($mode) {
            self::PLATFORM => 'Platform checkout',
            default => 'External checkout',
        };
    }

    public static function setForStore(Store $store, string $mode): Store
    {
        $mode = in_array($mode, self::ALL, true) ? $mode : self::EXTERNAL;

        return app(ChannelOwnershipService::class)->syncActiveCheckoutMode($store, $mode);
    }
}
