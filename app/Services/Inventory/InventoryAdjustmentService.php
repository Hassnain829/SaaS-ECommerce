<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLevel;
use App\Models\Location;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryAdjustmentService
{
    public function __construct(private readonly InventorySyncService $syncService) {}

    public function setVariantAvailable(
        ProductVariant $variant,
        int $newAvailable,
        string $reason,
        ?User $actor = null,
        array $context = [],
    ): InventoryLevel {
        $level = $this->syncService->ensureDefaultLevelForVariant(
            $variant,
            array_key_exists('initial_available', $context) ? (int) $context['initial_available'] : null
        );

        return $this->setAvailable($level->inventoryItem, $level->location, $newAvailable, $reason, $actor, $context);
    }

    public function adjustAvailable(
        InventoryItem $item,
        Location $location,
        int $quantityChange,
        string $reason,
        ?User $actor = null,
        array $context = [],
    ): InventoryLevel {
        $item->loadMissing('variant');
        $level = $this->syncService->ensureLevel($item, $location, (int) ($item->variant?->stock ?? 0));

        return $this->setAvailable($item, $location, (int) $level->available + $quantityChange, $reason, $actor, $context);
    }

    public function setAvailable(
        InventoryItem $item,
        Location $location,
        int $newAvailable,
        string $reason,
        ?User $actor = null,
        array $context = [],
    ): InventoryLevel {
        return DB::transaction(function () use ($item, $location, $newAvailable, $reason, $actor, $context): InventoryLevel {
            $this->syncService->ensureLevel($item, $location, 0);

            /** @var InventoryLevel $level */
            $level = InventoryLevel::query()
                ->where('store_id', $item->store_id)
                ->where('inventory_item_id', $item->id)
                ->where('location_id', $location->id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = $this->snapshot($level);
            $nextAvailable = (int) $newAvailable;

            if ($nextAvailable < 0 && empty($context['allow_negative'])) {
                throw ValidationException::withMessages([
                    (string) ($context['validation_key'] ?? 'inventory') => 'Available stock cannot go below zero.',
                ]);
            }

            if ((int) $level->available === $nextAvailable) {
                $this->syncVariantCacheFromItem($item);

                return $level->refresh();
            }

            $level->update(['available' => $nextAvailable]);
            $level->refresh();
            $after = $this->snapshot($level);

            $this->recordMovementForLevel(
                $level,
                $before,
                $after,
                $after['available'] - $before['available'],
                (string) ($context['movement_type'] ?? StockMovement::TYPE_MANUAL_ADJUSTMENT),
                $reason,
                $actor,
                $context
            );

            $this->syncVariantCacheFromItem($item);

            return $level;
        });
    }

    /**
     * @param  array{available: int, reserved: int, committed: int}  $before
     * @param  array{available: int, reserved: int, committed: int}  $after
     */
    public function recordMovementForLevel(
        InventoryLevel $level,
        array $before,
        array $after,
        int $quantityChange,
        string $movementType,
        string $reason,
        ?User $actor = null,
        array $context = [],
    ): StockMovement {
        $level->loadMissing('inventoryItem.product', 'inventoryItem.variant', 'location');
        $item = $level->inventoryItem;

        return StockMovement::query()->create([
            'store_id' => $level->store_id,
            'product_id' => $item?->product_id,
            'variant_id' => $item?->variant_id,
            'location_id' => $level->location_id,
            'inventory_item_id' => $level->inventory_item_id,
            'inventory_level_id' => $level->id,
            'reservation_id' => $context['reservation_id'] ?? null,
            'previous_stock' => array_key_exists('previous_stock_for_movement', $context)
                ? $context['previous_stock_for_movement']
                : $before['available'],
            'quantity_change' => $quantityChange,
            'new_stock' => $after['available'],
            'available_before' => $before['available'],
            'available_after' => $after['available'],
            'reserved_before' => $before['reserved'],
            'reserved_after' => $after['reserved'],
            'committed_before' => $before['committed'],
            'committed_after' => $after['committed'],
            'movement_type' => $movementType,
            'reason' => $reason,
            'source' => $context['source'] ?? null,
            'reference_id' => $context['reference_id'] ?? null,
            'reference_type' => $context['reference_type'] ?? null,
            'reference_code' => $context['reference_code'] ?? null,
            'notes' => $context['notes'] ?? null,
            'performed_by' => $actor?->id ?? ($context['performed_by'] ?? null),
        ]);
    }

    /**
     * @return array{available: int, reserved: int, committed: int}
     */
    public function snapshot(InventoryLevel $level): array
    {
        return [
            'available' => (int) $level->available,
            'reserved' => (int) $level->reserved,
            'committed' => (int) $level->committed,
        ];
    }

    private function syncVariantCacheFromItem(InventoryItem $item): void
    {
        $item->loadMissing('variant');

        if ($item->variant) {
            $this->syncService->syncVariantStockCache($item->variant);
        }
    }
}
