<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLevel;
use App\Models\InventoryReservation;
use App\Models\Location;
use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryReservationService
{
    public function __construct(
        private readonly DefaultLocationService $defaultLocationService,
        private readonly InventorySyncService $syncService,
        private readonly InventoryAdjustmentService $adjustmentService,
    ) {
    }

    public function reserve(
        InventoryItem $item,
        int $quantity,
        string $referenceType,
        int|string $referenceId,
        ?Location $preferredLocation = null,
        ?\DateTimeInterface $expiresAt = null,
        array $context = [],
    ): InventoryReservation {
        $quantity = max(1, $quantity);

        return DB::transaction(function () use ($item, $quantity, $referenceType, $referenceId, $preferredLocation, $expiresAt, $context): InventoryReservation {
            $location = $preferredLocation ?: $this->defaultLocationService->ensureForStore($item->store);
            $item->loadMissing('variant');
            $this->syncService->ensureLevel($item, $location, (int) ($item->variant?->stock ?? 0));

            /** @var InventoryLevel $level */
            $level = InventoryLevel::query()
                ->where('store_id', $item->store_id)
                ->where('inventory_item_id', $item->id)
                ->where('location_id', $location->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $level->available < $quantity) {
                $item->loadMissing('product', 'variant');
                $label = $item->product?->name ?? 'this item';
                $sku = $item->variant?->sku ? ' (SKU '.$item->variant->sku.')' : '';

                throw ValidationException::withMessages([
                    (string) ($context['validation_key'] ?? 'items') => "Insufficient stock for {$label}{$sku}.",
                ]);
            }

            $before = $this->adjustmentService->snapshot($level);

            $reservation = InventoryReservation::query()->create([
                'store_id' => $item->store_id,
                'inventory_item_id' => $item->id,
                'location_id' => $location->id,
                'order_id' => ($context['order'] ?? null) instanceof Order ? $context['order']->id : ($context['order_id'] ?? null),
                'checkout_reference' => $context['checkout_reference'] ?? null,
                'reference_type' => $referenceType,
                'reference_id' => (string) $referenceId,
                'quantity' => $quantity,
                'status' => InventoryReservation::STATUS_ACTIVE,
                'expires_at' => $expiresAt,
                'metadata' => $context['metadata'] ?? null,
            ]);

            $level->update([
                'available' => (int) $level->available - $quantity,
                'reserved' => (int) $level->reserved + $quantity,
            ]);
            $level->refresh();
            $after = $this->adjustmentService->snapshot($level);

            $this->adjustmentService->recordMovementForLevel(
                $level,
                $before,
                $after,
                -$quantity,
                (string) ($context['reserve_movement_type'] ?? StockMovement::TYPE_ORDER_RESERVED),
                (string) ($context['reserve_reason'] ?? 'Stock reserved for order'),
                null,
                $this->movementContext($context, $reservation)
            );

            $this->syncItemVariant($item);

            return $reservation->refresh();
        });
    }

    public function commit(InventoryReservation $reservation, array $context = []): void
    {
        DB::transaction(function () use ($reservation, $context): void {
            $reservation = InventoryReservation::query()->lockForUpdate()->findOrFail($reservation->id);
            if ($reservation->status !== InventoryReservation::STATUS_ACTIVE) {
                return;
            }

            $level = $this->lockReservationLevel($reservation);
            $before = $this->adjustmentService->snapshot($level);
            $qty = (int) $reservation->quantity;

            $level->update([
                'reserved' => max(0, (int) $level->reserved - $qty),
                'committed' => (int) $level->committed + $qty,
            ]);
            $reservation->update([
                'status' => InventoryReservation::STATUS_COMMITTED,
                'committed_at' => now(),
            ]);

            $level->refresh();
            $this->adjustmentService->recordMovementForLevel(
                $level,
                $before,
                $this->adjustmentService->snapshot($level),
                0,
                (string) ($context['commit_movement_type'] ?? StockMovement::TYPE_ORDER_COMMITTED),
                (string) ($context['commit_reason'] ?? 'Reserved stock committed to order'),
                null,
                $this->movementContext($context, $reservation)
            );
        });
    }

    public function deductCommitted(InventoryReservation $reservation, array $context = []): void
    {
        DB::transaction(function () use ($reservation, $context): void {
            $reservation = InventoryReservation::query()->lockForUpdate()->findOrFail($reservation->id);
            if ($reservation->status === InventoryReservation::STATUS_ACTIVE) {
                $this->commit($reservation, $context);
                $reservation = InventoryReservation::query()->lockForUpdate()->findOrFail($reservation->id);
            }

            if ($reservation->status !== InventoryReservation::STATUS_COMMITTED) {
                return;
            }

            $level = $this->lockReservationLevel($reservation);
            $before = $this->adjustmentService->snapshot($level);
            $qty = (int) $reservation->quantity;

            $level->update([
                'committed' => max(0, (int) $level->committed - $qty),
            ]);
            $reservation->update([
                'status' => InventoryReservation::STATUS_DEDUCTED,
                'deducted_at' => now(),
            ]);

            $level->refresh();
            $this->adjustmentService->recordMovementForLevel(
                $level,
                $before,
                $this->adjustmentService->snapshot($level),
                -$qty,
                (string) ($context['deduct_movement_type'] ?? StockMovement::TYPE_ORDER_DEDUCTED),
                (string) ($context['deduct_reason'] ?? 'Stock deducted for order'),
                null,
                $this->movementContext($context, $reservation)
            );

            $reservation->inventoryItem?->variant
                ? $this->syncService->syncVariantStockCache($reservation->inventoryItem->variant)
                : null;
        });
    }

    public function release(InventoryReservation $reservation, array $context = []): void
    {
        DB::transaction(function () use ($reservation, $context): void {
            $reservation = InventoryReservation::query()->lockForUpdate()->findOrFail($reservation->id);
            if (! in_array($reservation->status, [InventoryReservation::STATUS_ACTIVE, InventoryReservation::STATUS_COMMITTED], true)) {
                return;
            }

            $level = $this->lockReservationLevel($reservation);
            $before = $this->adjustmentService->snapshot($level);
            $qty = (int) $reservation->quantity;
            $updates = ['available' => (int) $level->available + $qty];

            if ($reservation->status === InventoryReservation::STATUS_ACTIVE) {
                $updates['reserved'] = max(0, (int) $level->reserved - $qty);
            } else {
                $updates['committed'] = max(0, (int) $level->committed - $qty);
            }

            $level->update($updates);
            $reservation->update([
                'status' => InventoryReservation::STATUS_RELEASED,
                'released_at' => now(),
            ]);

            $level->refresh();
            $this->adjustmentService->recordMovementForLevel(
                $level,
                $before,
                $this->adjustmentService->snapshot($level),
                $qty,
                (string) ($context['release_movement_type'] ?? StockMovement::TYPE_RESERVATION_RELEASED),
                (string) ($context['release_reason'] ?? 'Reserved stock released'),
                null,
                $this->movementContext($context, $reservation)
            );

            $this->syncItemVariant($reservation->inventoryItem);
        });
    }

    private function lockReservationLevel(InventoryReservation $reservation): InventoryLevel
    {
        return InventoryLevel::query()
            ->where('inventory_item_id', $reservation->inventory_item_id)
            ->where('location_id', $reservation->location_id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function movementContext(array $context, InventoryReservation $reservation): array
    {
        return [
            ...$context,
            'reservation_id' => $reservation->id,
            'reference_id' => $context['reference_id'] ?? $reservation->reference_id,
            'reference_type' => $context['reference_type'] ?? $reservation->reference_type,
            'reference_code' => $context['reference_code'] ?? null,
            'source' => $context['source'] ?? null,
        ];
    }

    private function syncItemVariant(?InventoryItem $item): void
    {
        if (! $item) {
            return;
        }

        $item->loadMissing('variant');
        if ($item->variant) {
            $this->syncService->syncVariantStockCache($item->variant);
        }
    }
}
