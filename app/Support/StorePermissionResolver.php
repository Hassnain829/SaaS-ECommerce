<?php

namespace App\Support;

use App\Models\Store;
use App\Models\StoreMemberPermission;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class StorePermissionResolver
{
    /**
     * @return list<string>
     */
    public static function permissionsFor(User $user, Store|int|null $store): array
    {
        $storeModel = self::storeModel($store);
        $cacheKey = self::requestCacheKey($user, $storeModel);
        if ($cacheKey !== null) {
            $cached = request()->attributes->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $resolved = self::resolvePermissions($user, $storeModel);
        if ($cacheKey !== null) {
            request()->attributes->set($cacheKey, $resolved);
        }

        return $resolved;
    }

    public static function userCan(?User $user, Store|int|null $store, string $permission): bool
    {
        if (! $user || ! $store || ! StorePermission::exists($permission)) {
            return false;
        }

        return in_array($permission, self::permissionsFor($user, $store), true);
    }

    public static function forget(?User $user, Store|int|null $store): void
    {
        if (! $user) {
            return;
        }

        $cacheKey = self::requestCacheKey($user, self::storeModel($store));
        if ($cacheKey !== null) {
            request()->attributes->remove($cacheKey);
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    public static function userCanAny(?User $user, Store|int|null $store, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (self::userCan($user, $store, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function granularFor(User $user, Store $store): array
    {
        if ($user->hasRole('admin')) {
            return StoreMemberAccess::keys();
        }

        $membership = self::membership($user, $store);
        if (! $membership || ! StoreMemberAccess::isUsableMembershipStatus($membership->status ?? null, $membership->role ?? null)) {
            return [];
        }

        if (($membership->role ?? null) === Store::ROLE_OWNER) {
            return StoreMemberAccess::keys();
        }

        $stored = self::storedPermissions($store, $user);
        if ($stored !== []) {
            return StoreMemberAccess::normalize($stored);
        }

        $role = $membership->role ?? $user->roleInStore($store);

        return match ($role) {
            Store::ROLE_MANAGER => StoreMemberAccess::managerFallbackKeys(),
            Store::ROLE_STAFF => StoreMemberAccess::staffFallbackKeys(),
            default => [],
        };
    }

    /**
     * Sidebar / nav visibility flags. Account, notifications, stores, and home stay reachable.
     *
     * @return array<string, bool>
     */
    public static function navFor(?User $user, Store|int|null $store): array
    {
        $always = [
            'dashboard' => true,
            'stores' => true,
            'settings' => true,
            'notifications' => true,
            'account' => true,
        ];

        if (! $user || ! $store) {
            return $always + [
                'orders' => false,
                'shipments' => false,
                'products' => false,
                'customers' => false,
                'website' => false,
                'locations' => false,
                'delivery' => false,
                'taxes' => false,
                'payments' => false,
                'discounts' => false,
                'team' => false,
                'security' => false,
            ];
        }

        $can = static fn (string ...$keys): bool => self::userCanAny($user, $store, $keys);

        return $always + [
            'orders' => $can('orders.view', StorePermission::ORDERS_VIEW),
            'shipments' => $can('orders.view', StorePermission::ORDERS_VIEW),
            'products' => $can('products.view', StorePermission::CATALOG_VIEW),
            'customers' => $can('customers.view', StorePermission::CUSTOMERS_VIEW),
            'website' => $can('website.view', StorePermission::DEVELOPER_API_VIEW),
            'locations' => $can('settings.locations'),
            'delivery' => $can('settings.delivery'),
            'taxes' => $can('settings.taxes'),
            'payments' => $can('settings.payments'),
            'discounts' => $can('settings.discounts'),
            'team' => $can('team.view', StorePermission::TEAM_VIEW),
            'security' => $can('security.view', StorePermission::SECURITY_VIEW),
        ];
    }

    /**
     * @return list<string>
     */
    private static function resolvePermissions(User $user, ?Store $storeModel): array
    {
        if ($user->hasRole('admin')) {
            return self::allEffective();
        }

        if (! $storeModel) {
            return [];
        }

        $membership = self::membership($user, $storeModel);
        if (! $membership || ! StoreMemberAccess::isUsableMembershipStatus($membership->status ?? null, $membership->role ?? null)) {
            return [];
        }

        if (($membership->role ?? null) === Store::ROLE_OWNER) {
            return self::allEffective();
        }

        $stored = self::storedPermissions($storeModel, $user);
        if ($stored !== []) {
            return self::effectiveFromGranular($stored);
        }

        $role = $membership->role ?? null;
        $granular = match ($role) {
            Store::ROLE_MANAGER => StoreMemberAccess::managerFallbackKeys(),
            Store::ROLE_STAFF => StoreMemberAccess::staffFallbackKeys(),
            default => [],
        };

        $coarse = StorePermission::forRole($role);
        $effective = array_values(array_unique([
            ...$granular,
            ...$coarse,
            ...StoreMemberAccess::impliedCoarse($granular),
        ]));
        sort($effective);

        return $effective;
    }

    /**
     * @return list<string>
     */
    private static function storedPermissions(Store $store, User $user): array
    {
        if (! Schema::hasTable('store_member_permissions')) {
            return [];
        }

        return StoreMemberPermission::query()
            ->where('store_id', $store->id)
            ->where('user_id', $user->id)
            ->orderBy('permission')
            ->pluck('permission')
            ->filter(static fn ($permission): bool => is_string($permission) && StoreMemberAccess::exists($permission))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $granular
     * @return list<string>
     */
    private static function effectiveFromGranular(array $granular): array
    {
        $normalized = StoreMemberAccess::normalize($granular);
        $coarse = StoreMemberAccess::impliedCoarse($normalized);

        $effective = array_values(array_unique([...$normalized, ...$coarse]));
        sort($effective);

        return $effective;
    }

    /**
     * @return list<string>
     */
    private static function allEffective(): array
    {
        $effective = array_values(array_unique([...StorePermission::all(), ...StoreMemberAccess::keys()]));
        sort($effective);

        return $effective;
    }

    /**
     * @return object{role: ?string, status: ?string}|null
     */
    private static function membership(User $user, Store $store): ?object
    {
        $loadedStore = $user->relationLoaded('memberStores')
            ? $user->memberStores->firstWhere('id', $store->id)
            : null;

        if ($loadedStore) {
            return (object) [
                'role' => $loadedStore->pivot?->role,
                'status' => $loadedStore->pivot?->status,
            ];
        }

        $row = $user->memberStores()
            ->where('stores.id', $store->id)
            ->first();

        if (! $row) {
            return null;
        }

        return (object) [
            'role' => $row->pivot?->role,
            'status' => $row->pivot?->status,
        ];
    }

    private static function storeModel(Store|int|null $store): ?Store
    {
        if ($store instanceof Store) {
            return $store;
        }

        if (! $store) {
            return null;
        }

        return Store::query()->find($store);
    }

    private static function requestCacheKey(User $user, ?Store $store): ?string
    {
        if (! $store || ! app()->bound('request')) {
            return null;
        }

        try {
            request();
        } catch (\Throwable) {
            return null;
        }

        return '_store_permissions.'.$user->id.'.'.$store->id;
    }
}
