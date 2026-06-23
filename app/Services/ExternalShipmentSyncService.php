<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Models\Store;
use App\Services\Fulfillment\FulfillmentStatusService;
use App\Support\OrderLifecycle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExternalShipmentSyncService
{
    private const ORDER_SOURCE = 'external_checkout';

    private const EXTERNAL_STATUS_MAP = [
        'pending' => Shipment::STATUS_PENDING,
        'open' => Shipment::STATUS_PENDING,
        'label_created' => Shipment::STATUS_LABEL_CREATED,
        'processing' => Shipment::STATUS_PENDING,
        'shipped' => Shipment::STATUS_SHIPPED,
        'in_transit' => Shipment::STATUS_IN_TRANSIT,
        'delivered' => Shipment::STATUS_DELIVERED,
        'failed' => Shipment::STATUS_FAILED,
        'returned' => Shipment::STATUS_RETURNED,
        'cancelled' => Shipment::STATUS_CANCELLED,
        'canceled' => Shipment::STATUS_CANCELLED,
    ];

    public function __construct(
        private readonly OrderEventRecorder $eventRecorder,
        private readonly ShipmentNumberGenerator $shipmentNumberGenerator,
        private readonly FulfillmentStatusService $fulfillmentStatusService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{shipment: Shipment, created: bool, order: Order}
     */
    public function sync(Store $store, array $payload): array
    {
        $order = Order::query()
            ->where('store_id', $store->id)
            ->where('order_source', self::ORDER_SOURCE)
            ->where('external_order_number', $payload['external_order_number'])
            ->first();

        if (! $order) {
            throw ValidationException::withMessages([
                'external_order_number' => 'No external order was found for this store.',
            ]);
        }

        $externalShipmentId = (string) $payload['external_shipment_id'];
        $status = $this->mapShipmentStatus((string) ($payload['status'] ?? 'pending'));

        return DB::transaction(function () use ($store, $order, $payload, $externalShipmentId, $status): array {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $existing = $this->findExternalShipment($order, $externalShipmentId);
            $created = $existing === null;

            $shipment = $existing ?? Shipment::query()->create([
                'store_id' => $store->id,
                'order_id' => $order->id,
                'shipment_number' => $this->shipmentNumberGenerator->generate($store),
                'status' => $status,
                'metadata' => [
                    'source' => 'external',
                    'external_shipment_id' => $externalShipmentId,
                ],
            ]);

            $metadata = array_merge($shipment->metadata ?? [], [
                'source' => 'external',
                'external_shipment_id' => $externalShipmentId,
                'carrier_name' => $payload['carrier_name'] ?? null,
                'synced_at' => now()->toISOString(),
            ]);

            $shipment->forceFill([
                'status' => $status,
                'tracking_number' => $this->blankToNull($payload['tracking_number'] ?? $shipment->tracking_number),
                'tracking_url' => $this->blankToNull($payload['tracking_url'] ?? $shipment->tracking_url),
                'carrier_service' => $this->blankToNull($payload['carrier_name'] ?? $shipment->carrier_service),
                'shipped_at' => $this->parseTimestamp($payload['shipped_at'] ?? null) ?? $shipment->shipped_at,
                'delivered_at' => $this->parseTimestamp($payload['delivered_at'] ?? null) ?? $shipment->delivered_at,
                'metadata' => $metadata,
            ])->save();

            if ($created) {
                $this->syncShipmentItems($shipment, $order, $payload['items'] ?? []);
            } else {
                $this->syncShipmentItems($shipment, $order, $payload['items'] ?? [], replace: true);
            }

            $this->appendExternalShipmentMeta($order, $shipment, $payload);
            $this->fulfillmentStatusService->recalculateAndPersist($order, null, 'external_shipment_sync');

            $this->eventRecorder->record(
                $order,
                OrderLifecycle::EVENT_EXTERNAL_SHIPMENT_UPDATED,
                'External shipment updated',
                'Shipment '.$externalShipmentId.' was synced from an external storefront.',
                [
                    'external_shipment_id' => $externalShipmentId,
                    'shipment_id' => $shipment->id,
                    'shipment_number' => $shipment->shipment_number,
                    'status' => $shipment->status,
                    'tracking_number' => $shipment->tracking_number,
                ],
            );

            return [
                'shipment' => $shipment->load('items.orderItem'),
                'created' => $created,
                'order' => $order->fresh(['items', 'shipments.items']),
            ];
        });
    }

    private function findExternalShipment(Order $order, string $externalShipmentId): ?Shipment
    {
        return $order->shipments()
            ->where('metadata->external_shipment_id', $externalShipmentId)
            ->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncShipmentItems(Shipment $shipment, Order $order, array $items, bool $replace = false): void
    {
        if ($replace) {
            $shipment->items()->delete();
        }

        if ($items === []) {
            if ($shipment->items()->exists()) {
                return;
            }

            foreach ($order->items as $orderItem) {
                $shipment->items()->create([
                    'store_id' => $order->store_id,
                    'order_item_id' => $orderItem->id,
                    'quantity' => (int) $orderItem->quantity,
                ]);
            }

            return;
        }

        $orderItems = $order->items()->get();
        foreach ($items as $index => $row) {
            $sku = strtolower(trim((string) ($row['sku'] ?? '')));
            $quantity = max(1, (int) ($row['quantity'] ?? 1));
            $orderItem = $orderItems->first(function (OrderItem $item) use ($sku): bool {
                return $sku !== '' && strtolower((string) $item->sku_snapshot) === $sku;
            });

            if (! $orderItem) {
                throw ValidationException::withMessages([
                    'items.'.$index.'.sku' => 'No order line matches this SKU for the external order.',
                ]);
            }

            $shipment->items()->create([
                'store_id' => $order->store_id,
                'order_item_id' => $orderItem->id,
                'quantity' => min($quantity, (int) $orderItem->quantity),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function appendExternalShipmentMeta(Order $order, Shipment $shipment, array $payload): void
    {
        $meta = $order->meta ?? [];
        $entries = collect($meta['external_shipments'] ?? [])
            ->filter(fn ($entry): bool => is_array($entry))
            ->reject(fn (array $entry): bool => ($entry['external_shipment_id'] ?? null) === ($payload['external_shipment_id'] ?? null))
            ->values()
            ->all();

        $entries[] = [
            'external_shipment_id' => $payload['external_shipment_id'],
            'shipment_id' => $shipment->id,
            'shipment_number' => $shipment->shipment_number,
            'status' => $shipment->status,
            'carrier_name' => $payload['carrier_name'] ?? null,
            'tracking_number' => $shipment->tracking_number,
            'tracking_url' => $shipment->tracking_url,
            'shipped_at' => $shipment->shipped_at?->toISOString(),
            'delivered_at' => $shipment->delivered_at?->toISOString(),
            'synced_at' => now()->toISOString(),
        ];

        $meta['external_shipments'] = $entries;
        $order->forceFill(['meta' => $meta])->save();
    }

    private function mapShipmentStatus(string $raw): string
    {
        $normalized = strtolower(trim($raw));
        $mapped = self::EXTERNAL_STATUS_MAP[$normalized] ?? null;

        if ($mapped === null) {
            throw ValidationException::withMessages([
                'status' => 'Use a supported external shipment status such as pending, shipped, in_transit, or delivered.',
            ]);
        }

        return $mapped;
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        return Carbon::parse($value);
    }

    private function blankToNull(mixed $value): ?string
    {
        $string = trim((string) ($value ?? ''));

        return $string === '' ? null : $string;
    }
}
