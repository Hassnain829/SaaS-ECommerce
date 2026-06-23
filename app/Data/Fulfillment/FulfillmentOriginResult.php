<?php

namespace App\Data\Fulfillment;

use App\Models\Location;

final class FulfillmentOriginResult
{
    public function __construct(
        public readonly string $mode,
        public readonly Location $originLocation,
        public readonly ?Location $pickupLocation,
        public readonly string $matchedBy,
        public readonly array $serviceArea,
        public readonly int $score,
        public readonly bool $stockChecked = true,
        public readonly string $routingStrategy = 'nearest_eligible_0a',
        public readonly string $routingBasis = 'service_area_stock_priority',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'mode' => $this->mode,
            'origin_location_id' => $this->originLocation->id,
            'origin_name' => $this->originLocation->name,
            'origin_type' => $this->originLocation->type,
            'pickup_location_id' => $this->pickupLocation?->id,
            'pickup_name' => $this->pickupLocation?->name,
            'pickup_type' => $this->pickupLocation?->type,
            'matched_by' => $this->matchedBy,
            'routing_strategy' => $this->routingStrategy,
            'routing_basis' => $this->routingBasis,
            'service_area' => $this->serviceArea,
            'score' => $this->score,
            'stock_checked' => $this->stockChecked,
            'selected_at' => now()->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function publicOrigin(): array
    {
        return [
            'location_id' => $this->originLocation->id,
            'name' => $this->originLocation->name,
            'type' => $this->originLocation->type,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function publicPickupLocation(): ?array
    {
        if (! $this->pickupLocation) {
            return null;
        }

        return [
            'id' => $this->pickupLocation->id,
            'name' => $this->pickupLocation->name,
            'type' => $this->pickupLocation->type,
            'city' => $this->pickupLocation->city,
            'state' => $this->pickupLocation->state,
            'postal_code' => $this->pickupLocation->postal_code,
        ];
    }
}
