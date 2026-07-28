<?php

namespace App\Services\Returns;

use App\Models\Location;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\ProductVariant;
use App\Models\ReturnItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Channels\ChannelOwnershipService;
use App\Services\Inventory\InventoryAdjustmentService;
use App\Services\Inventory\InventorySyncService;
use App\Services\OrderEventRecorder;
use App\Services\SecurityLogRecorder;
use App\Support\ReturnLifecycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnRestockService
{
    public function __construct(
        private readonly InventoryAdjustmentService $inventoryAdjustmentService,
        private readonly InventorySyncService $inventorySyncService,
        private readonly ChannelOwnershipService $channelOwnershipService,
        private readonly OrderEventRecorder $orderEventRecorder,
        private readonly SecurityLogRecorder $securityLogRecorder,
    ) {}

    /**
     * Apply pending restock for a received return item (idempotent).
     */
    public function restockReturnItem(ReturnItem $returnItem, ?User $actor = null, ?Request $request = null): ReturnItem
    {
        return DB::transaction(function () use ($returnItem, $actor, $request): ReturnItem {
            $returnItem = ReturnItem::query()
                ->whereKey($returnItem->id)
                ->lockForUpdate()
                ->with(['orderReturn.order.store', 'orderItem.variant', 'restockLocation'])
                ->firstOrFail();

            $return = $returnItem->orderReturn;
            if (! $return || ! in_array($return->status, [ReturnLifecycle::STATUS_RECEIVED, ReturnLifecycle::STATUS_COMPLETED], true)) {
                throw ValidationException::withMessages([
                    'restock' => 'Items can only be restocked after the return is received.',
                ]);
            }

            if (! $returnItem->restock) {
                throw ValidationException::withMessages([
                    'restock' => 'This return item is not marked for restock.',
                ]);
            }

            $remaining = max(0, (int) $returnItem->received_quantity - (int) $returnItem->restocked_quantity);
            if ($remaining < 1) {
                return $returnItem;
            }

            $condition = $returnItem->condition ?: ReturnLifecycle::CONDITION_SELLABLE;
            if ($condition !== ReturnLifecycle::CONDITION_SELLABLE) {
                // Damaged / non-sellable: never create sellable restock movements.
                $returnItem->forceFill([
                    'restocked_quantity' => 0,
                    'disposition' => 'hold',
                    'restock' => false,
                    'restock_location_id' => null,
                ])->save();

                return $returnItem->refresh();
            }

            $order = $return->order;
            if (! $this->orderUsesPlatformInventory($order)) {
                $returnItem->forceFill([
                    'restocked_quantity' => (int) $returnItem->received_quantity,
                    'disposition' => 'external_inventory',
                    'meta' => array_merge($returnItem->meta ?? [], [
                        'restock_note' => 'Inventory is managed externally for this order; no platform stock movement was created.',
                    ]),
                ])->save();

                return $returnItem->refresh();
            }

            $variant = $returnItem->orderItem?->variant;
            if (! $variant instanceof ProductVariant) {
                throw ValidationException::withMessages([
                    'restock' => 'This return item has no variant available for restock.',
                ]);
            }

            $location = $returnItem->restockLocation;
            if (! $location) {
                throw ValidationException::withMessages([
                    'restock_location_id' => 'Choose a restock location before returning stock to inventory.',
                ]);
            }

            if ((int) $location->store_id !== (int) $return->store_id || ! $location->is_active) {
                throw ValidationException::withMessages([
                    'restock_location_id' => 'Choose a restock location that belongs to this store.',
                ]);
            }

            $inventoryItem = $this->inventorySyncService->ensureInventoryItemForVariant($variant);
            $this->inventoryAdjustmentService->adjustAvailable(
                $inventoryItem,
                $location,
                $remaining,
                'Return restock '.$return->return_number,
                $actor,
                [
                    'movement_type' => StockMovement::TYPE_RETURN_RESTOCK,
                    'source' => 'return_restock',
                    'reference_type' => OrderReturn::class,
                    'reference_id' => $return->id,
                    'reference_code' => $return->return_number,
                    'notes' => 'Restocked from return item #'.$returnItem->id,
                ]
            );

            $returnItem->forceFill([
                'restocked_quantity' => (int) $returnItem->restocked_quantity + $remaining,
                'disposition' => 'restock',
            ])->save();

            $this->orderEventRecorder->record(
                $order,
                'return.restocked',
                'Return restocked',
                $remaining.' unit(s) from '.$return->return_number.' were returned to stock.',
                [
                    'return_id' => $return->id,
                    'return_item_id' => $returnItem->id,
                    'quantity' => $remaining,
                    'location_id' => $location->id,
                ],
                $actor
            );

            $this->securityLogRecorder->record(
                $request,
                'return.restocked',
                store: $order->store,
                user: $actor,
                metadata: [
                    'order_id' => $order->id,
                    'return_id' => $return->id,
                    'return_item_id' => $returnItem->id,
                    'quantity' => $remaining,
                    'location_id' => $location->id,
                ]
            );

            return $returnItem->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function restockReturn(OrderReturn $return, array $payload = [], ?User $actor = null, ?Request $request = null): OrderReturn
    {
        return DB::transaction(function () use ($return, $payload, $actor, $request): OrderReturn {
            $return = OrderReturn::query()
                ->whereKey($return->id)
                ->lockForUpdate()
                ->with(['items.orderItem.variant', 'items.restockLocation', 'order.store'])
                ->firstOrFail();

            $itemPayload = $payload['items'] ?? [];

            foreach ($return->items as $returnItem) {
                $row = $itemPayload[$returnItem->id] ?? $itemPayload[(string) $returnItem->id] ?? [];
                if (! is_array($row)) {
                    $row = [];
                }

                if (array_key_exists('restock', $row)) {
                    $returnItem->restock = (bool) $row['restock'];
                }
                if (array_key_exists('condition', $row) && $row['condition'] !== '') {
                    $newCondition = (string) $row['condition'];
                    if (! in_array($newCondition, ReturnLifecycle::conditions(), true)) {
                        throw ValidationException::withMessages([
                            'items' => 'Choose a valid item condition.',
                        ]);
                    }

                    $current = (string) ($returnItem->condition ?: ReturnLifecycle::CONDITION_SELLABLE);
                    if (ReturnLifecycle::isNonSellable($current)
                        && $newCondition === ReturnLifecycle::CONDITION_SELLABLE) {
                        throw ValidationException::withMessages([
                            'items' => 'Damaged or non-sellable items cannot be changed to sellable after receive.',
                        ]);
                    }

                    $returnItem->condition = $newCondition;
                }

                if (ReturnLifecycle::isNonSellable($returnItem->condition)) {
                    $returnItem->restock = false;
                    $returnItem->restocked_quantity = 0;
                    $returnItem->restock_location_id = null;
                    $returnItem->disposition = 'hold';
                }

                if (array_key_exists('restock_location_id', $row) && $row['restock_location_id'] !== '') {
                    $locationId = (int) $row['restock_location_id'];
                    $location = Location::query()
                        ->whereKey($locationId)
                        ->where('store_id', $return->store_id)
                        ->where('is_active', true)
                        ->first();
                    if (! $location) {
                        throw ValidationException::withMessages([
                            'items' => 'Choose a valid restock location.',
                        ]);
                    }
                    $returnItem->restock_location_id = $location->id;
                }
                $returnItem->save();

                if ($returnItem->restock && ! ReturnLifecycle::isNonSellable($returnItem->condition)) {
                    $this->restockReturnItem($returnItem, $actor, $request);
                }
            }

            return $return->fresh(['items']);
        });
    }

    private function orderUsesPlatformInventory(Order $order): bool
    {
        $snapshot = data_get($order->meta, 'channel_ownership.inventory_owner');
        if ($snapshot === ChannelOwnershipService::OWNER_EXTERNAL) {
            return false;
        }
        if ($snapshot === ChannelOwnershipService::OWNER_PLATFORM) {
            return true;
        }

        $order->loadMissing('store');

        return $this->channelOwnershipService->usesPlatformInventory(
            $order->store,
            $order->order_source === 'platform_checkout' ? 'platform_checkout' : $order->order_source
        );
    }
}
