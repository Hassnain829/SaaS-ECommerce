<?php

namespace App\Services\Store;

use App\Models\Store;
use InvalidArgumentException;

/**
 * Merchant purge eligibility gate.
 *
 * Today every closed store may be purged by its owner. Future legal/accounting
 * retention rules can live here (e.g. must retain orders until a given date).
 */
final class StorePurgeEligibilityService
{
    public function assertEligibleForMerchantPurge(Store $store): void
    {
        if (! $store->trashed()) {
            throw new InvalidArgumentException('Only a closed store can be permanently deleted.');
        }
    }
}
