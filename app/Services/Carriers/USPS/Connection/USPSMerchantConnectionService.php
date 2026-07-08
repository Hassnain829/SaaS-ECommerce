<?php

namespace App\Services\Carriers\USPS\Connection;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\Core\CarrierOriginReadinessService;
use App\Services\Carriers\USPS\Auth\USPSMerchantOAuthService;
use App\Services\Carriers\USPS\Support\USPSConfig;
use App\Services\Carriers\USPS\Support\USPSMerchantConnectionContext;
use App\Services\Carriers\USPS\Support\USPSMerchantWizard;
use Illuminate\Support\Facades\DB;

class USPSMerchantConnectionService
{
    public function __construct(
        private readonly USPSConfig $config,
        private readonly CarrierOriginReadinessService $originReadiness,
        private readonly USPSMerchantWizard $wizard,
        private readonly USPSMerchantOAuthService $oauthService,
    ) {}

    public function merchantConnectionAvailable(): bool
    {
        return $this->config->isEnabled() && $this->config->merchantConnectionEnabled();
    }

    public function findMerchantAccount(Store $store): ?CarrierAccount
    {
        return $store->carrierAccounts()
            ->where('provider', CarrierAccount::PROVIDER_USPS)
            ->where('connection_mode', CarrierAccount::CONNECTION_MODE_USPS_MERCHANT_LABEL_PROVIDER)
            ->where('usps_authorization_status', '!=', CarrierAccount::USPS_AUTH_DISABLED)
            ->orderByDesc('updated_at')
            ->first();
    }

    public function findActiveMerchantAccount(Store $store): ?CarrierAccount
    {
        return $this->findMerchantAccount($store);
    }

    public function startOrResume(Store $store, User $user, Location $originLocation): CarrierAccount
    {
        abort_unless((int) $originLocation->store_id === (int) $store->id, 404);

        return DB::transaction(function () use ($store, $user, $originLocation): CarrierAccount {
            Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();

            $existing = CarrierAccount::query()
                ->where('store_id', $store->id)
                ->where('provider', CarrierAccount::PROVIDER_USPS)
                ->where('connection_mode', CarrierAccount::CONNECTION_MODE_USPS_MERCHANT_LABEL_PROVIDER)
                ->where('usps_authorization_status', '!=', CarrierAccount::USPS_AUTH_DISABLED)
                ->orderByDesc('updated_at')
                ->first();

            if ($existing !== null) {
                $this->applyOrigin($existing, $originLocation);

                return $existing->fresh(['defaultOriginLocation', 'carrier']);
            }

            $uspsCarrier = Carrier::query()
                ->where('code', 'usps')
                ->where('is_active', true)
                ->firstOrFail();

            $account = CarrierAccount::query()->create(array_merge([
                'store_id' => $store->id,
                'carrier_id' => $uspsCarrier->id,
                'provider' => CarrierAccount::PROVIDER_USPS,
                'environment' => CarrierAccount::ENVIRONMENT_TESTING,
                'display_name' => $store->name.' USPS account',
                'connection_type' => CarrierAccount::CONNECTION_API,
                'connection_mode' => CarrierAccount::CONNECTION_MODE_USPS_MERCHANT_LABEL_PROVIDER,
                'billing_owner' => CarrierAccount::BILLING_OWNER_MERCHANT,
                'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
                'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
                'usps_authorization_status' => CarrierAccount::USPS_AUTH_SETUP_REQUIRED,
                'usps_enrollment_status' => CarrierAccount::USPS_ENROLLMENT_NOT_STARTED,
                'usps_active_store_key' => $store->id,
                'supported_countries' => ['US'],
                'enabled_for_checkout' => false,
                'created_by' => $user->id,
                'connection_context_json' => [
                    USPSMerchantConnectionContext::CONTEXT_KEY => [
                        'started_at' => now()->toIso8601String(),
                        'completed_wizard_steps' => [],
                    ],
                ],
            ], CarrierAccount::ownershipAttributesForUspsMerchantLabelProvider()));

            $this->applyOrigin($account, $originLocation);
            $this->markSetupRequired($account);

            return $account->fresh(['defaultOriginLocation', 'carrier']);
        });
    }

    public function applyOrigin(CarrierAccount $account, Location $location): void
    {
        abort_unless($account->isUspsMerchantLabelProvider(), 404);
        abort_unless((int) $location->store_id === (int) $account->store_id, 404);

        $readiness = $this->originReadiness->assessForFulfillmentOrigin(
            $location,
            CarrierOriginReadinessService::CARRIER_USPS,
        );

        $account->assignDefaultOriginLocation($location->id);
        $account->syncOriginValidation(
            $readiness->ready ? CarrierAccount::ORIGIN_VALIDATION_READY : CarrierAccount::ORIGIN_VALIDATION_NEEDS_ATTENTION,
            $readiness->merchantMessage,
        );

        USPSMerchantConnectionContext::for($account)->markWizardStepComplete(USPSMerchantWizard::STEP_ORIGIN);
    }

    /**
     * @param  array{merchant_crid: string, merchant_mid: string, merchant_epa: string, merchant_manifest_mid?: string|null}  $identifiers
     */
    public function saveMerchantIdentifiers(CarrierAccount $account, array $identifiers): void
    {
        abort_unless($account->isUspsMerchantLabelProvider(), 404);

        $account->setUspsMerchantIdentifiers(
            crid: $identifiers['merchant_crid'],
            mid: $identifiers['merchant_mid'],
            epa: $identifiers['merchant_epa'],
            manifestMid: $identifiers['merchant_manifest_mid'] ?? null,
        );

        $account->forceFill([
            'connection_status' => CarrierAccount::CONNECTION_PENDING_VALIDATION,
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
            'usps_authorization_status' => CarrierAccount::USPS_AUTH_AWAITING_AUTHORIZATION,
            'last_error_code' => null,
            'last_error_message' => null,
        ])->save();

        USPSMerchantConnectionContext::for($account)->markWizardStepComplete(USPSMerchantWizard::STEP_IDENTIFIERS);
    }

    public function acknowledgePortalAuthorization(CarrierAccount $account): void
    {
        abort_unless($account->isUspsMerchantLabelProvider(), 404);
        abort_unless(
            $account->usps_authorization_status === CarrierAccount::USPS_AUTH_AWAITING_AUTHORIZATION,
            422,
        );

        $account->forceFill([
            'connection_status' => CarrierAccount::CONNECTION_PENDING_VALIDATION,
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
            'usps_authorization_status' => CarrierAccount::USPS_AUTH_VERIFYING,
            'last_error_code' => null,
            'last_error_message' => null,
        ])->save();

        $context = USPSMerchantConnectionContext::for($account);
        $context->markWizardStepComplete(USPSMerchantWizard::STEP_AUTHORIZATION);
        $context->merge([
            'authorization_acknowledged_at' => now()->toIso8601String(),
        ]);
    }

    public function completeOAuthAuthorization(CarrierAccount $account): void
    {
        abort_unless($account->isUspsMerchantLabelProvider(), 404);

        $account->forceFill([
            'connection_status' => CarrierAccount::CONNECTION_PENDING_VALIDATION,
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
            'usps_authorization_status' => CarrierAccount::USPS_AUTH_VERIFYING,
            'last_error_code' => null,
            'last_error_message' => null,
        ])->save();

        $context = USPSMerchantConnectionContext::for($account);
        $context->markWizardStepComplete(USPSMerchantWizard::STEP_AUTHORIZATION);
        $context->merge([
            'oauth_authorization_received_at' => now()->toIso8601String(),
        ]);
    }

    public function resetAuthorization(CarrierAccount $account): void
    {
        abort_unless($account->isUspsMerchantLabelProvider(), 404);

        $account->forceFill([
            'connection_status' => CarrierAccount::CONNECTION_PENDING_VALIDATION,
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
            'usps_authorization_status' => CarrierAccount::USPS_AUTH_AWAITING_AUTHORIZATION,
            'last_error_code' => null,
            'last_error_message' => null,
        ])->save();

        USPSMerchantConnectionContext::for($account)->merge([
            'authorization_acknowledged_at' => null,
            'authorization_reset_at' => now()->toIso8601String(),
            'oauth_authorization_verified_at' => null,
            'oauth_authorization_verification_method' => null,
        ]);

        if ($account->hasMerchantOAuthTokens()) {
            $store = $account->store;

            if ($store !== null) {
                $this->oauthService->revokeRefreshToken($store, $account);
            }

            $account->clearMerchantOAuthTokens();
        }

        $account->clearMerchantOAuthSubjectId();
    }

    public function markOAuthAuthorizationVerified(
        CarrierAccount $account,
        string $verificationMethod = 'oauth_token_only',
    ): void {
        abort_unless($account->isUspsMerchantLabelProvider(), 404);

        $account->forceFill([
            'connection_status' => CarrierAccount::CONNECTION_PENDING_VALIDATION,
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
            'usps_authorization_status' => CarrierAccount::USPS_AUTH_VERIFYING,
            'last_error_code' => null,
            'last_error_message' => null,
        ])->save();

        $context = USPSMerchantConnectionContext::for($account);
        $context->markWizardStepComplete(USPSMerchantWizard::STEP_AUTHORIZATION);
        $context->merge([
            'oauth_authorization_verified_at' => now()->toIso8601String(),
            'oauth_authorization_verification_method' => $verificationMethod,
        ]);
    }

    public function merchantOAuthAvailable(): bool
    {
        return $this->config->merchantOAuthEnabled();
    }

    public function markSetupRequired(CarrierAccount $account): void
    {
        abort_unless($account->isUspsMerchantLabelProvider(), 404);

        $account->forceFill([
            'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
            'usps_authorization_status' => CarrierAccount::USPS_AUTH_SETUP_REQUIRED,
        ])->save();

        USPSMerchantConnectionContext::for($account)->markWizardStepComplete(USPSMerchantWizard::STEP_REQUIREMENTS);
    }

    public function disconnect(CarrierAccount $account): void
    {
        abort_unless($account->isUspsMerchantLabelProvider(), 404);

        if ($account->hasMerchantOAuthTokens()) {
            $store = $account->store;

            if ($store !== null) {
                $this->oauthService->revokeRefreshToken($store, $account);
            }

            $account->clearMerchantOAuthTokens();
        }

        $account->forceFill([
            'connection_status' => CarrierAccount::CONNECTION_DISABLED,
            'status' => CarrierAccount::STATUS_DISABLED,
            'usps_authorization_status' => CarrierAccount::USPS_AUTH_DISABLED,
            'usps_active_store_key' => null,
            'enabled_for_checkout' => false,
            'credentials_encrypted' => null,
            'capabilities' => array_merge(
                is_array($account->capabilities) ? $account->capabilities : [],
                [
                    'rates' => false,
                    'labels' => false,
                    'tracking' => false,
                    'pickup' => false,
                ],
            ),
        ])->save();
    }

    public function wizard(): USPSMerchantWizard
    {
        return $this->wizard;
    }

    /**
     * @return list<array{key: string, label: string, description: string}>
     */
    public function merchantRequirements(): array
    {
        return [
            [
                'key' => 'business_account',
                'label' => 'USPS business account',
                'description' => 'Create or sign in to your USPS business account in the USPS Business Portal.',
            ],
            [
                'key' => 'payment_account',
                'label' => 'Enterprise Payment Account (EPA)',
                'description' => 'Add a payment method to your USPS business account. Postage for your labels is charged to your EPA — not to BmyBrand.',
            ],
            [
                'key' => 'crid_mid',
                'label' => 'CRID and Mailer ID',
                'description' => 'Your business account includes a Customer Registration ID (CRID) and Mailer ID (MID). These are account numbers, not passwords.',
            ],
            [
                'key' => 'label_provider',
                'label' => 'Authorize BmyBrand as Label Provider',
                'description' => 'In the USPS Business Portal, authorize BmyBrand to create labels on your behalf. This is required before labels or postage verification can work.',
            ],
        ];
    }

    public function uspsBusinessPortalUrl(): string
    {
        return (string) config('carriers.usps.business_portal_url', 'https://gateway.usps.com/eAdmin/action/home');
    }

    public function platformLabelProviderName(): string
    {
        return (string) config('carriers.usps.platform_label_provider_name', 'BmyBrand');
    }
}
