<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\Checkout;
use Illuminate\Support\Collection;

/**
 * Builds FedEx package lines from checkout cart contents for negotiated rate quotes.
 */
final class FedExCheckoutPackageBuilder
{
    /**
     * @return array{
     *     packages: list<array{weight: float, weight_unit: string, length: float, width: float, height: float, dimension_unit: string}>,
     *     fingerprint: string,
     *     item_count: int,
     *     total_quantity: int
     * }
     */
    public function buildFromCheckout(Checkout $checkout): array
    {
        $checkout->loadMissing(['items.variant.product']);
        $defaults = (array) config('carriers.fedex.checkout_default_package', []);
        $defaultWeight = max(0.01, (float) ($defaults['weight'] ?? 1));
        $defaultLength = max(1.0, (float) ($defaults['length'] ?? 9));
        $defaultWidth = max(1.0, (float) ($defaults['width'] ?? 6));
        $defaultHeight = max(1.0, (float) ($defaults['height'] ?? 2));

        $packages = [];
        $fingerprintParts = [];
        $totalQuantity = 0;

        /** @var Collection<int, mixed> $items */
        $items = $checkout->items;

        foreach ($items as $item) {
            $qty = max(1, (int) ($item->quantity ?? 1));
            $totalQuantity += $qty;
            $variant = $item->variant;
            $product = $variant?->product;

            $unitWeight = $this->resolveWeight($item, $variant, $product, $defaultWeight);
            $dims = $this->resolveDimensions($item, $variant, $product, $defaultLength, $defaultWidth, $defaultHeight);

            // One physical package line per unit keeps MPS accurate when weights differ by line.
            for ($i = 0; $i < $qty; $i++) {
                $packages[] = [
                    'weight' => $unitWeight,
                    'weight_unit' => 'LB',
                    'length' => $dims['length'],
                    'width' => $dims['width'],
                    'height' => $dims['height'],
                    'dimension_unit' => 'IN',
                ];
            }

            $fingerprintParts[] = implode(':', [
                (string) ($item->product_variant_id ?? $item->variant_id ?? $item->product_id ?? $item->id),
                (string) $qty,
                number_format($unitWeight, 3, '.', ''),
                number_format($dims['length'], 2, '.', ''),
                number_format($dims['width'], 2, '.', ''),
                number_format($dims['height'], 2, '.', ''),
            ]);
        }

        if ($packages === []) {
            $packages[] = [
                'weight' => $defaultWeight,
                'weight_unit' => 'LB',
                'length' => $defaultLength,
                'width' => $defaultWidth,
                'height' => $defaultHeight,
                'dimension_unit' => 'IN',
            ];
            $fingerprintParts[] = 'empty-cart-default';
        }

        // Cap extreme MPS fan-out for checkout performance; aggregate overflow into last package by weight.
        $maxPackages = max(1, (int) config('carriers.fedex.checkout_max_package_lines', 20));
        if (count($packages) > $maxPackages) {
            $kept = array_slice($packages, 0, $maxPackages - 1);
            $overflow = array_slice($packages, $maxPackages - 1);
            $overflowWeight = array_sum(array_map(static fn (array $p): float => (float) $p['weight'], $overflow));
            $kept[] = [
                'weight' => max(0.01, $overflowWeight),
                'weight_unit' => 'LB',
                'length' => $defaultLength,
                'width' => $defaultWidth,
                'height' => $defaultHeight,
                'dimension_unit' => 'IN',
            ];
            $packages = $kept;
            $fingerprintParts[] = 'capped:'.$maxPackages;
        }

        return [
            'packages' => $packages,
            'fingerprint' => hash('sha256', implode('|', $fingerprintParts)),
            'item_count' => $items->count(),
            'total_quantity' => $totalQuantity,
        ];
    }

    private function resolveWeight(mixed $item, mixed $variant, mixed $product, float $default): float
    {
        foreach ([
            data_get($item, 'weight'),
            data_get($item, 'meta.weight'),
            data_get($variant, 'weight'),
            data_get($variant, 'meta.weight'),
            data_get($variant, 'meta.shipping_weight'),
            data_get($product, 'weight'),
            data_get($product, 'meta.weight'),
            data_get($product, 'meta.shipping_weight'),
        ] as $candidate) {
            if (is_numeric($candidate) && (float) $candidate > 0) {
                return max(0.01, (float) $candidate);
            }
        }

        return $default;
    }

    /**
     * @return array{length: float, width: float, height: float}
     */
    private function resolveDimensions(mixed $item, mixed $variant, mixed $product, float $l, float $w, float $h): array
    {
        $length = $this->firstPositive([
            data_get($item, 'length'),
            data_get($variant, 'meta.length'),
            data_get($product, 'meta.length'),
        ], $l);
        $width = $this->firstPositive([
            data_get($item, 'width'),
            data_get($variant, 'meta.width'),
            data_get($product, 'meta.width'),
        ], $w);
        $height = $this->firstPositive([
            data_get($item, 'height'),
            data_get($variant, 'meta.height'),
            data_get($product, 'meta.height'),
        ], $h);

        return [
            'length' => $length,
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * @param  list<mixed>  $candidates
     */
    private function firstPositive(array $candidates, float $default): float
    {
        foreach ($candidates as $candidate) {
            if (is_numeric($candidate) && (float) $candidate > 0) {
                return max(1.0, (float) $candidate);
            }
        }

        return $default;
    }
}
