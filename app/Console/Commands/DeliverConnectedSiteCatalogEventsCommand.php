<?php

namespace App\Console\Commands;

use App\Services\ConnectedSiteCatalogEventDeliveryService;
use Illuminate\Console\Command;

class DeliverConnectedSiteCatalogEventsCommand extends Command
{
    protected $signature = 'connected-sites:deliver-catalog-events {--limit=50 : Max pending deliveries to attempt}';

    protected $description = 'Retry pending WordPress catalog-cache invalidation events';

    public function handle(ConnectedSiteCatalogEventDeliveryService $deliveryService): int
    {
        $processed = $deliveryService->retryDue(max(1, (int) $this->option('limit')));
        $this->info("Attempted {$processed} catalog event delivery(ies).");

        return self::SUCCESS;
    }
}
