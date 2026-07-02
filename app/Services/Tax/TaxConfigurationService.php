<?php

namespace App\Services\Tax;

use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use App\Models\User;
use App\Services\SecurityLogRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaxConfigurationService
{
    public function ensureSettingsForStore(Store $store): TaxSetting
    {
        return TaxSetting::query()->firstOrCreate(
            ['store_id' => $store->id],
            [
                'enabled' => false,
                'prices_include_tax' => false,
                'default_product_taxable' => true,
                'shipping_taxable' => false,
                'calculation_address' => TaxSetting::CALCULATION_ADDRESS_SHIPPING,
                'settings_version' => 1,
            ]
        );
    }

    public function settingsForStore(Store $store): ?TaxSetting
    {
        return TaxSetting::query()->where('store_id', $store->id)->first();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateSettings(
        Store $store,
        array $validated,
        User $actor,
        ?Request $request = null,
    ): TaxSetting {
        return DB::transaction(function () use ($store, $validated, $actor, $request): TaxSetting {
            $settings = $this->lockSettings($store);

            $payload = [
                'enabled' => (bool) ($validated['enabled'] ?? false),
                'prices_include_tax' => (bool) ($validated['prices_include_tax'] ?? false),
                'default_product_taxable' => (bool) ($validated['default_product_taxable'] ?? false),
                'shipping_taxable' => (bool) ($validated['shipping_taxable'] ?? false),
                'calculation_address' => $validated['calculation_address'],
            ];

            if (! $this->settingsPayloadChanged($settings, $payload)) {
                return $settings;
            }

            $settings->update($payload);
            $settings = $this->bumpSettingsVersion($settings);

            app(SecurityLogRecorder::class)->record(
                $request,
                'tax.settings.updated',
                store: $store,
                user: $actor,
                metadata: [
                    'settings_version' => $settings->settings_version,
                    'enabled' => $settings->enabled,
                    'prices_include_tax' => $settings->prices_include_tax,
                    'default_product_taxable' => $settings->default_product_taxable,
                    'shipping_taxable' => $settings->shipping_taxable,
                ]
            );

            return $settings;
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createRate(
        Store $store,
        array $validated,
        User $actor,
        ?Request $request = null,
    ): TaxRate {
        return DB::transaction(function () use ($store, $validated, $actor, $request): TaxRate {
            $settings = $this->lockSettings($store);

            $rate = TaxRate::query()->create([
                'store_id' => $store->id,
                'country_code' => $validated['country_code'],
                'region_code' => $validated['region_code'],
                'name' => $validated['name'],
                'rate_percent' => $validated['rate_percent'],
                'priority' => (int) ($validated['priority'] ?? 100),
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ]);

            $settings = $this->bumpSettingsVersion($settings);

            app(SecurityLogRecorder::class)->record(
                $request,
                'tax.rate.created',
                store: $store,
                user: $actor,
                metadata: $this->rateLogMetadata($rate, $settings->settings_version)
            );

            return $rate;
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateRate(
        Store $store,
        TaxRate $rate,
        array $validated,
        User $actor,
        ?Request $request = null,
    ): TaxRate {
        abort_unless((int) $rate->store_id === (int) $store->id, 404);

        return DB::transaction(function () use ($store, $rate, $validated, $actor, $request): TaxRate {
            $settings = $this->lockSettings($store);

            $payload = [
                'country_code' => $validated['country_code'],
                'region_code' => $validated['region_code'],
                'name' => $validated['name'],
                'rate_percent' => $validated['rate_percent'],
                'priority' => (int) ($validated['priority'] ?? 100),
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ];

            if (! $this->ratePayloadChanged($rate, $payload)) {
                return $rate;
            }

            $rate->update($payload);
            $rate->refresh();

            $settings = $this->bumpSettingsVersion($settings);

            app(SecurityLogRecorder::class)->record(
                $request,
                'tax.rate.updated',
                store: $store,
                user: $actor,
                metadata: $this->rateLogMetadata($rate, $settings->settings_version)
            );

            return $rate;
        });
    }

    public function deleteRate(
        Store $store,
        TaxRate $rate,
        User $actor,
        ?Request $request = null,
    ): void {
        abort_unless((int) $rate->store_id === (int) $store->id, 404);

        DB::transaction(function () use ($store, $rate, $actor, $request): void {
            $settings = $this->lockSettings($store);
            $metadata = $this->rateLogMetadata($rate, $settings->settings_version + 1);

            $rate->delete();

            $settings = $this->bumpSettingsVersion($settings);
            $metadata['settings_version'] = $settings->settings_version;

            app(SecurityLogRecorder::class)->record(
                $request,
                'tax.rate.deleted',
                store: $store,
                user: $actor,
                metadata: $metadata
            );
        });
    }

    private function lockSettings(Store $store): TaxSetting
    {
        $this->ensureSettingsForStore($store);

        return TaxSetting::query()
            ->where('store_id', $store->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function bumpSettingsVersion(TaxSetting $settings): TaxSetting
    {
        $settings->increment('settings_version');

        return $settings->refresh();
    }

    /**
     * @param  array<string, bool|string>  $payload
     */
    private function settingsPayloadChanged(TaxSetting $settings, array $payload): bool
    {
        foreach ($payload as $key => $value) {
            if ($settings->getAttribute($key) != $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, bool|int|string>  $payload
     */
    private function ratePayloadChanged(TaxRate $rate, array $payload): bool
    {
        foreach ($payload as $key => $value) {
            $current = $rate->getAttribute($key);

            if ($key === 'rate_percent') {
                if (! $this->ratePercentEquals($current, $value)) {
                    return true;
                }

                continue;
            }

            if ($current != $value) {
                return true;
            }
        }

        return false;
    }

    private function ratePercentEquals(mixed $left, mixed $right): bool
    {
        return bccomp(
            $this->normalizeRatePercent($left),
            $this->normalizeRatePercent($right),
            4
        ) === 0;
    }

    private function normalizeRatePercent(mixed $value): string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return '0.0000';
        }

        return bcadd($normalized, '0', 4);
    }

    /**
     * @return array<string, int|string|null>
     */
    private function rateLogMetadata(TaxRate $rate, int $settingsVersion): array
    {
        return [
            'settings_version' => $settingsVersion,
            'tax_rate_id' => $rate->id,
            'country_code' => $rate->country_code,
            'region_code' => $rate->region_code,
            'is_active' => $rate->is_active,
        ];
    }
}
