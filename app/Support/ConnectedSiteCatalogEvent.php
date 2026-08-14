<?php

namespace App\Support;

final class ConnectedSiteCatalogEvent
{
    public const PRODUCT_PUBLISHED = 'product.published';

    public const PRODUCT_UPDATED = 'product.updated';

    public const PRODUCT_UNPUBLISHED = 'product.unpublished';

    public const PRODUCT_DELETED = 'product.deleted';

    public const VARIANT_UPDATED = 'variant.updated';

    public const INVENTORY_AVAILABILITY_CHANGED = 'inventory.availability_changed';

    public const CATEGORY_UPDATED = 'category.updated';

    public const CATALOG_UPDATED = 'catalog.updated';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::PRODUCT_PUBLISHED,
            self::PRODUCT_UPDATED,
            self::PRODUCT_UNPUBLISHED,
            self::PRODUCT_DELETED,
            self::VARIANT_UPDATED,
            self::INVENTORY_AVAILABILITY_CHANGED,
            self::CATEGORY_UPDATED,
            self::CATALOG_UPDATED,
        ];
    }
}
