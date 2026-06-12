<?php

namespace App\Services\Carriers\FedEx;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Store;
use App\Services\Carriers\CarrierConnectionWizardService;
use App\Services\Carriers\CarrierOriginReadinessService;
use App\Services\Carriers\CarrierProviderManager;
use App\Services\Carriers\DTO\CarrierConnectionTestResult;
use App\Services\Carriers\FedEx\DTO\FedExMerchantConnectionResult;

class FedExMerchantAccountConnectionService
{
    public function __construct(
        private readonly CarrierConnectionWizardService $wizard,
        private readonly CarrierProviderManager $providerManager,
    ) {
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function saveMerchantAccount(Store $store, array $input, Location $location, ?int $createdBy = null): CarrierAccount
    {
        abort_unless((int) $location->store_id === (int) $store->id, 404);

        $input = app(FedExRegistrationInputValidator::class)->validateOrFail($input);
        $accountNumber = (string) $input['provider_account_number'];
        $fedExCarrier = Carrier::query()->where('code', 'fedex')->where('is_active', true)->firstOrFail();

        $account = $store->carrierAccounts()->create([
            'carrier_id' => $fedExCarrier->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'display_name' => filled($input['display_name'] ?? null)
                ? (string) $input['display_name']
                : 'FedEx merchant account',
            'connection_type' => CarrierAccount::CONNECTION_API,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_FEDEX_INTEGRATOR,
            'provider_account_number' => $accountNumber,
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
            'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
            'enabled_for_checkout' => false,
            'settings' => [
                'registration' => $this->normalizedRegistrationDetails($input, $accountNumber),
                'verification_status' => 'pending',
                'verification_summary' => 'FedEx account saved. Run the connection check to verify account details.',
            ],
            'created_by' => $createdBy,
            ...CarrierAccount::ownershipAttributesForFedExMerchantOwned(),
        ]);

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
                'verified',
                'FedEx account saved and testing connection is available. Labels are not enabled in this phase.',
            );

            return FedExMerchantConnectionResult::connectedForTesting(
                $account->fresh(),
                'FedEx account saved and testing connection is available. FedEx billing stays between you and FedEx.',
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
            $testResult->detailMessage ?? $testResult->message ?? 'Complete carrier verification before using FedEx services.',
        );

        return FedExMerchantConnectionResult::failedSafe(
            $account->fresh(),
            'FedEx account saved. Complete carrier verification before using FedEx services.',
            $testResult->detailMessage ?? $testResult->message,
            $testResult->errorCode,
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizedRegistrationDetails(array $input, string $accountNumber): array
    {
        return [
            'company_name' => trim((string) ($input['company_name'] ?? '')),
            'contact_name' => trim((string) ($input['contact_name'] ?? '')),
            'address_line1' => trim((string) ($input['address_line1'] ?? '')),
            'address_line2' => filled($input['address_line2'] ?? null) ? trim((string) $input['address_line2']) : null,
            'city' => trim((string) ($input['city'] ?? '')),
            'state' => filled($input['state'] ?? null) ? trim((string) $input['state']) : null,
            'postal_code' => trim((string) ($input['postal_code'] ?? '')),
            'country_code' => (string) $input['country_code'],
            'phone' => (string) $input['phone'],
            'email' => (string) $input['email'],
            'provider_account_number' => $accountNumber,
            'residential' => (bool) ($input['residential'] ?? false),
        ];
    }

    private function normalizeAccountNumber(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
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
