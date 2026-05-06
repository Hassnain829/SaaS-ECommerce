<?php

namespace App\Support;

final class ProductTypeBehavior
{
    public const PHYSICAL = 'physical';
    public const DIGITAL = 'digital';
    public const SERVICE = 'service';
    public const SUBSCRIPTION = 'subscription';
    public const VIRTUAL = 'virtual';

    public const TYPES = [
        self::PHYSICAL,
        self::DIGITAL,
        self::SERVICE,
        self::SUBSCRIPTION,
        self::VIRTUAL,
    ];

    public static function normalize(?string $type): string
    {
        $type = strtolower(trim((string) $type));

        return in_array($type, self::TYPES, true) ? $type : self::PHYSICAL;
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return self::TYPES;
    }

    public static function label(string $type): string
    {
        return match (self::normalize($type)) {
            self::DIGITAL => 'Digital',
            self::SERVICE => 'Service',
            self::SUBSCRIPTION => 'Subscription',
            self::VIRTUAL => 'Virtual',
            default => 'Physical',
        };
    }

    public static function description(string $type): string
    {
        return match (self::normalize($type)) {
            self::DIGITAL => 'Delivered online; no shipping address or physical fulfillment is needed.',
            self::SERVICE => 'Sold as a service; inventory is not tracked until booking tools exist.',
            self::SUBSCRIPTION => 'Prepared for recurring commerce; inventory and billing rules can expand later.',
            self::VIRTUAL => 'No physical shipping; useful for access, warranties, or non-tangible items.',
            default => 'Ships to the customer and tracks stock.',
        };
    }

    public static function requiresShipping(string $type): bool
    {
        return self::normalize($type) === self::PHYSICAL;
    }

    public static function tracksInventory(string $type): bool
    {
        return self::normalize($type) === self::PHYSICAL;
    }

    /**
     * @return array{requires_shipping: bool, track_inventory: bool}
     */
    public static function defaultColumnsFor(string $type): array
    {
        $normalized = self::normalize($type);

        return [
            'requires_shipping' => self::requiresShipping($normalized),
            'track_inventory' => self::tracksInventory($normalized),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function behaviorFor(?string $type): array
    {
        $normalized = self::normalize($type);

        return [
            'type' => $normalized,
            'label' => self::label($normalized),
            'description' => self::description($normalized),
            'requires_shipping' => self::requiresShipping($normalized),
            'track_inventory' => self::tracksInventory($normalized),
            'fulfillment_kind' => self::requiresShipping($normalized) ? 'physical' : 'non_physical',
        ];
    }
}
