<?php

namespace App\Services;

use App\Models\Exchange;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Store;
use App\Models\User;
use Throwable;
use App\Services\Channels\ChannelOwnershipService;
use App\Services\Inventory\InventoryReservationService;
use App\Services\Inventory\InventorySyncService;
use App\Services\Notifications\CommerceNotificationEmitter;
use App\Support\ExchangeLifecycle;
use App\Support\Money\CurrencyPrecision;
use App\Support\NotificationEvent;
use App\Support\OrderLifecycle;
use App\Support\RefundLifecycle;
use App\Support\ReturnLifecycle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExchangeService
{
    public function __construct(
        private readonly OrderNumberGenerator $orderNumberGenerator,
        private readonly OrderEventRecorder $orderEventRecorder,
        private readonly SecurityLogRecorder $securityLogRecorder,
        private readonly InventoryReservationService $inventoryReservationService,
        private readonly InventorySyncService $inventorySyncService,
        private readonly ChannelOwnershipService $channelOwnershipService,
        private readonly RefundService $refundService,
        private readonly CommerceNotificationEmitter $commerceNotifications,
    ) {}

    /**
     * Remaining exchangeable quantity per order item id.
     *
     * @return array<int, int>
     */
    public function remainingExchangeableQuantities(Order $order): array
    {
        $order->loadMissing(['items', 'exchanges.items']);

        $remaining = [];
        foreach ($order->items as $item) {
            $activeClaimed = $this->activeExchangeQuantity($order, (int) $item->id);
            $remaining[(int) $item->id] = max(
                0,
                (int) $item->quantity - (int) $item->refunded_quantity - $activeClaimed
            );
        }

        return $remaining;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function exchangeableItems(Order $order): Collection
    {
        $remaining = $this->remainingExchangeableQuantities($order);

        return $order->items
            ->filter(function (OrderItem $item) use ($remaining): bool {
                if (($remaining[(int) $item->id] ?? 0) < 1) {
                    return false;
                }

                return ReturnLifecycle::isPhysicalProductType($item->product_type_snapshot);
            })
            ->values();
    }

    /**
     * Active physical replacement variants for the store (UI list).
     *
     * @return Collection<int, ProductVariant>
     */
    public function replacementVariantsForStore(Store $store, int $limit = 200): Collection
    {
        return ProductVariant::query()
            ->whereHas('product', function ($query) use ($store): void {
                $query->where('store_id', $store->id)
                    ->where('status', true)
                    ->where(function ($inner): void {
                        $inner->whereNull('product_type')
                            ->orWhere('product_type', '')
                            ->orWhereNotIn('product_type', ['digital', 'service', 'subscription']);
                    });
            })
            ->with('product:id,name,store_id,product_type,status')
            ->orderBy('sku')
            ->limit($limit)
            ->get()
            ->filter(fn (ProductVariant $variant): bool => ReturnLifecycle::isPhysicalProductType($variant->product?->product_type))
            ->values();
    }

    /**
     * Authoritative exchange eligibility for UI visibility and service guards.
     *
     * @return array{
     *     eligible: bool,
     *     reason: ?string,
     *     exchangeable_items: Collection<int, OrderItem>,
     *     replacement_variants: Collection<int, ProductVariant>
     * }
     */
    public function eligibilityForExchange(Order $order, ?Store $store = null, int $replacementLimit = 200): array
    {
        $order->loadMissing(['items', 'exchanges.items', 'store']);
        $store ??= $order->store;
        $exchangeableItems = $this->exchangeableItems($order);
        $replacementVariants = $store
            ? $this->replacementVariantsForStore($store, $replacementLimit)
            : collect();

        if (in_array($order->status, [
            OrderLifecycle::ORDER_PENDING,
            OrderLifecycle::ORDER_CANCELLED,
            OrderLifecycle::ORDER_REFUNDED,
        ], true)) {
            return [
                'eligible' => false,
                'reason' => 'This order cannot accept an exchange in its current state.',
                'exchangeable_items' => $exchangeableItems,
                'replacement_variants' => $replacementVariants,
            ];
        }

        if ($exchangeableItems->isEmpty()) {
            return [
                'eligible' => false,
                'reason' => 'There are no exchangeable items left on this order.',
                'exchangeable_items' => $exchangeableItems,
                'replacement_variants' => $replacementVariants,
            ];
        }

        if ($replacementVariants->isEmpty()) {
            return [
                'eligible' => false,
                'reason' => 'There are no exchangeable items left on this order.',
                'exchangeable_items' => $exchangeableItems,
                'replacement_variants' => $replacementVariants,
            ];
        }

        return [
            'eligible' => true,
            'reason' => null,
            'exchangeable_items' => $exchangeableItems,
            'replacement_variants' => $replacementVariants,
        ];
    }

    public function canCreateExchange(Order $order, ?Store $store = null): bool
    {
        return $this->eligibilityForExchange($order, $store)['eligible'];
    }

    public function assertOrderAcceptsExchange(Order $order): void
    {
        if (in_array($order->status, [
            OrderLifecycle::ORDER_PENDING,
            OrderLifecycle::ORDER_CANCELLED,
            OrderLifecycle::ORDER_REFUNDED,
        ], true)) {
            throw ValidationException::withMessages([
                'order' => 'This order cannot accept an exchange in its current state.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createExchange(Order $order, array $payload, ?User $actor = null, ?Request $request = null): Exchange
    {
        $idempotencyKey = $this->blankToNull($payload['idempotency_key'] ?? null);
        $requestHash = $this->requestHash($payload);

        return DB::transaction(function () use ($order, $payload, $actor, $request, $idempotencyKey, $requestHash): Exchange {
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->with(['items', 'store', 'exchanges.items'])
                ->firstOrFail();

            if ($idempotencyKey) {
                $existing = Exchange::query()
                    ->where('store_id', $order->store_id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if ((string) $existing->request_hash !== '' && $existing->request_hash !== $requestHash) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => 'This idempotency key was already used with a different exchange request.',
                        ]);
                    }

                    return $existing->load(['items']);
                }
            }

            $this->assertOrderAcceptsExchange($order);

            $orderItemId = (int) ($payload['order_item_id'] ?? 0);
            $quantity = max(1, (int) ($payload['quantity'] ?? 1));
            $replacementVariantId = (int) ($payload['replacement_variant_id'] ?? 0);

            $orderItem = $order->items->firstWhere('id', $orderItemId);
            if (! $orderItem instanceof OrderItem) {
                throw ValidationException::withMessages([
                    'order_item_id' => 'Choose an order item to exchange.',
                ]);
            }

            if (! ReturnLifecycle::isPhysicalProductType($orderItem->product_type_snapshot)) {
                throw ValidationException::withMessages([
                    'order_item_id' => 'Only physical products can be exchanged.',
                ]);
            }

            $activeClaimed = $this->activeExchangeQuantity($order, $orderItemId);
            $available = max(0, (int) $orderItem->quantity - (int) $orderItem->refunded_quantity - $activeClaimed);
            if ($quantity > $available) {
                throw ValidationException::withMessages([
                    'quantity' => $available < 1
                        ? 'There are no exchangeable items left on this order.'
                        : 'Exchange quantity exceeds what remains on this line.',
                ]);
            }

            $returnId = isset($payload['return_id']) && $payload['return_id'] !== ''
                ? (int) $payload['return_id']
                : null;
            if ($returnId) {
                $belongs = OrderReturn::query()
                    ->whereKey($returnId)
                    ->where('store_id', $order->store_id)
                    ->where('order_id', $order->id)
                    ->exists();
                if (! $belongs) {
                    throw ValidationException::withMessages([
                        'return_id' => 'Choose a return that belongs to this order.',
                    ]);
                }
            }

            $variant = ProductVariant::query()
                ->with('product')
                ->whereKey($replacementVariantId)
                ->first();

            if (! $variant || (int) $variant->product?->store_id !== (int) $order->store_id) {
                throw ValidationException::withMessages([
                    'replacement_variant_id' => 'Choose a replacement variant from this store.',
                ]);
            }

            if (! $variant->product || ! $variant->product->status) {
                throw ValidationException::withMessages([
                    'replacement_variant_id' => 'Choose a replacement variant from this store.',
                ]);
            }

            if (! ReturnLifecycle::isPhysicalProductType($variant->product->product_type)) {
                throw ValidationException::withMessages([
                    'replacement_variant_id' => 'Only physical products can be exchanged.',
                ]);
            }

            if ((int) $variant->id === (int) $orderItem->product_variant_id) {
                throw ValidationException::withMessages([
                    'replacement_variant_id' => 'Choose a different variant for the exchange.',
                ]);
            }

            $currency = strtoupper((string) ($order->currency_code ?: $order->store?->currency ?: 'USD'));
            $scale = CurrencyPrecision::scale($currency);

            $itemQty = max(1, (int) $orderItem->quantity);
            $netLine = CurrencyPrecision::roundMajor(
                bcsub(
                    (string) ($orderItem->subtotal ?: '0'),
                    (string) ($orderItem->discount_amount ?: '0'),
                    8
                ),
                $currency
            );
            $outboundTotal = CurrencyPrecision::roundMajor(
                bcmul($netLine, bcdiv((string) $quantity, (string) $itemQty, 8), 8),
                $currency
            );
            $inboundUnit = CurrencyPrecision::roundMajor((string) ($variant->price ?: '0'), $currency);
            $inboundTotal = CurrencyPrecision::roundMajor(bcmul($inboundUnit, (string) $quantity, $scale + 2), $currency);
            $priceDifference = CurrencyPrecision::roundMajor(bcsub($inboundTotal, $outboundTotal, $scale + 2), $currency);
            $balanceDue = bccomp($priceDifference, '0', $scale) > 0
                ? $priceDifference
                : CurrencyPrecision::roundMajor('0', $currency);

            try {
                $exchange = Exchange::query()->create([
                    'store_id' => $order->store_id,
                    'order_id' => $order->id,
                    'return_id' => $returnId,
                    'exchange_number' => $this->orderNumberGenerator->generateExchange($order->store),
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'status' => ExchangeLifecycle::STATUS_REQUESTED,
                    'currency_code' => $currency,
                    'outbound_total' => $outboundTotal,
                    'inbound_total' => $inboundTotal,
                    'price_difference' => $priceDifference,
                    'balance_due' => $balanceDue,
                    'collected_amount' => CurrencyPrecision::roundMajor('0', $currency),
                    'notes' => $this->blankToNull($payload['notes'] ?? null),
                    'created_by' => $actor?->id,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                if (! $idempotencyKey) {
                    throw $e;
                }

                $existing = Exchange::query()
                    ->where('store_id', $order->store_id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if (! $existing) {
                    throw $e;
                }

                if ((string) $existing->request_hash !== '' && $existing->request_hash !== $requestHash) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'This idempotency key was already used with a different exchange request.',
                    ]);
                }

                return $existing->load(['items']);
            }

            $exchange->items()->create([
                'store_id' => $order->store_id,
                'direction' => ExchangeLifecycle::DIRECTION_OUTBOUND,
                'order_item_id' => $orderItem->id,
                'product_id' => $orderItem->product_id,
                'product_variant_id' => $orderItem->product_variant_id,
                'quantity' => $quantity,
                'unit_price' => CurrencyPrecision::roundMajor(
                    bcdiv($netLine, (string) $itemQty, 8),
                    $currency
                ),
                'line_total' => $outboundTotal,
                'product_name_snapshot' => $orderItem->product_name,
                'variant_label_snapshot' => $orderItem->variant_label,
                'sku_snapshot' => $orderItem->sku_snapshot,
            ]);

            $inboundItem = $exchange->items()->create([
                'store_id' => $order->store_id,
                'direction' => ExchangeLifecycle::DIRECTION_INBOUND,
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'quantity' => $quantity,
                'unit_price' => $inboundUnit,
                'line_total' => $inboundTotal,
                'product_name_snapshot' => $variant->product?->name,
                'variant_label_snapshot' => $variant->sku,
                'sku_snapshot' => $variant->sku,
            ]);

            if ($this->orderUsesPlatformInventory($order)) {
                $inventoryItem = $this->inventorySyncService->ensureInventoryItemForVariant($variant);
                $reservation = $this->inventoryReservationService->reserve(
                    $inventoryItem,
                    $quantity,
                    Exchange::class,
                    $exchange->id,
                    null,
                    null,
                    [
                        'order' => $order,
                        'validation_key' => 'replacement_variant_id',
                        'reserve_reason' => 'Stock reserved for exchange '.$exchange->exchange_number,
                        'metadata' => ['exchange_id' => $exchange->id],
                    ]
                );

                $inboundItem->forceFill([
                    'inventory_reservation_id' => $reservation->id,
                ])->save();

                $exchange->forceFill([
                    'status' => ExchangeLifecycle::STATUS_RESERVED,
                ])->save();
            }

            $this->orderEventRecorder->record(
                $order,
                ExchangeLifecycle::EVENT_EXCHANGE_CREATED,
                'Exchange created',
                'Exchange '.$exchange->exchange_number.' was created.',
                [
                    'exchange_id' => $exchange->id,
                    'exchange_number' => $exchange->exchange_number,
                    'price_difference' => $priceDifference,
                ],
                $actor
            );

            $this->securityLogRecorder->record(
                $request,
                'exchange.created',
                store: $order->store,
                user: $actor,
                metadata: [
                    'order_id' => $order->id,
                    'exchange_id' => $exchange->id,
                    'exchange_number' => $exchange->exchange_number,
                ]
            );

            $this->commerceNotifications->exchangeEvent(
                $exchange,
                NotificationEvent::EXCHANGE_CREATED,
                'Exchange created',
                $actor
            );

            return $exchange->fresh(['items']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordCollection(Exchange $exchange, array $payload, ?User $actor = null, ?Request $request = null): Exchange
    {
        return DB::transaction(function () use ($exchange, $payload, $actor, $request): Exchange {
            $exchange = Exchange::query()->whereKey($exchange->id)->lockForUpdate()->firstOrFail();
            $currency = (string) $exchange->currency_code;
            $scale = CurrencyPrecision::scale($currency);

            if (! in_array($exchange->status, [ExchangeLifecycle::STATUS_REQUESTED, ExchangeLifecycle::STATUS_RESERVED], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Collection can only be recorded for open exchanges awaiting payment.',
                ]);
            }

            if (bccomp((string) $exchange->balance_due, '0', $scale) <= 0) {
                throw ValidationException::withMessages([
                    'balance_due' => 'This exchange does not have an upgrade balance to collect.',
                ]);
            }

            $remaining = CurrencyPrecision::roundMajor(
                bcsub((string) $exchange->balance_due, (string) $exchange->collected_amount, 8),
                $currency
            );
            if (bccomp($remaining, '0', $scale) <= 0) {
                throw ValidationException::withMessages([
                    'collected_amount' => 'The upgrade balance has already been collected.',
                ]);
            }

            $amount = CurrencyPrecision::roundMajor((string) ($payload['collected_amount'] ?? '0'), $currency);
            if (bccomp($amount, $remaining, $scale) !== 0) {
                throw ValidationException::withMessages([
                    'collected_amount' => 'Collect the exact remaining balance of '.$remaining.' '.$currency.'.',
                ]);
            }

            $method = $this->blankToNull($payload['collection_method'] ?? null);
            if (! in_array($method, ['manual', 'external'], true)) {
                throw ValidationException::withMessages([
                    'collection_method' => 'Use manual or external collection evidence for upgrade balances.',
                ]);
            }

            $reference = $this->blankToNull($payload['collection_reference'] ?? null);
            if (! $reference) {
                throw ValidationException::withMessages([
                    'collection_reference' => 'Add a collection reference for this payment.',
                ]);
            }

            $exchange->forceFill([
                'collected_amount' => CurrencyPrecision::roundMajor(
                    bcadd((string) $exchange->collected_amount, $amount, 8),
                    $currency
                ),
                'collection_method' => $method,
                'collection_reference' => $reference,
                'collected_at' => now(),
                'collection_evidence' => [
                    'note' => $this->blankToNull($payload['notes'] ?? null),
                    'recorded_by' => $actor?->id,
                    'amount' => $amount,
                    'method' => $method,
                    'reference' => $reference,
                ],
            ])->save();

            $this->securityLogRecorder->record(
                $request,
                'exchange.collection_recorded',
                store: $exchange->order?->store ?? $exchange->store,
                user: $actor,
                metadata: [
                    'exchange_id' => $exchange->id,
                    'collected_amount' => $amount,
                    'collection_method' => $method,
                ]
            );

            return $exchange->fresh(['items']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function complete(Exchange $exchange, array $payload = [], ?User $actor = null, ?Request $request = null): Exchange
    {
        $claim = DB::transaction(function () use ($exchange, $payload): array {
            $exchange = Exchange::query()
                ->whereKey($exchange->id)
                ->lockForUpdate()
                ->with(['items.reservation', 'order.store', 'refund'])
                ->firstOrFail();

            if ($exchange->status === ExchangeLifecycle::STATUS_COMPLETED) {
                return ['exchange' => $exchange, 'already_done' => true];
            }

            if ($exchange->status === ExchangeLifecycle::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'status' => 'This exchange was cancelled and cannot be completed.',
                ]);
            }

            $currency = (string) $exchange->currency_code;
            $scale = CurrencyPrecision::scale($currency);
            $priceDifference = CurrencyPrecision::roundMajor((string) $exchange->price_difference, $currency);
            $resuming = $exchange->status === ExchangeLifecycle::STATUS_PROCESSING;

            if (! $resuming) {
                if (! in_array($exchange->status, [ExchangeLifecycle::STATUS_REQUESTED, ExchangeLifecycle::STATUS_RESERVED], true)) {
                    throw ValidationException::withMessages([
                        'status' => 'This exchange cannot be completed in its current status.',
                    ]);
                }

                if (bccomp($priceDifference, '0', $scale) > 0) {
                    if (bccomp((string) $exchange->collected_amount, (string) $exchange->balance_due, $scale) < 0) {
                        throw ValidationException::withMessages([
                            'balance_due' => 'Collect the upgrade balance before completing this exchange.',
                        ]);
                    }
                }

                $previousStatus = (string) $exchange->status;
                $exchange->forceFill([
                    'status' => ExchangeLifecycle::STATUS_PROCESSING,
                    'notes' => $this->blankToNull($payload['notes'] ?? $exchange->notes),
                    'meta' => array_merge($exchange->meta ?? [], [
                        'completion_previous_status' => $previousStatus,
                    ]),
                ])->save();
            } else {
                $previousStatus = (string) data_get($exchange->meta, 'completion_previous_status', ExchangeLifecycle::STATUS_RESERVED);
            }

            return [
                'exchange' => $exchange->fresh(['items.reservation', 'order.store', 'refund']),
                'previous_status' => $previousStatus,
                'already_done' => false,
                'resuming' => $resuming,
                'price_difference' => $priceDifference,
                'currency' => $currency,
                'scale' => $scale,
            ];
        });

        if (! empty($claim['already_done'])) {
            return $claim['exchange'];
        }

        $exchange = $claim['exchange'];
        $previousStatus = $claim['previous_status'];
        $priceDifference = $claim['price_difference'];
        $currency = $claim['currency'];
        $scale = $claim['scale'];
        $refund = null;

        try {
            if (bccomp($priceDifference, '0', $scale) < 0) {
                $refundKey = 'exchange_refund_'.$exchange->id;
                $existingRefund = Refund::query()
                    ->where('store_id', $exchange->store_id)
                    ->where('idempotency_key', $refundKey)
                    ->first();

                if ($existingRefund && $existingRefund->status === RefundLifecycle::STATUS_SUCCEEDED) {
                    $refund = $existingRefund;
                } elseif ($existingRefund) {
                    $refund = $this->refundService->recheckOrRetryRefund($existingRefund, $actor, $request);
                } else {
                    $refundAmount = CurrencyPrecision::roundMajor(bcmul($priceDifference, '-1', 8), $currency);
                    $refund = $this->refundService->refundOrder($exchange->order, [
                        'amount' => $refundAmount,
                        'reason' => 'Exchange price difference',
                        'notes' => 'Auto refund for exchange '.$exchange->exchange_number,
                        'processed_externally' => true,
                        'idempotency_key' => $refundKey,
                    ], $actor, $request);
                }

                if ($refund->status !== RefundLifecycle::STATUS_SUCCEEDED) {
                    throw ValidationException::withMessages([
                        'refund' => 'The exchange price-difference refund must succeed before completion. Use Recheck on the refund, then complete again.',
                    ]);
                }
            }

            return DB::transaction(function () use ($exchange, $payload, $actor, $request, $refund): Exchange {
                $exchange = Exchange::query()
                    ->whereKey($exchange->id)
                    ->lockForUpdate()
                    ->with(['items.reservation', 'order.store'])
                    ->firstOrFail();

                if ($exchange->status === ExchangeLifecycle::STATUS_CANCELLED) {
                    throw ValidationException::withMessages([
                        'status' => 'This exchange was cancelled before completion could finish.',
                    ]);
                }

                if ($exchange->status === ExchangeLifecycle::STATUS_COMPLETED) {
                    return $exchange->load(['items', 'refund']);
                }

                if ($exchange->status !== ExchangeLifecycle::STATUS_PROCESSING) {
                    $exchange->forceFill(['status' => ExchangeLifecycle::STATUS_PROCESSING])->save();
                }

                foreach ($exchange->items as $item) {
                    if ($item->direction !== ExchangeLifecycle::DIRECTION_INBOUND || ! $item->inventory_reservation_id) {
                        continue;
                    }

                    $reservation = $item->reservation;
                    if ($reservation) {
                        $this->inventoryReservationService->commit($reservation, [
                            'commit_reason' => 'Exchange '.$exchange->exchange_number.' completed',
                        ]);
                    }
                }

                $exchange->forceFill([
                    'status' => ExchangeLifecycle::STATUS_COMPLETED,
                    'refund_id' => $refund?->id ?? $exchange->refund_id,
                    'completed_by' => $actor?->id,
                    'completed_at' => now(),
                    'notes' => $this->blankToNull($payload['notes'] ?? $exchange->notes),
                ])->save();

                $this->orderEventRecorder->record(
                    $exchange->order,
                    ExchangeLifecycle::EVENT_EXCHANGE_COMPLETED,
                    'Exchange completed',
                    'Exchange '.$exchange->exchange_number.' was completed.',
                    [
                        'exchange_id' => $exchange->id,
                        'refund_id' => $refund?->id,
                        'price_difference' => $exchange->price_difference,
                    ],
                    $actor
                );

                $this->securityLogRecorder->record(
                    $request,
                    'exchange.completed',
                    store: $exchange->order->store,
                    user: $actor,
                    metadata: [
                        'order_id' => $exchange->order_id,
                        'exchange_id' => $exchange->id,
                    ]
                );

                $this->commerceNotifications->exchangeEvent(
                    $exchange,
                    NotificationEvent::EXCHANGE_COMPLETED,
                    'Exchange completed',
                    $actor
                );

                return $exchange->fresh(['items', 'refund']);
            });
        } catch (ValidationException $e) {
            $hasPersistedRefund = Refund::query()
                ->where('store_id', $exchange->store_id)
                ->where('idempotency_key', 'exchange_refund_'.$exchange->id)
                ->exists();

            // Roll back only when no cheaper-exchange refund was persisted yet.
            // Once a refund exists, stay processing so Complete can resume safely.
            if (empty($claim['resuming']) && ! $hasPersistedRefund) {
                $this->restoreExchangeStatus($exchange, $previousStatus);
            }
            throw $e;
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'status' => 'Exchange completion was interrupted. Try Complete again to finish safely.',
            ]);
        }
    }

    public function cancel(Exchange $exchange, ?User $actor = null, ?Request $request = null): Exchange
    {
        return DB::transaction(function () use ($exchange, $actor, $request): Exchange {
            $exchange = Exchange::query()
                ->whereKey($exchange->id)
                ->lockForUpdate()
                ->with(['items.reservation', 'order.store'])
                ->firstOrFail();

            if ($exchange->status === ExchangeLifecycle::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'status' => 'Completed exchanges cannot be cancelled.',
                ]);
            }

            if ($exchange->status === ExchangeLifecycle::STATUS_PROCESSING) {
                throw ValidationException::withMessages([
                    'status' => 'This exchange is being completed and cannot be cancelled right now.',
                ]);
            }

            if ($exchange->status === ExchangeLifecycle::STATUS_CANCELLED) {
                return $exchange;
            }

            foreach ($exchange->items as $item) {
                if ($item->reservation) {
                    $this->inventoryReservationService->release($item->reservation, [
                        'release_reason' => 'Exchange '.$exchange->exchange_number.' cancelled',
                    ]);
                }
            }

            $exchange->forceFill([
                'status' => ExchangeLifecycle::STATUS_CANCELLED,
                'cancelled_by' => $actor?->id,
                'cancelled_at' => now(),
            ])->save();

            $this->orderEventRecorder->record(
                $exchange->order,
                ExchangeLifecycle::EVENT_EXCHANGE_CANCELLED,
                'Exchange cancelled',
                'Exchange '.$exchange->exchange_number.' was cancelled.',
                ['exchange_id' => $exchange->id],
                $actor
            );

            $this->securityLogRecorder->record(
                $request,
                'exchange.cancelled',
                store: $exchange->order->store,
                user: $actor,
                metadata: [
                    'order_id' => $exchange->order_id,
                    'exchange_id' => $exchange->id,
                ]
            );

            return $exchange->fresh(['items']);
        });
    }

    private function restoreExchangeStatus(Exchange $exchange, string $previousStatus): void
    {
        DB::transaction(function () use ($exchange, $previousStatus): void {
            $exchange = Exchange::query()->whereKey($exchange->id)->lockForUpdate()->firstOrFail();
            if ($exchange->status !== ExchangeLifecycle::STATUS_PROCESSING) {
                return;
            }

            $restoreTo = in_array($previousStatus, [
                ExchangeLifecycle::STATUS_REQUESTED,
                ExchangeLifecycle::STATUS_RESERVED,
            ], true) ? $previousStatus : ExchangeLifecycle::STATUS_RESERVED;

            $exchange->forceFill([
                'status' => $restoreTo,
            ])->save();
        });
    }

    private function activeExchangeQuantity(Order $order, int $orderItemId): int
    {
        $qty = 0;
        foreach ($order->exchanges as $exchange) {
            if (! in_array($exchange->status, [
                ExchangeLifecycle::STATUS_REQUESTED,
                ExchangeLifecycle::STATUS_RESERVED,
                ExchangeLifecycle::STATUS_PROCESSING,
            ], true)) {
                continue;
            }

            foreach ($exchange->items as $item) {
                if ($item->direction === ExchangeLifecycle::DIRECTION_OUTBOUND
                    && (int) $item->order_item_id === $orderItemId) {
                    $qty += (int) $item->quantity;
                }
            }
        }

        return $qty;
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requestHash(array $payload): string
    {
        $normalized = [
            'order_item_id' => isset($payload['order_item_id']) ? (int) $payload['order_item_id'] : null,
            'quantity' => isset($payload['quantity']) ? (int) $payload['quantity'] : null,
            'replacement_variant_id' => isset($payload['replacement_variant_id']) ? (int) $payload['replacement_variant_id'] : null,
            'return_id' => isset($payload['return_id']) && $payload['return_id'] !== '' ? (int) $payload['return_id'] : null,
        ];

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
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
