<?php

namespace App\Services\Carriers\FedEx\Connection;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierAccountRegistrationSession;
use App\Models\CarrierApiEvent;
use App\Models\Location;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\Core\CarrierApiEventLogger;
use App\Services\Carriers\Core\CarrierOriginReadinessService;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\Auth\FedExIntegratorChildOAuthService;
use App\Services\Carriers\FedEx\Auth\FedExIntegratorParentOAuthService;
use App\Services\Carriers\FedEx\DTO\FedExValidationEventContext;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Validation\FedExValidationSwedenPassthroughSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FedExIntegratorRegistrationOrchestrator
{
    private const MAX_MFA_ATTEMPTS = 5;

    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExEulaService $eulaService,
        private readonly FedExIntegratorParentOAuthService $parentOAuth,
        private readonly FedExIntegratorChildOAuthService $childOAuth,
        private readonly FedExAccountRegistrationService $registrationService,
        private readonly CarrierOriginReadinessService $originReadiness,
        private readonly FedExRegistrationInputValidator $inputValidator,
        private readonly FedExRegistrationResponseAnalyzer $responseAnalyzer,
        private readonly CarrierApiEventLogger $eventLogger,
        private readonly FedExRegistrationEventLinker $registrationEventLinker,
    ) {}

    public function start(
        Store $store,
        User $user,
        int $originLocationId,
        string $environment = CarrierAccount::ENVIRONMENT_SANDBOX,
    ): CarrierAccountRegistrationSession {
        abort_unless($this->config->modelAEnabled(), 404);

        $environment = strtolower($environment);
        abort_unless($this->config->allowsIntegratorEnvironment($environment), 422);

        $location = Location::query()
            ->where('store_id', $store->id)
            ->whereKey($originLocationId)
            ->firstOrFail();

        $readiness = $this->originReadiness->assessForFulfillmentOrigin(
            $location,
            CarrierOriginReadinessService::CARRIER_GENERIC,
        );

        if (! $readiness->ready) {
            throw ValidationException::withMessages([
                'origin_location_id' => $readiness->merchantMessage,
            ]);
        }

        return CarrierAccountRegistrationSession::query()->create([
            'store_id' => $store->id,
            'provider' => CarrierAccountRegistrationSession::PROVIDER_FEDEX,
            'environment' => $environment,
            'connection_model' => CarrierAccountRegistrationSession::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
            'purpose' => CarrierAccountRegistrationSession::PURPOSE_CONNECTION,
            'status' => CarrierAccountRegistrationSession::STATUS_EULA_REQUIRED,
            'origin_location_id' => $location->id,
            'eula_version' => $this->eulaService->version(),
            'created_by' => $user->id,
        ]);
    }

    public function beginValidationEulaReview(
        Store $store,
        User $user,
        CarrierAccount $account,
    ): CarrierAccountRegistrationSession {
        abort_unless((int) $account->store_id === (int) $store->id, 404);
        abort_unless($account->isFedEx(), 404);

        $existing = CarrierAccountRegistrationSession::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->where('purpose', CarrierAccountRegistrationSession::PURPOSE_VALIDATION_EULA)
            ->where('status', CarrierAccountRegistrationSession::STATUS_EULA_REQUIRED)
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return CarrierAccountRegistrationSession::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => CarrierAccountRegistrationSession::PROVIDER_FEDEX,
            'environment' => $account->environment,
            'connection_model' => CarrierAccountRegistrationSession::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
            'purpose' => CarrierAccountRegistrationSession::PURPOSE_VALIDATION_EULA,
            'status' => CarrierAccountRegistrationSession::STATUS_EULA_REQUIRED,
            'origin_location_id' => $account->default_origin_location_id,
            'eula_version' => $this->eulaService->version(),
            'created_by' => $user->id,
        ]);
    }

    public function markEulaScrollComplete(
        CarrierAccountRegistrationSession $session,
        string $documentHash,
        int $renderedPageCount,
    ): CarrierAccountRegistrationSession {
        $this->assertSessionActive($session);
        abort_unless($session->status === CarrierAccountRegistrationSession::STATUS_EULA_REQUIRED, 422);

        $this->eulaService->assertValid();
        $currentHash = $this->eulaService->hash();

        if (! hash_equals($currentHash, $documentHash)) {
            throw ValidationException::withMessages([
                'document_hash' => 'The EULA document has changed. Reload the page and review the agreement again.',
            ]);
        }

        if ($renderedPageCount !== $this->eulaService->expectedPages()) {
            throw ValidationException::withMessages([
                'rendered_page_count' => 'The agreement page count does not match the configured official document.',
            ]);
        }

        $session->forceFill([
            'eula_scrolled_at' => now(),
            'eula_document_hash' => $currentHash,
            'eula_rendered_page_count' => $renderedPageCount,
        ])->save();

        return $session->refresh();
    }

    public function acceptEula(
        CarrierAccountRegistrationSession $session,
        User $user,
        string $documentHash,
    ): CarrierAccountRegistrationSession {
        $this->assertSessionActive($session);
        abort_unless($session->status === CarrierAccountRegistrationSession::STATUS_EULA_REQUIRED, 422);

        if (! $this->eulaService->isValid()) {
            throw ValidationException::withMessages([
                'eula' => 'FedEx End User License Agreement is not available. Contact a platform administrator.',
            ]);
        }

        $currentHash = $this->eulaService->hash();

        if (! hash_equals($currentHash, $documentHash)) {
            throw ValidationException::withMessages([
                'document_hash' => 'The EULA document has changed. Reload the page and review the agreement again.',
            ]);
        }

        if ($session->eula_scrolled_at === null
            || ! filled($session->eula_document_hash)
            || ! hash_equals($currentHash, (string) $session->eula_document_hash)
            || (int) $session->eula_rendered_page_count !== $this->eulaService->expectedPages()) {
            throw ValidationException::withMessages([
                'eula' => 'Scroll through the full agreement before accepting.',
            ]);
        }

        $acceptedAt = now();

        $session->forceFill([
            'status' => CarrierAccountRegistrationSession::STATUS_EULA_ACCEPTED,
            'eula_accepted_at' => $acceptedAt,
            'eula_accepted_by' => $user->id,
            'eula_version' => $this->eulaService->version(),
            'eula_document_hash' => $currentHash,
            'eula_read_acknowledged_at' => $acceptedAt,
        ])->save();

        if ($session->purpose === CarrierAccountRegistrationSession::PURPOSE_VALIDATION_EULA
            && $session->carrier_account_id !== null) {
            $this->copyEulaAcceptanceToCarrierAccount($session);
        }

        return $session->refresh();
    }

    public function copyEulaAcceptanceToCarrierAccount(CarrierAccountRegistrationSession $session): void
    {
        $account = $session->carrierAccount;
        abort_unless($account !== null, 422);

        $account->forceFill([
            'eula_accepted_at' => $session->eula_accepted_at,
            'eula_version' => $session->eula_version,
            'eula_document_hash' => $session->eula_document_hash,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function submitAccountDetails(
        CarrierAccountRegistrationSession $session,
        array $input,
    ): CarrierAccountRegistrationSession {
        $this->assertSessionActive($session);
        abort_unless(in_array($session->status, [
            CarrierAccountRegistrationSession::STATUS_EULA_ACCEPTED,
            CarrierAccountRegistrationSession::STATUS_FAILED,
        ], true), 422);

        $validation = $this->inputValidator->validate($input);
        if ($validation['errors'] !== []) {
            throw ValidationException::withMessages($validation['errors']);
        }

        $normalized = $validation['normalized'];
        $session->setAccountNumber((string) $normalized['provider_account_number']);
        $session->forceFill([
            'account_name' => $normalized['company_name'] ?: $normalized['contact_name'],
            'registration_address_json' => $normalized,
            'residential' => (bool) ($normalized['residential'] ?? false),
            'status' => CarrierAccountRegistrationSession::STATUS_ACCOUNT_DETAILS_SUBMITTED,
            'last_error_code' => null,
            'last_error_message' => null,
        ])->save();

        $platformToken = $this->parentOAuth->fetchTokenResult($session->environment, fresh: true);
        if (! $platformToken->success) {
            return $this->markSessionFailed(
                $session,
                $platformToken->errorMessage ?? 'FedEx platform authentication failed.',
                $platformToken->errorCode ?? 'platform_oauth_failed',
                $platformToken->requestSummary,
                $platformToken->responseSummary,
            );
        }

        $tokenPayload = is_array($platformToken->data) ? $platformToken->data : null;
        $result = $this->registrationService->registerSession(
            $session->store,
            $session,
            $normalized,
            $tokenPayload,
        );

        return $this->handleRegistrationResult($session, $result);
    }

    public function selectMfaMethod(
        CarrierAccountRegistrationSession $session,
        string $method,
    ): CarrierAccountRegistrationSession {
        if ($blocked = $this->mfaContinuationFailure($session)) {
            return $blocked;
        }

        if ($session->status !== CarrierAccountRegistrationSession::STATUS_MFA_METHOD_REQUIRED) {
            return $this->markSessionFailed(
                $session,
                'FedEx verification is not ready for this step. Start a new FedEx connection from Shipping & Delivery.',
                'invalid_mfa_state',
            );
        }

        $method = strtolower($method);
        $allowed = [CarrierAccountRegistrationSession::MFA_EMAIL, CarrierAccountRegistrationSession::MFA_SMS, CarrierAccountRegistrationSession::MFA_CALL, CarrierAccountRegistrationSession::MFA_INVOICE];
        if (! in_array($method, $allowed, true)) {
            return $this->markSessionFailed(
                $session,
                'The selected FedEx verification method is not supported.',
                'invalid_mfa_method',
            );
        }

        if ($method === CarrierAccountRegistrationSession::MFA_INVOICE) {
            $session->forceFill([
                'mfa_method' => $method,
                'status' => CarrierAccountRegistrationSession::STATUS_INVOICE_PENDING,
                'mfa_destination_masked' => $this->maskedMfaDestination($session, $method),
            ])->save();

            return $session->refresh();
        }

        $platformToken = $this->parentOAuth->fetchTokenResult($session->environment);
        if ($this->config->mfaPinGenerationPath() !== null && $platformToken->success) {
            $generation = $this->registrationService->initiateMfaPinGeneration(
                $session,
                $method,
                is_array($platformToken->data) ? $platformToken->data : [],
            );

            if (! $generation->success && $generation->errorCode !== 'registration_mfa_required') {
                return $this->markSessionFailed(
                    $session,
                    $generation->errorMessage ?? 'FedEx could not start PIN delivery.',
                    $generation->errorCode,
                    $generation->requestSummary,
                    $generation->responseSummary,
                );
            }
        }

        $session->forceFill([
            'mfa_method' => $method,
            'status' => CarrierAccountRegistrationSession::STATUS_PIN_PENDING,
            'mfa_destination_masked' => $this->resolveMfaDestinationMasked($session, $method),
            'last_error_code' => null,
            'last_error_message' => null,
        ])->save();

        return $session->refresh();
    }

    public function verifyPin(CarrierAccountRegistrationSession $session, string $pin): CarrierAccountRegistrationSession
    {
        if ($blocked = $this->mfaContinuationFailure($session)) {
            return $blocked;
        }

        if ($session->status !== CarrierAccountRegistrationSession::STATUS_PIN_PENDING) {
            return $this->markSessionFailed(
                $session,
                'FedEx PIN verification is not ready for this step. Start a new FedEx connection from Shipping & Delivery.',
                'invalid_mfa_state',
            );
        }

        if ($this->config->mfaPinValidationPath() === null) {
            throw ValidationException::withMessages([
                'pin' => 'FedEx PIN validation endpoint is not configured yet. Set FEDEX_MFA_PIN_VALIDATION_PATH from the FedEx Developer Portal.',
            ]);
        }

        if ($session->mfa_attempt_count >= self::MAX_MFA_ATTEMPTS) {
            return $this->lockSession($session, 'Too many verification attempts. Start a new FedEx connection.');
        }

        $session->increment('mfa_attempt_count');

        $platformToken = $this->parentOAuth->fetchTokenResult($session->environment);
        if (! $platformToken->success) {
            return $this->markSessionFailed($session, $platformToken->errorMessage ?? 'FedEx platform authentication failed.', $platformToken->errorCode ?? 'platform_oauth_failed');
        }

        $result = $this->registrationService->validateMfaPin(
            $session,
            $pin,
            is_array($platformToken->data) ? $platformToken->data : [],
        );

        return $this->handleRegistrationResult($session, $result);
    }

    /**
     * @param  array<string, mixed>  $invoiceInput
     */
    public function verifyInvoice(
        CarrierAccountRegistrationSession $session,
        array $invoiceInput,
    ): CarrierAccountRegistrationSession {
        if ($blocked = $this->mfaContinuationFailure($session)) {
            return $blocked;
        }

        if ($session->status !== CarrierAccountRegistrationSession::STATUS_INVOICE_PENDING) {
            return $this->markSessionFailed(
                $session,
                'FedEx invoice verification is not ready for this step. Start a new FedEx connection from Shipping & Delivery.',
                'invalid_mfa_state',
            );
        }

        if ($this->config->mfaInvoiceValidationPath() === null) {
            throw ValidationException::withMessages([
                'invoice_number' => 'FedEx invoice validation endpoint is not configured yet. Set FEDEX_MFA_INVOICE_VALIDATION_PATH from the FedEx Developer Portal.',
            ]);
        }

        if ($session->mfa_attempt_count >= self::MAX_MFA_ATTEMPTS) {
            return $this->lockSession($session, 'Too many verification attempts. Start a new FedEx connection.');
        }

        $session->increment('mfa_attempt_count');

        $platformToken = $this->parentOAuth->fetchTokenResult($session->environment);
        if (! $platformToken->success) {
            return $this->markSessionFailed($session, $platformToken->errorMessage ?? 'FedEx platform authentication failed.', $platformToken->errorCode ?? 'platform_oauth_failed');
        }

        $result = $this->registrationService->validateMfaInvoice(
            $session,
            $invoiceInput,
            is_array($platformToken->data) ? $platformToken->data : [],
        );

        return $this->handleRegistrationResult($session, $result);
    }

    public function completeRegistrationFromFedExResponse(
        CarrierAccountRegistrationSession $session,
        CarrierApiResult $result,
    ): CarrierAccount {
        $child = $this->extractChildCredentials($result->data ?? []);

        if ($child === null) {
            throw ValidationException::withMessages([
                'registration' => 'FedEx did not return merchant child credentials.',
            ]);
        }

        return DB::transaction(function () use ($session, $result, $child): CarrierAccount {
            $store = $session->store;
            $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();

            $account = CarrierAccount::query()->create(array_merge([
                'store_id' => $store->id,
                'carrier_id' => $fedEx->id,
                'provider' => CarrierAccount::PROVIDER_FEDEX,
                'environment' => $session->environment,
                'display_name' => $session->account_name ?: 'FedEx account',
                'provider_account_number' => $session->accountNumber(),
                'default_origin_location_id' => $session->origin_location_id,
                'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
                'status' => CarrierAccount::STATUS_ENABLED,
                'registration_session_id' => $session->id,
                'eula_accepted_at' => $session->eula_accepted_at,
                'eula_version' => $session->eula_version,
                'eula_document_hash' => $session->eula_document_hash,
                'connection_context_json' => [
                    'connection_model' => CarrierAccountRegistrationSession::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
                    'integrator_registration' => true,
                ],
                'settings' => [
                    'default_origin_location_id' => $session->origin_location_id,
                    'registration' => $session->registrationAddress(),
                ],
                'created_by' => $session->created_by,
            ], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));

            $account->setCredentials([
                'customer_key' => $child['customer_key'],
                'customer_password' => $child['customer_password'],
            ]);
            $account->save();

            $oauthCheck = $this->childOAuth->fetchTokenResult($account, fresh: true);
            if (! $oauthCheck->success) {
                $account->markFailed(
                    $oauthCheck->errorMessage ?? 'FedEx child credential OAuth failed after registration.',
                    $oauthCheck->errorCode ?? 'child_oauth_failed',
                );
            } else {
                $account->markConnected($account->capabilities ?? []);
            }

            $session->setChildCredentials($child['customer_key'], $child['customer_password']);
            $session->forceFill([
                'carrier_account_id' => $account->id,
                'status' => CarrierAccountRegistrationSession::STATUS_REGISTERED,
                'completed_at' => now(),
                'fedex_transaction_id' => data_get($result->responseSummary, 'fedex_transaction_id'),
                'request_summary_json' => $result->requestSummary,
                'response_summary_json' => $result->responseSummary,
                'last_error_code' => null,
                'last_error_message' => null,
            ])->save();

            $childEvent = $this->eventLogger->start(
                store: $store,
                provider: CarrierAccount::PROVIDER_FEDEX,
                action: CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
                account: $account,
                requestSummary: array_merge($result->requestSummary ?? [], [
                    'mfa_step' => 'child_credentials_generated',
                    'registration_session_id' => $session->id,
                ]),
                environment: $session->environment,
                context: new FedExValidationEventContext(
                    registrationSessionId: $session->id,
                    scenarioKey: CarrierApiEvent::SCENARIO_REGISTRATION_CHILD_CREDENTIALS,
                ),
            );
            $this->eventLogger->complete($childEvent, $result);

            $this->registrationEventLinker->linkSessionEventsToAccount($account, $session);

            return $account->refresh();
        });
    }

    public function cancel(CarrierAccountRegistrationSession $session): void
    {
        if ($session->isCancelled() || $session->status === CarrierAccountRegistrationSession::STATUS_REGISTERED) {
            return;
        }

        $session->forceFill([
            'status' => CarrierAccountRegistrationSession::STATUS_CANCELLED,
            'completed_at' => now(),
        ])->save();
    }

    private function handleRegistrationResult(
        CarrierAccountRegistrationSession $session,
        CarrierApiResult $result,
    ): CarrierAccountRegistrationSession {
        $session->forceFill([
            'fedex_transaction_id' => data_get($result->responseSummary, 'fedex_transaction_id'),
            'request_summary_json' => $result->requestSummary,
            'response_summary_json' => $result->responseSummary,
        ]);

        if ($result->success) {
            $this->completeRegistrationFromFedExResponse($session, $result);

            return $session->refresh();
        }

        if ($result->errorCode === 'registration_mfa_required') {
            $authToken = $this->responseAnalyzer->extractAccountAuthToken($result->data);

            if ($authToken === null || ! filled($authToken['token'])) {
                return $this->markSessionFailed(
                    $session,
                    'FedEx returned MFA options but no accountAuthToken. Cannot continue registration.',
                    'account_auth_token_missing',
                    $result->requestSummary,
                    $result->responseSummary,
                );
            }

            $session->setAccountAuthToken($authToken['token'], $authToken['expires_at'] ?? null);

            $mfaOptions = $this->responseAnalyzer->parseSanitizedMfaOptions($result->data);
            $session->forceFill([
                'status' => CarrierAccountRegistrationSession::STATUS_MFA_METHOD_REQUIRED,
                'mfa_options_json' => $mfaOptions,
                'mfa_destination_masked' => $this->responseAnalyzer->extractMfaDestinationMasked($result->data),
                'last_error_code' => null,
                'last_error_message' => null,
            ])->save();

            return $session->refresh();
        }

        return $this->markSessionFailed(
            $session,
            $result->errorMessage ?? 'FedEx account registration failed.',
            $result->errorCode,
            $result->requestSummary,
            $result->responseSummary,
        );
    }

    private function extractChildCredentials(?array $data): ?array
    {
        return $this->responseAnalyzer->extractChildCredentials($data);
    }

    private function resolveMfaDestinationMasked(CarrierAccountRegistrationSession $session, string $method): ?string
    {
        $options = is_array($session->mfa_options_json) ? $session->mfa_options_json : [];

        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }

            if (($option['raw_key'] ?? null) === $method && filled($option['destination_masked'] ?? null)) {
                return (string) $option['destination_masked'];
            }
        }

        return $this->maskedMfaDestination($session, $method);
    }

    private function maskedMfaDestination(CarrierAccountRegistrationSession $session, string $method): ?string
    {
        $address = $session->registrationAddress();
        if ($method === CarrierAccountRegistrationSession::MFA_EMAIL) {
            $email = (string) ($address['email'] ?? '');
            if ($email === '' || ! str_contains($email, '@')) {
                return null;
            }
            [$local, $domain] = explode('@', $email, 2);

            return substr($local, 0, 1).'***@'.$domain;
        }

        $phone = (string) ($address['phone'] ?? '');
        if ($phone === '') {
            return null;
        }

        return '***'.substr(preg_replace('/\D+/', '', $phone) ?? '', -4);
    }

    /**
     * @param  array<string, mixed>|null  $requestSummary
     * @param  array<string, mixed>|null  $responseSummary
     */
    private function markSessionFailed(
        CarrierAccountRegistrationSession $session,
        string $message,
        ?string $code = null,
        ?array $requestSummary = null,
        ?array $responseSummary = null,
    ): CarrierAccountRegistrationSession {
        $preservedResponse = is_array($responseSummary)
            ? $responseSummary
            : (is_array($session->response_summary_json) ? $session->response_summary_json : []);
        $preservedResponse['technical_error_message'] = $message;
        if ($code !== null) {
            $preservedResponse['technical_error_code'] = $code;
        }

        $session->forceFill([
            'status' => CarrierAccountRegistrationSession::STATUS_FAILED,
            'last_error_code' => $code,
            'last_error_message' => FedExValidationSwedenPassthroughSupport::FAILURE_MESSAGE,
            'request_summary_json' => $requestSummary ?? $session->request_summary_json,
            'response_summary_json' => $preservedResponse,
        ])->save();

        return $session->refresh();
    }

    private function lockSession(CarrierAccountRegistrationSession $session, string $message): CarrierAccountRegistrationSession
    {
        $preservedResponse = is_array($session->response_summary_json) ? $session->response_summary_json : [];
        $preservedResponse['technical_error_message'] = $message;
        $preservedResponse['technical_error_code'] = 'mfa_locked';

        $session->forceFill([
            'status' => CarrierAccountRegistrationSession::STATUS_LOCKED,
            'last_error_message' => FedExValidationSwedenPassthroughSupport::FAILURE_MESSAGE,
            'last_error_code' => 'mfa_locked',
            'response_summary_json' => $preservedResponse,
        ])->save();

        return $session->refresh();
    }

    private function mfaContinuationFailure(CarrierAccountRegistrationSession $session): ?CarrierAccountRegistrationSession
    {
        if ($session->isCancelled()) {
            return $this->markSessionFailed(
                $session,
                'This FedEx connection setup was cancelled. Start a new FedEx connection from Shipping & Delivery.',
                'session_cancelled',
            );
        }

        if ($session->status === CarrierAccountRegistrationSession::STATUS_REGISTERED) {
            return $session;
        }

        if ($session->status === CarrierAccountRegistrationSession::STATUS_FAILED) {
            return $session;
        }

        if ($session->status === CarrierAccountRegistrationSession::STATUS_LOCKED) {
            return $session;
        }

        if (! $session->hasAccountAuthToken()) {
            return $this->markSessionFailed(
                $session,
                'FedEx verification could not continue because account authorization is missing. Start a new FedEx connection.',
                'account_auth_token_missing',
            );
        }

        if ($session->isAccountAuthTokenExpired()) {
            return $this->markSessionFailed(
                $session,
                'FedEx verification authorization has expired. Start a new FedEx connection.',
                'account_auth_token_expired',
            );
        }

        return null;
    }

    private function assertSessionActive(CarrierAccountRegistrationSession $session): void
    {
        abort_if($session->isCancelled(), 410);
        abort_if($session->status === CarrierAccountRegistrationSession::STATUS_REGISTERED, 422);
        abort_if($session->status === CarrierAccountRegistrationSession::STATUS_LOCKED, 423);
    }
}
