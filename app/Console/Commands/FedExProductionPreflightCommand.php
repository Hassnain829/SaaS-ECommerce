<?php

namespace App\Console\Commands;

use App\Services\Carriers\FedEx\Support\FedExConfig;
use Illuminate\Console\Command;

class FedExProductionPreflightCommand extends Command
{
    protected $signature = 'fedex:production-preflight';

    protected $description = 'Check FedEx Model A production readiness without echoing secrets';

    public function handle(FedExConfig $config): int
    {
        $this->info('FedEx Model A production preflight');
        $this->newLine();

        $labelsEnabled = filter_var(config('carriers.fedex.ops_ship_labels_enabled', false), FILTER_VALIDATE_BOOL);
        $trackingEnabled = filter_var(config('carriers.fedex.ops_tracking_enabled', false), FILTER_VALIDATE_BOOL);
        $checkoutEnabled = filter_var(config('carriers.fedex.checkout_rates_enabled', false), FILTER_VALIDATE_BOOL);

        $checks = [
            'FedEx enabled' => $config->isEnabled(),
            'Model A enabled' => $config->modelAEnabled(),
            'FEDEX_ENVIRONMENT=live' => $config->environment() === 'live',
            'Model B developer fallback off' => ! $config->modelBDeveloperFallbackEnabled(),
            'Validation mode off' => ! $config->validationModeEnabled(),
            'Live base URL is production APIs host' => $this->liveBaseUrlLooksSafe($config),
            'Live parent client id present' => filled(config('carriers.fedex.live.client_id')),
            'Live parent client secret present' => filled(config('carriers.fedex.live.client_secret')),
            'Integrator production flag enabled' => filter_var(
                config('carriers.fedex.integrator_production_enabled', false),
                FILTER_VALIDATE_BOOL
            ),
            'Live countries configured' => $config->liveAllowedCountries() !== [],
            'productionEnabled() aggregate' => $config->productionEnabled(),
            'Official MFA pin generation path configured' => filled($config->mfaPinGenerationPath()),
            'Official MFA pin validation path configured' => filled($config->mfaPinValidationPath()),
            'Official MFA invoice validation path configured' => filled($config->mfaInvoiceValidationPath()),
        ];

        if ($labelsEnabled) {
            $checks['Ship labels enabled → create path configured'] = filled($config->shipCreatePath());
            $checks['Ship labels enabled → cancel path configured'] = filled($config->shipCancelPath());
        }

        if ($trackingEnabled) {
            $checks['Tracking enabled → BIV path configured'] = filled($config->basicIntegratedVisibilityPath());
        }

        if ($checkoutEnabled) {
            $checks['Checkout rates enabled → Model A + production aggregate'] = $config->modelAEnabled() && $config->productionEnabled();
        }

        $failed = 0;
        foreach ($checks as $label => $ok) {
            if ($ok) {
                $this->line("<fg=green>PASS</>  {$label}");
            } else {
                $this->line("<fg=red>FAIL</>  {$label}");
                $failed++;
            }
        }

        $errors = $config->productionConfigurationErrors();
        if ($errors !== []) {
            $this->newLine();
            $this->warn('productionConfigurationErrors():');
            foreach ($errors as $error) {
                $this->line('  - '.$error);
            }
        }

        $this->newLine();
        $this->comment('Secrets are never printed. Configure live keys only on a protected environment, then re-run.');
        $this->comment('Staged smoke: rates → enable labels → label/void/return → enable tracking → tracking → enable checkout → checkout.');
        $this->comment(sprintf(
            'Current capability flags: labels=%s tracking=%s checkout=%s',
            $labelsEnabled ? 'on' : 'off',
            $trackingEnabled ? 'on' : 'off',
            $checkoutEnabled ? 'on' : 'off',
        ));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function liveBaseUrlLooksSafe(FedExConfig $config): bool
    {
        $base = rtrim((string) config('carriers.fedex.live.base_url', ''), '/');

        return $base === 'https://apis.fedex.com';
    }
}
