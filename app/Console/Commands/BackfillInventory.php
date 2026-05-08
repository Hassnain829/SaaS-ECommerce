<?php

namespace App\Console\Commands;

use App\Services\Inventory\InventoryBackfillService;
use Illuminate\Console\Command;

class BackfillInventory extends Command
{
    protected $signature = 'inventory:backfill {--store= : Limit the backfill to one store ID}';

    protected $description = 'Create inventory items and levels from existing product variant stock.';

    public function handle(InventoryBackfillService $backfillService): int
    {
        $storeId = $this->option('store') !== null && $this->option('store') !== ''
            ? (int) $this->option('store')
            : null;

        $result = $backfillService->backfill($storeId);

        $this->info('Inventory backfill complete.');
        $this->line('Locations created: '.$result['locations_created']);
        $this->line('Inventory items created: '.$result['items_created']);
        $this->line('Inventory levels created: '.$result['levels_created']);
        $this->line('Variants synced: '.$result['variants_synced']);

        return self::SUCCESS;
    }
}
