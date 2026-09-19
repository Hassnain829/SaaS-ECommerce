<?php

namespace App\Services\Settings;

use App\Models\Store;
use App\Models\StoreUser;
use App\Models\User;
use App\Notifications\TeamMemberInvitedNotification;
use App\Support\StorePermissionResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StoreMemberInvitationService
{
    public function send(User $member, User $invitedBy, Collection $stores): void
    {
        $member->notifyNow(new TeamMemberInvitedNotification(
            inviterName: $invitedBy->name,
            storeLabel: $this->storeLabel($stores),
            needsPassword: (bool) $member->must_set_password,
            storeIds: self::encodeStoreIds($stores),
        ));
    }

    /**
     * @param  list<int>  $storeIds
     * @return Collection<int, Store>
     */
    public function pendingStores(User $member, array $storeIds = []): Collection
    {
        $query = $member->memberStores()
            ->wherePivot('status', StoreUser::STATUS_INVITED);

        if ($storeIds !== []) {
            $query->whereIn('stores.id', $storeIds);
        }

        return $query
            ->orderBy('stores.name')
            ->get();
    }

    /**
     * @param  list<int>  $storeIds
     * @return Collection<int, Store>
     */
    public function accept(User $member, ?string $password = null, array $storeIds = []): Collection
    {
        $pending = $this->pendingStores($member, $storeIds);

        if ($pending->isEmpty()) {
            return $pending;
        }

        if ($member->must_set_password) {
            if (! filled($password)) {
                throw ValidationException::withMessages([
                    'password' => 'Choose a password to finish setting up your account.',
                ]);
            }

            $member->forceFill([
                'password' => $password,
                'must_set_password' => false,
                'is_active' => true,
                'remember_token' => Str::random(60),
            ])->save();
        } elseif (! $member->is_active) {
            $member->forceFill(['is_active' => true])->save();
        }

        if (! $member->hasVerifiedEmail()) {
            $member->markEmailAsVerified();
        }

        foreach ($pending as $store) {
            $store->members()->updateExistingPivot($member->id, [
                'status' => StoreUser::STATUS_ACTIVE,
            ]);
            StorePermissionResolver::forget($member, $store);
        }

        return $pending;
    }

    public function discardUnusedInviteAccount(User $member): void
    {
        $member->refresh();

        if (! $member->must_set_password) {
            return;
        }

        if ($member->last_login_at !== null) {
            return;
        }

        if (! $member->hasRole('user')) {
            return;
        }

        if ($member->stores()->exists() || $member->memberStores()->exists()) {
            return;
        }

        $member->delete();
    }

    /**
     * @param  Collection<int, Store>  $stores
     */
    public function storeLabel(Collection $stores): string
    {
        $names = $stores->pluck('name')->filter()->values()->all();

        return match (count($names)) {
            0 => 'a store',
            1 => (string) $names[0],
            2 => $names[0].' and '.$names[1],
            default => implode(', ', array_slice($names, 0, -1)).', and '.$names[array_key_last($names)],
        };
    }

    public static function encodeStoreIds(iterable $stores): string
    {
        $ids = [];
        foreach ($stores as $store) {
            $id = $store instanceof Store ? (int) $store->id : (int) $store;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        $ids = array_values($ids);
        sort($ids);

        return implode(',', $ids);
    }

    /**
     * @return list<int>
     */
    public static function decodeStoreIds(?string $raw): array
    {
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $ids = [];
        foreach (explode(',', $raw) as $part) {
            $id = (int) trim($part);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }
}
