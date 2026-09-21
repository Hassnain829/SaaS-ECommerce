<?php

namespace App\Services\Settings;

use App\Models\Store;
use App\Models\StoreUser;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamMemberInvitedNotification;
use App\Support\StorePermissionResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StoreMemberInvitationService
{
    public const TOKEN_BYTES = 32;

    public const EXPIRY_DAYS = 7;

    /**
     * Create (or rotate) invitations and email a single shareable token.
     *
     * @param  Collection<int, Store>  $stores
     */
    public function send(User $member, User $invitedBy, Collection $stores): string
    {
        $stores = $stores->unique('id')->values();
        if ($stores->isEmpty()) {
            throw ValidationException::withMessages([
                'store_ids' => 'Choose at least one store to invite this teammate to.',
            ]);
        }

        $plainToken = $this->issueInvitations($member, $invitedBy, $stores);

        $member->notifyNow(new TeamMemberInvitedNotification(
            inviterName: $invitedBy->name,
            storeLabel: $this->storeLabel($stores),
            needsPassword: (bool) $member->must_set_password,
            inviteToken: $plainToken,
        ));

        return $plainToken;
    }

    /**
     * @param  Collection<int, Store>  $stores
     */
    public function issueInvitations(User $member, User $invitedBy, Collection $stores): string
    {
        $plainToken = Str::random(self::TOKEN_BYTES);
        $tokenHash = $this->hashToken($plainToken);
        $expiresAt = now()->addDays(self::EXPIRY_DAYS);

        DB::transaction(function () use ($member, $invitedBy, $stores, $tokenHash, $expiresAt): void {
            foreach ($stores as $store) {
                $this->revokeActiveFor($member, $store);

                $nextVersion = ((int) TeamInvitation::query()
                    ->where('store_id', $store->id)
                    ->where('user_id', $member->id)
                    ->max('version')) + 1;

                TeamInvitation::query()->create([
                    'store_id' => $store->id,
                    'user_id' => $member->id,
                    'invited_by' => $invitedBy->id,
                    'token_hash' => $tokenHash,
                    'version' => max(1, $nextVersion),
                    'expires_at' => $expiresAt,
                ]);
            }
        });

        return $plainToken;
    }

    /**
     * @return Collection<int, TeamInvitation>
     */
    public function findActiveByToken(string $plainToken): Collection
    {
        $hash = $this->hashToken($plainToken);

        return TeamInvitation::query()
            ->with(['store', 'user', 'inviter'])
            ->where('token_hash', $hash)
            ->active()
            ->orderBy('store_id')
            ->get();
    }

    public function findInviteeByToken(string $plainToken): ?User
    {
        $hash = $this->hashToken($plainToken);

        $invitation = TeamInvitation::query()
            ->where('token_hash', $hash)
            ->orderByDesc('id')
            ->first();

        return $invitation?->user;
    }

    /**
     * Accept every active invitation sharing this token, atomically.
     *
     * @return Collection<int, Store>
     */
    public function acceptByToken(User $member, string $plainToken, ?string $password = null): Collection
    {
        $hash = $this->hashToken($plainToken);

        return DB::transaction(function () use ($member, $hash, $password): Collection {
            $invitations = TeamInvitation::query()
                ->where('token_hash', $hash)
                ->where('user_id', $member->id)
                ->lockForUpdate()
                ->get();

            $active = $invitations->filter(fn (TeamInvitation $invitation): bool => $invitation->isActive());

            if ($active->isEmpty()) {
                return collect();
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

            $acceptedStores = collect();

            foreach ($active as $invitation) {
                $updated = DB::table('store_user')
                    ->where('store_id', $invitation->store_id)
                    ->where('user_id', $member->id)
                    ->where('status', StoreUser::STATUS_INVITED)
                    ->update([
                        'status' => StoreUser::STATUS_ACTIVE,
                        'updated_at' => now(),
                    ]);

                if ($updated !== 1) {
                    continue;
                }

                $invitation->forceFill(['accepted_at' => now()])->save();

                $store = Store::query()->find($invitation->store_id);
                if ($store) {
                    StorePermissionResolver::forget($member, $store);
                    $acceptedStores->push($store);
                }
            }

            return $acceptedStores->unique('id')->values();
        });
    }

    public function revokeActiveFor(User $member, Store $store): void
    {
        TeamInvitation::query()
            ->where('store_id', $store->id)
            ->where('user_id', $member->id)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function revokeAllForMembership(User $member, Store $store): void
    {
        $this->revokeActiveFor($member, $store);
    }

    /**
     * @param  list<int>  $storeIds
     * @return Collection<int, Store>
     *
     * @deprecated Prefer token-based pending invitations.
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

        TeamInvitation::query()->where('user_id', $member->id)->delete();
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

    public function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function inviteUrl(string $plainToken): string
    {
        return route('team-invites.show', ['token' => $plainToken], absolute: true);
    }

    /**
     * @deprecated Legacy signed-URL helpers kept for older tests during migration.
     */
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
     *
     * @deprecated Legacy signed-URL helpers kept for older tests during migration.
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
