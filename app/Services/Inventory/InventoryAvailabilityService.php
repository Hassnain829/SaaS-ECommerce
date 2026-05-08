<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

class InventoryAvailabilityService
{
    public function __construct(private readonly InventorySyncService $syncService)
    {
    }

    public function availableForVariant(ProductVariant $variant): int
    {
        $level = $this->syncService->ensureDefaultLevelForVariant($variant);
        $item = $level->inventoryItem;

        return $this->availableForInventoryItem($item);
    }

    public function availableForInventoryItem(InventoryItem $item): int
    {
        return (int) $item->levels()
            ->whereHas('location', fn ($query) => $query->where('is_active', true))
            ->sum('available');
    }

    public function availableByLocation(InventoryItem $item): Collection
    {
        return $item->levels()
            ->with('location')
            ->whereHas('location', fn ($query) => $query->where('is_active', true))
            ->orderBy('location_id')
            ->get()
            ->map(fn ($level) => [
                'location' => $level->location,
                'available' => (int) $level->available,
                'reserved' => (int) $level->reserved,
                'committed' => (int) $level->committed,
                'incoming' => (int) $level->incoming,
            ]);
    }
}
