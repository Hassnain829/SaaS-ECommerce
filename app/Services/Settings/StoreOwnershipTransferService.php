<?php

namespace App\Services\Settings;

use App\Models\Store;
use App\Models\StoreMemberPermission;
use App\Models\User;
use App\Notifications\StoreOwnershipTransferredNotification;
use App\Services\SecurityLogRecorder;
use App\Support\StoreMemberAccess;
use App\Support\StorePermissionResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

final class StoreOwnershipTransferService
{
    public function __construct(
        private readonly StoreMemberPermissionSync $permissionSync,
        private readonly SecurityLogRecorder $securityLogs,
    ) {}

    public function transfer(Request $request, Store $store, User $actor, User $newOwner): void
    {
        if ($actor->roleInStore($store) !== Store::ROLE_OWNER) {
            throw ValidationException::withMessages([
                'ownership' => 'Only the current store owner can transfer ownership.',
            ]);
        }

        if ((int) $actor->id === (int) $newOwner->id) {
            throw ValidationException::withMessages([
                'ownership' => 'Choose another teammate to become the new owner.',
            ]);
        }

        $membership = $store->members()->where('users.id', $newOwner->id)->first();

        if (! $membership) {
            throw ValidationException::withMessages([
                'ownership' => 'That person is not a member of this store.',
            ]);
        }

        if (StoreMemberAccess::isOwnerRole($membership->pivot?->role)) {
            throw ValidationException::withMessages([
                'ownership' => 'That teammate is already an owner of this store.',
            ]);
        }

        $status = (string) ($membership->pivot?->status ?: StoreMemberAccess::STATUS_ACTIVE);
        if ($status !== StoreMemberAccess::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'ownership' => 'Only active teammates can receive ownership. Ask them to accept their invitation first, or reactivate their access.',
            ]);
        }

        if ($newOwner->is_active === false) {
            throw ValidationException::withMessages([
                'ownership' => 'That account is deactivated and cannot become the store owner.',
            ]);
        }

        DB::transaction(function () use ($request, $store, $actor, $newOwner): void {
            $ownerCountBefore = $store->members()
                ->wherePivot('role', Store::ROLE_OWNER)
                ->count();

            if ($ownerCountBefore < 1) {
                throw ValidationException::withMessages([
                    'ownership' => 'This store has no owner to transfer from.',
                ]);
            }

            $actorMembership = $store->members()->where('users.id', $actor->id)->first();
            $actorJobTitle = $actorMembership?->pivot?->job_title;

            $this->permissionSync->sync(
                $store,
                $actor,
                StoreMemberAccess::presets()[StoreMemberAccess::PRESET_FULL_OPERATIONAL],
                StoreMemberAccess::PRESET_FULL_OPERATIONAL,
                $actorJobTitle,
                null,
                StoreMemberAccess::STATUS_ACTIVE,
            );

            StoreMemberPermission::query()
                ->where('store_id', $store->id)
                ->where('user_id', $newOwner->id)
                ->delete();

            $store->members()->updateExistingPivot($newOwner->id, [
                'role' => Store::ROLE_OWNER,
                'status' => StoreMemberAccess::STATUS_ACTIVE,
                'access_preset' => StoreMemberAccess::PRESET_FULL_OPERATIONAL,
                'location_ids' => null,
            ]);

            StorePermissionResolver::forget($actor, $store);
            StorePermissionResolver::forget($newOwner, $store);

            $ownerCountAfter = $store->members()
                ->wherePivot('role', Store::ROLE_OWNER)
                ->count();

            if ($ownerCountAfter < 1) {
                throw ValidationException::withMessages([
                    'ownership' => 'Ownership transfer failed: the store must keep at least one owner.',
                ]);
            }

            $this->securityLogs->record(
                $request,
                'store_ownership_transferred',
                store: $store,
                targetUser: $newOwner,
                metadata: [
                    'previous_owner_id' => $actor->id,
                    'new_owner_id' => $newOwner->id,
                    'owner_count_after' => $ownerCountAfter,
                ]
            );
        });

        Notification::send(
            [$actor->fresh(), $newOwner->fresh()],
            new StoreOwnershipTransferredNotification(
                storeName: $store->name,
                previousOwnerName: $actor->name,
                newOwnerName: $newOwner->name,
                actorId: (int) $actor->id,
            )
        );
    }
}
