<?php

namespace App\Services\Billing;

use App\Models\SaasPackage;
use App\Models\Store;
use App\Models\StoreSubscription;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StoreSubscriptionService
{
    /**
     * Stores without a subscription row remain accessible (grandfathered until an admin assigns entitlement).
     * Suspended and expired stores are blocked. Past access_ends_at on trial/active flips to expired.
     */
    public function isAccessible(Store $store): bool
    {
        $subscription = $this->subscriptionFor($store);

        if (! $subscription) {
            return true;
        }

        $this->refreshStoreStatus($subscription);

        $subscription->refresh();

        if ($subscription->isSuspended() || $subscription->isExpiredStatus()) {
            return false;
        }

        return true;
    }

    public function grantTrial(
        Store $store,
        int $days,
        ?SaasPackage $package = null,
        ?User $assignedBy = null,
        ?string $notes = null,
        bool $extendFromExisting = true,
    ): StoreSubscription {
        $days = max(1, $days);

        return DB::transaction(function () use ($store, $days, $package, $assignedBy, $notes, $extendFromExisting) {
            $subscription = $this->lockOrCreate($store);

            $base = now();
            if ($extendFromExisting && $subscription->access_ends_at && $subscription->access_ends_at->gt($base)) {
                $base = $subscription->access_ends_at->copy();
            }

            $endsAt = $base->copy()->addDays($days);

            $subscription->fill([
                'package_id' => $package?->id ?? $subscription->package_id,
                'status' => StoreSubscription::STATUS_TRIAL,
                'trial_days' => $days,
                'trial_ends_at' => $endsAt,
                'access_ends_at' => $endsAt,
                'notes' => $notes ?? $subscription->notes,
                'assigned_by' => $assignedBy?->id ?? $subscription->assigned_by,
            ]);
            $subscription->save();

            return $subscription->fresh(['package', 'store']);
        });
    }

    public function assignPackage(
        Store $store,
        ?SaasPackage $package,
        ?User $assignedBy = null,
        ?string $notes = null,
        ?CarbonInterface $accessEndsAt = null,
        string $status = StoreSubscription::STATUS_ACTIVE,
    ): StoreSubscription {
        if (! in_array($status, [
            StoreSubscription::STATUS_TRIAL,
            StoreSubscription::STATUS_ACTIVE,
        ], true)) {
            $status = StoreSubscription::STATUS_ACTIVE;
        }

        return DB::transaction(function () use ($store, $package, $assignedBy, $notes, $accessEndsAt, $status) {
            $subscription = $this->lockOrCreate($store);

            $subscription->fill([
                'package_id' => $package?->id,
                'status' => $status,
                'notes' => $notes ?? $subscription->notes,
                'assigned_by' => $assignedBy?->id ?? $subscription->assigned_by,
            ]);

            if ($accessEndsAt !== null) {
                $subscription->access_ends_at = $accessEndsAt;
                if ($status === StoreSubscription::STATUS_TRIAL) {
                    $subscription->trial_ends_at = $accessEndsAt;
                }
            }

            $subscription->save();

            return $subscription->fresh(['package', 'store']);
        });
    }

    public function suspend(Store $store, ?User $assignedBy = null, ?string $notes = null): StoreSubscription
    {
        return DB::transaction(function () use ($store, $assignedBy, $notes) {
            $subscription = $this->lockOrCreate($store);

            $subscription->fill([
                'status' => StoreSubscription::STATUS_SUSPENDED,
                'notes' => $notes ?? $subscription->notes,
                'assigned_by' => $assignedBy?->id ?? $subscription->assigned_by,
            ]);
            $subscription->save();

            return $subscription->fresh(['package', 'store']);
        });
    }

    public function reactivate(
        Store $store,
        ?User $assignedBy = null,
        ?string $notes = null,
        ?int $grantTrialDays = null,
        ?SaasPackage $package = null,
    ): StoreSubscription {
        if ($grantTrialDays !== null && $grantTrialDays > 0) {
            return $this->grantTrial($store, $grantTrialDays, $package, $assignedBy, $notes, true);
        }

        return DB::transaction(function () use ($store, $assignedBy, $notes, $package) {
            $subscription = $this->lockOrCreate($store);

            $status = StoreSubscription::STATUS_ACTIVE;
            if ($subscription->access_ends_at && $subscription->access_ends_at->lte(now())) {
                $status = StoreSubscription::STATUS_EXPIRED;
            } elseif ($subscription->trial_ends_at && $subscription->trial_ends_at->gt(now())
                && (! $subscription->access_ends_at || $subscription->access_ends_at->equalTo($subscription->trial_ends_at))) {
                $status = StoreSubscription::STATUS_TRIAL;
            }

            $subscription->fill([
                'package_id' => $package?->id ?? $subscription->package_id,
                'status' => $status,
                'notes' => $notes ?? $subscription->notes,
                'assigned_by' => $assignedBy?->id ?? $subscription->assigned_by,
            ]);
            $subscription->save();

            $this->refreshStoreStatus($subscription);

            return $subscription->fresh(['package', 'store']);
        });
    }

    public function refreshExpiredStatuses(): int
    {
        $updated = 0;

        StoreSubscription::query()
            ->whereIn('status', [StoreSubscription::STATUS_TRIAL, StoreSubscription::STATUS_ACTIVE])
            ->whereNotNull('access_ends_at')
            ->where('access_ends_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (&$updated): void {
                foreach ($rows as $subscription) {
                    if ($this->refreshStoreStatus($subscription)) {
                        $updated++;
                    }
                }
            });

        return $updated;
    }

    public function refreshStoreStatus(StoreSubscription $subscription): bool
    {
        if ($subscription->status === StoreSubscription::STATUS_SUSPENDED) {
            return false;
        }

        if (in_array($subscription->status, [StoreSubscription::STATUS_TRIAL, StoreSubscription::STATUS_ACTIVE], true)
            && $subscription->accessHasEnded()) {
            $subscription->status = StoreSubscription::STATUS_EXPIRED;
            $subscription->save();

            return true;
        }

        return false;
    }

    public function subscriptionFor(Store $store): ?StoreSubscription
    {
        if (! Schema::hasTable('store_subscriptions')) {
            return null;
        }

        if ($store->relationLoaded('subscription')) {
            return $store->subscription;
        }

        return StoreSubscription::query()->where('store_id', $store->id)->first();
    }

    protected function lockOrCreate(Store $store): StoreSubscription
    {
        $existing = StoreSubscription::query()
            ->where('store_id', $store->id)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return $existing;
        }

        return StoreSubscription::query()->create([
            'store_id' => $store->id,
            'status' => StoreSubscription::STATUS_ACTIVE,
        ]);
    }
}
