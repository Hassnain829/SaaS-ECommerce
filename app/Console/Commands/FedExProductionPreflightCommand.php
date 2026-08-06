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

        $checks = [
            'FedEx enabled' => $config->isEnabled(),
            'Model A enabled' => $config->modelAEnabled(),
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
        ];

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
        $this->comment('Keep checkout rates, labels, and tracking disabled until Phase 6C-5B–5E.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function liveBaseUrlLooksSafe(FedExConfig $config): bool
    {
        $base = rtrim((string) config('carriers.fedex.live.base_url', ''), '/');

        return $base === 'https://apis.fedex.com';
    }
}
