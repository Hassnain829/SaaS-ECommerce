<?php

namespace App\Services\Store;

use App\Models\Product;
use App\Models\Store;

class StoreCurrencyChangeGuard
{
    /**
     * True when changing currency requires converting existing catalog prices.
     */
    public function requiresCatalogConversion(Store $store): bool
    {
        return Product::query()->where('store_id', $store->id)->exists();
    }

    /**
     * @deprecated Use requiresCatalogConversion(); currency is no longer hard-locked.
     */
    public function isLocked(Store $store): bool
    {
        return false;
    }
}
