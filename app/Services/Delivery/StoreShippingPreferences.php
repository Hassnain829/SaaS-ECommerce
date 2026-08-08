<?php

namespace App\Services\Delivery;

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

    /**
     * @return array{
     *     default_package_preset_id: int|null,
     *     default_label_format: string,
     *     default_signature_option: string|null,
     *     saturday_delivery_default: bool,
     *     default_handoff_type: string,
     *     weight_unit: string|null
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
            $next['weight_unit'] = $unit !== '' ? $unit : null;
        }

        $settings = is_array($store->settings) ? $store->settings : [];
        $settings[self::KEY] = $next;
        $store->forceFill(['settings' => $settings])->save();

        return $next;
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
