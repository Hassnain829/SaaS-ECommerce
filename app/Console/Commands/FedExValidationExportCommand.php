<?php

namespace App\Console\Commands;

use App\Models\CarrierAccount;
use App\Models\Store;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceExporter;
use App\Services\Carriers\FedEx\Validation\FedExValidationPreflightService;
use Illuminate\Console\Command;

class FedExValidationExportCommand extends Command
{
    protected $signature = 'fedex:validation-export
                            {--store= : Store ID}
                            {--carrier-account= : Carrier account ID}
                            {--region=US : Validation region}
                            {--environment=sandbox : sandbox or live}
                            {--mode=diagnostic : diagnostic or final}';

    protected $description = 'Generate a redacted FedEx integrator validation evidence bundle';

    public function handle(
        FedExValidationEvidenceExporter $exporter,
        FedExValidationPreflightService $preflight,
    ): int {
        $storeId = (int) $this->option('store');
        $accountId = (int) $this->option('carrier-account');
        $mode = strtolower((string) $this->option('mode'));

        if ($storeId <= 0) {
            $this->error('--store=ID is required');

            return self::FAILURE;
        }

        if (! in_array($mode, ['diagnostic', 'final'], true)) {
            $this->error('--mode must be diagnostic or final');

            return self::FAILURE;
        }

        $store = Store::query()->findOrFail($storeId);
        $account = $accountId > 0
            ? CarrierAccount::query()->where('store_id', $store->id)->whereKey($accountId)->firstOrFail()
            : null;

        if ($account === null) {
            $this->error('--carrier-account=ID is required');

            return self::FAILURE;
        }

        if ($mode === 'final') {
            $assessment = $preflight->assess($store, $account);
            if (! ($assessment['ready'] ?? false)) {
                $this->error('Final export blocked: preflight did not pass.');
                foreach ($assessment['blockers'] ?? [] as $blocker) {
                    $this->line('- '.($blocker['label'] ?? 'blocker'));
                }

                return self::FAILURE;
            }

            $zipPath = $exporter->exportFinal(
                store: $store,
                account: $account,
                region: (string) $this->option('region'),
            );
        } else {
            $zipPath = $exporter->exportDiagnostic(
                store: $store,
                account: $account,
                region: (string) $this->option('region'),
            );
        }

        $this->info('FedEx validation bundle created ('.$mode.'): '.$zipPath);

        return self::SUCCESS;
    }
}
