<?php

namespace App\Services\Fulfillment;

use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\OrderEventRecorder;
use App\Services\SecurityLogRecorder;
use App\Services\ShipmentNumberGenerator;
use App\Support\OrderLifecycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipmentService
{
    public function __construct(
        private readonly ShipmentNumberGenerator $shipmentNumberGenerator,
        private readonly FulfillmentStatusService $fulfillmentStatusService,
        private readonly OrderEventRecorder $orderEventRecorder,
        private readonly SecurityLogRecorder $securityLogRecorder,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createShipment(Order $order, array $payload, ?User $actor = null, ?Request $request = null): Shipment
    {
        return DB::transaction(function () use ($order, $payload, $actor, $request): Shipment {
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $origin = $this->storeScopedModel(Location::class, $order->store_id, $payload['origin_location_id'] ?? null, 'origin_location_id');
            $carrierAccount = $this->storeScopedModel(CarrierAccount::class, $order->store_id, $payload['carrier_account_id'] ?? null, 'carrier_account_id');
            $shippingMethod = $this->storeScopedModel(ShippingMethod::class, $order->store_id, $payload['shipping_method_id'] ?? null, 'shipping_method_id');

            if ($shippingMethod && $carrierAccount && $shippingMethod->carrier_account_id && (int) $shippingMethod->carrier_account_id !== (int) $carrierAccount->id) {
                throw ValidationException::withMessages([
                    'shipping_method_id' => 'Choose a delivery method that belongs to the selected carrier account.',
                ]);
            }

            $shipmentLines = $this->shipmentLines($order, $payload['items'] ?? []);
            if ($shipmentLines === []) {
                throw ValidationException::withMessages([
                    'items' => 'Choose at least one order item to fulfill.',
                ]);
            }

            $routedOriginId = (int) data_get($order->meta, 'fulfillment_routing.origin_location_id');
            $originSelection = $routedOriginId > 0
                ? ((int) ($origin?->id ?? 0) === $routedOriginId ? 'routed_origin' : 'manual_override')
                : ($origin ? 'manual_selection' : 'not_selected');

            $shipment = Shipment::query()->create([
                'store_id' => $order->store_id,
                'order_id' => $order->id,
                'shipment_number' => $this->shipmentNumberGenerator->generate($order->store),
                'origin_location_id' => $origin?->id,
                'carrier_account_id' => $carrierAccount?->id,
                'shipping_method_id' => $shippingMethod?->id,
                'status' => Shipment::STATUS_PENDING,
                'tracking_number' => $this->blankToNull($payload['tracking_number'] ?? null),
                'tracking_url' => $this->blankToNull($payload['tracking_url'] ?? null),
                'carrier_service' => $this->blankToNull($payload['carrier_service'] ?? null),
                'package_count' => max(1, (int) ($payload['package_count'] ?? 1)),
                'package_weight' => $this->decimalOrNull($payload['package_weight'] ?? null),
                'shipping_cost' => $this->decimalOrNull($payload['shipping_cost'] ?? null),
                'metadata' => array_filter([
                    'note' => $this->blankToNull($payload['note'] ?? null),
                    'routed_origin_location_id' => $routedOriginId > 0 ? $routedOriginId : null,
                    'selected_origin_location_id' => $origin?->id,
                    'origin_selection' => $originSelection,
                ], fn ($value): bool => $value !== null),
            ]);

            foreach ($shipmentLines as $orderItemId => $quantity) {
                $shipment->items()->create([
                    'store_id' => $order->store_id,
                    'order_item_id' => $orderItemId,
                    'quantity' => $quantity,
                ]);
            }

            $this->orderEventRecorder->record(
                $order,
                OrderLifecycle::EVENT_SHIPMENT_CREATED,
                'Shipment created',
                'Shipment '.$shipment->shipment_number.' was created.',
                [
                    'shipment_id' => $shipment->id,
                    'shipment_number' => $shipment->shipment_number,
                    'item_count' => count($shipmentLines),
                    'routed_origin_location_id' => $routedOriginId > 0 ? $routedOriginId : null,
                    'selected_origin_location_id' => $origin?->id,
                    'origin_selection' => $originSelection,
                ],
                $actor
            );

            if ($shipment->tracking_number) {
                $this->orderEventRecorder->record(
                    $order,
                    OrderLifecycle::EVENT_SHIPMENT_TRACKING_ADDED,
                    'Tracking number added',
                    'Tracking number '.$shipment->tracking_number.' was added to '.$shipment->shipment_number.'.',
                    [
                        'shipment_id' => $shipment->id,
                        'tracking_number' => $shipment->tracking_number,
                        'tracking_url' => $shipment->tracking_url,
                    ],
                    $actor
                );
            }

            $this->fulfillmentStatusService->recalculateAndPersist($order, $actor, 'shipment_created');
            $this->securityLogRecorder->record(
                $request,
                'shipment_created',
                store: $order->store,
                user: $actor,
                metadata: [
                    'order_id' => $order->id,
                    'shipment_id' => $shipment->id,
                    'shipment_number' => $shipment->shipment_number,
                ]
            );

            return $shipment->load(['items.orderItem', 'carrierAccount.carrier', 'shippingMethod', 'originLocation']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateTracking(Shipment $shipment, array $payload, ?User $actor = null, ?Request $request = null): Shipment
    {
        return DB::transaction(function () use ($shipment, $payload, $actor, $request): Shipment {
            $shipment = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $order = $shipment->order()->lockForUpdate()->firstOrFail();

            $previousTracking = $shipment->tracking_number;
            $shipment->update([
                'tracking_number' => $this->blankToNull($payload['tracking_number'] ?? null),
                'tracking_url' => $this->blankToNull($payload['tracking_url'] ?? null),
                'carrier_service' => $this->blankToNull($payload['carrier_service'] ?? null),
                'metadata' => array_filter([
                    ...($shipment->metadata ?? []),
                    'note' => $this->blankToNull($payload['note'] ?? data_get($shipment->metadata, 'note')),
                ], fn ($value) => $value !== null),
            ]);

            if ($previousTracking !== $shipment->tracking_number) {
                $this->orderEventRecorder->record(
                    $order,
                    OrderLifecycle::EVENT_SHIPMENT_TRACKING_ADDED,
                    'Tracking number added',
                    $shipment->tracking_number
                        ? 'Tracking number '.$shipment->tracking_number.' was added to '.$shipment->shipment_number.'.'
                        : 'Tracking details were updated for '.$shipment->shipment_number.'.',
                    [
                        'shipment_id' => $shipment->id,
                        'previous_tracking_number' => $previousTracking,
                        'tracking_number' => $shipment->tracking_number,
                        'tracking_url' => $shipment->tracking_url,
                    ],
                    $actor
                );
            }

            $this->securityLogRecorder->record(
                $request,
                'shipment_tracking_updated',
                store: $shipment->store,
                user: $actor,
                metadata: ['shipment_id' => $shipment->id, 'shipment_number' => $shipment->shipment_number]
            );

            return $shipment->refresh();
        });
    }

    public function markShipped(Shipment $shipment, ?User $actor = null, ?Request $request = null): Shipment
    {
        return $this->changeStatus($shipment, Shipment::STATUS_SHIPPED, $actor, $request);
    }

    public function markDelivered(Shipment $shipment, ?User $actor = null, ?Request $request = null): Shipment
    {
        return $this->changeStatus($shipment, Shipment::STATUS_DELIVERED, $actor, $request);
    }

    public function markFailed(Shipment $shipment, ?User $actor = null, ?Request $request = null): Shipment
    {
        return $this->changeStatus($shipment, Shipment::STATUS_FAILED, $actor, $request);
    }

    public function cancelShipment(Shipment $shipment, ?User $actor = null, ?Request $request = null): Shipment
    {
        return $this->changeStatus($shipment, Shipment::STATUS_CANCELLED, $actor, $request);
    }

    private function changeStatus(Shipment $shipment, string $status, ?User $actor = null, ?Request $request = null): Shipment
    {
        return DB::transaction(function () use ($shipment, $status, $actor, $request): Shipment {
            $shipment = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $order = $shipment->order()->lockForUpdate()->firstOrFail();
            $previousStatus = (string) $shipment->status;

            $this->validateStatusTransition($previousStatus, $status);
            if (in_array($status, Shipment::STATUSES_COUNTED_FOR_FULFILLMENT, true)) {
                $this->ensureShipmentCanCountTowardFulfillment($shipment, $order);
            }

            $updates = ['status' => $status];
            if ($status === Shipment::STATUS_SHIPPED && ! $shipment->shipped_at) {
                $updates['shipped_at'] = now();
                $updates['shipped_by'] = $actor?->id;
            }
            if ($status === Shipment::STATUS_DELIVERED) {
                $updates['delivered_at'] = now();
                $updates['shipped_at'] = $shipment->shipped_at ?? now();
                $updates['shipped_by'] = $shipment->shipped_by ?? $actor?->id;
            }

            $shipment->update($updates);

            $this->orderEventRecorder->record(
                $order,
                OrderLifecycle::EVENT_SHIPMENT_STATUS_CHANGED,
                'Shipment status changed',
                'Shipment '.$shipment->shipment_number.' changed from '.OrderLifecycle::shipmentStatusLabel($previousStatus).' to '.OrderLifecycle::shipmentStatusLabel($status).'.',
                [
                    'shipment_id' => $shipment->id,
                    'previous_status' => $previousStatus,
                    'new_status' => $status,
                ],
                $actor
            );

            $this->fulfillmentStatusService->recalculateAndPersist($order, $actor, 'shipment_'.$status);

            $this->securityLogRecorder->record(
                $request,
                'shipment_status_changed',
                store: $shipment->store,
                user: $actor,
                metadata: [
                    'shipment_id' => $shipment->id,
                    'shipment_number' => $shipment->shipment_number,
                    'previous_status' => $previousStatus,
                    'new_status' => $status,
                ]
            );

            return $shipment->refresh();
        });
    }

    private function ensureShipmentCanCountTowardFulfillment(Shipment $shipment, Order $order): void
    {
        $orderItems = OrderItem::query()
            ->where('order_id', $order->id)
            ->lockForUpdate()
            ->get(['id', 'quantity', 'product_name'])
            ->keyBy('id');

        $shipment->loadMissing('items');

        foreach ($shipment->items as $shipmentItem) {
            $orderItem = $orderItems->get($shipmentItem->order_item_id);
            if (! $orderItem) {
                throw ValidationException::withMessages([
                    'shipment_status' => 'One of the selected items does not belong to this order.',
                ]);
            }

            $alreadyCounted = (int) ShipmentItem::query()
                ->where('order_item_id', $shipmentItem->order_item_id)
                ->where('shipment_id', '!=', $shipment->id)
                ->whereHas('shipment', function ($query): void {
                    $query->whereIn('status', Shipment::STATUSES_COUNTED_FOR_FULFILLMENT);
                })
                ->sum('quantity');

            if ($alreadyCounted + (int) $shipmentItem->quantity > (int) $orderItem->quantity) {
                throw ValidationException::withMessages([
                    'shipment_status' => 'Shipment quantity exceeds the remaining quantity for this item.',
                ]);
            }
        }
    }

    /**
     * @return array<int, int>
     */
    private function shipmentLines(Order $order, mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $orderItems = OrderItem::query()
            ->where('order_id', $order->id)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $lines = [];

        foreach ($items as $key => $row) {
            $orderItemId = is_array($row) ? ($row['order_item_id'] ?? $key) : $key;
            $quantity = is_array($row) ? ($row['quantity'] ?? null) : $row;

            if (! is_numeric($orderItemId) || ! is_numeric($quantity)) {
                continue;
            }

            $orderItemId = (int) $orderItemId;
            $quantity = (int) $quantity;
            if ($quantity <= 0) {
                continue;
            }

            $lines[$orderItemId] = ($lines[$orderItemId] ?? 0) + $quantity;
        }

        if ($lines === []) {
            return [];
        }

        $remaining = $this->fulfillmentStatusService->remainingQuantities($order);

        foreach ($lines as $orderItemId => $quantity) {
            if (! $orderItems->has($orderItemId)) {
                throw ValidationException::withMessages([
                    'items' => 'One of the selected items does not belong to this order.',
                ]);
            }

            $available = (int) ($remaining[$orderItemId] ?? 0);
            if ($quantity > $available) {
                throw ValidationException::withMessages([
                    "items.{$orderItemId}" => 'Shipment quantity exceeds the remaining quantity for this item.',
                ]);
            }
        }

        return $lines;
    }

    private function validateStatusTransition(string $from, string $to): void
    {
        $allowed = [
            Shipment::STATUS_PENDING => [Shipment::STATUS_SHIPPED, Shipment::STATUS_FAILED, Shipment::STATUS_CANCELLED],
            Shipment::STATUS_LABEL_CREATED => [Shipment::STATUS_SHIPPED, Shipment::STATUS_FAILED, Shipment::STATUS_CANCELLED],
            Shipment::STATUS_SHIPPED => [Shipment::STATUS_IN_TRANSIT, Shipment::STATUS_DELIVERED, Shipment::STATUS_FAILED],
            Shipment::STATUS_IN_TRANSIT => [Shipment::STATUS_DELIVERED, Shipment::STATUS_FAILED],
            Shipment::STATUS_DELIVERED => [],
            Shipment::STATUS_FAILED => [],
            Shipment::STATUS_RETURNED => [],
            Shipment::STATUS_CANCELLED => [],
        ];

        if (! in_array($to, $allowed[$from] ?? [], true)) {
            throw ValidationException::withMessages([
                'shipment_status' => 'This shipment cannot move from '.OrderLifecycle::shipmentStatusLabel($from).' to '.OrderLifecycle::shipmentStatusLabel($to).'.',
            ]);
        }
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<T>  $model
     * @return T|null
     */
    private function storeScopedModel(string $model, int $storeId, mixed $id, string $errorKey): ?object
    {
        if (blank($id)) {
            return null;
        }

        $record = $model::query()
            ->whereKey($id)
            ->where('store_id', $storeId)
            ->first();

        if (! $record) {
            throw ValidationException::withMessages([
                $errorKey => 'Choose a valid option for this store.',
            ]);
        }

        return $record;
    }

    private function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function decimalOrNull(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return number_format((float) $value, 3, '.', '');
    }
}
