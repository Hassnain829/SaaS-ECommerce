<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\ReturnItem;
use App\Models\ReturnReason;
use App\Models\Store;
use App\Models\User;
use App\Services\Notifications\CommerceNotificationEmitter;
use App\Services\Returns\ReturnRestockService;
use App\Support\OrderLifecycle;
use App\Support\ReturnLifecycle;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnService
{
    public function __construct(
        private readonly OrderNumberGenerator $orderNumberGenerator,
        private readonly OrderEventRecorder $orderEventRecorder,
        private readonly SecurityLogRecorder $securityLogRecorder,
        private readonly ReturnRestockService $returnRestockService,
        private readonly CommerceNotificationEmitter $commerceNotifications,
    ) {}

    /**
     * @return list<array{code: string, label: string, sort_order: int}>
     */
    public static function defaultReasons(): array
    {
        return [
            ['code' => 'damaged', 'label' => 'Arrived damaged', 'sort_order' => 10],
            ['code' => 'defective', 'label' => 'Defective or not working', 'sort_order' => 20],
            ['code' => 'wrong_item', 'label' => 'Wrong item received', 'sort_order' => 30],
            ['code' => 'not_as_described', 'label' => 'Not as described', 'sort_order' => 40],
            ['code' => 'changed_mind', 'label' => 'Changed mind', 'sort_order' => 50],
            ['code' => 'other', 'label' => 'Other', 'sort_order' => 100],
        ];
    }

    public function ensureDefaultReasons(?Store $store = null): void
    {
        foreach (self::defaultReasons() as $reason) {
            ReturnReason::query()->firstOrCreate(
                [
                    'store_id' => $store?->id,
                    'code' => $reason['code'],
                ],
                [
                    'label' => $reason['label'],
                    'is_active' => true,
                    'sort_order' => $reason['sort_order'],
                ]
            );
        }
    }

    /**
     * @return Collection<int, ReturnReason>
     */
    public function activeReasonsForStore(Store $store): Collection
    {
        $this->ensureDefaultReasons(null);

        $storeReasons = ReturnReason::query()
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        if ($storeReasons->isNotEmpty()) {
            return $storeReasons;
        }

        return ReturnReason::query()
            ->whereNull('store_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }

    /**
     * Remaining returnable quantity per order item id.
     *
     * @return array<int, int>
     */
    public function remainingReturnableQuantities(Order $order): array
    {
        $order->loadMissing(['items', 'returns.items']);

        $openClaims = [];
        foreach ($order->returns as $return) {
            if (! $return->isOpenClaim()) {
                continue;
            }

            foreach ($return->items as $returnItem) {
                $orderItemId = (int) $returnItem->order_item_id;
                $openClaims[$orderItemId] = ($openClaims[$orderItemId] ?? 0) + $returnItem->claimQuantity();
            }
        }

        $remaining = [];
        foreach ($order->items as $item) {
            $ordered = (int) $item->quantity;
            $returned = (int) $item->returned_quantity;
            $claimed = (int) ($openClaims[(int) $item->id] ?? 0);
            $remaining[(int) $item->id] = max(0, $ordered - $returned - $claimed);
        }

        return $remaining;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function returnableItems(Order $order): Collection
    {
        $remaining = $this->remainingReturnableQuantities($order);

        return $order->items
            ->filter(function (OrderItem $item) use ($remaining): bool {
                if (($remaining[(int) $item->id] ?? 0) < 1) {
                    return false;
                }

                return ReturnLifecycle::isPhysicalProductType($item->product_type_snapshot);
            })
            ->values();
    }

    public function assertEligibleForReturn(Order $order): void
    {
        if (in_array($order->status, [OrderLifecycle::ORDER_CANCELLED, OrderLifecycle::ORDER_PENDING], true)) {
            throw ValidationException::withMessages([
                'order' => 'This order cannot accept a return in its current status.',
            ]);
        }

        if ($this->returnableItems($order)->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'There are no returnable items left on this order.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function requestReturn(Order $order, array $payload, ?User $actor = null, ?Request $request = null): OrderReturn
    {
        return DB::transaction(function () use ($order, $payload, $actor, $request): OrderReturn {
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->with(['items', 'returns.items', 'store'])
                ->firstOrFail();

            $this->assertEligibleForReturn($order);

            $reason = $this->resolveReason($order->store_id, $payload['return_reason_id'] ?? null);
            $lines = $this->validatedRequestLines($order, $payload['items'] ?? []);

            $return = OrderReturn::query()->create([
                'store_id' => $order->store_id,
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'return_number' => $this->orderNumberGenerator->generateReturn($order->store),
                'status' => ReturnLifecycle::STATUS_REQUESTED,
                'source' => ReturnLifecycle::SOURCE_MERCHANT,
                'return_reason_id' => $reason?->id,
                'merchant_notes' => $this->blankToNull($payload['merchant_notes'] ?? null),
                'customer_notes' => $this->blankToNull($payload['customer_notes'] ?? null),
                'manual_instructions' => $this->blankToNull($payload['manual_instructions'] ?? null),
                'tracking_reference' => $this->blankToNull($payload['tracking_reference'] ?? null),
                'requested_by' => $actor?->id,
                'requested_at' => now(),
            ]);

            foreach ($lines as $line) {
                /** @var OrderItem $orderItem */
                $orderItem = $line['order_item'];
                $return->items()->create([
                    'store_id' => $order->store_id,
                    'order_item_id' => $orderItem->id,
                    'requested_quantity' => $line['quantity'],
                    'approved_quantity' => 0,
                    'received_quantity' => 0,
                    'restocked_quantity' => 0,
                    'restock' => false,
                    'product_name_snapshot' => $orderItem->product_name,
                    'variant_label_snapshot' => $orderItem->variant_label,
                    'sku_snapshot' => $orderItem->sku_snapshot,
                    'product_type_snapshot' => $orderItem->product_type_snapshot,
                ]);
            }

            $this->recordEvent(
                $order,
                ReturnLifecycle::EVENT_RETURN_REQUESTED,
                'Return requested',
                'Return '.$return->return_number.' was requested.',
                [
                    'return_id' => $return->id,
                    'return_number' => $return->return_number,
                    'status' => $return->status,
                ],
                $actor
            );

            $this->securityLogRecorder->record(
                $request,
                'return.requested',
                store: $order->store,
                user: $actor,
                metadata: [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'return_id' => $return->id,
                    'return_number' => $return->return_number,
                ]
            );

            $this->commerceNotifications->returnStatus(
                $return,
                ReturnLifecycle::EVENT_RETURN_REQUESTED,
                'Return requested',
                $actor
            );

            return $return->fresh(['items', 'reason']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function approve(OrderReturn $return, array $payload = [], ?User $actor = null, ?Request $request = null): OrderReturn
    {
        return $this->transition($return, ReturnLifecycle::STATUS_APPROVED, function (OrderReturn $locked, Order $order) use ($payload, $actor): void {
            $quantities = $payload['items'] ?? [];
            $remaining = $this->remainingReturnableQuantities($order);

            foreach ($locked->items as $returnItem) {
                $orderItemId = (int) $returnItem->order_item_id;
                $requested = (int) $returnItem->requested_quantity;
                $approved = array_key_exists($orderItemId, $quantities) || array_key_exists((string) $orderItemId, $quantities)
                    ? (int) ($quantities[$orderItemId] ?? $quantities[(string) $orderItemId] ?? 0)
                    : $requested;

                if ($approved < 1 || $approved > $requested) {
                    throw ValidationException::withMessages([
                        'items' => 'Approved quantity must be between 1 and the requested quantity for each item.',
                    ]);
                }

                // Remaining excludes this return's open claim; add it back for this approval check.
                $available = ($remaining[$orderItemId] ?? 0) + $returnItem->claimQuantity();
                if ($approved > $available) {
                    throw ValidationException::withMessages([
                        'items' => 'Approved quantity exceeds what can still be returned for an item.',
                    ]);
                }

                $returnItem->forceFill([
                    'approved_quantity' => $approved,
                ])->save();
            }

            if ($locked->items->sum('approved_quantity') < 1) {
                throw ValidationException::withMessages([
                    'items' => 'Approve at least one item quantity.',
                ]);
            }

            $locked->forceFill([
                'status' => ReturnLifecycle::STATUS_APPROVED,
                'approved_by' => $actor?->id,
                'approved_at' => now(),
                'merchant_notes' => $this->blankToNull($payload['merchant_notes'] ?? $locked->merchant_notes),
                'manual_instructions' => $this->blankToNull($payload['manual_instructions'] ?? $locked->manual_instructions),
                'tracking_reference' => $this->blankToNull($payload['tracking_reference'] ?? $locked->tracking_reference),
            ])->save();
        }, ReturnLifecycle::EVENT_RETURN_APPROVED, 'Return approved', 'return.approved', $actor, $request);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function reject(OrderReturn $return, array $payload = [], ?User $actor = null, ?Request $request = null): OrderReturn
    {
        return $this->transition($return, ReturnLifecycle::STATUS_REJECTED, function (OrderReturn $locked) use ($payload, $actor): void {
            $locked->forceFill([
                'status' => ReturnLifecycle::STATUS_REJECTED,
                'rejected_at' => now(),
                'cancelled_by' => $actor?->id,
                'merchant_notes' => $this->blankToNull($payload['merchant_notes'] ?? $locked->merchant_notes),
                'meta' => array_merge($locked->meta ?? [], array_filter([
                    'rejection_note' => $this->blankToNull($payload['rejection_note'] ?? null),
                ])),
            ])->save();
        }, ReturnLifecycle::EVENT_RETURN_REJECTED, 'Return rejected', 'return.rejected', $actor, $request);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function receive(OrderReturn $return, array $payload = [], ?User $actor = null, ?Request $request = null): OrderReturn
    {
        return $this->transition($return, ReturnLifecycle::STATUS_RECEIVED, function (OrderReturn $locked, Order $order) use ($payload, $actor): void {
            $itemPayload = $payload['items'] ?? [];

            foreach ($locked->items as $returnItem) {
                $orderItemId = (int) $returnItem->order_item_id;
                $row = $itemPayload[$orderItemId] ?? $itemPayload[(string) $orderItemId] ?? [];
                if (! is_array($row)) {
                    $row = [];
                }

                $maxReceivable = (int) $returnItem->approved_quantity;
                if ($maxReceivable < 1) {
                    throw ValidationException::withMessages([
                        'items' => 'Approve the return before marking items as received.',
                    ]);
                }

                $received = array_key_exists('received_quantity', $row)
                    ? (int) $row['received_quantity']
                    : $maxReceivable;

                if ($received < 1 || $received > $maxReceivable) {
                    throw ValidationException::withMessages([
                        'items' => 'Received quantity must be between 1 and the approved quantity.',
                    ]);
                }

                $condition = $this->blankToNull($row['condition'] ?? ReturnLifecycle::CONDITION_SELLABLE);
                $allowedConditions = [
                    ReturnLifecycle::CONDITION_SELLABLE,
                    ReturnLifecycle::CONDITION_DAMAGED,
                    ReturnLifecycle::CONDITION_DEFECTIVE,
                    ReturnLifecycle::CONDITION_NON_SELLABLE,
                ];
                if ($condition !== null && ! in_array($condition, $allowedConditions, true)) {
                    throw ValidationException::withMessages([
                        'items' => 'Choose a valid item condition.',
                    ]);
                }

                $restockRaw = $row['restock'] ?? false;
                $restock = $restockRaw === true
                    || $restockRaw === 1
                    || $restockRaw === '1'
                    || $restockRaw === 'true'
                    || $restockRaw === 'on'
                    || $restockRaw === 'yes';

                // Damaged / defective / non-sellable items never restock sellable inventory.
                if ($condition !== ReturnLifecycle::CONDITION_SELLABLE) {
                    $restock = false;
                }

                $restockLocationId = isset($row['restock_location_id']) && $row['restock_location_id'] !== ''
                    ? (int) $row['restock_location_id']
                    : null;

                if ($restock) {
                    if (! $restockLocationId) {
                        throw ValidationException::withMessages([
                            'items' => 'Choose a restock location when returning items to inventory.',
                        ]);
                    }

                    $locationExists = Location::query()
                        ->whereKey($restockLocationId)
                        ->where('store_id', $locked->store_id)
                        ->where('is_active', true)
                        ->exists();

                    if (! $locationExists) {
                        throw ValidationException::withMessages([
                            'items' => 'Choose a restock location that belongs to this store.',
                        ]);
                    }
                }

                $previousReceived = (int) $returnItem->received_quantity;
                $delta = max(0, $received - $previousReceived);

                $returnItem->forceFill([
                    'received_quantity' => $received,
                    'condition' => $condition,
                    'disposition' => $restock ? 'restock' : 'hold',
                    'restock' => $restock,
                    'restock_location_id' => $restock ? $restockLocationId : null,
                ])->save();

                if ($delta > 0) {
                    $orderItem = OrderItem::query()
                        ->whereKey($returnItem->order_item_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $orderItem->forceFill([
                        'returned_quantity' => (int) $orderItem->returned_quantity + $delta,
                    ])->save();
                }
            }

            $locked->forceFill([
                'status' => ReturnLifecycle::STATUS_RECEIVED,
                'received_by' => $actor?->id,
                'received_at' => now(),
                'tracking_reference' => $this->blankToNull($payload['tracking_reference'] ?? $locked->tracking_reference),
                'merchant_notes' => $this->blankToNull($payload['merchant_notes'] ?? $locked->merchant_notes),
            ])->save();

            // Apply inventory after the return is marked received.
            foreach ($locked->items()->get() as $returnItem) {
                if ($returnItem->restock) {
                    $this->returnRestockService->restockReturnItem($returnItem, $actor, null);
                }
            }
        }, ReturnLifecycle::EVENT_RETURN_RECEIVED, 'Return received', 'return.received', $actor, $request);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function complete(OrderReturn $return, array $payload = [], ?User $actor = null, ?Request $request = null): OrderReturn
    {
        return $this->transition($return, ReturnLifecycle::STATUS_COMPLETED, function (OrderReturn $locked) use ($payload, $actor): void {
            $locked->forceFill([
                'status' => ReturnLifecycle::STATUS_COMPLETED,
                'completed_by' => $actor?->id,
                'completed_at' => now(),
                'merchant_notes' => $this->blankToNull($payload['merchant_notes'] ?? $locked->merchant_notes),
            ])->save();
        }, ReturnLifecycle::EVENT_RETURN_COMPLETED, 'Return completed', 'return.completed', $actor, $request);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function cancel(OrderReturn $return, array $payload = [], ?User $actor = null, ?Request $request = null): OrderReturn
    {
        return $this->transition($return, ReturnLifecycle::STATUS_CANCELLED, function (OrderReturn $locked) use ($payload, $actor): void {
            if ((int) $locked->items->sum('received_quantity') > 0) {
                throw ValidationException::withMessages([
                    'status' => 'Received returns cannot be cancelled. Complete the return instead.',
                ]);
            }

            $locked->forceFill([
                'status' => ReturnLifecycle::STATUS_CANCELLED,
                'cancelled_by' => $actor?->id,
                'cancelled_at' => now(),
                'merchant_notes' => $this->blankToNull($payload['merchant_notes'] ?? $locked->merchant_notes),
            ])->save();
        }, ReturnLifecycle::EVENT_RETURN_CANCELLED, 'Return cancelled', 'return.cancelled', $actor, $request);
    }

    /**
     * @param  callable(OrderReturn, Order): void  $mutator
     */
    private function transition(
        OrderReturn $return,
        string $toStatus,
        callable $mutator,
        string $eventType,
        string $eventTitle,
        string $securityEvent,
        ?User $actor,
        ?Request $request,
    ): OrderReturn {
        return DB::transaction(function () use ($return, $toStatus, $mutator, $eventType, $eventTitle, $securityEvent, $actor, $request): OrderReturn {
            $locked = OrderReturn::query()
                ->whereKey($return->id)
                ->lockForUpdate()
                ->with(['items', 'order.items', 'order.returns.items', 'order.store'])
                ->firstOrFail();

            if (! ReturnLifecycle::canTransition($locked->status, $toStatus)) {
                throw ValidationException::withMessages([
                    'status' => 'This return cannot move from '.ReturnLifecycle::statusLabel($locked->status).' to '.ReturnLifecycle::statusLabel($toStatus).'.',
                ]);
            }

            $order = $locked->order;
            $mutator($locked, $order);
            $locked->refresh();

            $this->recordEvent(
                $order,
                $eventType,
                $eventTitle,
                'Return '.$locked->return_number.' is now '.ReturnLifecycle::statusLabel($locked->status).'.',
                [
                    'return_id' => $locked->id,
                    'return_number' => $locked->return_number,
                    'status' => $locked->status,
                ],
                $actor
            );

            $this->securityLogRecorder->record(
                $request,
                $securityEvent,
                store: $order->store,
                user: $actor,
                metadata: [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'return_id' => $locked->id,
                    'return_number' => $locked->return_number,
                    'status' => $locked->status,
                ]
            );

            if (in_array($eventType, [
                ReturnLifecycle::EVENT_RETURN_APPROVED,
                ReturnLifecycle::EVENT_RETURN_REJECTED,
                ReturnLifecycle::EVENT_RETURN_RECEIVED,
                ReturnLifecycle::EVENT_RETURN_COMPLETED,
            ], true)) {
                $this->commerceNotifications->returnStatus($locked, $eventType, $eventTitle, $actor);
            }

            return $locked->fresh(['items', 'reason']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function recordEvent(
        Order $order,
        string $eventType,
        string $title,
        string $description,
        array $data,
        ?User $actor,
    ): void {
        $this->orderEventRecorder->record($order, $eventType, $title, $description, $data, $actor);
    }

    private function resolveReason(int $storeId, mixed $reasonId): ?ReturnReason
    {
        if ($reasonId === null || $reasonId === '') {
            return null;
        }

        $reason = ReturnReason::query()
            ->whereKey((int) $reasonId)
            ->where('is_active', true)
            ->where(function ($query) use ($storeId): void {
                $query->whereNull('store_id')->orWhere('store_id', $storeId);
            })
            ->first();

        if (! $reason) {
            throw ValidationException::withMessages([
                'return_reason_id' => 'Choose a valid return reason.',
            ]);
        }

        return $reason;
    }

    /**
     * @param  array<int|string, mixed>  $items
     * @return list<array{order_item: OrderItem, quantity: int}>
     */
    private function validatedRequestLines(Order $order, array $items): array
    {
        $remaining = $this->remainingReturnableQuantities($order);
        $lines = [];

        foreach ($items as $orderItemId => $quantity) {
            if (is_array($quantity)) {
                $orderItemId = $quantity['order_item_id'] ?? $orderItemId;
                $quantity = $quantity['quantity'] ?? 0;
            }

            $qty = (int) $quantity;
            if ($qty < 1) {
                continue;
            }

            $orderItem = $order->items->firstWhere('id', (int) $orderItemId);
            if (! $orderItem) {
                throw ValidationException::withMessages([
                    'items' => 'One of the selected items does not belong to this order.',
                ]);
            }

            if (! ReturnLifecycle::isPhysicalProductType($orderItem->product_type_snapshot)) {
                throw ValidationException::withMessages([
                    'items' => 'Digital and service items cannot be returned through the physical return flow.',
                ]);
            }

            $available = (int) ($remaining[(int) $orderItem->id] ?? 0);
            if ($qty > $available) {
                throw ValidationException::withMessages([
                    'items' => 'You cannot return more than the remaining quantity for '.$orderItem->product_name.'.',
                ]);
            }

            $lines[] = [
                'order_item' => $orderItem,
                'quantity' => $qty,
            ];
        }

        if ($lines === []) {
            throw ValidationException::withMessages([
                'items' => 'Choose at least one item quantity to return.',
            ]);
        }

        return $lines;
    }

    private function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
