<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierAccount;
use App\Models\CarrierAccountRegistrationSession;
use App\Models\CarrierApiEvent;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\Auth\FedExIntegratorParentOAuthService;
use App\Services\Carriers\FedEx\Connection\FedExAccountRegistrationService;
use App\Services\Carriers\FedEx\Connection\FedExRegistrationEventLinker;
use App\Services\Carriers\FedEx\Connection\FedExRegistrationResponseAnalyzer;
use App\Services\Carriers\FedEx\Support\FedExConfig;

class FedExValidationMfaEvidenceService
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExIntegratorParentOAuthService $parentOAuth,
        private readonly FedExAccountRegistrationService $registrationService,
        private readonly FedExRegistrationResponseAnalyzer $responseAnalyzer,
        private readonly FedExRegistrationEventLinker $registrationEventLinker,
    ) {}

    /**
     * @param  array<string, mixed>  $invoiceInput
     */
    public function runInvoiceValidation(CarrierAccount $account, array $invoiceInput): CarrierApiResult
    {
        abort_unless($this->config->validationModeEnabled(), 403);
        abort_unless(
            $this->config->mfaInvoiceValidationPath() !== null,
            422,
            'FedEx invoice validation endpoint is not configured. Set FEDEX_MFA_INVOICE_VALIDATION_PATH in your environment.',
        );

        $session = $this->resolveRegistrationSession($account);
        $result = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            if ($refreshResult = $this->refreshAccountAuthToken($session, $account)) {
                return $refreshResult;
            }

            $result = $this->attemptInvoiceValidation($session, $account, $invoiceInput);

            if ($result->success || ! $this->isAuthTokenExpiredFailure($result)) {
                break;
            }

            $session->forceFill([
                'fedex_account_auth_token_encrypted' => null,
                'account_auth_token_expires_at' => null,
            ])->save();
        }

        $this->registrationEventLinker->linkSessionEventsToAccount($account, $session);

        return $result ?? CarrierApiResult::failure(
            message: 'Invoice validation could not be completed.',
            code: 'invoice_validation_failed',
        );
    }

    public function runRegistrationAddressValidation(CarrierAccount $account): CarrierApiResult
    {
        abort_unless($this->config->validationModeEnabled(), 403);

        $session = $this->resolveRegistrationSession($account);
        $address = $this->resolveRegistrationAddress($session, $account);

        abort_if(
            $address === [],
            422,
            'Registration address details are missing. Reconnect FedEx or restore the linked registration session address before rerunning this check.',
        );

        $platformToken = $this->parentOAuth->fetchTokenResult($session->environment, fresh: true);
        if (! $platformToken->success) {
            return CarrierApiResult::failure(
                message: $platformToken->errorMessage ?? 'FedEx platform authentication failed.',
                code: $platformToken->errorCode ?? 'platform_oauth_failed',
            );
        }

        $result = $this->registrationService->registerSession(
            $session->store,
            $session,
            $address,
            is_array($platformToken->data) ? $platformToken->data : null,
        );

        $this->registrationEventLinker->linkSessionEventsToAccount($account, $session);

        $event = CarrierApiEvent::query()
            ->where('store_id', $account->store_id)
            ->where('scenario_key', CarrierApiEvent::SCENARIO_REGISTRATION_ADDRESS)
            ->where(function ($query) use ($account, $session): void {
                $query->where('carrier_account_id', $account->id)
                    ->orWhere('registration_session_id', $session->id);
            })
            ->latest('id')
            ->first();

        return $result->copyWith(
            responseSummary: array_merge(
                is_array($result->responseSummary) ? $result->responseSummary : [],
                ['carrier_api_event_id' => $event?->id],
            ),
        );
    }

    public function registrationAddressResultIsEvidenceSuccess(CarrierApiResult $result): bool
    {
        if ($result->success) {
            return true;
        }

        if ($result->errorCode === 'registration_mfa_required') {
            return true;
        }

        return (bool) data_get($result->responseSummary, 'mfa_detected');
    }

    public function runPinGeneration(CarrierAccount $account, string $method): CarrierApiResult
    {
        abort_unless($this->config->validationModeEnabled(), 403);
        abort_unless(
            $this->config->mfaPinGenerationPath() !== null,
            422,
            'FedEx PIN generation endpoint is not configured. Set FEDEX_MFA_PIN_GENERATION_PATH in your environment.',
        );

        $method = $this->normalizePinMfaMethod($method);
        $session = $this->resolveRegistrationSession($account);

        if ($refreshResult = $this->refreshAccountAuthToken($session, $account)) {
            return $refreshResult;
        }

        $platformToken = $this->parentOAuth->fetchTokenResult($session->environment);
        if (! $platformToken->success) {
            return CarrierApiResult::failure(
                message: $platformToken->errorMessage ?? 'FedEx platform authentication failed.',
                code: $platformToken->errorCode ?? 'platform_oauth_failed',
            );
        }

        $session->forceFill(['mfa_method' => $method])->save();

        $result = $this->registrationService->initiateMfaPinGeneration(
            $session,
            $method,
            is_array($platformToken->data) ? $platformToken->data : [],
            carrierAccount: $account,
        );

        $this->registrationEventLinker->linkSessionEventsToAccount($account, $session);

        return $result;
    }

    public function runPinValidation(CarrierAccount $account, string $method, string $pin): CarrierApiResult
    {
        abort_unless($this->config->validationModeEnabled(), 403);
        abort_unless(
            $this->config->mfaPinValidationPath() !== null,
            422,
            'FedEx PIN validation endpoint is not configured. Set FEDEX_MFA_PIN_VALIDATION_PATH in your environment.',
        );

        $method = $this->normalizePinMfaMethod($method);
        $session = $this->resolveRegistrationSession($account);
        $result = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            if ($refreshResult = $this->refreshAccountAuthToken($session, $account)) {
                return $refreshResult;
            }

            $platformToken = $this->parentOAuth->fetchTokenResult($session->environment);
            if (! $platformToken->success) {
                return CarrierApiResult::failure(
                    message: $platformToken->errorMessage ?? 'FedEx platform authentication failed.',
                    code: $platformToken->errorCode ?? 'platform_oauth_failed',
                );
            }

            $session->forceFill(['mfa_method' => $method])->save();

            $result = $this->registrationService->validateMfaPin(
                session: $session,
                pin: $pin,
                platformToken: is_array($platformToken->data) ? $platformToken->data : [],
                finalizeRegistration: false,
                carrierAccount: $account,
            );

            if ($result->success || ! $this->isAuthTokenExpiredFailure($result)) {
                break;
            }

            $session->forceFill([
                'fedex_account_auth_token_encrypted' => null,
                'account_auth_token_expires_at' => null,
            ])->save();
        }

        $this->registrationEventLinker->linkSessionEventsToAccount($account, $session);

        return $result ?? CarrierApiResult::failure(
            message: 'PIN validation could not be completed.',
            code: 'pin_validation_failed',
        );
    }

    /**
     * @param  array<string, mixed>  $invoiceInput
     */
    private function attemptInvoiceValidation(
        CarrierAccountRegistrationSession $session,
        CarrierAccount $account,
        array $invoiceInput,
    ): CarrierApiResult {
        $platformToken = $this->parentOAuth->fetchTokenResult($session->environment);
        if (! $platformToken->success) {
            return CarrierApiResult::failure(
                message: $platformToken->errorMessage ?? 'FedEx platform authentication failed.',
                code: $platformToken->errorCode ?? 'platform_oauth_failed',
            );
        }

        return $this->registrationService->validateMfaInvoice(
            session: $session,
            invoiceInput: $invoiceInput,
            platformToken: is_array($platformToken->data) ? $platformToken->data : [],
            finalizeRegistration: false,
            carrierAccount: $account,
        );
    }

    private function isAuthTokenExpiredFailure(CarrierApiResult $result): bool
    {
        if ($result->errorCode === 'SESSION.EXPIRED.ERROR') {
            return true;
        }

        foreach ((array) data_get($result->data, 'errors', []) as $error) {
            if (! is_array($error)) {
                continue;
            }

            if (($error['code'] ?? null) === 'SESSION.EXPIRED.ERROR') {
                return true;
            }

            if (str_contains(strtolower((string) ($error['message'] ?? '')), 'auth token expired')) {
                return true;
            }
        }

        return false;
    }

    private function resolveRegistrationSession(CarrierAccount $account): CarrierAccountRegistrationSession
    {
        abort_unless(
            $account->registration_session_id !== null,
            422,
            'This FedEx account has no linked registration session for MFA evidence.',
        );

        $session = CarrierAccountRegistrationSession::query()
            ->where('store_id', $account->store_id)
            ->whereKey($account->registration_session_id)
            ->first();

        abort_unless(
            $session !== null,
            422,
            'Linked FedEx registration session was not found.',
        );

        return $session;
    }

    private function refreshAccountAuthToken(
        CarrierAccountRegistrationSession $session,
        CarrierAccount $account,
    ): ?CarrierApiResult {
        if ($session->hasAccountAuthToken()
            && ! $session->isAccountAuthTokenExpired()
            && filled($session->fedex_transaction_id)) {
            return null;
        }

        $address = $this->resolveRegistrationAddress($session, $account);

        abort_if(
            $address === [],
            422,
            'Registration address details are missing. Restore registration settings before running MFA evidence.',
        );

        $platformToken = $this->parentOAuth->fetchTokenResult($session->environment, fresh: true);
        if (! $platformToken->success) {
            return CarrierApiResult::failure(
                message: $platformToken->errorMessage ?? 'FedEx platform authentication failed while refreshing verification authorization.',
                code: $platformToken->errorCode ?? 'platform_oauth_failed',
            );
        }

        $result = $this->registrationService->registerSession(
            $session->store,
            $session,
            $address,
            is_array($platformToken->data) ? $platformToken->data : null,
        );

        $this->registrationEventLinker->linkSessionEventsToAccount($account, $session);

        if ($result->errorCode === 'registration_mfa_required') {
            $this->syncRegistrationMfaSessionState($session, $result);

            if ($session->hasAccountAuthToken() && ! $session->isAccountAuthTokenExpired()) {
                return null;
            }
        }

        if (! $result->success && $result->errorCode !== 'registration_mfa_required') {
            return CarrierApiResult::failure(
                message: $result->errorMessage ?? 'FedEx could not refresh verification authorization.',
                code: $result->errorCode ?? 'account_auth_refresh_failed',
                responseSummary: $result->responseSummary,
            );
        }

        if ($session->hasAccountAuthToken() && ! $session->isAccountAuthTokenExpired()) {
            return null;
        }

        return CarrierApiResult::failure(
            message: 'FedEx did not return a fresh account authorization token. Try again or reconnect FedEx if this continues.',
            code: 'account_auth_token_missing',
        );
    }

    private function syncRegistrationMfaSessionState(
        CarrierAccountRegistrationSession $session,
        CarrierApiResult $result,
    ): void {
        $updates = [];

        $transactionId = data_get($result->responseSummary, 'fedex_transaction_id')
            ?? data_get($result->data, 'transactionId');

        if (filled($transactionId)) {
            $updates['fedex_transaction_id'] = $transactionId;
        }

        $authToken = $this->responseAnalyzer->extractAccountAuthToken($result->data);
        if ($authToken !== null && filled($authToken['token'])) {
            $session->setAccountAuthToken($authToken['token'], $authToken['expires_at'] ?? null);
        }

        if ($updates !== []) {
            $session->forceFill($updates);
        }

        if ($updates !== [] || ($authToken !== null && filled($authToken['token']))) {
            $session->save();
        }
    }

    private function normalizePinMfaMethod(string $method): string
    {
        $method = strtolower(trim($method));

        abort_unless(
            in_array($method, ['email', 'call'], true),
            422,
            'PIN MFA method must be email or call.',
        );

        return $method;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveRegistrationAddress(
        CarrierAccountRegistrationSession $session,
        CarrierAccount $account,
    ): array {
        $address = $session->registrationAddress();
        if ($address === []) {
            $address = $this->registrationDetailsFromAccount($account);
        }

        if ($address === []) {
            return [];
        }

        if (filled($address['registration_postal_code_raw'] ?? null)) {
            $address['postal_code'] = (string) $address['registration_postal_code_raw'];
        }

        return $address;
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationDetailsFromAccount(CarrierAccount $account): array
    {
        $registration = (array) data_get($account->settings, 'registration', []);

        return array_filter([
            'provider_account_number' => $account->provider_account_number,
            'company_name' => $registration['company_name'] ?? $account->display_name,
            'contact_name' => $registration['contact_name'] ?? null,
            'email' => $registration['email'] ?? null,
            'phone' => $registration['phone'] ?? null,
            'address_line1' => $registration['address_line1'] ?? null,
            'address_line2' => $registration['address_line2'] ?? null,
            'city' => $registration['city'] ?? null,
            'state' => $registration['state'] ?? null,
            'postal_code' => $registration['postal_code'] ?? null,
            'country_code' => $registration['country_code'] ?? 'US',
            'residential' => (bool) ($registration['residential'] ?? false),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
