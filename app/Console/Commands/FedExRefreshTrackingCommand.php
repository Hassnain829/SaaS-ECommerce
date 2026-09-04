<?php

namespace App\Console\Commands;

use App\Jobs\Carriers\FedEx\RefreshFedExShipmentTrackingJob;
use App\Models\CarrierAccount;
use App\Models\Shipment;
use Illuminate\Console\Command;

class FedExRefreshTrackingCommand extends Command
{
    protected $signature = 'fedex:refresh-tracking {--limit=100 : Max shipments to queue}';

    protected $description = 'Queue FedEx tracking refresh for non-terminal labeled shipments';

    public function handle(): int
    {
        if (! filter_var(config('carriers.fedex.ops_tracking_enabled', false), FILTER_VALIDATE_BOOL)) {
            $this->warn('FedEx tracking ops are disabled (FEDEX_OPS_TRACKING_ENABLED=false).');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));

        $query = Shipment::query()
            ->whereNotNull('tracking_number')
            ->whereNotIn('status', [
                Shipment::STATUS_DELIVERED,
                Shipment::STATUS_CANCELLED,
                Shipment::STATUS_RETURNED,
            ])
            ->whereHas('carrierAccount', function ($q): void {
                $q->where('provider', CarrierAccount::PROVIDER_FEDEX);
            })
            ->orderBy('id')
            ->limit($limit);

        $count = 0;
        foreach ($query->cursor() as $shipment) {
            RefreshFedExShipmentTrackingJob::dispatch((int) $shipment->id);
            $count++;
        }

        $this->info("Queued {$count} FedEx tracking refresh job(s).");

        return self::SUCCESS;
    }
}
