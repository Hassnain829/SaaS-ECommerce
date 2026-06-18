<?php

namespace App\Console\Commands;

use App\Models\CarrierAccount;
use App\Models\Store;
use App\Services\Carriers\FedEx\FedExValidationEvidenceExporter;
use Illuminate\Console\Command;

class FedExValidationExportCommand extends Command
{
    protected $signature = 'fedex:validation-export
                            {--store= : Store ID}
                            {--carrier-account= : Carrier account ID}
                            {--region=US : Validation region}
                            {--environment=sandbox : sandbox or live}';

    protected $description = 'Generate a redacted FedEx integrator validation evidence bundle';

    public function handle(FedExValidationEvidenceExporter $exporter): int
    {
        $storeId = (int) $this->option('store');
        $accountId = (int) $this->option('carrier-account');

        if ($storeId <= 0) {
            $this->error('--store=ID is required');

            return self::FAILURE;
        }

        $store = Store::query()->findOrFail($storeId);
        $account = $accountId > 0
            ? CarrierAccount::query()->where('store_id', $store->id)->whereKey($accountId)->firstOrFail()
            : null;

        $zipPath = $exporter->export(
            store: $store,
            account: $account,
            session: $account?->latestRegistrationSession,
            region: (string) $this->option('region'),
            environment: (string) $this->option('environment'),
        );

        $this->info('FedEx validation bundle created: '.$zipPath);

        return self::SUCCESS;
    }
}
