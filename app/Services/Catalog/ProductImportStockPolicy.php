<?php

namespace App\Services\Catalog;

use App\Models\Location;
use App\Models\ProductImport;
use App\Models\Store;

/**
 * WooCommerce imports require an explicit destination location and replace/preserve choice.
 * Generic spreadsheet imports keep the previous default-location replace behavior.
 */
final class ProductImportStockPolicy
{
    public const MODE_REPLACE = 'replace';

    public const MODE_PRESERVE = 'preserve';

    public function __construct(
        public readonly string $mode,
        public readonly ?Location $location,
    ) {}

    public static function fromImport(ProductImport $import, Store $store): self
    {
        $state = is_array($import->import_state) ? $import->import_state : [];
        $mode = ($state['stock_mode'] ?? self::MODE_REPLACE) === self::MODE_PRESERVE
            ? self::MODE_PRESERVE
            : self::MODE_REPLACE;

        $locationId = (int) ($state['location_id'] ?? 0);
        $location = null;
        if ($locationId > 0) {
            $location = Location::query()
                ->where('store_id', $store->id)
                ->where('is_active', true)
                ->whereKey($locationId)
                ->first();
        }

        return new self($mode, $location);
    }

    public function shouldWriteStock(bool $recordAlreadyExisted): bool
    {
        if ($this->mode === self::MODE_PRESERVE && $recordAlreadyExisted) {
            return false;
        }

        return true;
    }
}
