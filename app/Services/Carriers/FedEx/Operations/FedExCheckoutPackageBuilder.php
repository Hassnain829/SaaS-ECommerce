<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\Checkout;
use App\Models\ShippingPackagePreset;
use App\Models\Store;
use App\Services\Delivery\ShippingWeightResolver;
use App\Services\Delivery\StoreShippingPreferences;
use App\Support\ProductTypeBehavior;
use Illuminate\Support\Collection;

/**
 * Builds FedEx package lines from checkout cart contents for negotiated rate quotes.
 * Never invents weight or dimensions — callers hide FedEx rates when ready is false.
 */
final class FedExCheckoutPackageBuilder
{
    public function __construct(
        private readonly StoreShippingPreferences $shippingPreferences,
        private readonly ShippingWeightResolver $weightResolver,
    ) {}

    /**
     * @return array{
     *     ready: bool,
     *     packages: list<array{weight: float, weight_unit: string, length: float, width: float, height: float, dimension_unit: string}>,
     *     fingerprint: string,
     *     item_count: int,
     *     total_quantity: int,
     *     missing_weights: list<string>,
     *     reason: string|null
     * }
     */
    public function buildFromCheckout(Checkout $checkout): array
    {
        $checkout->loadMissing(['store', 'items.variant.product']);
        $store = $checkout->store;
        if (! $store instanceof Store) {
            return $this->notReady([], 0, 0, [], 'missing_store');
        }

        $prefs = $this->shippingPreferences->get($store);
        $weightUnit = $prefs['weight_unit'] ?: 'LB';
        $defaultPreset = $this->shippingPreferences->defaultPackagePreset($store);

        // Production MVP packing: one FedEx package = sum(item weight × qty) + default preset dimensions.
        // Do not invent one package per unit. Optional ships_separately keeps those units separate.
        if (! $defaultPreset instanceof ShippingPackagePreset || ! $defaultPreset->hasCompleteDimensions()) {
            $fingerprintParts = [];
            $missingWeights = [];
            $totalQuantity = 0;
            $physicalItemCount = 0;

            foreach ($checkout->items as $item) {
                $variant = $item->variant;
                $product = $variant?->product ?? $item->product;
                if (! $this->itemRequiresShipping($item, $product)) {
                    continue;
                }
                $qty = max(1, (int) ($item->quantity ?? 1));
                $totalQuantity += $qty;
                $physicalItemCount++;
                $unitWeight = $this->resolveWeight($store, $item, $variant, $product);
                $label = $this->itemLabel($item, $product);
                if ($unitWeight === null) {
                    $missingWeights[] = $label;
                }
                $fingerprintParts[] = implode(':', [
                    (string) ($item->product_variant_id ?? $item->variant_id ?? $item->product_id ?? $item->id),
                    (string) $qty,
                    $unitWeight === null ? 'missing-weight' : number_format($unitWeight, 3, '.', ''),
                    'missing-dims',
                ]);
            }

            if ($physicalItemCount === 0) {
                return $this->notReady(['empty-cart'], $checkout->items->count(), 0, [], 'empty_cart');
            }

            if ($missingWeights !== []) {
                return $this->notReady($fingerprintParts, $physicalItemCount, $totalQuantity, array_values(array_unique($missingWeights)), 'missing_weights');
            }

            return $this->notReady($fingerprintParts, $physicalItemCount, $totalQuantity, [], 'missing_default_package');
        }

        $presetDims = [
            'length' => max(1.0, (float) $defaultPreset->length),
            'width' => max(1.0, (float) $defaultPreset->width),
            'height' => max(1.0, (float) $defaultPreset->height),
            'dimension_unit' => strtoupper((string) ($defaultPreset->dimension_unit ?: 'IN')),
        ];

        $packages = [];
        $fingerprintParts = [];
        $missingWeights = [];
        $totalQuantity = 0;
        $physicalItemCount = 0;
        $aggregatedWeight = 0.0;
        $hasAggregatedItems = false;

        /** @var Collection<int, mixed> $items */
        $items = $checkout->items;

        foreach ($items as $item) {
            $variant = $item->variant;
            $product = $variant?->product ?? $item->product;

            if (! $this->itemRequiresShipping($item, $product)) {
                continue;
            }

            $qty = max(1, (int) ($item->quantity ?? 1));
            $totalQuantity += $qty;
            $physicalItemCount++;

            $unitWeight = $this->resolveWeight($store, $item, $variant, $product);
            $label = $this->itemLabel($item, $product);
            if ($unitWeight === null) {
                $missingWeights[] = $label;
            }

            $shipsSeparately = $this->itemShipsSeparately($item, $variant, $product);

            $fingerprintParts[] = implode(':', [
                (string) ($item->product_variant_id ?? $item->variant_id ?? $item->product_id ?? $item->id),
                (string) $qty,
                $unitWeight === null ? 'missing-weight' : number_format($unitWeight, 3, '.', ''),
                implode('x', [
                    number_format($presetDims['length'], 2, '.', ''),
                    number_format($presetDims['width'], 2, '.', ''),
                    number_format($presetDims['height'], 2, '.', ''),
                ]),
                $shipsSeparately ? 'separate' : 'agg',
            ]);

            if ($unitWeight === null) {
                continue;
            }

            if ($shipsSeparately) {
                for ($i = 0; $i < $qty; $i++) {
                    $packages[] = [
                        'weight' => $unitWeight,
                        'weight_unit' => $weightUnit,
                        'length' => $presetDims['length'],
                        'width' => $presetDims['width'],
                        'height' => $presetDims['height'],
                        'dimension_unit' => $presetDims['dimension_unit'],
                    ];
                }
            } else {
                $aggregatedWeight += $unitWeight * $qty;
                $hasAggregatedItems = true;
            }
        }

        if ($physicalItemCount === 0) {
            return $this->notReady(
                fingerprintParts: ['empty-cart'],
                itemCount: $items->count(),
                totalQuantity: 0,
                missingWeights: [],
                reason: 'empty_cart',
            );
        }

        if ($missingWeights !== []) {
            return $this->notReady(
                fingerprintParts: $fingerprintParts,
                itemCount: $physicalItemCount,
                totalQuantity: $totalQuantity,
                missingWeights: array_values(array_unique($missingWeights)),
                reason: 'missing_weights',
            );
        }

        if ($hasAggregatedItems) {
            $packages[] = [
                'weight' => max(0.01, $aggregatedWeight),
                'weight_unit' => $weightUnit,
                'length' => $presetDims['length'],
                'width' => $presetDims['width'],
                'height' => $presetDims['height'],
                'dimension_unit' => $presetDims['dimension_unit'],
            ];
        }

        if ($packages === []) {
            return $this->notReady(
                fingerprintParts: $fingerprintParts,
                itemCount: $physicalItemCount,
                totalQuantity: $totalQuantity,
                missingWeights: [],
                reason: 'missing_default_package',
            );
        }

        $fingerprintParts[] = 'pack-policy:aggregate-v1';
        $fingerprintParts[] = 'preset:'.(string) $defaultPreset->id;

        $maxPackages = max(1, (int) config('carriers.fedex.checkout_max_package_lines', 20));
        if (count($packages) > $maxPackages) {
            // Never merge ships_separately overflow into a fake combined package.
            return $this->notReady(
                fingerprintParts: array_merge($fingerprintParts, ['too-many-packages:'.count($packages)]),
                itemCount: $physicalItemCount,
                totalQuantity: $totalQuantity,
                missingWeights: [],
                reason: 'too_many_packages',
            );
        }

        // Configured FedEx parcel ceiling is always expressed in LB; convert for KG stores.
        $maxParcelWeightLb = (float) config(
            'carriers.fedex.checkout_max_package_weight',
            StoreShippingPreferences::MAX_ITEM_WEIGHT
        );
        if ($maxParcelWeightLb <= 0) {
            $maxParcelWeightLb = StoreShippingPreferences::MAX_ITEM_WEIGHT;
        }
        $storeWeightUnit = strtoupper((string) $weightUnit) ?: 'LB';
        $maxParcelWeight = $storeWeightUnit === 'KG'
            ? round($maxParcelWeightLb / 2.20462, 3)
            : $maxParcelWeightLb;
        foreach ($packages as $package) {
            if ((float) ($package['weight'] ?? 0) > $maxParcelWeight) {
                return $this->notReady(
                    fingerprintParts: array_merge($fingerprintParts, ['package-too-heavy:'.number_format((float) $package['weight'], 3, '.', '')]),
                    itemCount: $physicalItemCount,
                    totalQuantity: $totalQuantity,
                    missingWeights: [],
                    reason: 'package_weight_unsupported',
                );
            }
        }

        return [
            'ready' => true,
            'packages' => $packages,
            'fingerprint' => hash('sha256', implode('|', $fingerprintParts)),
            'item_count' => $physicalItemCount,
            'total_quantity' => $totalQuantity,
            'missing_weights' => [],
            'reason' => null,
        ];
    }

    public function canBuild(Checkout $checkout): bool
    {
        return (bool) ($this->buildFromCheckout($checkout)['ready'] ?? false);
    }

    /**
     * Build a single diagnostic package from merchant-provided preset or custom dims/weight.
     * Never invents defaults — ready is false when weight or dimensions are missing.
     *
     * @param  array{
     *     package_preset_id?: int|null,
     *     weight?: float|null,
     *     weight_unit?: string|null,
     *     length?: float|null,
     *     width?: float|null,
     *     height?: float|null,
     *     dimension_unit?: string|null
     * }  $input
     * @return array{
     *     ready: bool,
     *     packages: list<array{weight: float, weight_unit: string, length: float, width: float, height: float, dimension_unit: string}>,
     *     fingerprint: string,
     *     item_count: int,
     *     total_quantity: int,
     *     missing_weights: list<string>,
     *     reason: string|null,
     *     source: string|null
     * }
     */
    public function buildFromDiagnosticInput(Store $store, array $input): array
    {
        $prefs = $this->shippingPreferences->get($store);
        $weightUnit = strtoupper((string) ($input['weight_unit'] ?? $prefs['weight_unit'] ?: 'LB'));
        $dimensionUnit = strtoupper((string) ($input['dimension_unit'] ?? 'IN'));

        $customWeight = is_numeric($input['weight'] ?? null) ? (float) $input['weight'] : null;
        $customLength = is_numeric($input['length'] ?? null) ? (float) $input['length'] : null;
        $customWidth = is_numeric($input['width'] ?? null) ? (float) $input['width'] : null;
        $customHeight = is_numeric($input['height'] ?? null) ? (float) $input['height'] : null;
        $hasCustomPackage = $customWeight !== null
            || $customLength !== null
            || $customWidth !== null
            || $customHeight !== null;

        if ($hasCustomPackage) {
            if ($customWeight === null || $customWeight <= 0) {
                return $this->diagnosticNotReady('missing_weight');
            }
            if ($customLength === null || $customLength <= 0
                || $customWidth === null || $customWidth <= 0
                || $customHeight === null || $customHeight <= 0) {
                return $this->diagnosticNotReady('missing_dimensions');
            }

            $package = [
                'weight' => max(0.01, $customWeight),
                'weight_unit' => $weightUnit !== '' ? $weightUnit : 'LB',
                'length' => max(1.0, $customLength),
                'width' => max(1.0, $customWidth),
                'height' => max(1.0, $customHeight),
                'dimension_unit' => $dimensionUnit !== '' ? $dimensionUnit : 'IN',
            ];

            return [
                'ready' => true,
                'packages' => [$package],
                'fingerprint' => hash('sha256', 'diagnostic-custom|'.json_encode($package)),
                'item_count' => 1,
                'total_quantity' => 1,
                'missing_weights' => [],
                'reason' => null,
                'source' => 'custom',
            ];
        }

        $presetId = is_numeric($input['package_preset_id'] ?? null) ? (int) $input['package_preset_id'] : null;
        $preset = null;
        if ($presetId !== null && $presetId > 0) {
            $preset = ShippingPackagePreset::query()
                ->forStore($store)
                ->whereKey($presetId)
                ->first();
            if (! $preset) {
                return $this->diagnosticNotReady('preset_not_found');
            }
        } else {
            $preset = $this->shippingPreferences->defaultPackagePreset($store);
        }

        if (! $preset instanceof ShippingPackagePreset) {
            return $this->diagnosticNotReady('missing_package_preset');
        }

        $weight = is_numeric($preset->weight_value) ? (float) $preset->weight_value : null;
        if ($weight === null || $weight <= 0) {
            return $this->diagnosticNotReady('preset_incomplete');
        }
        if (! $preset->hasCompleteDimensions()) {
            return $this->diagnosticNotReady('preset_incomplete');
        }

        $package = [
            'weight' => max(0.01, $weight),
            'weight_unit' => strtoupper((string) ($preset->weight_unit ?: $weightUnit ?: 'LB')),
            'length' => max(1.0, (float) $preset->length),
            'width' => max(1.0, (float) $preset->width),
            'height' => max(1.0, (float) $preset->height),
            'dimension_unit' => strtoupper((string) ($preset->dimension_unit ?: 'IN')),
        ];

        return [
            'ready' => true,
            'packages' => [$package],
            'fingerprint' => hash('sha256', 'diagnostic-preset:'.$preset->id.'|'.json_encode($package)),
            'item_count' => 1,
            'total_quantity' => 1,
            'missing_weights' => [],
            'reason' => null,
            'source' => 'preset:'.$preset->id,
        ];
    }

    /**
     * @return array{
     *     ready: bool,
     *     packages: list<array{weight: float, weight_unit: string, length: float, width: float, height: float, dimension_unit: string}>,
     *     fingerprint: string,
     *     item_count: int,
     *     total_quantity: int,
     *     missing_weights: list<string>,
     *     reason: string|null,
     *     source: string|null
     * }
     */
    private function diagnosticNotReady(string $reason): array
    {
        return [
            'ready' => false,
            'packages' => [],
            'fingerprint' => hash('sha256', 'diagnostic:'.$reason),
            'item_count' => 0,
            'total_quantity' => 0,
            'missing_weights' => $reason === 'missing_weight' ? ['Package'] : [],
            'reason' => $reason,
            'source' => null,
        ];
    }

    /**
     * @param  list<string>  $fingerprintParts
     * @param  list<string>  $missingWeights
     * @return array{
     *     ready: bool,
     *     packages: list<array{weight: float, weight_unit: string, length: float, width: float, height: float, dimension_unit: string}>,
     *     fingerprint: string,
     *     item_count: int,
     *     total_quantity: int,
     *     missing_weights: list<string>,
     *     reason: string|null
     * }
     */
    private function notReady(
        array $fingerprintParts,
        int $itemCount,
        int $totalQuantity,
        array $missingWeights,
        string $reason,
    ): array {
        return [
            'ready' => false,
            'packages' => [],
            'fingerprint' => hash('sha256', implode('|', $fingerprintParts !== [] ? $fingerprintParts : [$reason])),
            'item_count' => $itemCount,
            'total_quantity' => $totalQuantity,
            'missing_weights' => $missingWeights,
            'reason' => $reason,
        ];
    }

    private function itemRequiresShipping(mixed $item, mixed $product): bool
    {
        if ($product && array_key_exists('requires_shipping', $product->getAttributes())) {
            return (bool) $product->requires_shipping;
        }

        $type = (string) (
            data_get($item, 'product_type_snapshot')
            ?: data_get($product, 'product_type')
            ?: ProductTypeBehavior::PHYSICAL
        );

        return ProductTypeBehavior::requiresShipping($type);
    }

    private function itemLabel(mixed $item, mixed $product): string
    {
        $name = trim((string) (
            data_get($item, 'product_name')
            ?: data_get($product, 'name')
            ?: 'Product'
        ));

        return $name !== '' ? $name : 'Product';
    }

    private function resolveWeight(Store $store, mixed $item, mixed $variant, mixed $product): ?float
    {
        foreach ([
            data_get($item, 'metadata.shipping_weight_snapshot'),
            data_get($item, 'weight'),
            data_get($item, 'meta.weight'),
            data_get($item, 'metadata.weight'),
        ] as $candidate) {
            if (is_numeric($candidate) && (float) $candidate > 0) {
                return max(0.01, (float) $candidate);
            }
        }

        return $this->weightResolver->resolveForStore($store, $product, $variant);
    }

    /**
     * Forward-compatible opt-out from cart aggregation. Not required for MVP, but honored when set.
     */
    private function itemShipsSeparately(mixed $item, mixed $variant, mixed $product): bool
    {
        foreach ([
            data_get($item, 'ships_separately'),
            data_get($item, 'meta.ships_separately'),
            data_get($item, 'metadata.ships_separately'),
            data_get($variant, 'meta.ships_separately'),
            data_get($product, 'meta.ships_separately'),
            data_get($product, 'ships_separately'),
        ] as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }

            return filter_var($candidate, FILTER_VALIDATE_BOOL);
        }

        return false;
    }
}
