<?php

namespace App\Console\Commands;

use App\Models\CarrierAccount;
use App\Models\Store;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Validation\FedExValidationLegacyBundleImporter;
use Illuminate\Console\Command;

class ImportFedExValidationBundleCommand extends Command
{
    protected $signature = 'fedex:import-validation-bundle
                            {path : Path to extracted bundle folder or ZIP file}
                            {--store= : Store ID (defaults to carrier account store)}
                            {--account= : FedEx validation carrier account ID}';

    protected $description = 'Import grandfathered US ship label evidence from a prior FedEx validation diagnostic bundle';

    public function handle(FedExConfig $config, FedExValidationLegacyBundleImporter $importer): int
    {
        abort_unless($config->validationModeEnabled(), 1, 'FedEx validation mode is disabled.');

        $accountId = (int) ($this->option('account') ?: 2);
        $account = CarrierAccount::query()->findOrFail($accountId);
        abort_unless($account->usesFedExIntegratorProvider(), 1, 'Carrier account is not a FedEx integrator validation account.');

        $storeId = (int) ($this->option('store') ?: $account->store_id);
        $store = Store::query()->findOrFail($storeId);

        $result = $importer->importUsShipEvidence(
            store: $store,
            account: $account,
            bundleRoot: (string) $this->argument('path'),
        );

        $this->info('Imported US legacy validation bundle from: '.$result['bundle_root']);
        foreach ($result['scenarios'] as $testCase => $scenario) {
            if (($scenario['imported'] ?? false) === true) {
                $this->line(sprintf(
                    '  %s: event #%d, labels=%d, scans=%d',
                    $testCase,
                    $scenario['event_id'],
                    $scenario['generated_labels'],
                    $scenario['printed_scans'],
                ));
            } else {
                $this->warn('  '.$testCase.': not imported ('.($scenario['reason'] ?? 'unknown').')');
            }
        }

        return self::SUCCESS;
    }
}
