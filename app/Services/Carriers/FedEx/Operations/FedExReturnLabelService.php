<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;

/**
 * Creates a FedEx return label via the production ship purchase path.
 */
final class FedExReturnLabelService
{
    public function __construct(
        private readonly FedExShipmentPurchaseService $purchaseService,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function createReturnLabel(
        Store $store,
        Order $order,
        CarrierAccount $account,
        Location $origin,
        array $input,
        ?User $actor = null,
    ): array {
        $input['return_shipment'] = true;

        return $this->purchaseService->purchase(
            store: $store,
            order: $order,
            account: $account,
            origin: $origin,
            input: $input,
            actor: $actor,
        );
    }
}
