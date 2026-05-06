<?php

namespace App\Support;

use App\Models\Store;

final class StorePermission
{
    public const CATALOG_VIEW = 'catalog.view';
    public const CATALOG_MANAGE = 'catalog.manage';
    public const IMPORTS_VIEW = 'imports.view';
    public const IMPORTS_MANAGE = 'imports.manage';
    public const ORDERS_VIEW = 'orders.view';
    public const ORDERS_MANAGE = 'orders.manage';
    public const CUSTOMERS_VIEW = 'customers.view';
    public const CUSTOMERS_MANAGE = 'customers.manage';
    public const SETTINGS_VIEW = 'settings.view';
    public const SETTINGS_MANAGE = 'settings.manage';
    public const TEAM_VIEW = 'team.view';
    public const TEAM_MANAGE = 'team.manage';
    public const DEVELOPER_API_VIEW = 'developer_api.view';
    public const DEVELOPER_API_MANAGE = 'developer_api.manage';
    public const SECURITY_VIEW = 'security.view';
    public const SECURITY_MANAGE = 'security.manage';
    public const BILLING_VIEW = 'billing.view';
    public const BILLING_MANAGE = 'billing.manage';

    public const ALL = [
        self::CATALOG_VIEW,
        self::CATALOG_MANAGE,
        self::IMPORTS_VIEW,
        self::IMPORTS_MANAGE,
        self::ORDERS_VIEW,
        self::ORDERS_MANAGE,
        self::CUSTOMERS_VIEW,
        self::CUSTOMERS_MANAGE,
        self::SETTINGS_VIEW,
        self::SETTINGS_MANAGE,
        self::TEAM_VIEW,
        self::TEAM_MANAGE,
        self::DEVELOPER_API_VIEW,
        self::DEVELOPER_API_MANAGE,
        self::SECURITY_VIEW,
        self::SECURITY_MANAGE,
        self::BILLING_VIEW,
        self::BILLING_MANAGE,
    ];

    private const ROLE_PERMISSIONS = [
        Store::ROLE_OWNER => self::ALL,
        Store::ROLE_MANAGER => [
            self::CATALOG_VIEW,
            self::CATALOG_MANAGE,
            self::IMPORTS_VIEW,
            self::IMPORTS_MANAGE,
            self::ORDERS_VIEW,
            self::ORDERS_MANAGE,
            self::CUSTOMERS_VIEW,
            self::CUSTOMERS_MANAGE,
            self::SETTINGS_VIEW,
            self::DEVELOPER_API_VIEW,
            self::SECURITY_VIEW,
        ],
        Store::ROLE_STAFF => [
            self::CATALOG_VIEW,
            self::ORDERS_VIEW,
            self::CUSTOMERS_VIEW,
            self::SETTINGS_VIEW,
        ],
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return self::ALL;
    }

    public static function exists(string $permission): bool
    {
        return in_array($permission, self::ALL, true);
    }

    /**
     * @return list<string>
     */
    public static function forRole(?string $storeRole): array
    {
        if ($storeRole === null) {
            return [];
        }

        return self::ROLE_PERMISSIONS[$storeRole] ?? [];
    }
}
