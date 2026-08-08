<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\Order;
use App\Models\ShippingPackagePreset;
use App\Models\ShipmentPackage;
use App\Models\Store;
use App\Models\User;
use App\Services\Delivery\StoreShippingPreferences;
use Illuminate\Validation\ValidationException;

/**
 * Creates immutable transaction package snapshots used for FedEx rates and labels.
 */
final class FedExOrderPackageSnapshotService
{
    public function __construct(
        private readonly StoreShippingPreferences $shippingPreferences,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function createFromOrderInput(
        Store $store,
        Order $order,
        int $originLocationId,
        array $input,
        ?User $actor = null,
    ): ShipmentPackage {
        abort_unless((int) $order->store_id === (int) $store->id, 404);

        $source = strtolower(trim((string) ($input['package_source'] ?? 'preset')));
        $resolved = $source === 'custom'
            ? $this->resolveCustom($input)
            : $this->resolvePreset($store, $input);

        return ShipmentPackage::query()->create([
            'store_id' => $store->id,
            'shipment_id' => null,
            'order_id' => $order->id,
            'origin_location_id' => $originLocationId,
            'name' => $resolved['name'],
            'weight_value' => $resolved['weight'],
            'weight_unit' => $resolved['weight_unit'],
            'length' => $resolved['length'],
            'width' => $resolved['width'],
            'height' => $resolved['height'],
            'dimension_unit' => $resolved['dimension_unit'],
            'package_type' => $resolved['package_type'],
            'metadata' => [
                'source' => $resolved['source'],
                'shipping_package_preset_id' => $resolved['preset_id'],
                'copied_at' => now()->toIso8601String(),
            ],
            'created_by' => $actor?->id,
        ]);
    }

    /**
     * @return list<array{weight: float, weight_unit: string, length: float, width: float, height: float, dimension_unit: string}>
     */
    public function toRatePackages(ShipmentPackage $package): array
    {
        $this->assertPackageReady($package);

        return [[
            'weight' => (float) $package->weight_value,
            'weight_unit' => strtoupper((string) ($package->weight_unit ?: 'LB')),
            'length' => (float) $package->length,
            'width' => (float) $package->width,
            'height' => (float) $package->height,
            'dimension_unit' => strtoupper((string) ($package->dimension_unit ?: 'IN')),
        ]];
    }

    public function assertPackageReady(ShipmentPackage $package): void
    {
        if (! is_numeric($package->weight_value) || (float) $package->weight_value <= 0) {
            throw ValidationException::withMessages([
                'package' => 'Choose a package with a valid weight before requesting FedEx rates.',
            ]);
        }

        foreach (['length', 'width', 'height'] as $field) {
            if (! is_numeric($package->{$field}) || (float) $package->{$field} <= 0) {
                throw ValidationException::withMessages([
                    'package' => 'Choose a package with length, width, and height before requesting FedEx rates.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{name: string, weight: float, weight_unit: string, length: float, width: float, height: float, dimension_unit: string, package_type: string, source: string, preset_id: int|null}
     */
    private function resolvePreset(Store $store, array $input): array
    {
        $presetId = (int) ($input['shipping_package_preset_id'] ?? 0);
        $preset = null;

        if ($presetId > 0) {
            $preset = ShippingPackagePreset::query()
                ->forStore($store)
                ->active()
                ->whereKey($presetId)
                ->first();
        }

        if (! $preset) {
            $preset = $this->shippingPreferences->defaultPackagePreset($store);
        }

        if (! $preset) {
            throw ValidationException::withMessages([
                'shipping_package_preset_id' => 'Choose a package before requesting FedEx rates.',
            ]);
        }

        $weight = $preset->weight_value;
        if ((! is_numeric($weight) || (float) $weight <= 0) && isset($input['weight']) && is_numeric($input['weight'])) {
            $weight = (float) $input['weight'];
        }

        if (! is_numeric($weight) || (float) $weight <= 0
            || ! is_numeric($preset->length) || (float) $preset->length <= 0
            || ! is_numeric($preset->width) || (float) $preset->width <= 0
            || ! is_numeric($preset->height) || (float) $preset->height <= 0
        ) {
            throw ValidationException::withMessages([
                'shipping_package_preset_id' => 'That saved package is missing weight or dimensions. Edit the package or enter a custom package.',
            ]);
        }

        return [
            'name' => (string) $preset->name,
            'weight' => (float) $weight,
            'weight_unit' => strtoupper((string) ($preset->weight_unit ?: 'LB')),
            'length' => (float) $preset->length,
            'width' => (float) $preset->width,
            'height' => (float) $preset->height,
            'dimension_unit' => strtoupper((string) ($preset->dimension_unit ?: 'IN')),
            'package_type' => (string) ($preset->package_type ?: 'YOUR_PACKAGING'),
            'source' => 'preset',
            'preset_id' => (int) $preset->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{name: string, weight: float, weight_unit: string, length: float, width: float, height: float, dimension_unit: string, package_type: string, source: string, preset_id: int|null}
     */
    private function resolveCustom(array $input): array
    {
        $weight = $input['weight'] ?? null;
        $length = $input['length'] ?? null;
        $width = $input['width'] ?? null;
        $height = $input['height'] ?? null;

        if (! is_numeric($weight) || (float) $weight <= 0) {
            throw ValidationException::withMessages([
                'weight' => 'Enter a package weight greater than zero.',
            ]);
        }

        if (! is_numeric($length) || (float) $length <= 0
            || ! is_numeric($width) || (float) $width <= 0
            || ! is_numeric($height) || (float) $height <= 0
        ) {
            throw ValidationException::withMessages([
                'length' => 'Enter length, width, and height for the custom package.',
            ]);
        }

        return [
            'name' => 'Custom package',
            'weight' => (float) $weight,
            'weight_unit' => strtoupper((string) ($input['weight_unit'] ?? 'LB')),
            'length' => (float) $length,
            'width' => (float) $width,
            'height' => (float) $height,
            'dimension_unit' => strtoupper((string) ($input['dimension_unit'] ?? 'IN')),
            'package_type' => 'YOUR_PACKAGING',
            'source' => 'custom',
            'preset_id' => null,
        ];
    }
}
