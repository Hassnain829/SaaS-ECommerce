<?php

namespace App\Services;

use App\Data\Payments\PaymentRefundResult;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\PaymentIntent;
use App\Models\Refund;
use App\Models\User;
use App\Services\Channels\ChannelOwnershipService;
use App\Services\Payments\PaymentProviderManager;
use App\Support\Money\CurrencyPrecision;
use App\Support\Money\DecimalString;
use App\Support\OrderLifecycle;
use App\Support\RefundLifecycle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class RefundService
{
    public function __construct(
        private readonly OrderNumberGenerator $orderNumberGenerator,
        private readonly OrderEventRecorder $orderEventRecorder,
        private readonly SecurityLogRecorder $securityLogRecorder,
        private readonly PaymentProviderManager $paymentProviderManager,
        private readonly ChannelOwnershipService $channelOwnershipService,
        private readonly CustomerMetricsService $customerMetricsService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function refundOrder(Order $order, array $payload, ?User $actor = null, ?Request $request = null): Refund
    {
        $idempotencyKey = $this->blankToNull($payload['idempotency_key'] ?? null);
        $currencyHint = strtoupper((string) ($order->currency_code ?: $order->store?->currency ?: 'USD'));
        $requestHash = $this->requestHash($payload, $currencyHint);

        /** @var array{order: Order, refund: Refund, breakdown: array<string, mixed>, routing: array<string, mixed>, method: string} $prepared */
        $prepared = DB::transaction(function () use ($order, $payload, $actor, $idempotencyKey, $requestHash): array {
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->with(['items', 'store', 'paymentIntents.paymentProviderAccount', 'paymentIntents.captures', 'customer', 'refunds.items', 'refunds.adjustments'])
                ->firstOrFail();

            $currency = strtoupper((string) ($order->currency_code ?: $order->store?->currency ?: 'USD'));
            $requestHash = $this->requestHash($payload, $currency);

            if ($idempotencyKey) {
                $existing = Refund::query()
                    ->where('store_id', $order->store_id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if ((string) $existing->request_hash !== '' && $existing->request_hash !== $requestHash) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => 'This idempotency key was already used with a different refund request.',
                        ]);
                    }

                    return [
                        'order' => $order,
                        'refund' => $existing->load(['items', 'adjustments']),
                        'breakdown' => $this->breakdownFromRefundRecord($existing),
                        'routing' => $this->resolveRefundRouting($order),
                        'method' => (string) $existing->method,
                        'resume' => true,
                    ];
                }
            }

            $this->assertPaymentEligible($order);

            $routing = $this->resolveRefundRouting($order);

            if ($routing['payment_owner'] === ChannelOwnershipService::OWNER_PLATFORM && ! $routing['payment_intent']) {
                throw ValidationException::withMessages([
                    'payment' => 'Platform-owned payments require an eligible captured payment before refunding.',
                ]);
            }

            $breakdown = $this->buildRefundBreakdown($order, $payload, $currency);
            $amount = $breakdown['amount'];
            $amountMinor = CurrencyPrecision::toMinorUnits($amount, $currency);

            if ($amountMinor < 1) {
                throw ValidationException::withMessages([
                    'amount' => 'Refund amount must be greater than zero.',
                ]);
            }

            $refundableMinor = $this->remainingRefundableMinor($order, $currency);
            if ($amountMinor > $refundableMinor) {
                throw ValidationException::withMessages([
                    'amount' => 'Refund cannot exceed the remaining refundable amount.',
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

            $method = $routing['requires_provider']
                ? RefundLifecycle::METHOD_PROVIDER
                : ((bool) ($payload['processed_externally'] ?? true)
                    ? RefundLifecycle::METHOD_EXTERNAL
                    : RefundLifecycle::METHOD_MANUAL);

            if ($routing['payment_owner'] === ChannelOwnershipService::OWNER_PLATFORM) {
                $method = RefundLifecycle::METHOD_PROVIDER;
            }

            $providerIdempotencyKey = $idempotencyKey
                ?: ('refund_order_'.$order->id.'_'.substr($requestHash, 0, 24));

            try {
                $refund = Refund::query()->create([
                    'store_id' => $order->store_id,
                    'order_id' => $order->id,
                    'return_id' => $returnId,
                    'payment_intent_id' => $routing['payment_intent']?->id,
                    'payment_provider_account_id' => $routing['payment_provider_account_id'],
                    'refund_number' => $this->orderNumberGenerator->generateRefund($order->store),
                    'status' => RefundLifecycle::STATUS_PENDING,
                    'method' => $method,
                    'currency_code' => $currency,
                    'amount' => $amount,
                    'amount_minor' => $amountMinor,
                    'reason' => $this->blankToNull($payload['reason'] ?? null),
                    'notes' => $this->blankToNull($payload['notes'] ?? null),
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'provider_idempotency_key' => $method === RefundLifecycle::METHOD_PROVIDER ? $providerIdempotencyKey : null,
                    'provider' => $routing['provider'],
                    'mode' => $routing['mode'],
                    'provider_account_id' => $routing['provider_account_id'],
                    'payment_owner' => $routing['payment_owner'],
                    'order_source_snapshot' => $routing['order_source'],
                    'routing_snapshot' => $routing['snapshot'],
                    'requested_by' => $actor?->id,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                if (! $idempotencyKey) {
                    throw $e;
                }

                $existing = Refund::query()
                    ->where('store_id', $order->store_id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if (! $existing) {
                    throw $e;
                }

                if ((string) $existing->request_hash !== '' && $existing->request_hash !== $requestHash) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'This idempotency key was already used with a different refund request.',
                    ]);
                }

                return [
                    'order' => $order,
                    'refund' => $existing->load(['items', 'adjustments']),
                    'breakdown' => $this->breakdownFromRefundRecord($existing),
                    'routing' => $routing,
                    'method' => (string) $existing->method,
                    'resume' => true,
                ];
            }

            foreach ($breakdown['items'] as $line) {
                $refund->items()->create([
                    'store_id' => $order->store_id,
                    'order_item_id' => $line['order_item']->id,
                    'quantity' => $line['quantity'],
                    'unit_amount' => $line['unit_amount'],
                    'subtotal' => $line['subtotal'],
                    'discount_amount' => $line['discount_amount'],
                    'tax_amount' => $line['tax_amount'],
                    'total' => $line['total'],
                    'total_minor' => $line['total_minor'],
                    'product_name_snapshot' => $line['order_item']->product_name,
                    'variant_label_snapshot' => $line['order_item']->variant_label,
                    'sku_snapshot' => $line['order_item']->sku_snapshot,
                ]);
            }

            foreach ($breakdown['adjustments'] as $adjustment) {
                $refund->adjustments()->create([
                    'store_id' => $order->store_id,
                    'type' => $adjustment['type'],
                    'label' => $adjustment['label'],
                    'amount' => $adjustment['amount'],
                ]);
            }

            $this->orderEventRecorder->record(
                $order,
                RefundLifecycle::EVENT_REFUND_REQUESTED,
                'Refund requested',
                'Refund '.$refund->refund_number.' for '.$amount.' '.$currency.' was requested.',
                [
                    'refund_id' => $refund->id,
                    'refund_number' => $refund->refund_number,
                    'amount' => $amount,
                    'method' => $method,
                ],
                $actor
            );

            return [
                'order' => $order,
                'refund' => $refund->fresh(['items', 'adjustments']),
                'breakdown' => $breakdown,
                'routing' => $routing,
                'method' => $method,
                'resume' => false,
            ];
        });

        $refund = $prepared['refund'];
        $order = $prepared['order'];
        $routing = $prepared['routing'];
        $method = $prepared['method'];
        $breakdown = $prepared['breakdown'];

        if (! empty($prepared['resume'])) {
            return $this->resumeExistingRefund($refund, $actor, $request);
        }

        if ($method !== RefundLifecycle::METHOD_PROVIDER) {
            return $this->finalizeSucceededRefund($order, $refund, $breakdown, $actor, $request);
        }

        return $this->processProviderRefund($order, $refund, $breakdown, $routing, $actor, $request);
    }

    public function remainingRefundableAmount(Order $order, ?string $currency = null): string
    {
        $currency = strtoupper((string) ($currency ?: $order->currency_code ?: 'USD'));

        return CurrencyPrecision::fromMinorUnits($this->remainingRefundableMinor($order, $currency), $currency);
    }

    public function remainingRefundableMinor(Order $order, ?string $currency = null): int
    {
        $currency = strtoupper((string) ($currency ?: $order->currency_code ?: 'USD'));
        $capturedMinor = $this->capturedPayableMinor($order, $currency);
        $allocatedMinor = $this->allocatedRefundMinor($order);

        return max(0, $capturedMinor - $allocatedMinor);
    }

    /**
     * @return array{
     *   requires_provider: bool,
     *   payment_owner: string,
     *   order_source: ?string,
     *   payment_intent: ?PaymentIntent,
     *   payment_provider_account_id: ?int,
     *   provider: ?string,
     *   mode: ?string,
     *   provider_account_id: ?string,
     *   snapshot: array<string, mixed>
     * }
     */
    public function resolveRefundRouting(Order $order): array
    {
        $order->loadMissing(['store', 'paymentIntents.paymentProviderAccount', 'paymentIntents.captures']);

        $snapshotOwner = data_get($order->meta, 'channel_ownership.payment_owner');
        $orderSource = $order->order_source ? (string) $order->order_source : null;

        $paymentIntent = $this->eligiblePaymentIntent($order);

        $paymentOwner = is_string($snapshotOwner) && $snapshotOwner !== ''
            ? $snapshotOwner
            : (
                $orderSource === 'external_checkout'
                    ? ChannelOwnershipService::OWNER_EXTERNAL
                    : (
                        $paymentIntent
                            ? ChannelOwnershipService::OWNER_PLATFORM
                            : $this->channelOwnershipService->paymentOwner(
                                $order->store,
                                $orderSource === 'platform_checkout' ? 'platform_checkout' : $orderSource
                            )
                    )
            );

        if ($orderSource === 'platform_checkout' || $paymentOwner === ChannelOwnershipService::OWNER_PLATFORM) {
            $paymentOwner = ChannelOwnershipService::OWNER_PLATFORM;
        }

        if ($orderSource === 'external_checkout' && ! $paymentIntent) {
            $paymentOwner = ChannelOwnershipService::OWNER_EXTERNAL;
        }

        $requiresProvider = $paymentOwner === ChannelOwnershipService::OWNER_PLATFORM;

        return [
            'requires_provider' => $requiresProvider,
            'payment_owner' => $paymentOwner,
            'order_source' => $orderSource,
            'payment_intent' => $paymentIntent,
            'payment_provider_account_id' => $paymentIntent?->payment_provider_account_id,
            'provider' => $paymentIntent?->provider,
            'mode' => $paymentIntent?->mode,
            'provider_account_id' => $paymentIntent?->provider_account_id,
            'snapshot' => [
                'payment_owner' => $paymentOwner,
                'order_source' => $orderSource,
                'payment_intent_id' => $paymentIntent?->id,
                'provider_intent_id' => $paymentIntent?->provider_intent_id,
                'payment_provider_account_id' => $paymentIntent?->payment_provider_account_id,
                'mode' => $paymentIntent?->mode,
                'provider_account_id' => $paymentIntent?->provider_account_id,
                'resolved_without_meta_mutation' => true,
            ],
        ];
    }

    private function resumeExistingRefund(Refund $refund, ?User $actor, ?Request $request): Refund
    {
        $refund->loadMissing(['items', 'adjustments', 'order.store', 'order.customer', 'paymentIntent.paymentProviderAccount']);

        if ($refund->status === RefundLifecycle::STATUS_SUCCEEDED) {
            return $refund;
        }

        if (in_array($refund->status, [RefundLifecycle::STATUS_PENDING, RefundLifecycle::STATUS_PROCESSING], true)
            && $refund->method === RefundLifecycle::METHOD_PROVIDER) {
            return $this->recheckOrRetryProviderRefund($refund, $actor, $request);
        }

        if ($refund->status === RefundLifecycle::STATUS_FAILED
            && $refund->method === RefundLifecycle::METHOD_PROVIDER) {
            return $this->recheckOrRetryProviderRefund($refund, $actor, $request);
        }

        return $refund;
    }

    private function recheckOrRetryProviderRefund(Refund $refund, ?User $actor, ?Request $request): Refund
    {
        $order = $refund->order;
        $routing = $this->resolveRefundRouting($order);
        $paymentIntent = $refund->paymentIntent ?: $routing['payment_intent'];

        if (! $paymentIntent) {
            throw ValidationException::withMessages([
                'payment' => 'This refund cannot be retried without the original payment.',
            ]);
        }

        $provider = $this->paymentProviderManager->driver($refund->provider ?: 'stripe');
        $options = [
            'provider_account' => $paymentIntent->paymentProviderAccount,
            'mode' => $refund->mode ?: $paymentIntent->mode,
            'idempotency_key' => $refund->provider_idempotency_key,
        ];

        try {
            if (filled($refund->provider_refund_id)) {
                $result = $provider->retrieveRefund((string) $refund->provider_refund_id, $paymentIntent, $options);
            } else {
                $result = $provider->createRefund(
                    $paymentIntent,
                    (int) $refund->amount_minor,
                    (string) $refund->currency_code,
                    $options
                );
            }
        } catch (Throwable $e) {
            $this->markRefundFailed($order, $refund, $actor, $e->getMessage(), [
                'provider_error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'payment' => 'The refund could not be processed: '.$e->getMessage(),
            ]);
        }

        return $this->handleProviderResult($order, $refund, $result, $paymentIntent, $actor, $request);
    }

    /**
     * @param  array<string, mixed>  $routing
     * @param  array<string, mixed>  $breakdown
     */
    private function processProviderRefund(
        Order $order,
        Refund $refund,
        array $breakdown,
        array $routing,
        ?User $actor,
        ?Request $request,
    ): Refund {
        $paymentIntent = $routing['payment_intent'];
        if (! $paymentIntent) {
            $this->markRefundFailed($order, $refund, $actor, 'Missing payment intent');

            throw ValidationException::withMessages([
                'payment' => 'Platform-owned payments require an eligible captured payment before refunding.',
            ]);
        }

        try {
            $provider = $this->paymentProviderManager->driver($routing['provider'] ?? 'stripe');
            $result = $provider->createRefund(
                $paymentIntent,
                (int) $refund->amount_minor,
                (string) $refund->currency_code,
                [
                    'provider_account' => $paymentIntent->paymentProviderAccount,
                    'mode' => $routing['mode'],
                    'idempotency_key' => $refund->provider_idempotency_key,
                    'reason' => 'requested_by_customer',
                ]
            );
        } catch (Throwable $e) {
            $this->markRefundFailed($order, $refund, $actor, $e->getMessage(), [
                'provider_error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'payment' => 'The refund could not be processed: '.$e->getMessage(),
            ]);
        }

        return $this->handleProviderResult($order, $refund, $result, $paymentIntent, $actor, $request, $breakdown);
    }

    /**
     * @param  array<string, mixed>|null  $breakdown
     */
    private function handleProviderResult(
        Order $order,
        Refund $refund,
        PaymentRefundResult $result,
        PaymentIntent $paymentIntent,
        ?User $actor,
        ?Request $request,
        ?array $breakdown = null,
    ): Refund {
        // Persist identity/status only. Mode and account are written after verification.
        $refund->forceFill([
            'provider_refund_id' => filled($result->providerRefundId)
                ? $result->providerRefundId
                : $refund->provider_refund_id,
            'provider_status' => $result->status,
            'meta' => array_merge($refund->meta ?? [], [
                'provider_result' => $this->sanitizeProviderMeta($result->raw),
            ]),
        ])->save();

        if ($result->failed()) {
            $this->markRefundFailed(
                $order,
                $refund,
                $actor,
                $result->failureMessage ?: 'Provider refund failed.',
                [
                    'provider_refund_id' => $result->providerRefundId,
                    'provider_result' => $this->sanitizeProviderMeta($result->raw),
                    'failure_code' => $result->failureCode,
                ]
            );

            throw ValidationException::withMessages([
                'payment' => $result->failureMessage ?: 'The payment provider could not process this refund.',
            ]);
        }

        if ($result->isPending() || ! $result->succeeded()) {
            $refund->forceFill([
                'status' => RefundLifecycle::STATUS_PROCESSING,
            ])->save();

            return $refund->fresh(['items', 'adjustments']);
        }

        try {
            $this->assertProviderResultMatchesRefund($refund, $result, $paymentIntent);
        } catch (ValidationException $e) {
            $this->recordProviderMismatch($order, $refund, $actor, $e->errors(), $result);

            throw $e;
        }

        $refund->forceFill([
            'mode' => $result->mode ?? $refund->mode,
            'provider_account_id' => $result->providerAccountId ?? $refund->provider_account_id,
        ])->save();

        $breakdown ??= $this->breakdownFromRefundRecord($refund);

        return $this->finalizeSucceededRefund($order, $refund, $breakdown, $actor, $request);
    }

    private function assertProviderResultMatchesRefund(
        Refund $refund,
        PaymentRefundResult $result,
        PaymentIntent $paymentIntent,
    ): void {
        $paymentIntent->loadMissing('paymentProviderAccount');
        $snapshot = is_array($refund->routing_snapshot) ? $refund->routing_snapshot : [];

        if (! filled($result->providerRefundId)) {
            throw ValidationException::withMessages([
                'payment' => 'Provider refund is missing a refund identifier.',
            ]);
        }

        if ($result->amountMinor === null || (int) $result->amountMinor !== (int) $refund->amount_minor) {
            throw ValidationException::withMessages([
                'payment' => 'Provider refund amount does not match the requested refund.',
            ]);
        }

        $expectedCurrency = strtoupper((string) ($refund->currency_code ?: $paymentIntent->currency_code ?: ''));
        $actualCurrency = strtoupper((string) ($result->currencyCode ?: ''));
        if ($expectedCurrency === '' || $actualCurrency === '' || $expectedCurrency !== $actualCurrency) {
            throw ValidationException::withMessages([
                'payment' => 'Provider refund currency does not match the order currency.',
            ]);
        }

        $expectedMode = (string) (
            $snapshot['mode']
            ?? $refund->mode
            ?? $paymentIntent->mode
            ?? $paymentIntent->paymentProviderAccount?->mode
            ?? ''
        );
        $actualMode = (string) ($result->mode ?: '');
        if ($expectedMode !== $actualMode) {
            throw ValidationException::withMessages([
                'payment' => 'Provider refund mode does not match the original payment mode.',
            ]);
        }

        $expectedAccount = (string) (
            $snapshot['provider_account_id']
            ?? $refund->provider_account_id
            ?? $paymentIntent->provider_account_id
            ?? $paymentIntent->paymentProviderAccount?->provider_account_id
            ?? ''
        );
        $actualAccount = (string) ($result->providerAccountId ?: '');
        if ($expectedAccount !== $actualAccount) {
            throw ValidationException::withMessages([
                'payment' => 'Provider refund account does not match the original payment account.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    private function recordProviderMismatch(
        Order $order,
        Refund $refund,
        ?User $actor,
        array $errors,
        PaymentRefundResult $result,
    ): void {
        $message = collect($errors)->flatten()->filter()->first() ?: 'Provider refund verification failed.';

        DB::transaction(function () use ($order, $refund, $actor, $message, $result): void {
            $refund = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($refund->status === RefundLifecycle::STATUS_SUCCEEDED) {
                return;
            }

            $refund->forceFill([
                'status' => RefundLifecycle::STATUS_FAILED,
                'failed_at' => now(),
                'meta' => array_merge($refund->meta ?? [], [
                    'provider_mismatch' => true,
                    'provider_status' => $result->status,
                    'provider_refund_id' => $result->providerRefundId,
                    'sanitized_error' => $message,
                ]),
            ])->save();

            $this->orderEventRecorder->record(
                $order,
                RefundLifecycle::EVENT_REFUND_MISMATCH,
                'Refund provider mismatch',
                'Refund '.$refund->refund_number.' could not be finalized because the provider response did not match the original payment.',
                [
                    'refund_id' => $refund->id,
                    'error' => $message,
                ],
                $actor
            );
        });
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function sanitizeProviderMeta(array $raw): array
    {
        $blocked = ['client_secret', 'secret', 'authorization', 'password', 'api_key'];
        $clean = [];
        foreach ($raw as $key => $value) {
            $lower = strtolower((string) $key);
            if (collect($blocked)->contains(fn (string $needle): bool => str_contains($lower, $needle))) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $breakdown
     */
    private function finalizeSucceededRefund(
        Order $order,
        Refund $refund,
        array $breakdown,
        ?User $actor,
        ?Request $request,
    ): Refund {
        return DB::transaction(function () use ($order, $refund, $breakdown, $actor, $request): Refund {
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->with(['items', 'store', 'customer'])
                ->firstOrFail();

            $refund = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if ($refund->status === RefundLifecycle::STATUS_SUCCEEDED) {
                return $refund->load(['items', 'adjustments']);
            }

            if ($refund->status === RefundLifecycle::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'payment' => 'This refund was cancelled and cannot be applied.',
                ]);
            }

            // Failed refunds may be retried on the same record after a successful provider recheck.
            $this->applySuccessfulRefund($order, $refund, $breakdown, $actor);

            $this->securityLogRecorder->record(
                $request,
                'refund.succeeded',
                store: $order->store,
                user: $actor,
                metadata: [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'refund_id' => $refund->id,
                    'refund_number' => $refund->refund_number,
                    'amount' => (string) $refund->amount,
                    'method' => $refund->method,
                ]
            );

            return $refund->fresh(['items', 'adjustments']);
        });
    }

    private function assertPaymentEligible(Order $order): void
    {
        $paymentStatus = (string) $order->payment_status;

        if (in_array($paymentStatus, [
            OrderLifecycle::PAYMENT_PENDING,
            OrderLifecycle::PAYMENT_FAILED,
            '',
        ], true)) {
            throw ValidationException::withMessages([
                'payment' => 'Unpaid or failed payments cannot be refunded.',
            ]);
        }

        if ($paymentStatus === OrderLifecycle::PAYMENT_AUTHORIZED) {
            // Authorized-but-not-captured is not refundable as a capture refund.
            $intent = $this->eligiblePaymentIntent($order);
            if (! $intent) {
                throw ValidationException::withMessages([
                    'payment' => 'Only captured or paid payments can be refunded.',
                ]);
            }
        }
    }

    private function eligiblePaymentIntent(Order $order): ?PaymentIntent
    {
        $order->loadMissing(['paymentIntents.captures', 'paymentIntents.paymentProviderAccount']);
        $orderCurrency = strtoupper((string) ($order->currency_code ?: $order->store?->currency ?: 'USD'));

        return $order->paymentIntents
            ->sortByDesc('id')
            ->first(function (PaymentIntent $intent) use ($orderCurrency): bool {
                if (! filled($intent->provider_intent_id)) {
                    return false;
                }

                $intentCurrency = strtoupper((string) ($intent->currency_code ?: ''));
                if ($intentCurrency !== '' && $intentCurrency !== $orderCurrency) {
                    return false;
                }

                $hasCapture = $intent->captures->contains(function ($capture) use ($orderCurrency): bool {
                    if (! in_array((string) $capture->status, ['succeeded', 'captured'], true)) {
                        return false;
                    }
                    $captureCurrency = strtoupper((string) ($capture->currency_code ?: ''));

                    return $captureCurrency === '' || $captureCurrency === $orderCurrency;
                });

                if ($hasCapture) {
                    return true;
                }

                // requires_capture alone is not refundable without a successful capture.
                return (string) $intent->status === 'succeeded';
            });
    }

    private function capturedPayableMinor(Order $order, string $currency): int
    {
        $order->loadMissing(['paymentIntents.captures']);
        $currency = strtoupper($currency);
        $routing = $this->resolveRefundRouting($order);
        $isPlatform = $routing['payment_owner'] === ChannelOwnershipService::OWNER_PLATFORM
            || $order->paymentIntents->isNotEmpty();

        $fromCaptures = 0;
        foreach ($order->paymentIntents as $intent) {
            $intentCurrency = strtoupper((string) ($intent->currency_code ?: $currency));
            if ($intentCurrency !== $currency) {
                continue;
            }

            foreach ($intent->captures as $capture) {
                if (! in_array((string) $capture->status, ['succeeded', 'captured'], true)) {
                    continue;
                }
                $captureCurrency = strtoupper((string) ($capture->currency_code ?: $intentCurrency));
                if ($captureCurrency !== $currency) {
                    continue;
                }
                $fromCaptures += (int) ($capture->amount_minor ?: CurrencyPrecision::toMinorUnits((string) $capture->amount, $currency));
            }

            if ((string) $intent->status === 'succeeded' && (int) $intent->amount_minor > 0) {
                // Prefer explicit captures; if none, count succeeded intent once.
                $intentHasCapture = $intent->captures->contains(
                    fn ($capture): bool => in_array((string) $capture->status, ['succeeded', 'captured'], true)
                );
                if (! $intentHasCapture) {
                    $fromCaptures += (int) $intent->amount_minor;
                }
            }
        }

        if ($fromCaptures > 0) {
            return $fromCaptures;
        }

        if ($isPlatform) {
            return 0;
        }

        return CurrencyPrecision::toMinorUnits((string) ($order->grand_total ?: $order->total ?: '0'), $currency);
    }

    private function allocatedRefundMinor(Order $order): int
    {
        $order->loadMissing('refunds');

        return (int) $order->refunds
            ->whereIn('status', RefundLifecycle::ALLOCATING_STATUSES)
            ->sum(fn (Refund $refund): int => (int) $refund->amount_minor);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *   amount: string,
     *   amount_minor: int,
     *   items: list<array{order_item: OrderItem, quantity: int, unit_amount: string, subtotal: string, discount_amount: string, tax_amount: string, total: string, total_minor: int}>,
     *   adjustments: list<array{type: string, label: string, amount: string, amount_minor: int}>,
     *   has_breakdown: bool
     * }
     */
    private function buildRefundBreakdown(Order $order, array $payload, string $currency): array
    {
        $scale = CurrencyPrecision::scale($currency);
        $itemsPayload = $payload['items'] ?? [];
        $lines = [];
        $itemsMinor = 0;

        $order->loadMissing(['refunds.items', 'refunds.adjustments']);

        if (is_array($itemsPayload) && $itemsPayload !== []) {
            foreach ($itemsPayload as $orderItemId => $raw) {
                $quantity = is_array($raw) ? (int) ($raw['quantity'] ?? 0) : (int) $raw;
                if ($quantity < 1) {
                    continue;
                }

                $orderItem = $order->items->firstWhere('id', (int) $orderItemId);
                if (! $orderItem) {
                    throw ValidationException::withMessages([
                        'items' => 'One of the selected items does not belong to this order.',
                    ]);
                }

                $remainingQty = max(0, (int) $orderItem->quantity - (int) $orderItem->refunded_quantity - $this->pendingRefundQuantity($order, (int) $orderItem->id));
                if ($quantity > $remainingQty) {
                    throw ValidationException::withMessages([
                        'items' => 'Cannot refund more than the remaining quantity for '.$orderItem->product_name.'.',
                    ]);
                }

                $allocation = $this->allocateItemSnapshot($order, $orderItem, $quantity, $currency);
                $lines[] = $allocation;
                $itemsMinor += $allocation['total_minor'];
            }
        }

        $adjustments = [];
        $adjustmentsMinor = 0;

        foreach ([
            'shipping_amount' => [RefundLifecycle::ADJUSTMENT_SHIPPING, 'Shipping refund', (string) ($order->shipping ?: '0')],
            'shipping_tax_amount' => [RefundLifecycle::ADJUSTMENT_SHIPPING_TAX, 'Shipping tax refund', (string) ($order->shipping_tax ?: '0')],
            'tax_amount' => [RefundLifecycle::ADJUSTMENT_TAX, 'Tax refund', (string) ($order->tax ?: '0')],
            'other_amount' => [RefundLifecycle::ADJUSTMENT_OTHER, 'Other refund', null],
        ] as $key => [$type, $label, $orderCap]) {
            if (! isset($payload[$key]) || $payload[$key] === '' || $payload[$key] === null) {
                continue;
            }

            $amount = CurrencyPrecision::roundMajor(DecimalString::normalizeNonNegative($payload[$key]), $currency);
            $amountMinor = CurrencyPrecision::toMinorUnits($amount, $currency);
            if ($amountMinor < 1) {
                continue;
            }

            if ($orderCap !== null) {
                $remainingCapMinor = CurrencyPrecision::toMinorUnits($orderCap, $currency)
                    - $this->allocatedAdjustmentMinor($order, $type);
                if ($type === RefundLifecycle::ADJUSTMENT_TAX) {
                    $remainingCapMinor -= $this->allocatedItemTaxMinor($order);
                }
                if ($amountMinor > max(0, $remainingCapMinor)) {
                    throw ValidationException::withMessages([
                        $key => 'Refund '.$label.' exceeds the remaining amount for this order.',
                    ]);
                }
            }

            $adjustments[] = [
                'type' => $type,
                'label' => $label,
                'amount' => $amount,
                'amount_minor' => $amountMinor,
            ];
            $adjustmentsMinor += $amountMinor;
        }

        $hasBreakdown = $lines !== [] || $adjustments !== [];
        $breakdownMinor = $itemsMinor + $adjustmentsMinor;

        $explicitAmount = isset($payload['amount']) && $payload['amount'] !== '' && $payload['amount'] !== null
            ? CurrencyPrecision::roundMajor(DecimalString::normalizeNonNegative($payload['amount']), $currency)
            : null;

        if ($explicitAmount !== null) {
            $amountMinor = CurrencyPrecision::toMinorUnits($explicitAmount, $currency);
            if ($hasBreakdown && $amountMinor !== $breakdownMinor) {
                throw ValidationException::withMessages([
                    'amount' => 'Refund amount must exactly equal item allocations plus adjustments.',
                ]);
            }
            $amount = $explicitAmount;
        } elseif ($hasBreakdown) {
            $amountMinor = $breakdownMinor;
            $amount = CurrencyPrecision::fromMinorUnits($amountMinor, $currency);
        } else {
            $amountMinor = $this->remainingRefundableMinor($order, $currency);
            $amount = CurrencyPrecision::fromMinorUnits($amountMinor, $currency);
        }

        return [
            'amount' => $amount,
            'amount_minor' => $amountMinor,
            'items' => $lines,
            'adjustments' => $adjustments,
            'has_breakdown' => $hasBreakdown,
        ];
    }

    /**
     * @return array{order_item: OrderItem, quantity: int, unit_amount: string, subtotal: string, discount_amount: string, tax_amount: string, total: string, total_minor: int}
     */
    private function allocateItemSnapshot(Order $order, OrderItem $orderItem, int $quantity, string $currency): array
    {
        $itemQty = max(1, (int) $orderItem->quantity);
        $origSubMinor = CurrencyPrecision::toMinorUnits((string) ($orderItem->subtotal ?: '0'), $currency);
        $origDiscMinor = CurrencyPrecision::toMinorUnits((string) ($orderItem->discount_amount ?: '0'), $currency);
        $origTaxMinor = CurrencyPrecision::toMinorUnits((string) ($orderItem->tax_amount ?: '0'), $currency);

        $already = $this->allocatedItemComponents($order, (int) $orderItem->id, $currency);
        $remainingQty = max(
            0,
            (int) $orderItem->quantity - (int) $orderItem->refunded_quantity - $this->pendingRefundQuantity($order, (int) $orderItem->id)
        );
        $alreadyQty = max(0, $itemQty - $remainingQty);

        $subMinor = $this->allocateComponentMinor($origSubMinor, $already['subtotal'], $quantity, $remainingQty, $alreadyQty, $itemQty);
        $discMinor = $this->allocateComponentMinor($origDiscMinor, $already['discount'], $quantity, $remainingQty, $alreadyQty, $itemQty);
        $taxMinor = $this->allocateComponentMinor($origTaxMinor, $already['tax'], $quantity, $remainingQty, $alreadyQty, $itemQty);
        $totalMinor = $subMinor - $discMinor + $taxMinor;

        if ($totalMinor < 0) {
            throw ValidationException::withMessages([
                'items' => 'Refund allocation for '.$orderItem->product_name.' is invalid.',
            ]);
        }

        $unit = CurrencyPrecision::roundMajor((string) ($orderItem->unit_price ?: '0'), $currency);

        return [
            'order_item' => $orderItem,
            'quantity' => $quantity,
            'unit_amount' => $unit,
            'subtotal' => CurrencyPrecision::fromMinorUnits($subMinor, $currency),
            'discount_amount' => CurrencyPrecision::fromMinorUnits($discMinor, $currency),
            'tax_amount' => CurrencyPrecision::fromMinorUnits($taxMinor, $currency),
            'total' => CurrencyPrecision::fromMinorUnits($totalMinor, $currency),
            'total_minor' => $totalMinor,
        ];
    }

    private function allocateComponentMinor(
        int $originalMinor,
        int $alreadyAllocatedMinor,
        int $quantity,
        int $remainingQty,
        int $alreadyQty,
        int $itemQty,
    ): int {
        $remaining = max(0, $originalMinor - $alreadyAllocatedMinor);
        if ($quantity < 1 || $remaining < 1) {
            return 0;
        }

        if ($quantity === $remainingQty) {
            return $remaining;
        }

        $nextQty = min($itemQty, $alreadyQty + $quantity);
        $cumulative = intdiv($originalMinor * $nextQty, $itemQty);
        $slice = $cumulative - $alreadyAllocatedMinor;

        return max(0, min($remaining, $slice));
    }

    /**
     * @return array{subtotal: int, discount: int, tax: int, total: int}
     */
    private function allocatedItemComponents(Order $order, int $orderItemId, string $currency): array
    {
        $sub = 0;
        $disc = 0;
        $tax = 0;
        $total = 0;

        foreach ($order->refunds as $refund) {
            if (! in_array($refund->status, RefundLifecycle::ALLOCATING_STATUSES, true)) {
                continue;
            }
            foreach ($refund->items as $item) {
                if ((int) $item->order_item_id !== $orderItemId) {
                    continue;
                }
                $refundCurrency = (string) ($refund->currency_code ?: $currency);
                $sub += CurrencyPrecision::toMinorUnits((string) ($item->subtotal ?: '0'), $refundCurrency);
                $disc += CurrencyPrecision::toMinorUnits((string) ($item->discount_amount ?: '0'), $refundCurrency);
                $tax += CurrencyPrecision::toMinorUnits((string) ($item->tax_amount ?: '0'), $refundCurrency);
                $total += (int) ($item->total_minor ?: CurrencyPrecision::toMinorUnits((string) $item->total, $refundCurrency));
            }
        }

        return [
            'subtotal' => $sub,
            'discount' => $disc,
            'tax' => $tax,
            'total' => $total,
        ];
    }

    private function allocatedItemTaxMinor(Order $order): int
    {
        $currency = strtoupper((string) ($order->currency_code ?: 'USD'));
        $sum = 0;
        foreach ($order->items as $orderItem) {
            $sum += $this->allocatedItemComponents($order, (int) $orderItem->id, $currency)['tax'];
        }

        return $sum;
    }

    private function allocatedItemMinor(Order $order, int $orderItemId): int
    {
        $currency = strtoupper((string) ($order->currency_code ?: 'USD'));

        return $this->allocatedItemComponents($order, $orderItemId, $currency)['total'];
    }

    private function allocatedAdjustmentMinor(Order $order, string $type): int
    {
        $sum = 0;
        foreach ($order->refunds as $refund) {
            if (! in_array($refund->status, RefundLifecycle::ALLOCATING_STATUSES, true)) {
                continue;
            }
            foreach ($refund->adjustments as $adjustment) {
                if ($adjustment->type === $type) {
                    $sum += CurrencyPrecision::toMinorUnits((string) $adjustment->amount, (string) $refund->currency_code);
                }
            }
        }

        return $sum;
    }

    private function pendingRefundQuantity(Order $order, int $orderItemId): int
    {
        $qty = 0;
        foreach ($order->refunds as $refund) {
            if (! in_array($refund->status, [RefundLifecycle::STATUS_PENDING, RefundLifecycle::STATUS_PROCESSING], true)) {
                continue;
            }
            foreach ($refund->items as $item) {
                if ((int) $item->order_item_id === $orderItemId) {
                    $qty += (int) $item->quantity;
                }
            }
        }

        return $qty;
    }

    /**
     * @return array{amount: string, items: list<array<string, mixed>>, adjustments: list<array<string, mixed>>}
     */
    private function breakdownFromRefundRecord(Refund $refund): array
    {
        $refund->loadMissing(['items.orderItem', 'adjustments']);

        $items = [];
        foreach ($refund->items as $item) {
            $items[] = [
                'order_item' => $item->orderItem,
                'quantity' => (int) $item->quantity,
                'unit_amount' => (string) $item->unit_amount,
                'subtotal' => (string) $item->subtotal,
                'discount_amount' => (string) $item->discount_amount,
                'tax_amount' => (string) $item->tax_amount,
                'total' => (string) $item->total,
                'total_minor' => (int) $item->total_minor,
            ];
        }

        $adjustments = [];
        foreach ($refund->adjustments as $adjustment) {
            $adjustments[] = [
                'type' => $adjustment->type,
                'label' => $adjustment->label,
                'amount' => (string) $adjustment->amount,
            ];
        }

        return [
            'amount' => (string) $refund->amount,
            'items' => $items,
            'adjustments' => $adjustments,
        ];
    }

    /**
     * @param  array{amount: string, items: list<array<string, mixed>>, adjustments: list<array<string, mixed>>}  $breakdown
     */
    private function applySuccessfulRefund(Order $order, Refund $refund, array $breakdown, ?User $actor): void
    {
        $currency = (string) $refund->currency_code;
        $scale = CurrencyPrecision::scale($currency);
        $amount = CurrencyPrecision::roundMajor((string) $refund->amount, $currency);

        foreach ($breakdown['items'] as $line) {
            if (! ($line['order_item'] ?? null) instanceof OrderItem) {
                continue;
            }
            $orderItem = OrderItem::query()->whereKey($line['order_item']->id)->lockForUpdate()->firstOrFail();
            $orderItem->forceFill([
                'refunded_quantity' => (int) $orderItem->refunded_quantity + (int) $line['quantity'],
            ])->save();
        }

        $previousRefunded = CurrencyPrecision::roundMajor((string) ($order->refunded_total ?: '0'), $currency);
        $newRefunded = CurrencyPrecision::roundMajor(bcadd($previousRefunded, $amount, $scale + 2), $currency);
        $grand = CurrencyPrecision::roundMajor((string) ($order->grand_total ?: $order->total ?: '0'), $currency);
        $isFull = bccomp($newRefunded, $grand, $scale) >= 0;

        $paymentStatus = $isFull
            ? OrderLifecycle::PAYMENT_REFUNDED
            : OrderLifecycle::PAYMENT_PARTIALLY_REFUNDED;

        $updates = [
            'refunded_total' => $newRefunded,
            'payment_status' => $paymentStatus,
            'updated_by' => $actor?->id,
        ];

        $previousStatus = (string) $order->status;
        if ($isFull && in_array($previousStatus, [
            OrderLifecycle::ORDER_CONFIRMED,
            OrderLifecycle::ORDER_PROCESSING,
            OrderLifecycle::ORDER_COMPLETED,
        ], true)) {
            $updates['status'] = OrderLifecycle::ORDER_REFUNDED;
            $updates['refunded_at'] = now();
        } elseif ($isFull) {
            $updates['refunded_at'] = now();
        }

        $previousPayment = (string) $order->payment_status;
        $order->forceFill($updates)->save();

        $refund->forceFill([
            'status' => RefundLifecycle::STATUS_SUCCEEDED,
            'processed_at' => now(),
        ])->save();

        if ($previousPayment !== $paymentStatus) {
            $this->orderEventRecorder->record(
                $order,
                OrderLifecycle::EVENT_PAYMENT_STATUS_CHANGED,
                'Payment status changed',
                'Payment status changed to '.OrderLifecycle::paymentStatusLabel($paymentStatus).'.',
                [
                    'previous_status' => $previousPayment,
                    'new_status' => $paymentStatus,
                    'refund_id' => $refund->id,
                ],
                $actor
            );
        }

        $this->orderEventRecorder->record(
            $order,
            RefundLifecycle::EVENT_REFUND_SUCCEEDED,
            'Refund succeeded',
            'Refund '.$refund->refund_number.' for '.$amount.' '.$currency.' succeeded.',
            [
                'refund_id' => $refund->id,
                'refund_number' => $refund->refund_number,
                'amount' => $amount,
                'method' => $refund->method,
            ],
            $actor
        );

        if (($updates['status'] ?? null) === OrderLifecycle::ORDER_REFUNDED && $previousStatus !== OrderLifecycle::ORDER_REFUNDED) {
            $this->orderEventRecorder->record(
                $order,
                OrderLifecycle::EVENT_ORDER_REFUNDED,
                'Order refunded',
                'The order was fully refunded.',
                [
                    'refund_id' => $refund->id,
                    'refunded_total' => $newRefunded,
                ],
                $actor
            );
        }

        if ($order->customer_id) {
            $order->loadMissing('customer');
            if ($order->customer) {
                $this->customerMetricsService->recalculate($order->customer);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function markRefundFailed(Order $order, Refund $refund, ?User $actor, string $message, array $meta = []): void
    {
        DB::transaction(function () use ($order, $refund, $actor, $meta): void {
            $refund = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($refund->status === RefundLifecycle::STATUS_SUCCEEDED) {
                return;
            }

            $refund->forceFill([
                'status' => RefundLifecycle::STATUS_FAILED,
                'provider_refund_id' => $meta['provider_refund_id'] ?? $refund->provider_refund_id,
                'failed_at' => now(),
                'meta' => array_merge($refund->meta ?? [], $meta),
            ])->save();

            $this->orderEventRecorder->record(
                $order,
                RefundLifecycle::EVENT_REFUND_FAILED,
                'Refund failed',
                'Refund '.$refund->refund_number.' failed before money was returned.',
                [
                    'refund_id' => $refund->id,
                    'error' => $meta['provider_error'] ?? ($meta['failure_code'] ?? null),
                ],
                $actor
            );
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requestHash(array $payload, ?string $currency = null): string
    {
        $currency = strtoupper((string) ($currency ?: 'USD'));

        $normalized = [
            'amount' => $this->normalizeMoneyForHash($payload['amount'] ?? null, $currency),
            'shipping_amount' => $this->normalizeMoneyForHash($payload['shipping_amount'] ?? null, $currency),
            'shipping_tax_amount' => $this->normalizeMoneyForHash($payload['shipping_tax_amount'] ?? null, $currency),
            'tax_amount' => $this->normalizeMoneyForHash($payload['tax_amount'] ?? null, $currency),
            'other_amount' => $this->normalizeMoneyForHash($payload['other_amount'] ?? null, $currency),
            'items' => $this->normalizeItemsForHash($payload['items'] ?? null),
            'return_id' => isset($payload['return_id']) && $payload['return_id'] !== '' ? (int) $payload['return_id'] : null,
            'reason' => $this->blankToNull($payload['reason'] ?? null),
            'processed_externally' => (bool) ($payload['processed_externally'] ?? false),
        ];

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    private function normalizeMoneyForHash(mixed $value, string $currency): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CurrencyPrecision::fromMinorUnits(
            CurrencyPrecision::toMinorUnits(
                CurrencyPrecision::roundMajor(DecimalString::normalizeNonNegative($value), $currency),
                $currency
            ),
            $currency
        );
    }

    private function normalizeItemsForHash(mixed $items): ?array
    {
        if (! is_array($items) || $items === []) {
            return null;
        }

        $normalized = [];
        foreach ($items as $orderItemId => $raw) {
            $quantity = is_array($raw) ? (int) ($raw['quantity'] ?? 0) : (int) $raw;
            if ($quantity < 1) {
                continue;
            }
            $normalized[(string) (int) $orderItemId] = $quantity;
        }
        ksort($normalized);

        return $normalized === [] ? null : $normalized;
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
