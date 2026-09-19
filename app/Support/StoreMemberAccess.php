<?php

namespace App\Support;

use App\Models\Store;
use App\Models\User;

final class StoreMemberAccess
{
    public const PRESET_FULL_OPERATIONAL = 'full_operational';

    public const PRESET_OPERATIONS = 'operations';

    public const PRESET_VIEW = 'view';

    public const PRESET_CUSTOM = 'custom';

    public const MEMBERSHIP_OWNER = 'owner';

    public const MEMBERSHIP_MEMBER = 'member';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INVITED = 'invited';

    public const STATUS_SUSPENDED = 'suspended';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    public static function exists(string $permission): bool
    {
        return isset(self::definitions()[$permission]);
    }

    /**
     * @return list<string>
     */
    public static function sensitiveKeys(): array
    {
        return array_values(array_filter(
            self::keys(),
            static fn (string $key): bool => (self::definitions()[$key]['sensitive'] ?? false) === true
        ));
    }

    /**
     * @return list<string>
     */
    public static function ownerOnlyKeys(): array
    {
        return [
            'owner.transfer',
            'owner.close_store',
            'owner.subscription',
            'owner.promote',
            'owner.billing_authority',
        ];
    }

    /**
     * @return list<string>
     */
    public static function presetKeys(): array
    {
        return [
            self::PRESET_FULL_OPERATIONAL,
            self::PRESET_OPERATIONS,
            self::PRESET_VIEW,
            self::PRESET_CUSTOM,
        ];
    }

    /**
     * @return list<string>
     */
    public static function jobTitleSuggestions(): array
    {
        return [
            'Warehouse Operator',
            'Customer Support',
            'Product Manager',
            'Accountant',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function presets(): array
    {
        return [
            self::PRESET_VIEW => [
                'products.view',
                'orders.view',
                'customers.view',
            ],
            self::PRESET_OPERATIONS => [
                'products.view',
                'products.edit',
                'products.inventory',
                'orders.view',
                'orders.draft',
                'orders.edit',
                'fulfillment.fulfill',
                'fulfillment.tracking',
                'fulfillment.inventory_adjust',
                'customers.view',
                'customers.edit',
                'customers.returns',
                'customers.exchanges',
                'settings.delivery',
            ],
            self::PRESET_FULL_OPERATIONAL => [
                'products.view',
                'products.edit',
                'products.prices',
                'products.inventory',
                'products.import',
                'orders.view',
                'orders.draft',
                'orders.edit',
                'orders.cancel',
                'fulfillment.fulfill',
                'fulfillment.tracking',
                'fulfillment.inventory_adjust',
                'customers.view',
                'customers.edit',
                'customers.returns',
                'customers.exchanges',
                'settings.discounts',
                'settings.locations',
                'settings.delivery',
                'settings.taxes',
                'website.view',
                'website.plugin',
                'security.view',
            ],
            self::PRESET_CUSTOM => [],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function dependencies(): array
    {
        return [
            'products.edit' => ['products.view'],
            'products.prices' => ['products.view'],
            'products.inventory' => ['products.view'],
            'products.import' => ['products.view'],
            'products.delete' => ['products.view', 'products.edit'],
            'orders.draft' => ['orders.view'],
            'orders.edit' => ['orders.view'],
            'orders.payments' => ['orders.view'],
            'orders.cancel' => ['orders.view'],
            'fulfillment.fulfill' => ['orders.view', 'products.view'],
            'fulfillment.labels.purchase' => ['orders.view', 'fulfillment.fulfill'],
            'fulfillment.labels.cancel' => ['orders.view', 'fulfillment.fulfill'],
            'fulfillment.tracking' => ['orders.view', 'fulfillment.fulfill'],
            'fulfillment.inventory_adjust' => ['orders.view', 'products.view', 'fulfillment.fulfill'],
            'customers.edit' => ['customers.view'],
            'customers.returns' => ['customers.view', 'orders.view'],
            'customers.exchanges' => ['customers.view', 'orders.view'],
            'customers.refunds' => ['customers.view', 'orders.view', 'orders.payments'],
            'customers.export' => ['customers.view'],
            'settings.discounts' => [],
            'settings.locations' => [],
            'settings.delivery' => [],
            'settings.delivery.delete' => ['settings.delivery'],
            'settings.taxes' => [],
            'settings.carriers' => [],
            'settings.payments' => [],
            'website.manage' => ['website.view'],
            'website.plugin' => ['website.view'],
            'website.token' => ['website.view'],
            'website.cutover' => ['website.view'],
            'team.manage' => ['team.view'],
            'billing.manage' => ['billing.view'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function children(): array
    {
        $children = [];
        foreach (self::dependencies() as $permission => $parents) {
            foreach ($parents as $parent) {
                $children[$parent][] = $permission;
            }
        }

        return $children;
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string>
     */
    public static function normalize(array $permissions): array
    {
        $selected = [];
        foreach ($permissions as $permission) {
            $permission = is_string($permission) ? trim($permission) : '';
            if ($permission === '' || ! self::exists($permission)) {
                continue;
            }
            $selected[$permission] = true;
        }

        $changed = true;
        while ($changed) {
            $changed = false;
            foreach (array_keys($selected) as $permission) {
                foreach (self::dependencies()[$permission] ?? [] as $parent) {
                    if (! isset($selected[$parent])) {
                        $selected[$parent] = true;
                        $changed = true;
                    }
                }
            }
        }

        $children = self::children();
        $queue = [];
        foreach (['products.view', 'orders.view', 'customers.view', 'website.view', 'team.view', 'billing.view', 'settings.delivery'] as $root) {
            if (! isset($selected[$root])) {
                $queue[] = $root;
            }
        }
        while ($queue !== []) {
            $parent = array_shift($queue);
            foreach ($children[$parent] ?? [] as $child) {
                if (isset($selected[$child])) {
                    unset($selected[$child]);
                    $queue[] = $child;
                }
            }
        }

        $normalized = array_values(array_filter(
            array_intersect(self::keys(), array_keys($selected)),
            static fn (string $key): bool => ! self::isTeamHidden($key)
        ));
        sort($normalized);

        return $normalized;
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string>
     */
    public static function impliedCoarse(array $permissions): array
    {
        $coarse = [];
        foreach (self::normalize($permissions) as $permission) {
            foreach (self::definitions()[$permission]['implies'] ?? [] as $key) {
                $coarse[$key] = true;
            }
        }

        return array_values(array_intersect(StorePermission::all(), array_keys($coarse)));
    }

    /**
     * @param  list<string>  $permissions
     */
    public static function matchPreset(array $permissions): string
    {
        $normalized = self::normalize($permissions);
        foreach ([self::PRESET_VIEW, self::PRESET_OPERATIONS, self::PRESET_FULL_OPERATIONAL] as $preset) {
            $presetKeys = self::normalize(self::presets()[$preset]);
            if ($normalized === $presetKeys) {
                return $preset;
            }
        }

        return self::PRESET_CUSTOM;
    }

    /**
     * @return list<array{key: string, label: string, short: string, description: string}>
     */
    public static function presetOptions(): array
    {
        return [
            [
                'key' => self::PRESET_FULL_OPERATIONAL,
                'label' => 'Full operational access',
                'short' => 'Full',
                'description' => 'Catalog, orders, fulfillment, and day-to-day store settings. Sensitive money and admin controls stay off.',
            ],
            [
                'key' => self::PRESET_OPERATIONS,
                'label' => 'Operations access',
                'short' => 'Operations',
                'description' => 'Work orders, stock, customer follow-up, and the delivery hub. Sensitive money and admin controls stay off.',
            ],
            [
                'key' => self::PRESET_VIEW,
                'label' => 'View only',
                'short' => 'View',
                'description' => 'See products, orders, and customers. Cannot change records.',
            ],
            [
                'key' => self::PRESET_CUSTOM,
                'label' => 'Custom access',
                'short' => 'Custom',
                'description' => 'Start with basic view permissions, then fine-tune access from the member panel.',
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string, permissions: list<array{key: string, label: string, sensitive: bool}>}>
     */
    public static function groups(): array
    {
        $groups = [];
        foreach (self::groupMeta() as $groupKey => $meta) {
            $permissions = [];
            foreach (self::definitions() as $key => $definition) {
                if (($definition['group'] ?? null) !== $groupKey) {
                    continue;
                }
                if (($definition['sensitive'] ?? false) === true) {
                    continue;
                }
                if (self::isTeamHidden($key)) {
                    continue;
                }
                $permissions[] = [
                    'key' => $key,
                    'label' => $definition['label'],
                    'sensitive' => false,
                ];
            }
            $groups[] = [
                'key' => $groupKey,
                'label' => $meta['label'],
                'permissions' => $permissions,
            ];
        }

        return $groups;
    }

    /**
     * @return list<array{key: string, label: string, sensitive: bool}>
     */
    public static function sensitivePermissions(): array
    {
        $permissions = [];
        foreach (self::definitions() as $key => $definition) {
            if (($definition['sensitive'] ?? false) !== true) {
                continue;
            }
            if (self::isTeamHidden($key)) {
                continue;
            }
            $permissions[] = [
                'key' => $key,
                'label' => $definition['label'],
                'sensitive' => true,
            ];
        }

        return $permissions;
    }

    /**
     * Compact View / Manage modules for the team inspector.
     *
     * View and manage keys are explicit. Groups without a view permission
     * leave `view` empty instead of promoting a mutation key.
     *
     * @return list<array{key: string, label: string, symbol: string, view: list<string>, manage: list<string>}>
     */
    public static function modules(): array
    {
        return [
            [
                'key' => 'products',
                'label' => 'Products and inventory',
                'symbol' => 'P',
                'view' => ['products.view'],
                'manage' => ['products.edit', 'products.prices', 'products.inventory'],
            ],
            [
                'key' => 'orders',
                'label' => 'Orders',
                'symbol' => 'O',
                'view' => ['orders.view'],
                'manage' => ['orders.draft', 'orders.edit', 'orders.cancel'],
            ],
            [
                'key' => 'fulfillment',
                'label' => 'Fulfillment',
                'symbol' => 'F',
                'view' => [],
                'manage' => ['fulfillment.fulfill', 'fulfillment.tracking', 'fulfillment.inventory_adjust'],
            ],
            [
                'key' => 'delivery',
                'label' => 'Delivery',
                'symbol' => 'D',
                'view' => [],
                'manage' => ['settings.delivery'],
            ],
            [
                'key' => 'customers',
                'label' => 'Customers and after-sales',
                'symbol' => 'C',
                'view' => ['customers.view'],
                'manage' => ['customers.edit', 'customers.returns', 'customers.exchanges'],
            ],
            [
                'key' => 'locations',
                'label' => 'Locations',
                'symbol' => 'L',
                'view' => [],
                'manage' => ['settings.locations'],
            ],
            [
                'key' => 'discounts',
                'label' => 'Discounts',
                'symbol' => 'S',
                'view' => [],
                'manage' => ['settings.discounts'],
            ],
            [
                'key' => 'taxes',
                'label' => 'Taxes',
                'symbol' => 'T',
                'view' => [],
                'manage' => ['settings.taxes'],
            ],
            [
                'key' => 'website',
                'label' => 'Website integration',
                'symbol' => 'W',
                'view' => ['website.view'],
                'manage' => ['website.manage', 'website.plugin'],
            ],
            [
                'key' => 'team',
                'label' => 'Team',
                'symbol' => 'M',
                'view' => ['team.view'],
                'manage' => [],
            ],
            [
                'key' => 'admin',
                'label' => 'Administration',
                'symbol' => 'A',
                'view' => ['security.view'],
                'manage' => [],
            ],
        ];
    }

    /**
     * @return array{
     *     presets: list<array{key: string, label: string, short: string, description: string, permissions: list<string>}>,
     *     groups: list<array{key: string, label: string, permissions: list<array{key: string, label: string, sensitive: bool}>}>,
     *     modules: list<array{key: string, label: string, symbol: string, view: list<string>, manage: list<string>}>,
     *     sensitive: list<string>,
     *     sensitive_permissions: list<array{key: string, label: string, sensitive: bool}>,
     *     dependencies: array<string, list<string>>,
     *     children: array<string, list<string>>,
     *     job_titles: list<string>
     * }
     */
    public static function catalog(): array
    {
        $presets = [];
        foreach (self::presetOptions() as $option) {
            $presets[] = [
                ...$option,
                'permissions' => self::normalize(self::presets()[$option['key']] ?? []),
            ];
        }

        return [
            'presets' => $presets,
            'groups' => self::groups(),
            'modules' => self::modules(),
            'sensitive' => array_column(self::sensitivePermissions(), 'key'),
            'sensitive_permissions' => self::sensitivePermissions(),
            'dependencies' => self::dependencies(),
            'children' => self::children(),
            'job_titles' => self::jobTitleSuggestions(),
        ];
    }

    /**
     * Keys owners keep, but teammates cannot see or receive until SaaS billing is live.
     */
    public static function isTeamHidden(string $permission): bool
    {
        return (self::definitions()[$permission]['team_hidden'] ?? false) === true;
    }

    /**
     * @return list<string>
     */
    public static function teamGrantableKeys(): array
    {
        return array_values(array_filter(
            self::keys(),
            static fn (string $key): bool => ! self::isTeamHidden($key)
        ));
    }

    public static function canGrantTeamManage(User $actor, Store $store): bool
    {
        return $actor->hasRole('admin') || $actor->roleInStore($store) === Store::ROLE_OWNER;
    }

    /**
     * Keys only a store owner (or platform admin) may grant to teammates.
     *
     * @return list<string>
     */
    public static function ownerGrantOnlyKeys(): array
    {
        return ['team.manage'];
    }

    /**
     * Keys this actor may add or remove on another teammate.
     *
     * Owners can grant any team-visible key. Members with team manage can only
     * grant keys they already hold, and can never grant team.manage.
     *
     * @return list<string>
     */
    public static function grantableKeysFor(User $actor, Store $store): array
    {
        $all = self::teamGrantableKeys();

        if ($actor->hasRole('admin') || $actor->roleInStore($store) === Store::ROLE_OWNER) {
            return $all;
        }

        $held = array_fill_keys(StorePermissionResolver::granularFor($actor, $store), true);

        $ownerGrantOnly = array_fill_keys(self::ownerGrantOnlyKeys(), true);

        return array_values(array_filter(
            $all,
            static fn (string $key): bool => isset($held[$key]) && ! isset($ownerGrantOnly[$key])
        ));
    }

    /**
     * Apply an access change without letting the actor create or revoke keys they cannot grant.
     *
     * @param  list<string>  $existing
     * @param  list<string>  $requested
     * @param  list<string>  $grantable
     * @return list<string>
     */
    public static function mergeEditablePermissions(array $existing, array $requested, array $grantable): array
    {
        $grantableSet = array_fill_keys($grantable, true);
        $requestedSet = array_fill_keys(self::normalize($requested), true);
        $existingSet = array_fill_keys(self::normalize($existing), true);
        $merged = [];

        foreach (self::teamGrantableKeys() as $key) {
            if (isset($grantableSet[$key])) {
                if (isset($requestedSet[$key])) {
                    $merged[] = $key;
                }

                continue;
            }

            if (isset($existingSet[$key])) {
                $merged[] = $key;
            }
        }

        return self::normalize($merged);
    }

    public static function membershipLabel(?string $role, ?string $preset = null): string
    {
        if ($role === Store::ROLE_OWNER || $role === self::MEMBERSHIP_OWNER) {
            return 'Owner';
        }

        $preset = $preset ?: self::legacyPreset($role);
        if ($preset === self::PRESET_CUSTOM) {
            return 'Custom Access';
        }

        return 'Team Member';
    }

    public static function accessSummary(?string $role, ?string $preset = null): string
    {
        if ($role === Store::ROLE_OWNER) {
            return 'Full store control, including owner-only actions.';
        }

        $preset = $preset ?: self::legacyPreset($role);

        return match ($preset) {
            self::PRESET_FULL_OPERATIONAL => 'Full operational access for this store.',
            self::PRESET_OPERATIONS => 'Operations access for this store.',
            self::PRESET_VIEW => 'View-only access for this store.',
            default => 'Custom access for this store.',
        };
    }

    public static function legacyPreset(?string $role): string
    {
        return match ($role) {
            Store::ROLE_MANAGER => self::PRESET_FULL_OPERATIONAL,
            Store::ROLE_STAFF => self::PRESET_VIEW,
            default => self::PRESET_CUSTOM,
        };
    }

    public static function isOwnerRole(?string $role): bool
    {
        return $role === Store::ROLE_OWNER;
    }

    public static function isUsableMembershipStatus(?string $status, ?string $role = null): bool
    {
        if (self::isOwnerRole($role)) {
            return true;
        }

        return $status === null || $status === '' || $status === self::STATUS_ACTIVE;
    }

    /**
     * Granular keys for legacy manager memberships that have no stored permission rows.
     *
     * @return list<string>
     */
    public static function managerFallbackKeys(): array
    {
        $keys = array_values(array_filter(
            self::presets()[self::PRESET_FULL_OPERATIONAL],
            static fn (string $key): bool => ! str_starts_with($key, 'settings.')
        ));

        $keys[] = 'products.delete';
        $keys[] = 'orders.payments';
        $keys[] = 'fulfillment.labels.purchase';
        $keys[] = 'fulfillment.labels.cancel';
        $keys[] = 'customers.refunds';

        return self::normalize($keys);
    }

    /**
     * @return list<string>
     */
    public static function staffFallbackKeys(): array
    {
        return self::normalize(self::presets()[self::PRESET_VIEW]);
    }

    /**
     * @return array<string, array{label: string, group: string, sensitive?: bool, team_hidden?: bool, implies: list<string>}>
     */
    private static function definitions(): array
    {
        return [
            'products.view' => ['label' => 'View products', 'group' => 'products', 'implies' => [StorePermission::CATALOG_VIEW]],
            'products.edit' => ['label' => 'Create and edit products', 'group' => 'products', 'implies' => [StorePermission::CATALOG_VIEW, StorePermission::CATALOG_MANAGE]],
            'products.prices' => ['label' => 'Edit prices', 'group' => 'products', 'implies' => [StorePermission::CATALOG_VIEW]],
            'products.inventory' => ['label' => 'Manage inventory', 'group' => 'products', 'implies' => [StorePermission::CATALOG_VIEW]],
            'products.import' => ['label' => 'Import products', 'group' => 'products', 'implies' => [StorePermission::CATALOG_VIEW, StorePermission::IMPORTS_VIEW, StorePermission::IMPORTS_MANAGE]],
            'products.delete' => ['label' => 'Delete products', 'group' => 'products', 'sensitive' => true, 'implies' => [StorePermission::CATALOG_VIEW]],
            'orders.view' => ['label' => 'View orders', 'group' => 'orders', 'implies' => [StorePermission::ORDERS_VIEW]],
            'orders.draft' => ['label' => 'Create draft orders', 'group' => 'orders', 'implies' => [StorePermission::ORDERS_VIEW]],
            'orders.edit' => ['label' => 'Edit orders', 'group' => 'orders', 'implies' => [StorePermission::ORDERS_VIEW, StorePermission::ORDERS_MANAGE]],
            'orders.payments' => ['label' => 'Record manual payments', 'group' => 'orders', 'sensitive' => true, 'implies' => [StorePermission::ORDERS_VIEW]],
            'orders.cancel' => ['label' => 'Cancel orders', 'group' => 'orders', 'implies' => [StorePermission::ORDERS_VIEW]],
            'fulfillment.fulfill' => ['label' => 'Fulfill orders', 'group' => 'fulfillment', 'implies' => [StorePermission::ORDERS_VIEW, StorePermission::CATALOG_VIEW]],
            'fulfillment.labels.purchase' => ['label' => 'Purchase shipping labels', 'group' => 'fulfillment', 'sensitive' => true, 'implies' => [StorePermission::ORDERS_VIEW]],
            'fulfillment.labels.cancel' => ['label' => 'Cancel labels', 'group' => 'fulfillment', 'sensitive' => true, 'implies' => [StorePermission::ORDERS_VIEW]],
            'fulfillment.tracking' => ['label' => 'Update tracking', 'group' => 'fulfillment', 'implies' => [StorePermission::ORDERS_VIEW]],
            'fulfillment.inventory_adjust' => ['label' => 'Adjust inventory during fulfillment', 'group' => 'fulfillment', 'implies' => [StorePermission::ORDERS_VIEW, StorePermission::CATALOG_VIEW]],
            'customers.view' => ['label' => 'View customers', 'group' => 'customers', 'implies' => [StorePermission::CUSTOMERS_VIEW]],
            'customers.edit' => ['label' => 'Edit customers', 'group' => 'customers', 'implies' => [StorePermission::CUSTOMERS_VIEW, StorePermission::CUSTOMERS_MANAGE]],
            'customers.returns' => ['label' => 'Manage returns', 'group' => 'customers', 'implies' => [StorePermission::CUSTOMERS_VIEW, StorePermission::ORDERS_VIEW]],
            'customers.exchanges' => ['label' => 'Manage exchanges', 'group' => 'customers', 'implies' => [StorePermission::CUSTOMERS_VIEW, StorePermission::ORDERS_VIEW]],
            'customers.refunds' => ['label' => 'Issue refunds', 'group' => 'customers', 'sensitive' => true, 'implies' => [StorePermission::CUSTOMERS_VIEW, StorePermission::ORDERS_VIEW]],
            'customers.export' => ['label' => 'Export customer information', 'group' => 'customers', 'sensitive' => true, 'implies' => [StorePermission::CUSTOMERS_VIEW]],
            'settings.discounts' => ['label' => 'Manage discounts', 'group' => 'store', 'implies' => []],
            'settings.locations' => ['label' => 'Manage locations', 'group' => 'store', 'implies' => []],
            'settings.delivery' => ['label' => 'Manage delivery options', 'group' => 'store', 'implies' => []],
            'settings.delivery.delete' => ['label' => 'Delete delivery areas and options', 'group' => 'store', 'sensitive' => true, 'implies' => []],
            'settings.taxes' => ['label' => 'Manage taxes', 'group' => 'store', 'implies' => []],
            'settings.carriers' => ['label' => 'Manage carrier connections', 'group' => 'store', 'sensitive' => true, 'implies' => []],
            'settings.payments' => ['label' => 'Manage payment connections', 'group' => 'store', 'sensitive' => true, 'implies' => []],
            'website.view' => ['label' => 'View website connection', 'group' => 'website', 'implies' => [StorePermission::DEVELOPER_API_VIEW]],
            'website.manage' => ['label' => 'Manage WordPress connection', 'group' => 'website', 'implies' => [StorePermission::DEVELOPER_API_VIEW]],
            'website.plugin' => ['label' => 'Download connector plugin', 'group' => 'website', 'implies' => [StorePermission::DEVELOPER_API_VIEW]],
            'website.token' => ['label' => 'Rotate API token', 'group' => 'website', 'sensitive' => true, 'implies' => [StorePermission::DEVELOPER_API_VIEW, StorePermission::DEVELOPER_API_MANAGE]],
            'website.cutover' => ['label' => 'Activate or roll back cutover', 'group' => 'website', 'implies' => [StorePermission::DEVELOPER_API_VIEW]],
            'team.view' => ['label' => 'View team members', 'group' => 'admin', 'implies' => [StorePermission::TEAM_VIEW]],
            'team.manage' => ['label' => 'Manage team members', 'group' => 'admin', 'sensitive' => true, 'implies' => [StorePermission::TEAM_VIEW, StorePermission::TEAM_MANAGE]],
            'stores.create' => ['label' => 'Create new stores', 'group' => 'admin', 'sensitive' => true, 'team_hidden' => true, 'implies' => []],
            'security.view' => ['label' => 'View security activity', 'group' => 'admin', 'implies' => [StorePermission::SECURITY_VIEW]],
            'billing.view' => ['label' => 'View billing', 'group' => 'admin', 'team_hidden' => true, 'implies' => [StorePermission::BILLING_VIEW]],
            'billing.manage' => ['label' => 'Manage billing', 'group' => 'admin', 'sensitive' => true, 'team_hidden' => true, 'implies' => [StorePermission::BILLING_VIEW, StorePermission::BILLING_MANAGE]],
        ];
    }

    /**
     * @return array<string, array{label: string}>
     */
    private static function groupMeta(): array
    {
        return [
            'products' => ['label' => 'Products and inventory'],
            'orders' => ['label' => 'Orders'],
            'fulfillment' => ['label' => 'Fulfillment'],
            'customers' => ['label' => 'Customers and after-sales'],
            'store' => ['label' => 'Store management'],
            'website' => ['label' => 'Website integration'],
            'admin' => ['label' => 'Administration'],
        ];
    }
}
