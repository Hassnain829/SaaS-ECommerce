<?php

namespace App\Services\Catalog;

use App\Models\Store;
use App\Models\TaxSetting;

class ProductTaxableDefaultResolver
{
    public function forStore(Store $store): bool
    {
        $settings = TaxSetting::query()
            ->where('store_id', $store->id)
            ->first();

        if (! $settings) {
            return true;
        }

        return (bool) $settings->default_product_taxable;
    }
}
