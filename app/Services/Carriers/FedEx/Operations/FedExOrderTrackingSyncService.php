<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Store;
use App\Services\Fulfillment\FulfillmentStatusService;
use App\Support\OrderLifecycle;
use Illuminate\Support\Carbon;

/**
 * Applies FedEx tracking results to shipment/order without overwriting terminal states.
 */
final class FedExOrderTrackingSyncService
{
    /** @var list<string> */
    private const TERMINAL_SHIPMENT = [
        Shipment::STATUS_DELIVERED,
        Shipment::STATUS_CANCELLED,
        Shipment::STATUS_RETURNED,
    ];

    /** @var list<string> */
    private const TERMINAL_ORDER = [
        OrderLifecycle::ORDER_CANCELLED,
        OrderLifecycle::ORDER_COMPLETED,
    ];

    public function __construct(
        private readonly FedExProductionTrackingService $tracking,
        private readonly FedExOperationGuard $guard,
        private readonly FulfillmentStatusService $fulfillmentStatus,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function refreshShipment(Store $store, Shipment $shipment): array
    {
        abort_unless((int) $shipment->store_id === (int) $store->id, 404);

        $account = $this->resolveTrackingAccount($store, $shipment);
        abort_unless($account instanceof CarrierAccount, 422, 'No active FedEx account is available for tracking.');

        $outcome = $this->tracking->trackShipment($store, $account, $shipment);
        if (! $outcome['result']->success) {
            return $outcome + ['synced' => false];
        }

        $mappedStatus = $this->mapFedExStatusToShipment($outcome['status'], $outcome['delivered_at'], $outcome['exception']);
        $meta = $shipment->metadata ?? [];
        $meta['fedex']['tracking'] = [
            'status_text' => $outcome['status'],
            'estimated_delivery' => $outcome['estimated_delivery'],
            'delivered_at' => $outcome['delivered_at'],
            'exception' => $outcome['exception'],
            'timeline' => $outcome['timeline'],
            'refreshed_at' => now()->toIso8601String(),
            'tracked_with_account_id' => $account->id,
        ];

        $updates = ['metadata' => $meta];

        if ($mappedStatus !== null
            && ! in_array((string) $shipment->status, self::TERMINAL_SHIPMENT, true)
        ) {
            if ($this->canTransition((string) $shipment->status, $mappedStatus)) {
                $updates['status'] = $mappedStatus;
            }
        }

        // Only set delivered_at when FedEx reports an actual delivery timestamp — never from fuzzy text.
        if (filled($outcome['delivered_at'])
            && $shipment->delivered_at === null
            && $mappedStatus === Shipment::STATUS_DELIVERED
        ) {
            try {
                $updates['delivered_at'] = Carbon::parse($outcome['delivered_at']);
                $updates['status'] = Shipment::STATUS_DELIVERED;
            } catch (\Throwable) {
                // keep prior delivered_at
            }
        }

        if (
            in_array(($updates['status'] ?? $shipment->status), [Shipment::STATUS_SHIPPED, Shipment::STATUS_IN_TRANSIT, Shipment::STATUS_DELIVERED], true)
            && $shipment->shipped_at === null
        ) {
            $updates['shipped_at'] = $shipment->shipped_at ?? now();
        }

        $shipment->forceFill($updates)->save();
        $this->syncOrderFulfillment($shipment->fresh(['order', 'items']));

        return $outcome + ['synced' => true, 'shipment' => $shipment->fresh()];
    }

    private function resolveTrackingAccount(Store $store, Shipment $shipment): ?CarrierAccount
    {
        // Always prefer the current active Model A account after reconnect/replacement.
        $active = $this->guard->resolveActiveModelAAccount($store);
        if ($active instanceof CarrierAccount) {
            try {
                $this->guard->assertAccountForOperation($store, $active, FedExOperationGuard::CAPABILITY_TRACKING);

                return $active;
            } catch (\Throwable) {
                // fall through to shipment account if still usable
            }
        }

        $account = $shipment->carrierAccount;
        if (! $account instanceof CarrierAccount || ! $account->isFedEx()) {
            return null;
        }

        try {
            $this->guard->assertAccountForOperation($store, $account, FedExOperationGuard::CAPABILITY_TRACKING);

            return $account;
        } catch (\Throwable) {
            return null;
        }
    }

    private function syncOrderFulfillment(Shipment $shipment): void
    {
        $order = $shipment->order;
        if (! $order instanceof Order) {
            return;
        }

        if (in_array((string) $order->status, self::TERMINAL_ORDER, true)) {
            return;
        }

        // Return labels never drive outbound fulfillment completion.
        if ($shipment->isReturn()) {
            return;
        }

        $this->fulfillmentStatus->recalculateAndPersist(
            $order->fresh('items'),
            null,
            'fedex_tracking_sync',
        );
    }

    private function mapFedExStatusToShipment(?string $statusText, ?string $deliveredAt, ?string $exception): ?string
    {
        $status = strtolower(trim((string) $statusText));
        $exceptionText = strtolower(trim((string) $exception));
        $haystack = trim($status.' '.$exceptionText);

        if ($haystack === '' && ! filled($deliveredAt)) {
            return null;
        }

        // Exceptions / failures first — never treat "delivery exception" as delivered.
        if (
            str_contains($haystack, 'exception')
            || str_contains($haystack, 'delivery failed')
            || str_contains($haystack, 'failed delivery')
            || str_contains($haystack, 'undeliverable')
            || str_contains($haystack, 'return to sender')
            || preg_match('/\bfail(?:ed|ure)?\b/', $haystack) === 1
        ) {
            return Shipment::STATUS_FAILED;
        }

        // Exact delivered signals only.
        if (filled($deliveredAt)
            || preg_match('/\b(?:delivered|delivery completed|delivery confirmation)\b/', $status) === 1
        ) {
            return Shipment::STATUS_DELIVERED;
        }

        // "Out for delivery" and similar remain in transit.
        if (
            str_contains($haystack, 'out for delivery')
            || str_contains($haystack, 'on the way')
            || str_contains($haystack, 'in transit')
            || str_contains($haystack, 'departed')
            || str_contains($haystack, 'arrived')
            || str_contains($haystack, 'at local facility')
        ) {
            return Shipment::STATUS_IN_TRANSIT;
        }

        if (
            str_contains($haystack, 'picked up')
            || str_contains($haystack, 'shipment information sent')
            || str_contains($haystack, 'label created')
            || str_contains($haystack, 'we have your package')
        ) {
            return Shipment::STATUS_SHIPPED;
        }

        return null;
    }

    private function canTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return false;
        }

        $rank = [
            Shipment::STATUS_PENDING => 1,
            Shipment::STATUS_LABEL_CREATED => 2,
            Shipment::STATUS_SHIPPED => 3,
            Shipment::STATUS_IN_TRANSIT => 4,
            Shipment::STATUS_FAILED => 5,
            Shipment::STATUS_DELIVERED => 6,
            Shipment::STATUS_RETURNED => 6,
            Shipment::STATUS_CANCELLED => 6,
        ];

        return ($rank[$to] ?? 0) >= ($rank[$from] ?? 0);
    }
}
