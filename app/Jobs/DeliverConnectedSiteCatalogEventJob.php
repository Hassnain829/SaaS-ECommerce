<?php

namespace App\Jobs;

use App\Models\ConnectedSiteEventDelivery;
use App\Services\ConnectedSiteCatalogEventDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverConnectedSiteCatalogEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $deliveryId,
    ) {
        $this->afterCommit();
    }

    public function handle(ConnectedSiteCatalogEventDeliveryService $deliveryService): void
    {
        $delivery = ConnectedSiteEventDelivery::query()
            ->with(['event', 'site'])
            ->find($this->deliveryId);

        if (! $delivery) {
            return;
        }

        $deliveryService->deliver($delivery);
    }
}
