<?php

namespace App\Services\Fulfillment;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\User;
use App\Services\OrderEventRecorder;
use App\Support\OrderLifecycle;

class FulfillmentStatusService
{
    /**
     * Quantities still available to put on a new outbound shipment.
     * Includes open labels / pending shipments so paid labels cannot double-consume.
     *
     * @return array<int, int>
     */
    public function remainingQuantities(Order $order): array
    {
        $items = $order->items()->get(['id', 'quantity']);
        $allocated = $this->allocatedOutboundQuantities($items->pluck('id')->all());

        return $items->mapWithKeys(function (OrderItem $item) use ($allocated): array {
            $used = (int) ($allocated[$item->id] ?? 0);

            return [$item->id => max(0, (int) $item->quantity - $used)];
        })->all();
    }

    public function recalculateAndPersist(Order $order, ?User $actor = null, ?string $reason = null): string
    {
        $items = $order->items()->get();
        $fulfilled = $this->fulfilledOutboundQuantities($items->pluck('id')->all());

        $totalQuantity = 0;
        $fulfilledQuantity = 0;

        foreach ($items as $item) {
            $quantity = max(0, (int) $item->quantity);
            $itemFulfilled = min($quantity, (int) ($fulfilled[$item->id] ?? 0));
            $itemStatus = match (true) {
                $quantity === 0 || $itemFulfilled === 0 => OrderLifecycle::FULFILLMENT_UNFULFILLED,
                $itemFulfilled < $quantity => OrderLifecycle::FULFILLMENT_PARTIAL,
                default => OrderLifecycle::FULFILLMENT_FULFILLED,
            };

            if ($item->fulfillment_status !== $itemStatus) {
                $item->forceFill(['fulfillment_status' => $itemStatus])->save();
            }

            $totalQuantity += $quantity;
            $fulfilledQuantity += $itemFulfilled;
        }

        $newStatus = match (true) {
            $totalQuantity === 0 || $fulfilledQuantity === 0 => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            $fulfilledQuantity < $totalQuantity => OrderLifecycle::FULFILLMENT_PARTIAL,
            default => OrderLifecycle::FULFILLMENT_FULFILLED,
        };

        $oldStatus = (string) $order->fulfillment_status;
        if ($oldStatus !== $newStatus) {
            $order->forceFill(['fulfillment_status' => $newStatus])->save();

            app(OrderEventRecorder::class)->record(
                $order,
                OrderLifecycle::EVENT_FULFILLMENT_STATUS_CHANGED,
                'Fulfillment status changed',
                'Fulfillment status changed from '.OrderLifecycle::fulfillmentStatusLabel($oldStatus).' to '.OrderLifecycle::fulfillmentStatusLabel($newStatus).'.',
                [
                    'previous_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'reason' => $reason,
                ],
                $actor
            );
        }

        return $newStatus;
    }

    /**
     * @param  list<int>  $orderItemIds
     * @return array<int, int>
     */
    private function allocatedOutboundQuantities(array $orderItemIds): array
    {
        return $this->sumOutboundShipmentItemQuantities(
            $orderItemIds,
            Shipment::STATUSES_RESERVED_AGAINST_ORDER,
        );
    }

    /**
     * @param  list<int>  $orderItemIds
     * @return array<int, int>
     */
    private function fulfilledOutboundQuantities(array $orderItemIds): array
    {
        return $this->sumOutboundShipmentItemQuantities(
            $orderItemIds,
            Shipment::STATUSES_COUNTED_FOR_FULFILLMENT,
        );
    }

    /**
     * @param  list<int>  $orderItemIds
     * @param  list<string>  $statuses
     * @return array<int, int>
     */
    private function sumOutboundShipmentItemQuantities(array $orderItemIds, array $statuses): array
    {
        if ($orderItemIds === []) {
            return [];
        }

        return ShipmentItem::query()
            ->selectRaw('order_item_id, SUM(quantity) as quantity')
            ->whereIn('order_item_id', $orderItemIds)
            ->whereHas('shipment', function ($query) use ($statuses): void {
                $query->whereIn('status', $statuses)
                    ->where(function ($directionQuery): void {
                        $directionQuery->where('direction', Shipment::DIRECTION_OUTBOUND)
                            ->orWhereNull('direction')
                            ->orWhere('direction', '');
                    });
            })
            ->groupBy('order_item_id')
            ->pluck('quantity', 'order_item_id')
            ->map(fn ($quantity): int => (int) $quantity)
            ->all();
    }
}
