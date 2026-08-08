<?php

namespace App\Services\Delivery;

/**
 * Resolves catalog shipping weight for rates and order line snapshots.
 */
final class ShippingWeightResolver
{
    public function resolve(mixed $product, mixed $variant = null): ?float
    {
        foreach ([
            data_get($variant, 'meta.shipping_weight'),
            data_get($variant, 'meta.weight'),
            data_get($variant, 'weight'),
            data_get($product, 'meta.shipping_weight'),
            data_get($product, 'meta.weight'),
            data_get($product, 'weight'),
        ] as $candidate) {
            if (is_numeric($candidate) && (float) $candidate > 0) {
                return max(0.01, (float) $candidate);
            }
        }

        return null;
    }

    public function resolveSnapshot(mixed $product, mixed $variant = null): ?string
    {
        $weight = $this->resolve($product, $variant);

        return $weight === null ? null : number_format($weight, 3, '.', '');
    }
}
