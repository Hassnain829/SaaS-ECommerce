<?php

namespace App\Services\Settings;

use App\Models\Store;
use App\Models\StoreMemberPermission;
use App\Models\User;
use App\Support\StoreMemberAccess;
use App\Support\StorePermissionResolver;
use Illuminate\Support\Facades\DB;

final class StoreMemberPermissionSync
{
    /**
     * @param  list<string>|null  $permissions
     * @param  list<int>|null  $locationIds  Unused — location scope is not enforced; always persisted as null.
     * @return list<string>
     */
    public function sync(
        Store $store,
        User $member,
        ?array $permissions,
        ?string $preset = null,
        ?string $jobTitle = null,
        ?array $locationIds = null,
        ?string $status = null,
    ): array {
        $preset = in_array($preset, StoreMemberAccess::presetKeys(), true)
            ? $preset
            : StoreMemberAccess::PRESET_CUSTOM;

        $selected = $preset === StoreMemberAccess::PRESET_CUSTOM
            ? ($permissions ?? [])
            : StoreMemberAccess::presets()[$preset];

        $normalized = StoreMemberAccess::normalize($selected);
        $matchedPreset = StoreMemberAccess::matchPreset($normalized);

        // Location scope is intentionally not part of the access contract yet.
        unset($locationIds);

        DB::transaction(function () use ($store, $member, $normalized, $matchedPreset, $jobTitle, $status): void {
            StoreMemberPermission::query()
                ->where('store_id', $store->id)
                ->where('user_id', $member->id)
                ->delete();

            foreach ($normalized as $permission) {
                StoreMemberPermission::query()->create([
                    'store_id' => $store->id,
                    'user_id' => $member->id,
                    'permission' => $permission,
                ]);
            }

            $pivot = [
                'role' => Store::ROLE_MEMBER,
                'job_title' => $jobTitle !== null && $jobTitle !== '' ? $jobTitle : null,
                'access_preset' => $matchedPreset,
                'location_ids' => null,
            ];

            if ($status !== null && $status !== '') {
                $pivot['status'] = $status;
            }

            $store->members()->updateExistingPivot($member->id, $pivot);
        });

        StorePermissionResolver::forget($member, $store);

        return $normalized;
    }

    public function forget(Store $store, User $member): void
    {
        DB::transaction(function () use ($store, $member): void {
            StoreMemberPermission::query()
                ->where('store_id', $store->id)
                ->where('user_id', $member->id)
                ->delete();

            $store->members()->detach($member->id);
        });

        StorePermissionResolver::forget($member, $store);
    }
}
