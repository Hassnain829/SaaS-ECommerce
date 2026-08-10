<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\CarrierRateQuote;
use App\Models\IdempotencyKey;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\ShipmentPackage;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\Auth\FedExAuthorizationClassifier;
use App\Services\Carriers\FedEx\DTO\FedExApiEventContext;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Fulfillment\FulfillmentStatusService;
use App\Services\ShipmentNumberGenerator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Production FedEx Ship create with crash-safe persistent idempotency (no HTTP-layer retries).
 */
final class FedExShipmentPurchaseService
{
    public const OPERATION = 'ship_create';

    public const STATE_PROCESSING = 'processing';

    public const STATE_SUCCEEDED = 'succeeded';

    public const STATE_UNCERTAIN = 'uncertain';

    public const STATE_FAILED = 'failed';

    private const PROCESSING_STALE_SECONDS = 120;

    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExOperationGuard $guard,
        private readonly FedExMerchantApiClient $apiClient,
        private readonly FedExShipPayloadFactory $payloadFactory,
        private readonly FedExShipResponseParser $responseParser,
        private readonly FedExProductionShipRequestBuilder $requestBuilder,
        private readonly FedExCustomsValidationService $customsValidation,
        private readonly FedExLabelArtifactStore $labelStore,
        private readonly FedExShipQuoteBindingService $quoteBinding,
        private readonly FulfillmentStatusService $fulfillmentStatus,
        private readonly ShipmentNumberGenerator $shipmentNumberGenerator,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     shipment: ?Shipment,
     *     result: CarrierApiResult,
     *     merchant_message: string,
     *     state: string,
     *     labels: list<array<string, mixed>>,
     *     replayed: bool
     * }
     */
    public function purchase(
        Store $store,
        Order $order,
        CarrierAccount $account,
        Location $origin,
        array $input,
        ?User $actor = null,
    ): array {
        abort_unless((int) $order->store_id === (int) $store->id, 404);
        $this->guard->assertAccountForOperation($store, $account, FedExOperationGuard::CAPABILITY_SHIP_LABELS);
        abort_unless((int) $origin->store_id === (int) $store->id, 404);

        $order->loadMissing(['addresses', 'items']);
        $recipient = $order->addresses->firstWhere('type', 'shipping') ?? $order->addresses->first();
        abort_unless($recipient, 422, 'This order does not have a shipping address.');

        // Hydrate authoritative package/context from the bound quote before fixture build.
        $preloadedQuote = null;
        $quoteIdEarly = (int) ($input['carrier_rate_quote_id'] ?? 0);
        if ($quoteIdEarly > 0 && ! (bool) ($input['return_shipment'] ?? false)) {
            $preloadedQuote = CarrierRateQuote::query()
                ->where('store_id', $store->id)
                ->where('order_id', $order->id)
                ->whereKey($quoteIdEarly)
                ->with('package')
                ->first();

            if ($preloadedQuote?->package) {
                $pkg = $preloadedQuote->package;
                $input['packages'] = [[
                    'weight' => (float) $pkg->weight_value,
                    'weight_unit' => strtoupper((string) ($pkg->weight_unit ?: 'LB')),
                    'length' => (float) $pkg->length,
                    'width' => (float) $pkg->width,
                    'height' => (float) $pkg->height,
                    'dimension_unit' => strtoupper((string) ($pkg->dimension_unit ?: 'IN')),
                ]];
                $input['shipment_package_id'] = $pkg->id;
            } elseif (is_array(data_get($preloadedQuote?->request_summary, 'packages'))) {
                $input['packages'] = data_get($preloadedQuote->request_summary, 'packages');
            }

            if (! array_key_exists('pickup_type', $input) || ! filled($input['pickup_type'] ?? null)) {
                $input['pickup_type'] = data_get($preloadedQuote?->request_summary, 'pickup_type');
            }
            if (! array_key_exists('residential', $input) && array_key_exists('destination_residential', (array) ($preloadedQuote?->request_summary ?? []))) {
                $input['residential'] = (bool) data_get($preloadedQuote->request_summary, 'destination_residential');
            }
            if (! filled($input['ship_date'] ?? null) && filled(data_get($preloadedQuote?->request_summary, 'ship_date'))) {
                $input['ship_date'] = (string) data_get($preloadedQuote->request_summary, 'ship_date');
            }

            $corrected = data_get($preloadedQuote?->request_summary, 'destination_address');
            if (is_array($corrected) && filled($corrected['address_line1'] ?? null)) {
                $input['destination_override'] = $corrected;
            }
        }

        $originCountry = strtoupper((string) ($origin->country_code ?: 'US'));
        $destinationCountry = strtoupper((string) (
            data_get($input, 'destination_override.country_code')
            ?: ($recipient->country_code ?: 'US')
        ));
        $this->guard->assertOriginDestinationAllowed($originCountry, $destinationCountry, $account->environment);
        $shipDate = $this->guard->assertShipDate($input['ship_date'] ?? null);

        $customs = null;
        if ($originCountry !== $destinationCountry) {
            $customs = $this->customsValidation->assertValidOrNull(
                $originCountry,
                $destinationCountry,
                is_array($input['customs_clearance'] ?? null) ? $input['customs_clearance'] : [],
            );
            $input['customs_clearance'] = $customs;
        }

        $input['ship_date'] = $shipDate;
        $isReturn = (bool) ($input['return_shipment'] ?? false);

        // Resolve all authoritative inputs (quote service + ETD documentId) BEFORE building the Ship fixture.
        // Building the fixture first caused selected Trade Documents to never reach the Ship API payload.
        $boundQuote = null;
        $boundTradeDocument = null;

        if (! $isReturn) {
            $quoteId = (int) ($input['carrier_rate_quote_id'] ?? 0);
            if ($quoteId <= 0) {
                throw ValidationException::withMessages([
                    'carrier_rate_quote_id' => 'Select a FedEx rate quote before creating a label.',
                ]);
            }

            if ($preloadedQuote) {
                $input['service_type'] = (string) $preloadedQuote->service_code;
                $input['shipping_cost'] = $preloadedQuote->amount;
                $input['carrier_rate_quote_id'] = $preloadedQuote->id;
            }
        }

        if (! $isReturn && $originCountry !== $destinationCountry) {
            $tradeDocumentId = (int) ($input['fedex_trade_document_id'] ?? 0);
            if ($tradeDocumentId > 0) {
                $boundTradeDocument = $this->quoteBinding->assertValidTradeDocumentForPurchase(
                    store: $store,
                    order: $order,
                    account: $account,
                    tradeDocumentId: $tradeDocumentId,
                    originCountry: $originCountry,
                    destinationCountry: $destinationCountry,
                );
                $input['etd_document_id'] = $boundTradeDocument->fedex_document_id;
                $input['etd_document_type'] = strtoupper((string) ($boundTradeDocument->document_type ?: 'COMMERCIAL_INVOICE'));
                $input['etd_enabled'] = true;
            }
        }

        $fixture = $this->requestBuilder->buildFixture($store, $order, $origin, $recipient, $input, $account);

        if (! $isReturn) {
            $quoteId = (int) ($input['carrier_rate_quote_id'] ?? 0);
            $boundQuote = $this->quoteBinding->assertValidQuoteForPurchase(
                store: $store,
                order: $order,
                account: $account,
                origin: $origin,
                quoteId: $quoteId,
                serviceType: (string) ($fixture['service_type'] ?? $input['service_type'] ?? ''),
                packages: $fixture['packages'] ?? [],
                destinationPostal: (string) (
                    data_get($input, 'destination_override.postal_code')
                    ?: ($recipient->postal_code ?? '')
                ),
                destinationCountry: $destinationCountry,
                currency: strtoupper((string) ($order->currency_code ?: 'USD')),
                originCountry: $originCountry,
                residential: array_key_exists('residential', $input) ? (bool) $input['residential'] : null,
                shipDate: $shipDate,
                pickupType: isset($input['pickup_type']) ? (string) $input['pickup_type'] : null,
            );

            // Force service/amount from the bound quote — never trust free-typed service alone.
            $fixture['service_type'] = (string) $boundQuote->service_code;
            $input['service_type'] = (string) $boundQuote->service_code;
            $input['shipping_cost'] = $boundQuote->amount;
            $input['carrier_rate_quote_id'] = $boundQuote->id;
        }

        $shipmentItems = $this->resolveShipmentItems($order, $input, $isReturn);
        $requestHash = hash('sha256', json_encode([
            'service' => $fixture['service_type'],
            'label' => $fixture['label_format'],
            'packages' => $fixture['packages'],
            'origin' => $origin->id,
            'dest' => [
                $recipient->postal_code,
                $destinationCountry,
                (bool) ($input['residential'] ?? false),
            ],
            'return' => $isReturn,
            'order_return_id' => $isReturn ? ($input['order_return_id'] ?? null) : null,
            'customs' => $customs,
            'items' => $shipmentItems,
            'quote_id' => $boundQuote?->id,
            'trade_document_id' => $boundTradeDocument?->id,
        ]) ?: '');

        $idempotencyKey = $this->guard->idempotencyKey(
            $store,
            $account,
            self::OPERATION.($isReturn ? '_return' : ''),
            'order:'.$order->id.':'.$requestHash,
        );

        $existing = IdempotencyKey::query()
            ->where('store_id', $store->id)
            ->where('key', $idempotencyKey)
            ->first();

        if ($replay = $this->replayIfTerminal($store, $existing, $idempotencyKey)) {
            return $replay;
        }

        // Order-level lock prevents concurrent paid labels with different request hashes.
        $orderLock = Cache::lock('fedex:ship:order:'.$store->id.':'.$order->id, 45);
        if (! $orderLock->get()) {
            abort(429, 'Another FedEx label request is already in progress for this order.');
        }

        $lock = Cache::lock('fedex:ship:'.$store->id.':'.$idempotencyKey, 45);
        if (! $lock->get()) {
            optional($orderLock)->release();
            abort(429, 'Another FedEx label request is already in progress for this order.');
        }

        try {
            // Re-read under lock, then reserve quantities in DB before any FedEx call.
            $existing = IdempotencyKey::query()
                ->where('store_id', $store->id)
                ->where('key', $idempotencyKey)
                ->first();

            if ($replay = $this->replayIfTerminal($store, $existing, $idempotencyKey)) {
                return $replay;
            }

            $reservedShipment = DB::transaction(function () use (
                $store,
                $order,
                $account,
                $origin,
                $fixture,
                $input,
                $shipmentItems,
                $isReturn,
                $idempotencyKey,
                $requestHash,
                $actor,
                $boundQuote,
                $boundTradeDocument,
            ): Shipment {
                Order::query()->whereKey($order->id)->lockForUpdate()->first();
                if (! $isReturn) {
                    $this->assertRemainingQuantities($order->fresh('items'), $shipmentItems);
                }

                $shipment = Shipment::query()->create([
                    'store_id' => $store->id,
                    'order_id' => $order->id,
                    'order_return_id' => $isReturn ? ($input['order_return_id'] ?? null) : null,
                    'shipment_number' => $this->shipmentNumberGenerator->generate($store),
                    'origin_location_id' => $origin->id,
                    'carrier_account_id' => $account->id,
                    'shipping_method_id' => $input['shipping_method_id'] ?? null,
                    'status' => Shipment::STATUS_PENDING,
                    'direction' => $isReturn ? Shipment::DIRECTION_RETURN : Shipment::DIRECTION_OUTBOUND,
                    'tracking_number' => null,
                    'carrier_service' => $fixture['service_type'] ?? null,
                    'package_count' => max(1, count($fixture['packages'] ?? [])),
                    'package_weight' => array_sum(array_map(
                        static fn (array $p): float => (float) ($p['weight'] ?? 0),
                        $fixture['packages'] ?? [],
                    )),
                    'shipping_cost' => $boundQuote?->amount ?? ($input['shipping_cost'] ?? null),
                    'shipped_by' => $actor?->id,
                    'metadata' => [
                        'fedex' => [
                            'idempotency_key' => $idempotencyKey,
                            'request_hash' => $requestHash,
                            'reservation' => true,
                            'label_format' => $fixture['label_format'] ?? 'PDF',
                            'return_shipment' => $isReturn,
                            'direction' => $isReturn ? Shipment::DIRECTION_RETURN : Shipment::DIRECTION_OUTBOUND,
                            'order_return_id' => $isReturn ? ($input['order_return_id'] ?? null) : null,
                            'carrier_rate_quote_id' => $boundQuote?->id,
                            'fedex_trade_document_id' => $boundTradeDocument?->id,
                            'etd_document_id' => $input['etd_document_id'] ?? null,
                            'public_tracking_token' => bin2hex(random_bytes(16)),
                        ],
                    ],
                ]);

                foreach ($shipmentItems as $orderItemId => $quantity) {
                    ShipmentItem::query()->create([
                        'store_id' => $store->id,
                        'shipment_id' => $shipment->id,
                        'order_item_id' => $orderItemId,
                        'quantity' => $quantity,
                    ]);
                }

                $this->rememberIdempotency(
                    store: $store,
                    key: $idempotencyKey,
                    requestHash: $requestHash,
                    responseCode: 102,
                    body: [
                        'state' => self::STATE_PROCESSING,
                        'started_at' => now()->toIso8601String(),
                        'reserved_shipment_id' => $shipment->id,
                    ],
                    resourceId: (int) $shipment->id,
                );

                return $shipment->fresh(['items']);
            });

            return $this->executePurchase(
                store: $store,
                order: $order,
                account: $account,
                origin: $origin,
                fixture: $fixture,
                input: $input,
                shipmentItems: $shipmentItems,
                idempotencyKey: $idempotencyKey,
                requestHash: $requestHash,
                actor: $actor,
                boundQuote: $boundQuote,
                boundTradeDocumentId: $boundTradeDocument?->id,
                reservedShipment: $reservedShipment,
            );
        } finally {
            optional($lock)->release();
            optional($orderLock)->release();
        }
    }

    /**
     * @return array{
     *     shipment: ?Shipment,
     *     result: CarrierApiResult,
     *     merchant_message: string,
     *     state: string,
     *     labels: list<array<string, mixed>>,
     *     replayed: bool
     * }|null
     */
    private function replayIfTerminal(Store $store, ?IdempotencyKey $existing, string $idempotencyKey): ?array
    {
        if ($existing === null) {
            return null;
        }

        $state = (string) data_get($existing->response_body, 'state');

        if ((int) $existing->response_code === 200 && $state === self::STATE_SUCCEEDED && filled($existing->resource_id)) {
            $shipment = Shipment::query()
                ->where('store_id', $store->id)
                ->whereKey($existing->resource_id)
                ->first();

            if ($shipment) {
                return [
                    'shipment' => $shipment,
                    'result' => CarrierApiResult::success(
                        data: ['replayed' => true],
                        requestSummary: ['idempotency_key' => $idempotencyKey],
                        responseSummary: ['http_status' => 200],
                    ),
                    'merchant_message' => 'FedEx shipment already created for this request.',
                    'state' => self::STATE_SUCCEEDED,
                    'labels' => (array) data_get($shipment->metadata, 'fedex.labels', []),
                    'replayed' => true,
                ];
            }
        }

        if (in_array($state, [self::STATE_UNCERTAIN, self::STATE_PROCESSING], true)) {
            if ($state === self::STATE_PROCESSING
                && $existing->updated_at
                && $existing->updated_at->gt(now()->subSeconds(self::PROCESSING_STALE_SECONDS))
            ) {
                abort(429, 'A FedEx label request is already in progress for this order. Wait and refresh before retrying.');
            }

            return [
                'shipment' => filled($existing->resource_id)
                    ? Shipment::query()->where('store_id', $store->id)->whereKey($existing->resource_id)->first()
                    : null,
                'result' => CarrierApiResult::failure(
                    message: 'A previous FedEx ship request may have succeeded but was not confirmed. Do not create again until you verify tracking with FedEx.',
                    code: 'ship_uncertain',
                    requestSummary: ['idempotency_key' => $idempotencyKey],
                    responseSummary: ['http_status' => (int) ($existing->response_code ?: 0)],
                ),
                'merchant_message' => 'FedEx may already have this shipment. Confirm tracking before trying again.',
                'state' => self::STATE_UNCERTAIN,
                'labels' => [],
                'replayed' => true,
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  array<string, mixed>  $input
     * @param  array<int, int>  $shipmentItems
     * @return array{
     *     shipment: ?Shipment,
     *     result: CarrierApiResult,
     *     merchant_message: string,
     *     state: string,
     *     labels: list<array<string, mixed>>,
     *     replayed: bool
     * }
     */
    private function executePurchase(
        Store $store,
        Order $order,
        CarrierAccount $account,
        Location $origin,
        array $fixture,
        array $input,
        array $shipmentItems,
        string $idempotencyKey,
        string $requestHash,
        ?User $actor,
        ?CarrierRateQuote $boundQuote = null,
        ?int $boundTradeDocumentId = null,
        ?Shipment $reservedShipment = null,
    ): array {
        abort_unless($reservedShipment instanceof Shipment, 500, 'FedEx shipment reservation is missing.');

        $endpoint = $this->config->shipCreatePath($account->environment);
        $payload = $this->payloadFactory->buildShipmentPayload(
            $account,
            $fixture,
            $fixture['label_format'] ?? 'PDF',
            ['ship_date' => $fixture['ship_date'] ?? null],
        );

        $expectedLabelCount = max(1, count($fixture['packages'] ?? []));
        $requestSummary = array_merge(
            $this->apiClient->baseRequestSummary($account, $endpoint),
            [
                'action' => CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL,
                'order_id' => $order->id,
                'origin_location_id' => $origin->id,
                'service_type' => $fixture['service_type'] ?? null,
                'label_format' => $fixture['label_format'] ?? null,
                'package_count' => $expectedLabelCount,
                'return_shipment' => (bool) ($input['return_shipment'] ?? false),
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'reserved_shipment_id' => $reservedShipment->id,
            ],
        );

        $apiResult = FedExAuthorizationClassifier::applyBlockedClassification(
            $this->apiClient->postJson(
                store: $store,
                account: $account,
                action: CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL,
                path: $endpoint,
                payload: $payload,
                requestSummary: $requestSummary,
                context: new FedExApiEventContext(scenarioKey: 'production_ship_create'),
                customerTransactionId: $idempotencyKey,
            ),
            $endpoint,
        );

        $httpStatus = (int) data_get($apiResult->responseSummary, 'http_status');
        $uncertain = $this->isUncertainOutcome($apiResult, $httpStatus);

        if ($uncertain) {
            // Keep quantity reservation — FedEx may already have charged / created the label.
            $this->rememberIdempotency(
                store: $store,
                key: $idempotencyKey,
                requestHash: $requestHash,
                responseCode: $httpStatus > 0 ? $httpStatus : 503,
                body: [
                    'state' => self::STATE_UNCERTAIN,
                    'message' => $apiResult->errorMessage,
                    'transaction_id' => data_get($apiResult->responseSummary, 'fedex_transaction_id'),
                    'reserved_shipment_id' => $reservedShipment->id,
                ],
                resourceId: (int) $reservedShipment->id,
            );

            return [
                'shipment' => $reservedShipment->fresh(['items']),
                'result' => $apiResult,
                'merchant_message' => 'FedEx did not confirm this shipment. Do not create the label again until you verify whether a tracking number already exists.',
                'state' => self::STATE_UNCERTAIN,
                'labels' => [],
                'replayed' => false,
            ];
        }

        if (! $apiResult->success) {
            $this->releaseReservation($reservedShipment);
            $this->rememberIdempotency(
                store: $store,
                key: $idempotencyKey,
                requestHash: $requestHash,
                responseCode: $httpStatus > 0 ? $httpStatus : 422,
                body: [
                    'state' => self::STATE_FAILED,
                    'message' => $apiResult->errorMessage,
                    'code' => $apiResult->errorCode,
                    'released_shipment_id' => $reservedShipment->id,
                ],
                resourceId: (int) $reservedShipment->id,
            );

            return [
                'shipment' => null,
                'result' => $apiResult,
                'merchant_message' => FedExSafeExceptionMapper::merchantMessage(
                    $apiResult->errorCode,
                    $apiResult->errorMessage,
                    $httpStatus > 0 ? $httpStatus : null,
                ),
                'state' => self::STATE_FAILED,
                'labels' => [],
                'replayed' => false,
            ];
        }

        $parsed = $this->responseParser->parse(is_array($apiResult->data) ? $apiResult->data : null);
        $masterTracking = $parsed['master_tracking_number'] ?? null;
        if (! filled($masterTracking) && $parsed['labels'] !== []) {
            $first = reset($parsed['labels']);
            $masterTracking = is_array($first) ? ($first['tracking_number'] ?? null) : null;
        }

        $usableLabels = [];
        foreach ($parsed['labels'] as $sequence => $label) {
            if (! is_array($label)) {
                continue;
            }
            $encoded = $label['encoded_label'] ?? null;
            if (! is_string($encoded) || $encoded === '' || base64_decode($encoded, true) === false) {
                continue;
            }
            $usableLabels[(int) $sequence] = $label;
        }

        if (! filled($masterTracking) || count($usableLabels) < $expectedLabelCount) {
            // Uncertain: retain reservation so a second paid label cannot slip through.
            $this->rememberIdempotency(
                store: $store,
                key: $idempotencyKey,
                requestHash: $requestHash,
                responseCode: 502,
                body: [
                    'state' => self::STATE_UNCERTAIN,
                    'message' => ! filled($masterTracking)
                        ? 'Ship response missing tracking number'
                        : 'Ship response missing complete label images',
                    'expected_labels' => $expectedLabelCount,
                    'usable_labels' => count($usableLabels),
                    'reserved_shipment_id' => $reservedShipment->id,
                ],
                resourceId: (int) $reservedShipment->id,
            );

            return [
                'shipment' => $reservedShipment->fresh(['items']),
                'result' => CarrierApiResult::failure(
                    message: 'FedEx accepted the shipment but did not return complete label images.',
                    code: 'incomplete_labels',
                    requestSummary: $requestSummary,
                    responseSummary: $apiResult->responseSummary,
                ),
                'merchant_message' => 'FedEx did not return complete labels. Confirm in FedEx before retrying — do not purchase again.',
                'state' => self::STATE_UNCERTAIN,
                'labels' => [],
                'replayed' => false,
            ];
        }

        // Promote pending reservation → label_created + packages (items already reserved).
        $shipment = DB::transaction(function () use (
            $store,
            $order,
            $origin,
            $fixture,
            $input,
            $usableLabels,
            $masterTracking,
            $idempotencyKey,
            $apiResult,
            $parsed,
            $actor,
            $boundQuote,
            $boundTradeDocumentId,
            $reservedShipment,
        ): Shipment {
            $isReturn = (bool) ($input['return_shipment'] ?? false);
            $meta = $reservedShipment->metadata ?? [];
            $publicToken = data_get($meta, 'fedex.public_tracking_token') ?: bin2hex(random_bytes(16));
            $meta['fedex'] = array_merge((array) ($meta['fedex'] ?? []), [
                'idempotency_key' => $idempotencyKey,
                'transaction_id' => data_get($apiResult->responseSummary, 'fedex_transaction_id'),
                'customer_transaction_id' => $idempotencyKey,
                'label_format' => $fixture['label_format'] ?? 'PDF',
                'return_shipment' => $isReturn,
                'direction' => $isReturn ? Shipment::DIRECTION_RETURN : Shipment::DIRECTION_OUTBOUND,
                'carrier_rate_quote_id' => $boundQuote?->id,
                'fedex_trade_document_id' => $boundTradeDocumentId,
                'etd_document_id' => $input['etd_document_id'] ?? null,
                'public_tracking_token' => $publicToken,
                'labels_pending_storage' => true,
                'reservation' => false,
            ]);

            $reservedShipment->forceFill([
                'status' => Shipment::STATUS_LABEL_CREATED,
                'tracking_number' => $masterTracking,
                'carrier_service' => $parsed['service_type'] ?? ($fixture['service_type'] ?? $reservedShipment->carrier_service),
                'package_count' => max(1, count($fixture['packages'] ?? [])),
                'package_weight' => array_sum(array_map(
                    static fn (array $p): float => (float) ($p['weight'] ?? 0),
                    $fixture['packages'] ?? [],
                )),
                'shipping_cost' => $boundQuote?->amount ?? ($input['shipping_cost'] ?? $reservedShipment->shipping_cost),
                'shipped_by' => $actor?->id ?? $reservedShipment->shipped_by,
                'metadata' => $meta,
            ])->save();

            foreach ($usableLabels as $sequence => $label) {
                $packageIndex = max(0, ((int) $sequence) - 1);
                $existingPackageId = (int) ($input['shipment_package_id'] ?? $boundQuote?->package_id ?? 0);

                if ($existingPackageId > 0 && (int) $sequence === 1) {
                    $existing = ShipmentPackage::query()
                        ->where('store_id', $store->id)
                        ->where('order_id', $order->id)
                        ->whereKey($existingPackageId)
                        ->first();
                    if ($existing) {
                        $existing->forceFill([
                            'shipment_id' => $reservedShipment->id,
                            'origin_location_id' => $origin->id,
                            'metadata' => array_merge(is_array($existing->metadata) ? $existing->metadata : [], [
                                'fedex_tracking_number' => $label['tracking_number'] ?? null,
                                'package_sequence' => (int) $sequence,
                            ]),
                        ])->save();
                        if ($boundQuote instanceof CarrierRateQuote && ! $boundQuote->package_id) {
                            $boundQuote->forceFill(['package_id' => $existing->id])->save();
                        }
                        continue;
                    }
                }

                ShipmentPackage::query()->create([
                    'store_id' => $store->id,
                    'shipment_id' => $reservedShipment->id,
                    'order_id' => $order->id,
                    'origin_location_id' => $origin->id,
                    'name' => 'Package '.$sequence,
                    'weight_value' => data_get($fixture, 'packages.'.$packageIndex.'.weight'),
                    'weight_unit' => data_get($fixture, 'packages.'.$packageIndex.'.weight_unit', 'LB'),
                    'length' => data_get($fixture, 'packages.'.$packageIndex.'.length'),
                    'width' => data_get($fixture, 'packages.'.$packageIndex.'.width'),
                    'height' => data_get($fixture, 'packages.'.$packageIndex.'.height'),
                    'dimension_unit' => data_get($fixture, 'packages.'.$packageIndex.'.dimension_unit', 'IN'),
                    'package_type' => $fixture['packaging_type'] ?? 'YOUR_PACKAGING',
                    'metadata' => [
                        'fedex_tracking_number' => $label['tracking_number'] ?? null,
                        'package_sequence' => (int) $sequence,
                    ],
                    'created_by' => $actor?->id,
                ]);
            }

            if ($boundQuote instanceof CarrierRateQuote) {
                $boundQuote->forceFill(['shipment_id' => $reservedShipment->id])->save();
            }

            return $reservedShipment->fresh(['items']);
        });

        // File writes AFTER commit — orphan files are recoverable; never roll back postage.
        $storedLabels = [];
        foreach ($usableLabels as $sequence => $label) {
            $stored = $this->labelStore->storeLabel($store, $shipment, $label, (int) $sequence);
            if ($stored !== null) {
                $storedLabels[] = $stored;
            }
        }

        if (count($storedLabels) < $expectedLabelCount) {
            $meta = $shipment->metadata ?? [];
            $meta['fedex']['labels'] = $storedLabels;
            $meta['fedex']['labels_pending_storage'] = true;
            $meta['fedex']['label_storage_failed'] = true;
            $shipment->forceFill(['metadata' => $meta])->save();

            $this->rememberIdempotency(
                store: $store,
                key: $idempotencyKey,
                requestHash: $requestHash,
                responseCode: 502,
                body: [
                    'state' => self::STATE_UNCERTAIN,
                    'message' => 'Label files could not be stored after FedEx accepted the shipment',
                    'tracking_number_last4' => strlen((string) $masterTracking) >= 4
                        ? substr((string) $masterTracking, -4)
                        : null,
                ],
                resourceId: (int) $shipment->id,
            );

            return [
                'shipment' => $shipment->fresh(),
                'result' => CarrierApiResult::failure(
                    message: 'FedEx created the shipment but label files could not be stored locally.',
                    code: 'label_storage_failed',
                    requestSummary: $requestSummary,
                    responseSummary: $apiResult->responseSummary,
                ),
                'merchant_message' => 'FedEx created this shipment, but label files are incomplete. Do not create again — recover the label using the tracking number.',
                'state' => self::STATE_UNCERTAIN,
                'labels' => $storedLabels,
                'replayed' => false,
            ];
        }

        $meta = $shipment->metadata ?? [];
        $meta['fedex']['labels'] = $storedLabels;
        $meta['fedex']['labels_pending_storage'] = false;
        unset($meta['fedex']['label_storage_failed']);
        $shipment->forceFill([
            'label_url' => $storedLabels[0]['url'] ?? $storedLabels[0]['path'] ?? null,
            'metadata' => $meta,
        ])->save();

        $this->rememberIdempotency(
            store: $store,
            key: $idempotencyKey,
            requestHash: $requestHash,
            responseCode: 200,
            body: [
                'state' => self::STATE_SUCCEEDED,
                'tracking_number_last4' => strlen((string) $masterTracking) >= 4
                    ? substr((string) $masterTracking, -4)
                    : null,
                'label_count' => count($storedLabels),
            ],
            resourceId: (int) $shipment->id,
        );

        // Label-created reserves quantity; recalculate after any outbound label success for consistency.
        if (($shipment->direction ?? Shipment::DIRECTION_OUTBOUND) !== Shipment::DIRECTION_RETURN) {
            $this->fulfillmentStatus->recalculateAndPersist($order->fresh('items'), $actor, 'fedex_label_created');
        }

        return [
            'shipment' => $shipment->fresh(),
            'result' => $apiResult,
            'merchant_message' => 'FedEx label created successfully.',
            'state' => self::STATE_SUCCEEDED,
            'labels' => $storedLabels,
            'replayed' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, int> order_item_id => quantity
     */
    private function resolveShipmentItems(Order $order, array $input, bool $isReturn = false): array
    {
        $lines = [];

        if (array_key_exists('items', $input)) {
            $raw = $input['items'];
            if (! is_array($raw)) {
                throw ValidationException::withMessages([
                    'items' => 'Shipment items must be a list of selected products and quantities.',
                ]);
            }

            foreach ($raw as $key => $row) {
                if (! is_array($row)) {
                    continue;
                }

                // Checkbox forms omit unchecked rows; skip explicitly unselected / zero qty.
                if (array_key_exists('selected', $row) && ! filter_var($row['selected'], FILTER_VALIDATE_BOOL)) {
                    continue;
                }

                $orderItemId = $row['order_item_id'] ?? $key;
                $quantity = $row['quantity'] ?? null;
                if (! is_numeric($orderItemId) || ! is_numeric($quantity) || (int) $quantity <= 0) {
                    continue;
                }
                $lines[(int) $orderItemId] = ((int) ($lines[(int) $orderItemId] ?? 0)) + (int) $quantity;
            }
        } elseif ($isReturn) {
            throw ValidationException::withMessages([
                'items' => 'Choose at least one order item for the return label.',
            ]);
        } else {
            // Service-level callers (tests) may omit items; use ordered quantities so
            // identical retries keep the same request hash and can replay before remaining checks.
            foreach ($order->items as $item) {
                $qty = max(1, (int) $item->quantity);
                $lines[(int) $item->id] = $qty;
            }
        }

        if ($lines === []) {
            throw ValidationException::withMessages([
                'items' => $isReturn
                    ? 'Choose at least one order item for the return label.'
                    : 'Choose at least one order item to include on the FedEx shipment.',
            ]);
        }

        $orderItemIds = OrderItem::query()
            ->where('order_id', $order->id)
            ->whereIn('id', array_keys($lines))
            ->pluck('id')
            ->all();

        foreach ($lines as $orderItemId => $quantity) {
            if (! in_array($orderItemId, $orderItemIds, true)) {
                throw ValidationException::withMessages([
                    'items' => 'One of the selected items does not belong to this order.',
                ]);
            }
        }

        // Remaining-quantity enforcement happens after idempotency replay checks
        // so identical retries can return the original shipment without failing.

        return $lines;
    }

    private function releaseReservation(Shipment $shipment): void
    {
        if ($shipment->status !== Shipment::STATUS_PENDING) {
            return;
        }

        $meta = $shipment->metadata ?? [];
        $meta['fedex']['reservation_released_at'] = now()->toIso8601String();
        $meta['fedex']['reservation'] = false;

        $shipment->forceFill([
            'status' => Shipment::STATUS_FAILED,
            'metadata' => $meta,
        ])->save();
    }

    /**
     * @param  array<int, int>  $lines
     */
    private function assertRemainingQuantities(Order $order, array $lines): void
    {
        $remaining = $this->fulfillmentStatus->remainingQuantities($order->loadMissing('items'));

        foreach ($lines as $orderItemId => $quantity) {
            $available = (int) ($remaining[$orderItemId] ?? 0);
            if ($quantity > $available) {
                throw ValidationException::withMessages([
                    "items.{$orderItemId}" => 'Shipment quantity exceeds the remaining quantity for this item.',
                ]);
            }
        }
    }

    private function isUncertainOutcome(CarrierApiResult $result, int $httpStatus): bool
    {
        if ($result->success) {
            return false;
        }

        if (in_array($httpStatus, [502, 503, 504], true)) {
            return true;
        }

        $code = strtolower((string) $result->errorCode);

        return $code === 'transport_error' || $httpStatus === 0;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function rememberIdempotency(
        Store $store,
        string $key,
        string $requestHash,
        int $responseCode,
        array $body,
        ?int $resourceId,
    ): void {
        IdempotencyKey::query()->updateOrCreate(
            [
                'store_id' => $store->id,
                'key' => $key,
            ],
            [
                'request_method' => 'POST',
                'request_path' => '/ship/v1/shipments',
                'request_hash' => $requestHash,
                'response_code' => $responseCode,
                'response_body' => $body,
                'resource_type' => $resourceId ? Shipment::class : null,
                'resource_id' => $resourceId,
            ],
        );
    }
}
