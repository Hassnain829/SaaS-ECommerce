<?php

namespace App\Support;

use App\Models\Store;
use App\Models\User;

final class StorePermissionResolver
{
    /**
     * @return list<string>
     */
    public static function permissionsFor(User $user, Store|int|null $store): array
    {
        if ($user->hasRole('admin')) {
            return StorePermission::all();
        }

        $role = $user->roleInStore($store);

        return StorePermission::forRole($role);
    }

    public static function userCan(?User $user, Store|int|null $store, string $permission): bool
    {
        if (! $user || ! $store || ! StorePermission::exists($permission)) {
            return false;
        }

        return in_array($permission, self::permissionsFor($user, $store), true);
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
}
