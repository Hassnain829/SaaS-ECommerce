<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierAccount;
use App\Models\FedExValidationRegionalAccount;
use App\Models\Store;
use Illuminate\Support\Str;

class FedExValidationRegionalAccountService
{
    /**
     * @return list<FedExValidationRegionalAccount>
     */
    public function ensureBaselineAccounts(Store $store, CarrierAccount $rootAccount): array
    {
        $accounts = [];

        foreach ($this->baselineAccountsForRegion(FedExGlobalShipCaseCatalog::REGION_CA) as $definition) {
            $accounts[] = $this->ensureAccount($store, $rootAccount, $definition);
        }

        return $accounts;
    }

    /**
     * @return list<FedExValidationRegionalAccount>
     */
    public function accountsForRegion(Store $store, CarrierAccount $rootAccount, string $region): array
    {
        return FedExValidationRegionalAccount::query()
            ->where('store_id', $store->id)
            ->where('root_carrier_account_id', $rootAccount->id)
            ->where('region', strtoupper($region))
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function primaryAccountForRegion(
        Store $store,
        CarrierAccount $rootAccount,
        string $region,
    ): ?FedExValidationRegionalAccount {
        $this->ensureBaselineAccounts($store, $rootAccount);

        return FedExValidationRegionalAccount::query()
            ->where('store_id', $store->id)
            ->where('root_carrier_account_id', $rootAccount->id)
            ->where('region', strtoupper($region))
            ->where('metadata_json->primary', true)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function workspaceSummary(Store $store, CarrierAccount $rootAccount, string $region): array
    {
        $accounts = $this->accountsForRegion($store, $rootAccount, $region);
        if ($accounts === []) {
            $accounts = $this->ensureBaselineAccounts($store, $rootAccount);
        }

        $ready = collect($accounts)->filter(fn (FedExValidationRegionalAccount $account): bool => $account->isReadyForShip())->count();

        return [
            'region' => strtoupper($region),
            'total_accounts' => count($accounts),
            'ready_accounts' => $ready,
            'accounts_ready' => count($accounts) > 0 && $ready === count($accounts),
            'accounts' => collect($accounts)->map(fn (FedExValidationRegionalAccount $account): array => [
                'id' => $account->id,
                'masked_account' => $account->maskedAccountNumber(),
                'status' => $account->status,
                'country_code' => $account->country_code,
                'primary' => (bool) data_get($account->metadata_json, 'primary', false),
                'label' => (string) data_get($account->metadata_json, 'label', 'Regional validation account'),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function ensureAccount(Store $store, CarrierAccount $rootAccount, array $definition): FedExValidationRegionalAccount
    {
        $accountNumber = (string) ($definition['account_number'] ?? '');
        $hash = hash('sha256', $accountNumber);

        $existing = FedExValidationRegionalAccount::query()
            ->where('store_id', $store->id)
            ->where('root_carrier_account_id', $rootAccount->id)
            ->where('environment', $rootAccount->environment)
            ->where('account_number_hash', $hash)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return FedExValidationRegionalAccount::query()->create([
            'store_id' => $store->id,
            'root_carrier_account_id' => $rootAccount->id,
            'environment' => $rootAccount->environment,
            'region' => strtoupper((string) ($definition['region'] ?? '')),
            'country_code' => strtoupper((string) ($definition['country_code'] ?? '')),
            'account_number_encrypted' => $accountNumber,
            'account_number_hash' => $hash,
            'account_last4' => substr($accountNumber, -4),
            'status' => FedExValidationRegionalAccount::STATUS_REGISTRATION_REQUIRED,
            'credential_source' => 'baseline_workbook',
            'baseline_version' => FedExCanadaShipTestCaseFixtureService::FIXTURE_VERSION,
            'metadata_json' => [
                'primary' => (bool) ($definition['primary'] ?? false),
                'label' => (string) ($definition['label'] ?? 'Canada validation account'),
                'address_line1' => (string) ($definition['address_line1'] ?? ''),
                'city' => (string) ($definition['city'] ?? ''),
                'state' => (string) ($definition['state'] ?? ''),
                'postal_code' => (string) ($definition['postal_code'] ?? ''),
            ],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function baselineAccountsForRegion(string $region): array
    {
        return match (strtoupper($region)) {
            FedExGlobalShipCaseCatalog::REGION_CA => [
                [
                    'region' => FedExGlobalShipCaseCatalog::REGION_CA,
                    'country_code' => 'CA',
                    'account_number' => FedExCanadaShipTestCaseFixtureService::CA_TEST_ACCOUNT,
                    'primary' => true,
                    'label' => 'Canada primary test account',
                    'address_line1' => '5985 EXPLORER DR',
                    'city' => 'Mississauga',
                    'state' => 'ON',
                    'postal_code' => 'L4W5K6',
                ],
                [
                    'region' => FedExGlobalShipCaseCatalog::REGION_CA,
                    'country_code' => 'CA',
                    'account_number' => FedExCanadaShipTestCaseFixtureService::CA_THIRD_PARTY_ACCOUNT,
                    'primary' => false,
                    'label' => 'Canada third-party payment account',
                    'address_line1' => '5985 EXPLORER DR',
                    'city' => 'Mississauga',
                    'state' => 'ON',
                    'postal_code' => 'L4W5K6',
                ],
            ],
            default => [],
        };
    }
}
