<?php

namespace App\Support;

use App\Models\Store;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

final class OnboardingStoreSession
{
    /**
     * @var list<string>
     */
    public const KEYS = [
        'onboarding_store_draft',
        'onboarding_store_id',
        'onboarding_last_store_id',
        'onboarding_product_draft',
        'onboarding_product_id',
        'onboarding_last_product_id',
    ];

    public static function forget(Session $session): void
    {
        $session->forget(self::KEYS);
    }

    public static function forgetIfStore(Session $session, int $storeId): void
    {
        if ((int) $session->get('onboarding_store_id') === $storeId) {
            self::forget($session);
        }
    }

    /**
     * Drop onboarding context when the user can no longer use that store
     * (suspended, removed, or the store is no longer in their active list).
     *
     * @param  Collection<int, Store>  $availableStores
     */
    public static function forgetIfInaccessible(Session $session, Collection $availableStores): void
    {
        $onboardingStoreId = (int) $session->get('onboarding_store_id');

        if ($onboardingStoreId && ! $availableStores->contains('id', $onboardingStoreId)) {
            self::forget($session);
        }
    }
}
