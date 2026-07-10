<?php

namespace App\Services\Carriers\USPS\Connection;

use App\Models\CarrierAccount;
use App\Models\Store;
use App\Services\Carriers\USPS\Support\USPSConfig;
use App\Services\Carriers\USPS\Support\USPSMerchantConnectionContext;

class USPSMerchantShipSuiteVerificationService
{
    public function __construct(
        private readonly USPSConfig $config,
        private readonly USPSShipEnrollmentService $enrollmentService,
        private readonly USPSPaymentAuthorizationService $paymentAuthorizationService,
        private readonly USPSMerchantConnectionService $connectionService,
    ) {}

    /**
     * @return array{success: bool, code: string, message: string}
     */
    public function verify(Store $store, CarrierAccount $account): array
    {
        abort_unless($account->isUspsMerchantLabelProvider(), 404);
        abort_unless((int) $account->store_id === (int) $store->id, 404);

        if (! $this->config->merchantShipSuiteVerifyEnabled()) {
            return $this->failure(
                code: 'usps_ship_suite_pending',
                message: 'USPS Ship enrollment and postage verification will be available once BmyBrand receives USPS Shipping Suite approval.',
            );
        }

        if (! $account->hasUspsMerchantIdentifiers()) {
            return $this->failure(
                code: 'identifiers_missing',
                message: 'Add your USPS CRID, MID, and EPA before verifying postage.',
            );
        }

        $context = USPSMerchantConnectionContext::for($account);
        if (! $context->hasOAuthAuthorizationVerified()) {
            return $this->failure(
                code: 'authorization_required',
                message: 'Verify Label Provider authorization with USPS before checking Ship enrollment and postage.',
            );
        }

        $this->connectionService->markEnrollmentPending($account->fresh());

        $enrollmentResult = $this->enrollmentService->verify($store, $account->fresh());
        if (! $enrollmentResult->success) {
            $this->connectionService->markEnrollmentFailed(
                $account->fresh(),
                code: (string) ($enrollmentResult->errorCode ?? 'ship_enrollment_failed'),
                message: $enrollmentResult->errorMessage ?? 'USPS Ship enrollment could not be verified.',
            );

            return $this->failure(
                code: (string) ($enrollmentResult->errorCode ?? 'ship_enrollment_failed'),
                message: $enrollmentResult->errorMessage ?? 'USPS Ship enrollment could not be verified.',
            );
        }

        $this->connectionService->markEnrollmentVerified($account->fresh());

        $paymentResult = $this->paymentAuthorizationService->verify($store, $account->fresh());
        if (! $paymentResult->success) {
            $this->connectionService->markPaymentVerificationFailed(
                $account->fresh(),
                code: (string) ($paymentResult->errorCode ?? 'payment_authorization_failed'),
                message: $paymentResult->errorMessage ?? 'USPS postage account could not be verified.',
            );

            return $this->failure(
                code: (string) ($paymentResult->errorCode ?? 'payment_authorization_failed'),
                message: $paymentResult->errorMessage ?? 'USPS postage account could not be verified.',
            );
        }

        $this->connectionService->markPaymentVerifiedAndConnected($account->fresh());

        return $this->success(
            code: 'postage_account_verified',
            message: 'USPS Ship enrollment and postage account verified. Rates and labels will be enabled in a later phase.',
        );
    }

    /**
     * @return array{success: bool, code: string, message: string}
     */
    private function success(string $code, string $message): array
    {
        return [
            'success' => true,
            'code' => $code,
            'message' => $message,
        ];
    }

    /**
     * @return array{success: bool, code: string, message: string}
     */
    private function failure(string $code, string $message): array
    {
        return [
            'success' => false,
            'code' => $code,
            'message' => $message,
        ];
    }
}
