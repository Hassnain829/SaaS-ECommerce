<?php

namespace App\Services\Delivery;

use App\Models\Store;
use App\Support\ProductTypeBehavior;

/**
 * Resolves catalog shipping weight for rates and order line snapshots.
 *
 * Exact catalog values always win over the store checkout fallback.
 */
final class ShippingWeightResolver
{
    public function __construct(
        private readonly StoreShippingPreferences $shippingPreferences,
    ) {}

    /**
     * Backward-compatible alias for exact catalog resolution (no store fallback).
     */
    public function resolve(mixed $product, mixed $variant = null): ?float
    {
        return $this->resolveExact($product, $variant);
    }

    public function resolveExact(mixed $product, mixed $variant = null): ?float
    {
        $variantExact = $this->resolveExactVariantLevel($variant);
        if ($variantExact !== null) {
            return $variantExact;
        }

        return $this->resolveExactProductLevel($product);
    }

    public function resolveExactVariantLevel(mixed $variant): ?float
    {
        if ($variant === null) {
            return null;
        }

        foreach ([
            data_get($variant, 'meta.shipping_weight'),
            data_get($variant, 'meta.weight'),
            data_get($variant, 'weight'),
        ] as $candidate) {
            $normalized = $this->normalizePositiveWeight($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * Product-level exact weight only (ignores variants). Used by Products filters and bulk missing_only.
     */
    public function resolveExactProductLevel(mixed $product): ?float
    {
        foreach ([
            data_get($product, 'meta.shipping_weight'),
            data_get($product, 'meta.weight'),
            data_get($product, 'weight'),
        ] as $candidate) {
            $normalized = $this->normalizePositiveWeight($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    public function resolveForStore(Store $store, mixed $product, mixed $variant = null): ?float
    {
        $exact = $this->resolveExact($product, $variant);
        if ($exact !== null) {
            return $exact;
        }

        return $this->shippingPreferences->fallbackItemWeight($store);
    }

    public function resolveSnapshot(mixed $product, mixed $variant = null): ?string
    {
        $weight = $this->resolveExact($product, $variant);

        return $weight === null ? null : number_format($weight, 3, '.', '');
    }

    public function resolveSnapshotForStore(Store $store, mixed $product, mixed $variant = null): ?string
    {
        $weight = $this->resolveForStore($store, $product, $variant);

        return $weight === null ? null : number_format($weight, 3, '.', '');
    }

    public function normalizePositiveWeight(mixed $candidate): ?float
    {
        if (! is_numeric($candidate)) {
            return null;
        }

        $value = (float) $candidate;
        if ($value <= 0) {
            return null;
        }

        return max(0.01, round($value, 3));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function persistVariantShippingWeightMeta(array &$meta, mixed $raw): void
    {
        if ($raw === null || $raw === '') {
            unset($meta['shipping_weight'], $meta['weight']);

            return;
        }

        $normalized = $this->normalizePositiveWeight($raw);
        if ($normalized === null) {
            return;
        }

        $meta['shipping_weight'] = $normalized;
        unset($meta['weight']);
    }

    public function itemRequiresShipping(mixed $product): bool
    {
        if ($product === null) {
            return false;
        }

        if (is_object($product) && array_key_exists('requires_shipping', $product->getAttributes())) {
            return (bool) $product->requires_shipping;
        }

        return ProductTypeBehavior::requiresShipping(
            (string) (data_get($product, 'product_type') ?: ProductTypeBehavior::PHYSICAL)
        );
    }
}
