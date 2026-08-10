<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Store;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\Auth\FedExAuthorizationClassifier;
use App\Services\Carriers\FedEx\DTO\FedExApiEventContext;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Fulfillment\FulfillmentStatusService;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Production FedEx shipment cancel/void — single attempt, idempotent locally.
 */
final class FedExShipmentCancelService
{
    public const OPERATION = 'ship_cancel';

    /** @var list<string> */
    public const NON_CANCELLABLE = [
        Shipment::STATUS_DELIVERED,
        Shipment::STATUS_RETURNED,
        Shipment::STATUS_CANCELLED,
        Shipment::STATUS_FAILED,
    ];

    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExOperationGuard $guard,
        private readonly FedExMerchantApiClient $apiClient,
        private readonly FulfillmentStatusService $fulfillmentStatus,
    ) {}

    public static function isCancellable(Shipment $shipment): bool
    {
        return ! in_array((string) $shipment->status, self::NON_CANCELLABLE, true)
            && filled($shipment->tracking_number);
    }

    /**
     * @return array{result: CarrierApiResult, merchant_message: string, shipment: Shipment, replayed: bool}
     */
    public function cancel(Store $store, CarrierAccount $preferredAccount, Shipment $shipment): array
    {
        abort_unless((int) $shipment->store_id === (int) $store->id, 404);
        abort_unless((int) $preferredAccount->store_id === (int) $store->id, 404);

        $account = $this->resolveCancelAccount($store, $shipment, $preferredAccount);
        $this->guard->assertAccountForOperation($store, $account, FedExOperationGuard::CAPABILITY_SHIP_LABELS);

        $trackingNumber = trim((string) $shipment->tracking_number);
        abort_unless($trackingNumber !== '', 422, 'This shipment has no FedEx tracking number to cancel.');

        if ($shipment->status === Shipment::STATUS_CANCELLED) {
            return [
                'result' => CarrierApiResult::success(
                    data: ['already_cancelled' => true],
                    requestSummary: [],
                    responseSummary: ['http_status' => 200],
                ),
                'merchant_message' => 'This FedEx shipment is already cancelled.',
                'shipment' => $shipment,
                'replayed' => true,
            ];
        }

        if (in_array((string) $shipment->status, self::NON_CANCELLABLE, true)) {
            throw new HttpException(
                422,
                'This shipment cannot be cancelled because it is already '.str_replace('_', ' ', (string) $shipment->status).'.',
            );
        }

        $idempotencyKey = $this->guard->idempotencyKey(
            $store,
            $account,
            self::OPERATION,
            'shipment:'.$shipment->id.':'.$trackingNumber,
        );

        $existing = IdempotencyKey::query()
            ->where('store_id', $store->id)
            ->where('key', $idempotencyKey)
            ->where('response_code', 200)
            ->first();

        if ($existing) {
            $shipment->forceFill(['status' => Shipment::STATUS_CANCELLED])->save();
            $this->recalculateFulfillmentAfterCancel($store, $shipment);

            return [
                'result' => CarrierApiResult::success(
                    data: ['replayed' => true],
                    requestSummary: ['idempotency_key' => $idempotencyKey],
                    responseSummary: ['http_status' => 200],
                ),
                'merchant_message' => 'FedEx cancellation already recorded for this shipment.',
                'shipment' => $shipment->fresh(),
                'replayed' => true,
            ];
        }

        $lock = Cache::lock('fedex:cancel:'.$store->id.':'.$shipment->id, 20);
        if (! $lock->get()) {
            abort(429, 'Another FedEx cancel request is already in progress for this shipment.');
        }

        try {
            $this->rememberProcessing($store, $idempotencyKey, $trackingNumber, $shipment);

            $endpoint = $this->config->shipCancelPath($account->environment);
            $payload = [
                'accountNumber' => [
                    'value' => (string) ($account->fedExAccountNumber() ?? ''),
                ],
                'trackingNumber' => $trackingNumber,
            ];

            $requestSummary = array_merge(
                $this->apiClient->baseRequestSummary($account, $endpoint),
                [
                    'action' => CarrierApiEvent::ACTION_FEDEX_SHIP_CANCEL,
                    'shipment_id' => $shipment->id,
                    'tracking_number_last4' => strlen($trackingNumber) >= 4 ? substr($trackingNumber, -4) : null,
                    'idempotency_key' => $idempotencyKey,
                ],
            );

            $result = FedExAuthorizationClassifier::applyBlockedClassification(
                $this->apiClient->putJson(
                    store: $store,
                    account: $account,
                    action: CarrierApiEvent::ACTION_FEDEX_SHIP_CANCEL,
                    path: $endpoint,
                    payload: $payload,
                    requestSummary: $requestSummary,
                    context: new FedExApiEventContext(scenarioKey: 'production_ship_cancel'),
                    customerTransactionId: $idempotencyKey,
                ),
                $endpoint,
            );

            $httpStatus = (int) data_get($result->responseSummary, 'http_status');

            if (! $result->success) {
                IdempotencyKey::query()->updateOrCreate(
                    ['store_id' => $store->id, 'key' => $idempotencyKey],
                    [
                        'request_method' => 'PUT',
                        'request_path' => $endpoint,
                        'request_hash' => hash('sha256', $trackingNumber),
                        'response_code' => $httpStatus > 0 ? $httpStatus : 422,
                        'response_body' => [
                            'state' => 'failed',
                            'message' => $result->errorMessage,
                        ],
                        'resource_type' => Shipment::class,
                        'resource_id' => $shipment->id,
                    ],
                );

                return [
                    'result' => $result,
                    'merchant_message' => FedExSafeExceptionMapper::merchantMessage(
                        $result->errorCode,
                        $result->errorMessage,
                        $httpStatus > 0 ? $httpStatus : null,
                    ),
                    'shipment' => $shipment,
                    'replayed' => false,
                ];
            }

            $meta = $shipment->metadata ?? [];
            $meta['fedex']['cancelled_at'] = now()->toIso8601String();
            $meta['fedex']['cancel_transaction_id'] = data_get($result->responseSummary, 'fedex_transaction_id');

            $shipment->forceFill([
                'status' => Shipment::STATUS_CANCELLED,
                'metadata' => $meta,
            ])->save();

            IdempotencyKey::query()->updateOrCreate(
                ['store_id' => $store->id, 'key' => $idempotencyKey],
                [
                    'request_method' => 'PUT',
                    'request_path' => $endpoint,
                    'request_hash' => hash('sha256', $trackingNumber),
                    'response_code' => 200,
                    'response_body' => ['state' => 'succeeded'],
                    'resource_type' => Shipment::class,
                    'resource_id' => $shipment->id,
                ],
            );

            $this->recalculateFulfillmentAfterCancel($store, $shipment);

            return [
                'result' => $result,
                'merchant_message' => 'FedEx shipment cancelled.',
                'shipment' => $shipment->fresh(),
                'replayed' => false,
            ];
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Prefer the purchasing account when still API-operable; after reconnect, allow the
     * replacement successor (credentials live on the new row, not the retired original).
     */
    private function resolveCancelAccount(
        Store $store,
        Shipment $shipment,
        CarrierAccount $preferred,
    ): CarrierAccount {
        $originalId = (int) ($shipment->carrier_account_id ?? 0);
        $original = $originalId > 0
            ? CarrierAccount::query()->where('store_id', $store->id)->whereKey($originalId)->first()
            : null;

        if ($original === null) {
            if ($this->accountIsOperableForCancelApi($preferred)) {
                return $preferred;
            }

            throw new HttpException(
                422,
                'Connect an active FedEx account before cancelling this shipment.',
            );
        }

        if ($this->accountIsOperableForCancelApi($original)) {
            if ((int) $preferred->id !== (int) $original->id
                && ! $this->isReplacementSuccessorOf($original, $preferred)
            ) {
                throw new HttpException(
                    422,
                    'Cancel this FedEx label using the same connected account that purchased it.',
                );
            }

            return $original;
        }

        // Original retired (disconnect/reconnect) — use preferred if it is the replacement lineage.
        if ($this->accountIsOperableForCancelApi($preferred) && $this->isReplacementSuccessorOf($original, $preferred)) {
            return $preferred;
        }

        $successor = $this->findOperableReplacementSuccessor($store, $original);
        if ($successor !== null) {
            return $successor;
        }

        throw new HttpException(
            422,
            'This label was purchased on a previous FedEx connection. Reconnect that FedEx account, then cancel the label.',
        );
    }

    private function accountIsOperableForCancelApi(CarrierAccount $account): bool
    {
        return $account->disconnected_at === null
            && $account->replaced_at === null
            && $account->hasLegacyFedExChildCredentials()
            && $account->isConnected()
            && $account->status === CarrierAccount::STATUS_ENABLED;
    }

    private function isReplacementSuccessorOf(CarrierAccount $original, CarrierAccount $candidate): bool
    {
        $cursor = $original;
        $guard = 0;
        while ($cursor && filled($cursor->replaced_by_carrier_account_id) && $guard++ < 10) {
            if ((int) $cursor->replaced_by_carrier_account_id === (int) $candidate->id) {
                return true;
            }

            $cursor = CarrierAccount::query()->whereKey($cursor->replaced_by_carrier_account_id)->first();
        }

        return false;
    }

    private function findOperableReplacementSuccessor(Store $store, CarrierAccount $original): ?CarrierAccount
    {
        $cursor = $original;
        $guard = 0;
        while ($cursor && filled($cursor->replaced_by_carrier_account_id) && $guard++ < 10) {
            $next = CarrierAccount::query()
                ->where('store_id', $store->id)
                ->whereKey($cursor->replaced_by_carrier_account_id)
                ->first();

            if ($next === null) {
                return null;
            }

            if ($this->accountIsOperableForCancelApi($next)) {
                return $next;
            }

            $cursor = $next;
        }

        return null;
    }

    private function recalculateFulfillmentAfterCancel(Store $store, Shipment $shipment): void
    {
        if ($shipment->isReturn() || ! filled($shipment->order_id)) {
            return;
        }

        $order = Order::query()
            ->where('store_id', $store->id)
            ->whereKey($shipment->order_id)
            ->first();

        if ($order) {
            $this->fulfillmentStatus->recalculateAndPersist($order->fresh('items'), null, 'fedex_label_cancelled');
        }
    }

    private function rememberProcessing(
        Store $store,
        string $idempotencyKey,
        string $trackingNumber,
        Shipment $shipment,
    ): void {
        IdempotencyKey::query()->updateOrCreate(
            ['store_id' => $store->id, 'key' => $idempotencyKey],
            [
                'request_method' => 'PUT',
                'request_path' => '/ship/v1/shipments/cancel',
                'request_hash' => hash('sha256', $trackingNumber),
                'response_code' => 102,
                'response_body' => [
                    'state' => 'processing',
                    'started_at' => now()->toIso8601String(),
                ],
                'resource_type' => Shipment::class,
                'resource_id' => $shipment->id,
            ],
        );
    }
}
