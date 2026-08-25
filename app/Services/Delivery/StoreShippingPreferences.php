<?php

namespace App\Services\Delivery;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingPackagePreset;
use App\Models\Store;

/**
 * Reads and writes store.settings['shipping'] defaults used by checkout rates and label ops.
 */
final class StoreShippingPreferences
{
    public const KEY = 'shipping';

    public const LABEL_FORMATS = ['PDF', 'PNG', 'ZPL'];

    public const SIGNATURE_NONE = 'SERVICE_DEFAULT';

    public const SIGNATURE_INDIRECT = 'INDIRECT';

    public const SIGNATURE_DIRECT = 'DIRECT';

    public const SIGNATURE_ADULT = 'ADULT';

    public const SIGNATURE_OPTIONS = [
        self::SIGNATURE_NONE,
        self::SIGNATURE_INDIRECT,
        self::SIGNATURE_DIRECT,
        self::SIGNATURE_ADULT,
    ];

    public const HANDOFF_USE_SCHEDULED_PICKUP = 'USE_SCHEDULED_PICKUP';

    public const HANDOFF_DROPOFF = 'DROPOFF_AT_FEDEX_LOCATION';

    public const HANDOFF_TYPES = [
        self::HANDOFF_USE_SCHEDULED_PICKUP,
        self::HANDOFF_DROPOFF,
    ];

    /** Upper ceiling aligned with common FedEx parcel limits (LB). */
    public const MAX_ITEM_WEIGHT = 150.0;

    /**
     * @return array{
     *     default_package_preset_id: int|null,
     *     default_label_format: string,
     *     default_signature_option: string|null,
     *     saturday_delivery_default: bool,
     *     default_handoff_type: string,
     *     weight_unit: string|null,
     *     fallback_item_weight: float|null
     * }
     */
    public function get(Store $store): array
    {
        $settings = is_array($store->settings) ? $store->settings : [];
        $shipping = is_array($settings[self::KEY] ?? null) ? $settings[self::KEY] : [];

        $labelFormat = strtoupper((string) ($shipping['default_label_format'] ?? 'PDF'));
        if (! in_array($labelFormat, self::LABEL_FORMATS, true)) {
            $labelFormat = 'PDF';
        }

        $handoff = strtoupper((string) ($shipping['default_handoff_type'] ?? self::HANDOFF_USE_SCHEDULED_PICKUP));
        if (! in_array($handoff, self::HANDOFF_TYPES, true)) {
            $handoff = self::HANDOFF_USE_SCHEDULED_PICKUP;
        }

        $presetId = $shipping['default_package_preset_id'] ?? null;
        $presetId = is_numeric($presetId) ? (int) $presetId : null;

        $weightUnit = isset($shipping['weight_unit']) ? strtoupper(trim((string) $shipping['weight_unit'])) : null;
        if ($weightUnit === '') {
            $weightUnit = null;
        }

        $signature = $shipping['default_signature_option'] ?? null;
        $signature = filled($signature) ? strtoupper(trim((string) $signature)) : null;
        if ($signature !== null && ! in_array($signature, self::SIGNATURE_OPTIONS, true)) {
            $signature = self::SIGNATURE_NONE;
        }

        return [
            'default_package_preset_id' => $presetId,
            'default_label_format' => $labelFormat,
            'default_signature_option' => $signature,
            'saturday_delivery_default' => (bool) ($shipping['saturday_delivery_default'] ?? false),
            'default_handoff_type' => $handoff,
            'weight_unit' => $weightUnit,
            'fallback_item_weight' => $this->normalizeFallbackWeight($shipping['fallback_item_weight'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function update(Store $store, array $input): array
    {
        $current = $this->get($store);
        $next = $current;

        if (array_key_exists('default_package_preset_id', $input)) {
            $raw = $input['default_package_preset_id'];
            if ($raw === null || $raw === '' || $raw === false) {
                $next['default_package_preset_id'] = null;
            } elseif (is_numeric($raw)) {
                $presetId = (int) $raw;
                $exists = ShippingPackagePreset::query()
                    ->forStore($store)
                    ->whereKey($presetId)
                    ->exists();
                $next['default_package_preset_id'] = $exists ? $presetId : null;
            }
        }

        if (array_key_exists('default_label_format', $input)) {
            $format = strtoupper((string) $input['default_label_format']);
            if (in_array($format, self::LABEL_FORMATS, true)) {
                $next['default_label_format'] = $format;
            }
        }

        if (array_key_exists('default_signature_option', $input)) {
            $signature = $input['default_signature_option'];
            if (! filled($signature)) {
                $next['default_signature_option'] = null;
            } else {
                $signature = strtoupper(trim((string) $signature));
                $next['default_signature_option'] = in_array($signature, self::SIGNATURE_OPTIONS, true)
                    ? $signature
                    : self::SIGNATURE_NONE;
            }
        }

        if (array_key_exists('saturday_delivery_default', $input)) {
            $next['saturday_delivery_default'] = filter_var(
                $input['saturday_delivery_default'],
                FILTER_VALIDATE_BOOL
            );
        }

        if (array_key_exists('default_handoff_type', $input)) {
            $handoff = strtoupper((string) $input['default_handoff_type']);
            if (in_array($handoff, self::HANDOFF_TYPES, true)) {
                $next['default_handoff_type'] = $handoff;
            }
        }

        if (array_key_exists('weight_unit', $input)) {
            $unit = strtoupper(trim((string) ($input['weight_unit'] ?? '')));
            $requested = $unit !== '' ? $unit : null;
            $existing = $current['weight_unit'];

            if ($requested === null) {
                // no-op
            } elseif ($existing === null) {
                if ($requested !== 'LB' && $this->storeHasCommittedWeightData($store, $current)) {
                    $next['weight_unit'] = 'LB';
                } else {
                    $next['weight_unit'] = $requested;
                }
            } elseif ($requested !== $existing) {
                $next['weight_unit'] = $existing;
            }
        }

        if (array_key_exists('fallback_item_weight', $input)) {
            $next['fallback_item_weight'] = $this->normalizeFallbackWeightForStore($store, $input['fallback_item_weight']);
            if ($next['fallback_item_weight'] !== null && $next['weight_unit'] === null) {
                $next['weight_unit'] = 'LB';
            }
        }

        $settings = is_array($store->settings) ? $store->settings : [];
        $settings[self::KEY] = $next;
        $store->forceFill(['settings' => $settings])->save();

        return $next;
    }

    public function fallbackItemWeight(Store $store): ?float
    {
        return $this->get($store)['fallback_item_weight'];
    }

    public function weightUnitLabel(Store $store): string
    {
        return $this->get($store)['weight_unit'] ?: 'LB';
    }

    public function maxItemWeightForUnit(string $unit): float
    {
        return strtoupper(trim($unit)) === 'KG'
            ? round(self::MAX_ITEM_WEIGHT / 2.20462, 3)
            : self::MAX_ITEM_WEIGHT;
    }

    public function maxItemWeightForStore(Store $store): float
    {
        return $this->maxItemWeightForUnit($this->weightUnitLabel($store));
    }

    public function normalizeFallbackWeightForStore(Store $store, mixed $raw): ?float
    {
        $normalized = $this->normalizeFallbackWeight($raw);
        if ($normalized === null) {
            return null;
        }

        $max = $this->maxItemWeightForStore($store);
        if ($normalized > $max) {
            return null;
        }

        return $normalized;
    }

    /**
     * Persist LB once the store has fallback or catalog weights so null cannot be relabeled later.
     */
    public function commitWeightUnitIfNeeded(Store $store): void
    {
        if ($this->get($store)['weight_unit'] !== null) {
            return;
        }

        $this->update($store, ['weight_unit' => 'LB']);
    }

    /**
     * @param  array<string, mixed>|null  $current
     */
    public function storeHasCommittedWeightData(Store $store, ?array $current = null): bool
    {
        $prefs = $current ?? $this->get($store);
        if ($prefs['fallback_item_weight'] !== null) {
            return true;
        }

        $hasProductWeight = Product::query()
            ->where('store_id', $store->id)
            ->where(function ($query): void {
                $query->where(function ($inner): void {
                    $inner->whereNotNull('meta->shipping_weight')
                        ->where('meta->shipping_weight', '>', 0);
                })->orWhere(function ($inner): void {
                    $inner->whereNotNull('meta->weight')
                        ->where('meta->weight', '>', 0);
                });
            })
            ->exists();

        if ($hasProductWeight) {
            return true;
        }

        return ProductVariant::query()
            ->whereHas('product', fn ($query) => $query->where('store_id', $store->id))
            ->where(function ($query): void {
                $query->where(function ($inner): void {
                    $inner->whereNotNull('meta->shipping_weight')
                        ->where('meta->shipping_weight', '>', 0);
                })->orWhere(function ($inner): void {
                    $inner->whereNotNull('meta->weight')
                        ->where('meta->weight', '>', 0);
                });
            })
            ->exists();
    }

    public function normalizeFallbackWeight(mixed $raw): ?float
    {
        if ($raw === null || $raw === '' || $raw === false) {
            return null;
        }

        if (! is_numeric($raw)) {
            return null;
        }

        $value = (float) $raw;
        if ($value <= 0 || $value > self::MAX_ITEM_WEIGHT) {
            return null;
        }

        return round($value, 3);
    }

    public function defaultHandoffType(Store $store): string
    {
        return $this->get($store)['default_handoff_type'];
    }

    public function defaultPackagePreset(Store $store): ?ShippingPackagePreset
    {
        $prefs = $this->get($store);
        $presetId = $prefs['default_package_preset_id'];

        if ($presetId) {
            $preset = ShippingPackagePreset::query()
                ->forStore($store)
                ->active()
                ->whereKey($presetId)
                ->first();
            if ($preset) {
                return $preset;
            }
        }

        return ShippingPackagePreset::query()
            ->forStore($store)
            ->active()
            ->default()
            ->orderBy('id')
            ->first();
    }
}
