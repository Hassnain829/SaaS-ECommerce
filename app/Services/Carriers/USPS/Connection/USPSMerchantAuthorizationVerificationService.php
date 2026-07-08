<?php

namespace App\Services\Carriers\USPS\Connection;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use App\Services\Carriers\Core\CarrierApiEventLogger;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\USPS\Auth\USPSMerchantOAuthService;
use App\Services\Carriers\USPS\Support\USPSConfig;
use App\Services\Carriers\USPS\Support\USPSHttpClient;
use App\Services\Carriers\USPS\Support\USPSOAuthSubjectExtractor;
use App\Services\Carriers\USPS\Support\USPSUserInfoParser;

class USPSMerchantAuthorizationVerificationService
{
    public function __construct(
        private readonly USPSConfig $config,
        private readonly USPSHttpClient $httpClient,
        private readonly USPSMerchantOAuthService $oauthService,
        private readonly CarrierApiEventLogger $eventLogger,
        private readonly USPSMerchantConnectionService $connectionService,
        private readonly USPSUserInfoParser $userInfoParser,
        private readonly USPSOAuthSubjectExtractor $subjectExtractor,
    ) {}

    /**
     * @return array{success: bool, code: string, message: string}
     */
    public function verify(Store $store, CarrierAccount $account): array
    {
        abort_unless($account->isUspsMerchantLabelProvider(), 404);
        abort_unless((int) $account->store_id === (int) $store->id, 404);

        if (! $account->hasUspsMerchantIdentifiers()) {
            return $this->failure(
                code: 'identifiers_missing',
                message: 'Add your USPS CRID, MID, and EPA before verifying the connection.',
            );
        }

        if (! $this->config->merchantOAuthEnabled()) {
            return $this->failure(
                code: 'usps_platform_oauth_pending',
                message: 'Automated USPS authorization verification will be available once BmyBrand platform Label Provider OAuth is enabled.',
            );
        }

        if (! $account->hasMerchantOAuthTokens()) {
            return $this->failure(
                code: 'oauth_required',
                message: 'Authorize BmyBrand with USPS using the secure OAuth button, then verify again.',
            );
        }

        $event = $this->eventLogger->start(
            store: $store,
            provider: CarrierAccount::PROVIDER_USPS,
            action: CarrierApiEvent::ACTION_USPS_AUTHORIZATION_VERIFY,
            account: $account,
            requestSummary: [
                'environment' => $this->config->environment(),
                'verification_method' => 'merchant_oauth_userinfo',
            ],
            environment: $account->environment ?? CarrierAccount::ENVIRONMENT_TESTING,
        );

        $accessToken = $this->oauthService->accessTokenForAccount($store, $account);

        if ($accessToken === null) {
            $result = CarrierApiResult::failure(
                message: 'Unable to obtain a valid USPS merchant access token. Reauthorize with USPS and try again.',
                code: 'merchant_token_missing',
            );
            $this->eventLogger->complete($event, $result);

            return $this->failure(
                code: 'merchant_token_missing',
                message: $result->errorMessage ?? 'Unable to verify USPS authorization.',
            );
        }

        $userinfoPath = trim($this->config->userinfoPath());

        if ($userinfoPath === '') {
            $result = CarrierApiResult::failure(
                message: 'USPS user profile verification is not configured. Authorization cannot be confirmed without userinfo validation.',
                code: 'userinfo_not_configured',
            );
            $this->eventLogger->complete($event, $result);

            return $this->failure(
                code: 'userinfo_not_configured',
                message: $result->errorMessage ?? 'Unable to verify USPS authorization.',
            );
        }

        $userinfoEvent = $this->eventLogger->start(
            store: $store,
            provider: CarrierAccount::PROVIDER_USPS,
            action: CarrierApiEvent::ACTION_USPS_USERINFO,
            account: $account,
            requestSummary: [
                'endpoint' => $userinfoPath,
                'environment' => $this->config->environment(),
            ],
            environment: $account->environment ?? CarrierAccount::ENVIRONMENT_TESTING,
        );

        $userinfoResult = $this->httpClient->getJson(
            path: $userinfoPath,
            query: [],
            headers: [],
            bearerToken: $accessToken,
            requestSummary: [
                'endpoint' => $userinfoPath,
                'environment' => $this->config->environment(),
            ],
        );

        $this->eventLogger->complete($userinfoEvent, $userinfoResult);

        if ($userinfoResult->success) {
            $subjectId = $this->subjectExtractor->extractFromUserInfo($userinfoResult->data ?? []);

            if ($subjectId !== null) {
                $account->setMerchantOAuthSubjectId($subjectId);
            }
        }

        if (! $userinfoResult->success) {
            $this->eventLogger->complete($event, $userinfoResult);

            $account->forceFill([
                'last_error_code' => $userinfoResult->errorCode,
                'last_error_message' => $userinfoResult->errorMessage,
            ])->save();

            return $this->failure(
                code: (string) ($userinfoResult->errorCode ?? 'userinfo_failed'),
                message: $userinfoResult->errorMessage ?? 'USPS could not confirm your Label Provider authorization.',
            );
        }

        $extracted = $this->userInfoParser->extractIdentifiers($userinfoResult->data ?? []);
        $identifierCheck = $this->userInfoParser->validateAgainstMerchantAccount(
            extracted: $extracted,
            expectedCrid: (string) $account->uspsMerchantCrid(),
            expectedMid: (string) $account->uspsMerchantMid(),
            expectedEpa: (string) $account->uspsMerchantEpa(),
        );

        if (! $identifierCheck['matched']) {
            $failureCode = $identifierCheck['code'];
            $mismatchResult = CarrierApiResult::failure(
                message: $identifierCheck['message'],
                code: $failureCode,
                responseSummary: $userinfoResult->responseSummary,
            );
            $this->eventLogger->complete($event, $mismatchResult);

            $account->forceFill([
                'last_error_code' => $failureCode,
                'last_error_message' => $identifierCheck['message'],
            ])->save();

            return $this->failure(
                code: $failureCode,
                message: $identifierCheck['message'],
            );
        }

        $this->connectionService->markOAuthAuthorizationVerified(
            $account->fresh(),
            verificationMethod: 'oauth_userinfo',
        );

        $result = CarrierApiResult::success(
            data: ['verification_method' => 'oauth_userinfo'],
            requestSummary: ['verification_method' => 'oauth_userinfo'],
            responseSummary: $userinfoResult->responseSummary,
        );
        $this->eventLogger->complete($event, $result);

        return $this->success(
            code: 'authorization_verified',
            message: 'USPS Label Provider authorization verified against your business account. Ship enrollment and postage verification continue in the next setup phase.',
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
