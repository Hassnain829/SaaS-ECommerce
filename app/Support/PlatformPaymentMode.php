<?php

namespace App\Support;

use App\Models\Store;

final class PlatformPaymentMode
{
    public const TEST = 'test';

    public const LIVE = 'live';

    public const ALL = [
        self::TEST,
        self::LIVE,
    ];

    public static function forStore(Store $store): string
    {
        $mode = strtolower((string) data_get($store->settings ?? [], 'platform_payment_mode', self::TEST));

        return in_array($mode, self::ALL, true) ? $mode : self::TEST;
    }

    public static function label(string $mode): string
    {
        return match ($mode) {
            self::LIVE => 'Live mode',
            default => 'Test mode',
        };
    }

    public static function setForStore(Store $store, string $mode): Store
    {
        $mode = in_array($mode, self::ALL, true) ? $mode : self::TEST;
        $settings = $store->settings ?? [];
        $settings['platform_payment_mode'] = $mode;
        $store->forceFill(['settings' => $settings])->save();

        return $store->fresh();
    }
}
