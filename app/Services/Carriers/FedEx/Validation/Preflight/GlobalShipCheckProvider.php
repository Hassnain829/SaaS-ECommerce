<?php

namespace App\Services\Carriers\FedEx\Validation\Preflight;

use App\Models\CarrierAccount;
use App\Models\Store;
use App\Services\Carriers\FedEx\Validation\FedExGlobalRegionalPreflightService;
use App\Services\Carriers\FedEx\Validation\FedExGlobalShipCaseCatalog;

final class GlobalShipCheckProvider
{
    public function __construct(
        private readonly FedExGlobalRegionalPreflightService $regionalPreflight,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function checks(Store $store, CarrierAccount $account): array
    {
        $checks = [];

        foreach ($this->regionalPreflight->assessCanada($store, $account)['checks'] as $check) {
            $checks[] = array_merge($check, [
                'category' => 'global_territories',
                'required' => ($check['key'] ?? '') !== 'ca_regional_accounts_ready',
                'status' => $this->normalizeStatus((string) ($check['status'] ?? 'missing')),
                'explanation' => $this->explanationForCanadaCheck($check),
            ]);
        }

        foreach ([
            FedExGlobalShipCaseCatalog::REGION_LAC => 'Latin America & Caribbean (IntegratorLAC*)',
            FedExGlobalShipCaseCatalog::REGION_AMEA => 'AMEA (Package 7D)',
            FedExGlobalShipCaseCatalog::REGION_EU => 'Europe + ETD (Package 7E)',
        ] as $region => $label) {
            $checks[] = [
                'key' => 'global_region_'.strtolower($region).'_scope',
                'category' => 'global_territories',
                'label' => $label,
                'required' => false,
                'status' => 'not_applicable',
                'explanation' => 'Excluded from this submission — Americas (US + Canada) only per Integrator Validation Cover Sheet.',
                'region' => $region,
            ];
        }

        return $checks;
    }

    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            'passed' => 'passed',
            'failed' => 'failed',
            'missing' => 'incomplete',
            default => $status,
        };
    }

    /**
     * @param  array<string, mixed>  $check
     */
    private function explanationForCanadaCheck(array $check): string
    {
        if (($check['key'] ?? '') === 'ca_regional_accounts_ready') {
            return 'Informational — Canada ship evidence uses workbook account numbers; separate regional registration is optional when labels already pass.';
        }

        return match ($check['status'] ?? '') {
            'passed' => 'Canada ship evidence complete.',
            'failed' => 'Latest Canada ship run failed — rerun the case from the workspace.',
            default => 'Run the Canada case and upload printed scans where required.',
        };
    }
}
