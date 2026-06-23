<?php

namespace App\Services\Carriers\FedEx\Connection;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Store;
use App\Services\Carriers\Core\CarrierConnectionWizardService;
use App\Services\Carriers\Core\CarrierOriginReadinessService;
use App\Services\Carriers\Core\CarrierProviderManager;
use App\Services\Carriers\Core\DTO\CarrierConnectionTestResult;
use App\Services\Carriers\FedEx\DTO\FedExMerchantConnectionResult;

class FedExMerchantAccountConnectionService
{
    public function __construct(
        private readonly CarrierConnectionWizardService $wizard,
        private readonly CarrierProviderManager $providerManager,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function saveMerchantAccount(Store $store, array $input, Location $location, ?int $createdBy = null): CarrierAccount
    {
        abort_unless((int) $location->store_id === (int) $store->id, 404);

        $input = app(FedExMerchantCredentialsInputValidator::class)->validateOrFail($input);
        $accountNumber = (string) $input['provider_account_number'];
        $environment = (string) $input['environment'];
        $fedExCarrier = Carrier::query()->where('code', 'fedex')->where('is_active', true)->firstOrFail();

        $account = $store->carrierAccounts()->create([
            'carrier_id' => $fedExCarrier->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => $environment,
            'display_name' => (string) $input['display_name'],
            'connection_type' => CarrierAccount::CONNECTION_API,
            'provider_account_number' => $accountNumber,
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
            'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
            'enabled_for_checkout' => false,
            'settings' => [
                'verification_status' => 'pending',
                'verification_summary' => 'FedEx credentials saved. Run the connection check to verify your API key and secret.',
                'connection_mode' => 'merchant_credentials',
            ],
            'created_by' => $createdBy,
            ...CarrierAccount::ownershipAttributesForFedExMerchantCredentials(),
        ]);

        $account->setCredentials([
            'client_id' => (string) $input['fedex_client_id'],
            'client_secret' => (string) $input['fedex_client_secret'],
        ]);
        $account->save();

        $this->wizard->applyOriginSelection($account, $location, CarrierOriginReadinessService::CARRIER_GENERIC);

        return $account->fresh(['defaultOriginLocation', 'carrier']);
    }

    public function runVerification(CarrierAccount $account): FedExMerchantConnectionResult
    {
        abort_unless($account->isFedEx() && $account->isMerchantOwned(), 404);

        $testResult = $this->providerManager
            ->provider(CarrierAccount::PROVIDER_FEDEX)
            ->testConnection($account->load('store'));

        $account->refresh();

        return $this->mapTestResult($account, $testResult);
    }

    private function mapTestResult(CarrierAccount $account, CarrierConnectionTestResult $testResult): FedExMerchantConnectionResult
    {
        if ($testResult->success) {
            $this->syncVerificationSummary(
                $account,
                'connected_for_testing',
                'FedEx merchant credentials verified. Connected using merchant credentials. Labels are not enabled in this phase.',
            );

            return FedExMerchantConnectionResult::connectedForTesting(
                $account->fresh(),
                'FedEx merchant credentials verified. FedEx billing stays between you and FedEx.',
            );
        }

        if ($testResult->connectionStatus === CarrierAccount::CONNECTION_BLOCKED_BY_FEDEX) {
            $this->syncVerificationSummary(
                $account,
                'carrier_support_required',
                'FedEx setup is blocked by carrier account validation. Contact FedEx support.',
            );

            return FedExMerchantConnectionResult::carrierSupportRequired(
                $account->fresh(),
                'FedEx account setup is saved, but FedEx validation is blocked by carrier account verification. Contact FedEx support or try again later.',
                $testResult->detailMessage ?? $testResult->message,
                $testResult->errorCode,
            );
        }

        $this->syncVerificationSummary(
            $account,
            'setup_required',
            $testResult->detailMessage ?? $testResult->message ?? 'FedEx credentials could not be verified.',
        );

        return FedExMerchantConnectionResult::failedSafe(
            $account->fresh(),
            'FedEx credentials saved. Check the API key, secret key, environment, and account number, then run the connection check again.',
            $testResult->detailMessage ?? $testResult->message,
            $testResult->errorCode,
        );
    }

    private function syncVerificationSummary(CarrierAccount $account, string $status, string $summary): void
    {
        $settings = is_array($account->settings) ? $account->settings : [];
        $settings['verification_status'] = $status;
        $settings['verification_summary'] = $summary;
        $settings['last_tested_at'] = now()->toIso8601String();

        $account->forceFill(['settings' => $settings])->save();
    }
}
