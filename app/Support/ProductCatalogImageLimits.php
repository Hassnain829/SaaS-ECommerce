<?php

namespace App\Support;

/**
 * Shared catalog photo limits for the product gallery and variant assignments.
 *
 * All photos still live on the product. Variants attach an ordered subset of that gallery.
 */
final class ProductCatalogImageLimits
{
    public const MAX = 32;

    public static function tooManyMessage(): string
    {
        return 'You can attach up to '.self::MAX.' photos per product.';
    }

    public static function helpText(): string
    {
        return 'JPG, PNG, or WebP · up to '.self::MAX.' photos · 4 MB each';
    }
}
