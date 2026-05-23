<?php

namespace App\Services\Channels;

use App\Models\Order;
use App\Models\Store;
use App\Support\CheckoutMode;

final class ChannelOwnershipService
{
    public const OWNER_EXTERNAL = 'external';

    public const OWNER_PLATFORM = 'platform';

    public const CHANNEL_EXTERNAL = 'external_checkout';

    public const CHANNEL_PLATFORM = 'platform_checkout';

    private const EXTERNAL_DEFAULTS = [
        'enabled' => true,
        'checkout_owner' => self::OWNER_EXTERNAL,
        'payment_owner' => self::OWNER_EXTERNAL,
        'shipping_owner' => self::OWNER_EXTERNAL,
        'fulfillment_owner' => self::OWNER_EXTERNAL,
        'inventory_owner' => self::OWNER_PLATFORM,
        'source_channel' => 'external_storefront',
    ];

    private const PLATFORM_DEFAULTS = [
        'enabled' => true,
        'checkout_owner' => self::OWNER_PLATFORM,
        'payment_owner' => self::OWNER_PLATFORM,
        'shipping_owner' => self::OWNER_PLATFORM,
        'fulfillment_owner' => self::OWNER_PLATFORM,
        'inventory_owner' => self::OWNER_PLATFORM,
        'source_channel' => 'platform_checkout',
    ];

    public function externalCheckoutConfig(Store $store): array
    {
        return $this->channelConfig($store, self::CHANNEL_EXTERNAL, self::EXTERNAL_DEFAULTS);
    }

    public function platformCheckoutConfig(Store $store): array
    {
        return $this->channelConfig($store, self::CHANNEL_PLATFORM, self::PLATFORM_DEFAULTS);
    }

    public function isExternalManaged(Store $store, ?string $sourceChannel = null): bool
    {
        if ($sourceChannel !== null) {
            return $this->resolveChannelKey($sourceChannel) === self::CHANNEL_EXTERNAL;
        }

        return CheckoutMode::forStore($store) === CheckoutMode::EXTERNAL;
    }

    public function isPlatformManaged(Store $store, ?string $sourceChannel = null): bool
    {
        return ! $this->isExternalManaged($store, $sourceChannel);
    }

    public function isOrderExternallyManaged(Order $order): bool
    {
        if ($order->order_source === 'external_checkout') {
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
        return (string) ($this->configForContext($store, $sourceChannel)['fulfillment_owner'] ?? self::OWNER_EXTERNAL);
    }

    public function shippingOwner(Store $store, ?string $sourceChannel = null): string
    {
        return (string) ($this->configForContext($store, $sourceChannel)['shipping_owner'] ?? self::OWNER_EXTERNAL);
    }

    public function paymentOwner(Store $store, ?string $sourceChannel = null): string
    {
        return (string) ($this->configForContext($store, $sourceChannel)['payment_owner'] ?? self::OWNER_EXTERNAL);
    }

    public function inventoryOwner(Store $store, ?string $sourceChannel = null): string
    {
        $owner = (string) ($this->configForContext($store, $sourceChannel)['inventory_owner'] ?? self::OWNER_PLATFORM);

        return in_array($owner, [self::OWNER_PLATFORM, self::OWNER_EXTERNAL], true)
            ? $owner
            : self::OWNER_PLATFORM;
    }

    public function usesPlatformInventory(Store $store, ?string $sourceChannel = null): bool
    {
        return $this->inventoryOwner($store, $sourceChannel) === self::OWNER_PLATFORM;
    }

    public function usesExternalInventory(Store $store, ?string $sourceChannel = null): bool
    {
        return ! $this->usesPlatformInventory($store, $sourceChannel);
    }

    public function setExternalCheckoutInventoryOwner(Store $store, string $owner): Store
    {
        if (! in_array($owner, [self::OWNER_PLATFORM, self::OWNER_EXTERNAL], true)) {
            throw new \InvalidArgumentException('Inventory owner must be platform or external.');
        }

        $store = $this->ensureChannelsStructure($store);
        $settings = $store->settings ?? [];
        $channels = is_array($settings['channels'] ?? null) ? $settings['channels'] : [];
        $external = is_array($channels[self::CHANNEL_EXTERNAL] ?? null) ? $channels[self::CHANNEL_EXTERNAL] : [];
        $external['inventory_owner'] = $owner;
        $external['inventory_owner_configured'] = true;
        $channels[self::CHANNEL_EXTERNAL] = $external;
        $settings['channels'] = $channels;
        $store->forceFill(['settings' => $settings])->save();

        return $store->fresh();
    }

    public function ensureChannelsStructure(Store $store): Store
    {
        $settings = $store->settings ?? [];
        $channels = is_array($settings['channels'] ?? null) ? $settings['channels'] : [];
        $changed = false;

        foreach ([self::CHANNEL_EXTERNAL => self::EXTERNAL_DEFAULTS, self::CHANNEL_PLATFORM => self::PLATFORM_DEFAULTS] as $key => $defaults) {
            $existing = is_array($channels[$key] ?? null) ? $channels[$key] : [];
            $merged = array_merge($defaults, $existing);
            if ($key === self::CHANNEL_EXTERNAL) {
                $merged = $this->normalizeExternalInventoryOwner($merged, $existing);
            }
            if ($merged !== ($channels[$key] ?? null)) {
                $channels[$key] = $merged;
                $changed = true;
            }
        }

        if (! $changed) {
            return $store;
        }

        $settings['channels'] = $channels;
        $store->forceFill(['settings' => $settings])->save();

        return $store->fresh();
    }

    public function syncActiveCheckoutMode(Store $store, string $checkoutMode): Store
    {
        $store = $this->ensureChannelsStructure($store);
        $settings = $store->settings ?? [];
        $settings['checkout_mode'] = $checkoutMode;
        $store->forceFill(['settings' => $settings])->save();

        return $store->fresh();
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function channelConfig(Store $store, string $channelKey, array $defaults): array
    {
        $store = $this->ensureChannelsStructure($store);
        $stored = data_get($store->settings, "channels.{$channelKey}", []);

        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    /**
     * @return array<string, mixed>
     */
    private function configForContext(Store $store, ?string $sourceChannel): array
    {
        $channelKey = $this->resolveChannelKey($sourceChannel) ?? (
            CheckoutMode::forStore($store) === CheckoutMode::PLATFORM
                ? self::CHANNEL_PLATFORM
                : self::CHANNEL_EXTERNAL
        );

        return $channelKey === self::CHANNEL_PLATFORM
            ? $this->platformCheckoutConfig($store)
            : $this->externalCheckoutConfig($store);
    }

    private function resolveChannelKey(?string $sourceChannel): ?string
    {
        if ($sourceChannel === null || trim($sourceChannel) === '') {
            return null;
        }

        $normalized = strtolower(trim($sourceChannel));

        if (in_array($normalized, [self::CHANNEL_PLATFORM, 'platform', 'platform_checkout', 'dev_storefront'], true)) {
            return self::CHANNEL_PLATFORM;
        }

        if (in_array($normalized, [self::CHANNEL_EXTERNAL, 'external', 'external_checkout', 'external_storefront', 'api'], true)) {
            return self::CHANNEL_EXTERNAL;
        }

        return str_contains($normalized, 'platform') ? self::CHANNEL_PLATFORM : self::CHANNEL_EXTERNAL;
    }

    /**
     * @param  array<string, mixed>  $merged
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private function normalizeExternalInventoryOwner(array $merged, array $existing): array
    {
        if (($existing['inventory_owner_configured'] ?? false) === true) {
            return $merged;
        }

        if (! array_key_exists('inventory_owner', $existing)) {
            $merged['inventory_owner'] = self::OWNER_PLATFORM;

            return $merged;
        }

        // Legacy Patch A persisted inventory_owner=external without an explicit merchant choice.
        if (($existing['inventory_owner'] ?? null) === self::OWNER_EXTERNAL) {
            $merged['inventory_owner'] = self::OWNER_PLATFORM;
        }

        return $merged;
    }
}
