<?php

namespace App\Support;

final class ConnectedSiteScope
{
    public const CATALOG_READ = 'catalog:read';

    public const CHECKOUT_CREATE = 'checkout:create';

    public const CHECKOUT_READ = 'checkout:read';

    public const ORDERS_READ = 'orders:read';

    public const CUSTOMERS_AUTHENTICATE = 'customers:authenticate';

    public const SHIPPING_QUOTE = 'shipping:quote';

    public const TRACKING_READ = 'tracking:read';

    public const SITE_HEALTH = 'site:health';

    public const ALL = [
        self::CATALOG_READ,
        self::CHECKOUT_CREATE,
        self::CHECKOUT_READ,
        self::ORDERS_READ,
        self::CUSTOMERS_AUTHENTICATE,
        self::SHIPPING_QUOTE,
        self::TRACKING_READ,
        self::SITE_HEALTH,
    ];

    /**
     * @return list<string>
     */
    public static function connectorDefaults(): array
    {
        return self::ALL;
    }

    public static function label(string $scope): string
    {
        return match ($scope) {
            self::CATALOG_READ => 'Read products',
            self::CHECKOUT_CREATE => 'Start checkout',
            self::CHECKOUT_READ => 'Read checkout',
            self::ORDERS_READ => 'Read customer orders',
            self::CUSTOMERS_AUTHENTICATE => 'Customer sign-in',
            self::SHIPPING_QUOTE => 'Request delivery rates',
            self::TRACKING_READ => 'Read tracking',
            self::SITE_HEALTH => 'Connection health',
            default => $scope,
        };
    }
}
