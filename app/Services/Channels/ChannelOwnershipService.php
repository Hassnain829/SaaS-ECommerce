<?php

namespace App\Services\Channels;

use App\Models\Order;
use App\Models\Store;

/**
 * Read-only ownership interpretation for platform checkout and historical orders.
 * New external checkout configuration must never be created from this service.
 */
final class ChannelOwnershipService
{
    public const OWNER_EXTERNAL = 'external';

    public const OWNER_PLATFORM = 'platform';

    public const CHANNEL_PLATFORM = 'platform_checkout';

    public function platformCheckoutConfig(Store $store): array
    {
        $stored = data_get($store->settings, 'channels.platform_checkout', []);

        return array_merge([
            'enabled' => true,
            'checkout_owner' => self::OWNER_PLATFORM,
            'payment_owner' => self::OWNER_PLATFORM,
            'shipping_owner' => self::OWNER_PLATFORM,
            'fulfillment_owner' => self::OWNER_PLATFORM,
            'inventory_owner' => self::OWNER_PLATFORM,
            'source_channel' => self::CHANNEL_PLATFORM,
        ], is_array($stored) ? $stored : []);
    }

    public function isExternalManaged(Store $store, ?string $sourceChannel = null): bool
    {
        return $this->isHistoricalExternalSource($sourceChannel);
    }

    public function isPlatformManaged(Store $store, ?string $sourceChannel = null): bool
    {
        return ! $this->isExternalManaged($store, $sourceChannel);
    }

    public function isOrderExternallyManaged(Order $order): bool
    {
        if ($this->isHistoricalExternalSource($order->order_source)) {
            return true;
        }

        $snapshot = data_get($order->meta, 'channel_ownership');
        if (is_array($snapshot) && ($snapshot['fulfillment_owner'] ?? null) === self::OWNER_EXTERNAL) {
            return true;
        }

        return data_get($order->meta, 'fulfillment.managed_by') === self::OWNER_EXTERNAL;
    }

    public function fulfillmentOwner(Store $store, ?string $sourceChannel = null): string
    {
        return $this->ownerFor($store, $sourceChannel, 'fulfillment_owner');
    }

    public function shippingOwner(Store $store, ?string $sourceChannel = null): string
    {
        return $this->ownerFor($store, $sourceChannel, 'shipping_owner');
    }

    public function paymentOwner(Store $store, ?string $sourceChannel = null): string
    {
        return $this->ownerFor($store, $sourceChannel, 'payment_owner');
    }

    public function inventoryOwner(Store $store, ?string $sourceChannel = null): string
    {
        return $this->ownerFor($store, $sourceChannel, 'inventory_owner', self::OWNER_PLATFORM);
    }

    public function usesPlatformInventory(Store $store, ?string $sourceChannel = null): bool
    {
        return $this->inventoryOwner($store, $sourceChannel) === self::OWNER_PLATFORM;
    }

    public function usesExternalInventory(Store $store, ?string $sourceChannel = null): bool
    {
        return ! $this->usesPlatformInventory($store, $sourceChannel);
    }

    private function ownerFor(Store $store, ?string $sourceChannel, string $field, string $default = self::OWNER_EXTERNAL): string
    {
        if (! $this->isHistoricalExternalSource($sourceChannel)) {
            return self::OWNER_PLATFORM;
        }

        $stored = data_get($store->settings, 'channels.external_checkout.'.$field, $default);

        return $stored === self::OWNER_PLATFORM ? self::OWNER_PLATFORM : self::OWNER_EXTERNAL;
    }

    private function isHistoricalExternalSource(?string $sourceChannel): bool
    {
        $source = strtolower(trim((string) $sourceChannel));

        return in_array($source, ['external', 'external_checkout', 'external_storefront', 'api'], true);
    }
}
