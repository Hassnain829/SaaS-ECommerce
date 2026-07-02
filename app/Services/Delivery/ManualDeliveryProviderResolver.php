<?php

namespace App\Services\Delivery;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Store;
use App\Models\User;
use RuntimeException;

class ManualDeliveryProviderResolver
{
    public function resolveForStore(Store $store, ?User $actor = null): CarrierAccount
    {
        $existing = $store->carrierAccounts()
            ->where('provider', CarrierAccount::PROVIDER_MANUAL)
            ->where('status', CarrierAccount::STATUS_ENABLED)
            ->orderBy('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $manualCarrier = Carrier::query()
            ->where('code', 'manual-delivery')
            ->where('is_active', true)
            ->first();

        if ($manualCarrier === null) {
            throw new RuntimeException('Manual delivery provider is not configured on this platform.');
        }

        $existingAny = $store->carrierAccounts()
            ->where('provider', CarrierAccount::PROVIDER_MANUAL)
            ->where('carrier_id', $manualCarrier->id)
            ->first();

        if ($existingAny !== null) {
            if ($existingAny->status !== CarrierAccount::STATUS_ENABLED) {
                $existingAny->update([
                    'status' => CarrierAccount::STATUS_ENABLED,
                    'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
                ]);
            }

            return $existingAny->fresh();
        }

        return $store->carrierAccounts()->create([
            'carrier_id' => $manualCarrier->id,
            'provider' => CarrierAccount::PROVIDER_MANUAL,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'display_name' => 'Manual delivery',
            'connection_type' => CarrierAccount::CONNECTION_MANUAL,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_MANUAL,
            'status' => CarrierAccount::STATUS_ENABLED,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'supported_countries' => null,
            'enabled_for_checkout' => false,
            'created_by' => $actor?->id,
            ...CarrierAccount::ownershipAttributesForManual(),
        ]);
    }
}
