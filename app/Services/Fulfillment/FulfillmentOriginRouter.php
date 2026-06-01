<?php

namespace App\Services\Fulfillment;

use App\Data\Fulfillment\FulfillmentOriginResult;
use App\Models\Carrier;
use App\Models\CheckoutItem;
use App\Models\InventoryItem;
use App\Models\InventoryLevel;
use App\Models\InventoryReservation;
use App\Models\Location;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\Store;
use App\Services\Inventory\InventorySyncService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FulfillmentOriginRouter
{
    public function __construct(
        private readonly LocationServiceAreaMatcher $serviceAreaMatcher,
        private readonly InventorySyncService $inventorySyncService,
    ) {
    }

    /**
     * @param  iterable<int, mixed>  $items
     * @param  array<string, mixed>  $destinationAddress
     */
    public function routeForCheckout(
        Store $store,
        iterable $items,
        array $destinationAddress,
        ?ShippingMethod $shippingMethod = null,
        ?int $pickupLocationId = null,
        ?string $reservationReferenceType = null,
        int|string|null $reservationReferenceId = null,
    ): FulfillmentOriginResult {
        $aggregatedItems = $this->aggregatedInventoryItems($store, $items);
        $shippingMethod?->loadMissing('carrierAccount.carrier');

        if ($this->isPickupMethod($shippingMethod)) {
            return $this->routePickup($store, $aggregatedItems, $pickupLocationId, $reservationReferenceType, $reservationReferenceId);
        }

        return $this->routeDelivery($store, $aggregatedItems, $destinationAddress, $reservationReferenceType, $reservationReferenceId);
    }

    public function isPickupMethod(?ShippingMethod $method): bool
    {
        if (! $method) {
            return false;
        }

        $method->loadMissing('carrierAccount.carrier');
        $carrier = $method->carrierAccount?->carrier;
        $nameAndCode = strtolower(trim($method->name.' '.$method->code.' '.($carrier?->code ?? '')));

        return $carrier?->type === Carrier::TYPE_PICKUP
            || $carrier?->code === 'store-pickup'
            || str_contains($nameAndCode, 'pickup');
    }

    /**
     * @param  iterable<int, mixed>  $items
     * @return list<array<string, mixed>>
     */
    public function eligiblePickupLocations(
        Store $store,
        iterable $items,
        ?string $reservationReferenceType = null,
        int|string|null $reservationReferenceId = null,
    ): array
    {
        $aggregatedItems = $this->aggregatedInventoryItems($store, $items);

        return $this->activeLocations($store)
            ->where('pickup_enabled', true)
            ->filter(fn (Location $location): bool => $this->locationHasStock($location, $aggregatedItems, $reservationReferenceType, $reservationReferenceId))
            ->sortBy([
                ['routing_priority', 'asc'],
                ['is_default', 'desc'],
                ['id', 'asc'],
            ])
            ->map(fn (Location $location): array => $this->publicPickupLocation($location))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{item: InventoryItem, quantity: int}>  $items
     * @param  array<string, mixed>  $destinationAddress
     */
    private function routeDelivery(
        Store $store,
        array $items,
        array $destinationAddress,
        ?string $reservationReferenceType,
        int|string|null $reservationReferenceId,
    ): FulfillmentOriginResult
    {
        $candidates = [];

        foreach ($this->activeLocations($store)->where('fulfills_online_orders', true) as $location) {
            $score = $this->serviceAreaMatcher->scoreAddress($location, $destinationAddress, $store);
            if (! $score['matches']) {
                continue;
            }

            if (! $this->locationHasStock($location, $items, $reservationReferenceType, $reservationReferenceId)) {
                continue;
            }

            $candidates[] = [
                'location' => $location,
                'score' => $score,
            ];
        }

        if ($candidates === []) {
            throw ValidationException::withMessages([
                'items' => 'No fulfillment location has enough stock for this address. Adjust inventory or choose another delivery option.',
            ]);
        }

        usort($candidates, function (array $a, array $b): int {
            /** @var Location $left */
            $left = $a['location'];
            /** @var Location $right */
            $right = $b['location'];

            return [
                -1 * (int) $a['score']['score'],
                (int) $left->routing_priority,
                -1 * (int) $left->is_default,
                (int) $left->id,
            ] <=> [
                -1 * (int) $b['score']['score'],
                (int) $right->routing_priority,
                -1 * (int) $right->is_default,
                (int) $right->id,
            ];
        });

        $best = $candidates[0];
        /** @var Location $location */
        $location = $best['location'];

        return new FulfillmentOriginResult(
            mode: 'delivery',
            originLocation: $location,
            pickupLocation: null,
            matchedBy: (string) $best['score']['matched_by'],
            serviceArea: $best['score']['service_area'],
            score: (int) $best['score']['score'],
        );
    }

    /**
     * @param  array<int, array{item: InventoryItem, quantity: int}>  $items
     */
    private function routePickup(
        Store $store,
        array $items,
        ?int $pickupLocationId,
        ?string $reservationReferenceType,
        int|string|null $reservationReferenceId,
    ): FulfillmentOriginResult
    {
        if ($pickupLocationId) {
            $location = $this->activeLocations($store)
                ->where('pickup_enabled', true)
                ->firstWhere('id', $pickupLocationId);

            if (! $location) {
                throw ValidationException::withMessages([
                    'pickup_location_id' => 'Choose a valid pickup location for this store.',
                ]);
            }

            if (! $this->locationHasStock($location, $items, $reservationReferenceType, $reservationReferenceId)) {
                throw ValidationException::withMessages([
                    'pickup_location_id' => 'No pickup location has enough stock for this order.',
                ]);
            }

            return $this->pickupResult($location, 'pickup_location');
        }

        $eligible = $this->activeLocations($store)
            ->where('pickup_enabled', true)
            ->filter(fn (Location $location): bool => $this->locationHasStock($location, $items, $reservationReferenceType, $reservationReferenceId))
            ->sortBy([
                ['routing_priority', 'asc'],
                ['is_default', 'desc'],
                ['id', 'asc'],
            ])
            ->values();

        if ($eligible->isEmpty()) {
            throw ValidationException::withMessages([
                'pickup_location_id' => 'No pickup location has enough stock for this order.',
            ]);
        }

        if ($eligible->count() > 1) {
            throw ValidationException::withMessages([
                'pickup_location_id' => 'Choose a pickup location for this order.',
            ]);
        }

        return $this->pickupResult($eligible->first(), 'single_pickup_location');
    }

    private function pickupResult(Location $location, string $matchedBy): FulfillmentOriginResult
    {
        return new FulfillmentOriginResult(
            mode: 'pickup',
            originLocation: $location,
            pickupLocation: $location,
            matchedBy: $matchedBy,
            serviceArea: [
                'pickup_location_id' => $location->id,
                'pickup_enabled' => true,
            ],
            score: 0,
        );
    }

    /**
     * @param  iterable<int, mixed>  $rows
     * @return array<int, array{item: InventoryItem, quantity: int}>
     */
    private function aggregatedInventoryItems(Store $store, iterable $rows): array
    {
        $items = [];

        foreach ($rows as $row) {
            $variant = $this->variantFromRow($store, $row);
            if (! $variant) {
                continue;
            }

            $quantity = $this->quantityFromRow($row);
            $item = $this->inventorySyncService->ensureInventoryItemForVariant($variant);
            if (! $item->levels()->exists()) {
                $this->inventorySyncService->ensureDefaultLevelForVariant($variant, (int) $variant->stock);
                $item = $this->inventorySyncService->ensureInventoryItemForVariant($variant);
            }
            $item->loadMissing(['variant', 'product']);

            $items[$item->id] ??= ['item' => $item, 'quantity' => 0];
            $items[$item->id]['quantity'] += $quantity;
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Choose at least one catalog item.',
            ]);
        }

        return $items;
    }

    private function variantFromRow(Store $store, mixed $row): ?ProductVariant
    {
        $variant = null;
        $variantId = null;

        if ($row instanceof CheckoutItem) {
            $row->loadMissing('variant.product');
            $variant = $row->variant;
            $variantId = $row->product_variant_id;
        } elseif (is_array($row)) {
            $variant = ($row['variant'] ?? null) instanceof ProductVariant ? $row['variant'] : null;
            $variantId = $row['product_variant_id'] ?? $row['variant_id'] ?? null;
        } elseif (is_object($row)) {
            $variant = $row->variant ?? null;
            $variantId = $row->product_variant_id ?? $row->variant_id ?? null;
        }

        if (! $variant && $variantId) {
            $variant = ProductVariant::query()
                ->with('product')
                ->whereKey($variantId)
                ->where(function ($query) use ($store): void {
                    $query->where('store_id', $store->id)
                        ->orWhereHas('product', fn ($productQuery) => $productQuery->where('store_id', $store->id));
                })
                ->first();
        }

        if (! $variant) {
            return null;
        }

        $variant->loadMissing('product');
        $variantStoreId = $variant->store_id ?: $variant->product?->store_id;
        if ((int) $variantStoreId !== (int) $store->id) {
            return null;
        }

        return $variant;
    }

    private function quantityFromRow(mixed $row): int
    {
        if ($row instanceof CheckoutItem) {
            return max(1, (int) $row->quantity);
        }

        if (is_array($row)) {
            return max(1, (int) ($row['quantity'] ?? 1));
        }

        if (is_object($row)) {
            return max(1, (int) ($row->quantity ?? 1));
        }

        return 1;
    }

    /**
     * @param  array<int, array{item: InventoryItem, quantity: int}>  $items
     */
    private function locationHasStock(
        Location $location,
        array $items,
        ?string $reservationReferenceType = null,
        int|string|null $reservationReferenceId = null,
    ): bool
    {
        foreach ($items as $row) {
            $item = $row['item'];
            if (! $item->tracked) {
                continue;
            }

            $available = (int) InventoryLevel::query()
                ->where('store_id', $location->store_id)
                ->where('inventory_item_id', $item->id)
                ->where('location_id', $location->id)
                ->value('available');
            $available += $this->reservedForReference($item, $location, $reservationReferenceType, $reservationReferenceId);

            if ($available < (int) $row['quantity']) {
                return false;
            }
        }

        return true;
    }

    private function reservedForReference(
        InventoryItem $item,
        Location $location,
        ?string $reservationReferenceType,
        int|string|null $reservationReferenceId,
    ): int {
        if (! $reservationReferenceType || $reservationReferenceId === null || $reservationReferenceId === '') {
            return 0;
        }

        return (int) InventoryReservation::query()
            ->where('store_id', $location->store_id)
            ->where('inventory_item_id', $item->id)
            ->where('location_id', $location->id)
            ->where('reference_type', $reservationReferenceType)
            ->where('reference_id', (string) $reservationReferenceId)
            ->whereIn('status', [InventoryReservation::STATUS_ACTIVE, InventoryReservation::STATUS_COMMITTED])
            ->sum('quantity');
    }

    /**
     * @return Collection<int, Location>
     */
    private function activeLocations(Store $store): Collection
    {
        return Location::query()
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->orderBy('routing_priority')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function publicPickupLocation(Location $location): array
    {
        return [
            'id' => $location->id,
            'name' => $location->name,
            'type' => $location->type,
            'city' => $location->city,
            'state' => $location->state,
            'postal_code' => $location->postal_code,
        ];
    }
}
