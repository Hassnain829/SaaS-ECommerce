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
use App\Services\Carriers\FedEx\DTO\FedExApiEventContext;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Support\FedExShipperPhoneResolver;
use Illuminate\Database\UniqueConstraintViolationException;
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
        private readonly FedExConnectionFailurePresenter $failurePresenter,
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

        return $session->refresh();
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

        $validation = $this->inputValidator->validate($input, [
            'environment' => $session->environment,
        ]);
        if ($validation['errors'] !== []) {
            throw ValidationException::withMessages($validation['errors']);
        }

        $normalized = $validation['normalized'];
        $accountNumber = (string) $normalized['provider_account_number'];
        unset($normalized['provider_account_number']);

        $session->setAccountNumber($accountNumber);
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
            array_merge($normalized, ['provider_account_number' => $accountNumber]),
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
                'pin' => 'This verification method is temporarily unavailable. Choose another verification method or contact support.',
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
                'invoice_number' => 'This verification method is temporarily unavailable. Choose another verification method or contact support.',
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

    private function finalizeRegistrationWithChildCredentials(
        CarrierAccountRegistrationSession $session,
        CarrierApiResult $result,
    ): CarrierAccount {
        $child = $this->extractChildCredentials($result->data ?? []);

        if ($child === null) {
            throw ValidationException::withMessages([
                'registration' => 'FedEx did not return merchant child credentials.',
            ]);
        }

        $account = $this->persistIssuedChildCredentials($session, $result, $child);
        $session->refresh();

        if (
            $session->status === CarrierAccountRegistrationSession::STATUS_REGISTERED
            && $account->isConnected()
        ) {
            return $account->refresh();
        }

        if (
            $session->status === CarrierAccountRegistrationSession::STATUS_FAILED
            || ! $account->hasLegacyFedExChildCredentials()
        ) {
            return $account->refresh();
        }

        // Durable short update — credentials remain committed even if OAuth crashes next.
        $session->forceFill([
            'status' => CarrierAccountRegistrationSession::STATUS_CHILD_OAUTH_VERIFYING,
            'completed_at' => null,
        ])->save();

        $oauthCheck = $this->childOAuth->fetchTokenResult($account->refresh(), fresh: true);

        return $this->finalizeChildOAuthOutcome($session, $account, $result, $oauthCheck);
    }

    /**
     * Transaction A: lock session, create/reuse one pending CarrierAccount, persist encrypted
     * child credentials, set credentials_issued. Does not run child OAuth.
     *
     * @param  array{customer_key: string, customer_password: string}  $child
     */
    private function persistIssuedChildCredentials(
        CarrierAccountRegistrationSession $session,
        CarrierApiResult $result,
        array $child,
    ): CarrierAccount {
        return DB::transaction(function () use ($session, $result, $child): CarrierAccount {
            /** @var CarrierAccountRegistrationSession $lockedSession */
            $lockedSession = CarrierAccountRegistrationSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedSession->status === CarrierAccountRegistrationSession::STATUS_REGISTERED
                && $lockedSession->carrier_account_id
            ) {
                return CarrierAccount::query()
                    ->whereKey($lockedSession->carrier_account_id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $store = $lockedSession->store;
            $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();
            $evidence = [
                'fedex_transaction_id' => data_get($result->responseSummary, 'fedex_transaction_id'),
                'request_summary_json' => $result->requestSummary,
                'response_summary_json' => $result->responseSummary,
                'last_error_code' => null,
                'last_error_message' => null,
                'completed_at' => null,
            ];

            if ($lockedSession->carrier_account_id) {
                $account = CarrierAccount::query()
                    ->whereKey($lockedSession->carrier_account_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $account->registration_session_id !== (int) $lockedSession->id) {
                    throw ValidationException::withMessages([
                        'registration' => 'FedEx registration session is linked to a different carrier account.',
                    ]);
                }

                $account->setFedExAccountNumber((string) ($lockedSession->accountNumber() ?? ''));
                $account->setCredentials([
                    'customer_key' => $child['customer_key'],
                    'customer_password' => $child['customer_password'],
                ]);
                $account->forceFill([
                    'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
                    'fedex_active_store_key' => null,
                ])->save();
            } else {
                $existing = CarrierAccount::query()
                    ->where('registration_session_id', $lockedSession->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    $account = $existing;
                    $account->setFedExAccountNumber((string) ($lockedSession->accountNumber() ?? ''));
                    $account->setCredentials([
                        'customer_key' => $child['customer_key'],
                        'customer_password' => $child['customer_password'],
                    ]);
                    $account->forceFill([
                        'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
                        'fedex_active_store_key' => null,
                    ])->save();
                } else {
                    try {
                        $account = CarrierAccount::query()->create(array_merge([
                            'store_id' => $store->id,
                            'carrier_id' => $fedEx->id,
                            'provider' => CarrierAccount::PROVIDER_FEDEX,
                            'environment' => $lockedSession->environment,
                            'display_name' => $lockedSession->account_name ?: 'FedEx account',
                            'default_origin_location_id' => $lockedSession->origin_location_id,
                            'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
                            'status' => CarrierAccount::STATUS_ENABLED,
                            'registration_session_id' => $lockedSession->id,
                            'fedex_active_store_key' => null,
                            'eula_accepted_at' => $lockedSession->eula_accepted_at,
                            'eula_version' => $lockedSession->eula_version,
                            'eula_document_hash' => $lockedSession->eula_document_hash,
                            'connection_context_json' => [
                                'connection_model' => CarrierAccountRegistrationSession::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
                                'integrator_registration' => true,
                            ],
                            'settings' => [
                                'default_origin_location_id' => $lockedSession->origin_location_id,
                                'registration' => $this->sanitizedRegistrationSettings($lockedSession),
                            ],
                            'created_by' => $lockedSession->created_by,
                        ], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));
                    } catch (UniqueConstraintViolationException) {
                        $account = CarrierAccount::query()
                            ->where('registration_session_id', $lockedSession->id)
                            ->lockForUpdate()
                            ->firstOrFail();
                    }

                    $account->setFedExAccountNumber((string) ($lockedSession->accountNumber() ?? ''));
                    $account->setCredentials([
                        'customer_key' => $child['customer_key'],
                        'customer_password' => $child['customer_password'],
                    ]);
                    $account->save();
                }
            }

            $lockedSession->forceFill(array_merge($evidence, [
                'carrier_account_id' => $account->id,
                'status' => CarrierAccountRegistrationSession::STATUS_CREDENTIALS_ISSUED,
            ]))->save();

            $this->backfillOriginLocationPhoneFromRegistration($lockedSession, $account);

            if (! $account->refresh()->hasLegacyFedExChildCredentials()) {
                $message = $this->failurePresenter->message(
                    $lockedSession,
                    'child_credentials_storage_failed',
                    'FedEx child credentials were not stored on the carrier account.',
                );
                $account->markFailed($message, 'child_credentials_storage_failed');
                $account->clearFedExActiveStoreKey();

                $lockedSession->forceFill([
                    'status' => CarrierAccountRegistrationSession::STATUS_FAILED,
                    'completed_at' => null,
                    'last_error_code' => 'child_credentials_storage_failed',
                    'last_error_message' => $message,
                ])->save();

                return $account->refresh();
            }

            return $account->refresh();
        });
    }

    /**
     * Resume child OAuth only — never re-runs Credential Registration.
     *
     * Completed registered sessions (including successful reconnect) are idempotent no-ops
     * when the incoming account owns the exact expected active key. Outgoing replacement
     * state is intentionally not re-validated after registration completes.
     */
    public function resumeChildOAuthVerification(
        Store $store,
        CarrierAccountRegistrationSession $session,
    ): CarrierAccountRegistrationSession {
        abort_unless((int) $session->store_id === (int) $store->id, 404);
        abort_unless(
            $session->provider === CarrierAccountRegistrationSession::PROVIDER_FEDEX
            && $session->connection_model === CarrierAccountRegistrationSession::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
            404,
        );

        $resumable = [
            CarrierAccountRegistrationSession::STATUS_CREDENTIALS_ISSUED,
            CarrierAccountRegistrationSession::STATUS_CHILD_OAUTH_VERIFYING,
            CarrierAccountRegistrationSession::STATUS_CHILD_OAUTH_FAILED,
            CarrierAccountRegistrationSession::STATUS_REGISTERED,
        ];
        abort_unless(in_array($session->status, $resumable, true), 422);

        $account = CarrierAccount::query()
            ->whereKey((int) $session->carrier_account_id)
            ->first();
        abort_unless($account !== null, 422);
        abort_unless((int) $session->carrier_account_id === (int) $account->id, 422);
        abort_unless((int) $account->registration_session_id === (int) $session->id, 422);
        abort_unless((int) $account->store_id === (int) $store->id, 404);
        abort_unless((int) $account->store_id === (int) $session->store_id, 404);
        abort_unless($account->usesFedExIntegratorProvider(), 404);
        abort_unless($account->disconnected_at === null && $account->replaced_at === null, 422);
        abort_unless(
            strtolower((string) $account->environment) === strtolower((string) $session->environment),
            422,
        );

        $expectedIncomingKey = CarrierAccount::fedExActiveStoreKeyFor(
            (int) $store->id,
            (string) $session->environment,
        );

        // Completed registration/reconnect: idempotent return before any outgoing checks or OAuth.
        if ($session->status === CarrierAccountRegistrationSession::STATUS_REGISTERED) {
            abort_unless($account->isConnected(), 422);
            abort_unless($account->fedex_active_store_key === $expectedIncomingKey, 422);

            return $session->refresh();
        }

        abort_unless($account->hasLegacyFedExChildCredentials(), 422);

        $replacingId = (int) ($session->replacing_carrier_account_id ?? 0);
        if ($replacingId > 0) {
            $outgoing = CarrierAccount::query()->whereKey($replacingId)->first();
            abort_unless($outgoing !== null, 422);
            abort_unless((int) $outgoing->store_id === (int) $store->id, 404);
            abort_unless($outgoing->usesFedExIntegratorProvider(), 422);
            abort_unless(
                strtolower((string) $outgoing->environment) === strtolower((string) $session->environment),
                422,
            );
            abort_unless($outgoing->fedex_active_store_key === $expectedIncomingKey, 422);
            abort_unless($outgoing->disconnected_at === null && $outgoing->replaced_at === null, 422);
            abort_unless((int) $outgoing->id !== (int) $account->id, 422);
        }

        $session->forceFill([
            'status' => CarrierAccountRegistrationSession::STATUS_CHILD_OAUTH_VERIFYING,
            'completed_at' => null,
            'last_error_code' => null,
            'last_error_message' => null,
        ])->save();

        $oauthCheck = $this->childOAuth->fetchTokenResult($account->refresh(), fresh: true);

        $registrationResult = CarrierApiResult::success(
            data: null,
            requestSummary: is_array($session->request_summary_json) ? $session->request_summary_json : [],
            responseSummary: is_array($session->response_summary_json) ? $session->response_summary_json : [],
        );

        $this->finalizeChildOAuthOutcome($session->refresh(), $account->refresh(), $registrationResult, $oauthCheck);

        return $session->refresh();
    }

    /**
     * Transaction B: lock session, then accounts in sorted ID order, activate or fail.
     * Assigns fedex_active_store_key only on successful activation.
     * Reconnect replacement is atomic; unique-key / activation failures roll back retirement.
     */
    private function finalizeChildOAuthOutcome(
        CarrierAccountRegistrationSession $session,
        CarrierAccount $account,
        CarrierApiResult $registrationResult,
        CarrierApiResult $oauthCheck,
    ): CarrierAccount {
        $outgoingTokenCacheKey = null;

        try {
            $finalAccount = DB::transaction(function () use ($session, $account, $oauthCheck, &$outgoingTokenCacheKey): CarrierAccount {
                /** @var CarrierAccountRegistrationSession $lockedSession */
                $lockedSession = CarrierAccountRegistrationSession::query()
                    ->whereKey($session->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $incomingId = (int) ($lockedSession->carrier_account_id ?? 0);
                $replacingId = (int) ($lockedSession->replacing_carrier_account_id ?? 0);

                if ($incomingId <= 0 || $incomingId !== (int) $account->id) {
                    throw ValidationException::withMessages([
                        'registration' => 'FedEx registration session and carrier account are out of sync.',
                    ]);
                }

                if (
                    $lockedSession->status === CarrierAccountRegistrationSession::STATUS_REGISTERED
                ) {
                    /** @var CarrierAccount $already */
                    $already = CarrierAccount::query()
                        ->whereKey($incomingId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($already->isConnected() && filled($already->fedex_active_store_key)) {
                        if ((int) $already->registration_session_id !== (int) $lockedSession->id) {
                            throw ValidationException::withMessages([
                                'registration' => 'FedEx registration session and carrier account are out of sync.',
                            ]);
                        }
                        $lockedSession->clearTransientFedExSecrets();

                        return $already->refresh();
                    }
                }

                $expectedStatuses = [
                    CarrierAccountRegistrationSession::STATUS_CREDENTIALS_ISSUED,
                    CarrierAccountRegistrationSession::STATUS_CHILD_OAUTH_VERIFYING,
                    CarrierAccountRegistrationSession::STATUS_CHILD_OAUTH_FAILED,
                ];
                if (! in_array($lockedSession->status, $expectedStatuses, true)
                    && $lockedSession->status !== CarrierAccountRegistrationSession::STATUS_REGISTERED
                ) {
                    throw ValidationException::withMessages([
                        'registration' => 'FedEx registration session is not ready for child OAuth finalization.',
                    ]);
                }

                if ($replacingId > 0) {
                    if ($replacingId === $incomingId) {
                        throw ValidationException::withMessages([
                            'registration' => $this->failurePresenter->message(
                                $lockedSession,
                                'fedex_reconnect_same_account',
                                'FedEx reconnect cannot replace an account with itself. Start reconnect again.',
                            ),
                        ]);
                    }

                    $sortedIds = [$incomingId, $replacingId];
                    sort($sortedIds, SORT_NUMERIC);

                    $lockedById = [];
                    foreach ($sortedIds as $id) {
                        $lockedById[$id] = CarrierAccount::query()
                            ->whereKey($id)
                            ->lockForUpdate()
                            ->first();
                    }

                    $incoming = $lockedById[$incomingId] ?? null;
                    $outgoing = $lockedById[$replacingId] ?? null;

                    if ($incoming === null || $outgoing === null) {
                        if ($incoming !== null) {
                            $this->failIncomingReplacement(
                                $lockedSession,
                                $incoming,
                                'fedex_reconnect_target_missing',
                                'The FedEx account you are replacing is no longer available. Start reconnect again.',
                            );

                            return $incoming->refresh();
                        }

                        throw ValidationException::withMessages([
                            'registration' => 'FedEx reconnect accounts are no longer available.',
                        ]);
                    }

                    if ((int) $incoming->registration_session_id !== (int) $lockedSession->id
                        || (int) $lockedSession->carrier_account_id !== (int) $incoming->id
                    ) {
                        throw ValidationException::withMessages([
                            'registration' => 'FedEx registration session and carrier account are out of sync.',
                        ]);
                    }

                    if (! $oauthCheck->success) {
                        $this->markChildOAuthFailed($lockedSession, $incoming, $oauthCheck);
                        $incoming->clearFedExActiveStoreKey();
                        $lockedSession->clearTransientFedExSecrets();

                        return $incoming->refresh();
                    }

                    $activeKey = CarrierAccount::fedExActiveStoreKeyFor(
                        (int) $incoming->store_id,
                        (string) $incoming->environment,
                    );

                    $this->assertAndApplyReconnectReplacement(
                        $lockedSession,
                        $incoming,
                        $outgoing,
                        $activeKey,
                        $outgoingTokenCacheKey,
                    );

                    return $incoming->refresh();
                }

                /** @var CarrierAccount $lockedAccount */
                $lockedAccount = CarrierAccount::query()
                    ->whereKey($incomingId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $lockedAccount->registration_session_id !== (int) $lockedSession->id) {
                    throw ValidationException::withMessages([
                        'registration' => 'FedEx registration session and carrier account are out of sync.',
                    ]);
                }

                if (! in_array($lockedSession->status, $expectedStatuses, true)) {
                    throw ValidationException::withMessages([
                        'registration' => 'FedEx registration session is not ready for child OAuth finalization.',
                    ]);
                }

                if (! $oauthCheck->success) {
                    $this->markChildOAuthFailed($lockedSession, $lockedAccount, $oauthCheck);
                    $lockedAccount->clearFedExActiveStoreKey();
                    $lockedSession->clearTransientFedExSecrets();

                    return $lockedAccount->refresh();
                }

                $activeKey = CarrierAccount::fedExActiveStoreKeyFor(
                    (int) $lockedAccount->store_id,
                    (string) $lockedAccount->environment,
                );

                $existingActive = CarrierAccount::query()
                    ->where('fedex_active_store_key', $activeKey)
                    ->where('id', '!=', $lockedAccount->id)
                    ->lockForUpdate()
                    ->first();

                if ($existingActive !== null) {
                    $message = $this->failurePresenter->message(
                        $lockedSession,
                        'fedex_active_account_exists',
                        'A FedEx account is already active for this store and environment.',
                    );
                    $lockedAccount->markFailed($message, 'fedex_active_account_exists');
                    $lockedAccount->clearFedExActiveStoreKey();
                    $lockedSession->forceFill([
                        'status' => CarrierAccountRegistrationSession::STATUS_CHILD_OAUTH_FAILED,
                        'completed_at' => null,
                        'last_error_code' => 'fedex_active_account_exists',
                        'last_error_message' => $message,
                    ])->save();
                    $lockedSession->clearTransientFedExSecrets();

                    return $lockedAccount->refresh();
                }

                $this->activateIncomingAccount($lockedAccount, $lockedSession, $activeKey, throwOnFailure: false);

                return $lockedAccount->refresh();
            });
        } catch (UniqueConstraintViolationException) {
            $this->markActivationConflictAfterRollback($session, $account);

            throw ValidationException::withMessages([
                'registration' => $this->failurePresenter->message(
                    $session->refresh(),
                    'fedex_active_account_exists',
                    'A FedEx account is already active for this store and environment.',
                ),
            ]);
        } catch (FedExReplacementRollbackException $exception) {
            $this->markActivationConflictAfterRollback(
                $session,
                $account,
                $exception->errorCode,
                $exception->fallbackMessage,
            );

            throw ValidationException::withMessages([
                'registration' => $this->failurePresenter->message(
                    $session->refresh(),
                    $exception->errorCode,
                    $exception->fallbackMessage,
                ),
            ]);
        }

        if (is_string($outgoingTokenCacheKey) && $outgoingTokenCacheKey !== '') {
            $this->childOAuth->clearTokenCacheKey($outgoingTokenCacheKey);
        }

        $session->refresh();
        $store = $session->store;

        $childEvent = $this->eventLogger->start(
            store: $store,
            provider: CarrierAccount::PROVIDER_FEDEX,
            action: CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
            account: $finalAccount,
            requestSummary: array_merge($registrationResult->requestSummary ?? [], [
                'mfa_step' => 'child_credentials_generated',
                'registration_session_id' => $session->id,
            ]),
            environment: $session->environment,
            context: new FedExApiEventContext(
                registrationSessionId: $session->id,
                scenarioKey: CarrierApiEvent::SCENARIO_REGISTRATION_CHILD_CREDENTIALS,
            ),
        );
        $this->eventLogger->complete($childEvent, $registrationResult);
        $this->registrationEventLinker->linkSessionEventsToAccount($finalAccount, $session);

        return $finalAccount->refresh();
    }

    /**
     * Incoming and outgoing accounts must already be locked in sorted ID order.
     * All pairing / tenancy / credential / active-key checks run before any retirement mutation.
     *
     * @param-out string|null $outgoingTokenCacheKey
     */
    private function assertAndApplyReconnectReplacement(
        CarrierAccountRegistrationSession $lockedSession,
        CarrierAccount $incoming,
        CarrierAccount $outgoing,
        string $activeKey,
        ?string &$outgoingTokenCacheKey,
    ): void {
        $failIncomingOnly = function (string $code, string $fallback) use ($lockedSession, $incoming): void {
            $this->failIncomingReplacement($lockedSession, $incoming, $code, $fallback);
        };

        if ((int) $incoming->id === (int) $outgoing->id) {
            $failIncomingOnly(
                'fedex_reconnect_same_account',
                'FedEx reconnect cannot replace an account with itself. Start reconnect again.',
            );

            return;
        }

        if (! $this->incomingReadyForActivation($lockedSession, $incoming, $activeKey)) {
            return;
        }

        if (! $outgoing->usesFedExIntegratorProvider() || ! $incoming->usesFedExIntegratorProvider()) {
            $failIncomingOnly('fedex_reconnect_model_mismatch', 'FedEx reconnect requires Model A accounts on both sides.');

            return;
        }

        if ((int) $outgoing->store_id !== (int) $incoming->store_id
            || (int) $outgoing->store_id !== (int) $lockedSession->store_id
        ) {
            $failIncomingOnly('fedex_reconnect_store_mismatch', 'FedEx reconnect accounts must belong to the same store.');

            return;
        }

        if (strtolower((string) $outgoing->environment) !== strtolower((string) $incoming->environment)
            || strtolower((string) $outgoing->environment) !== strtolower((string) $lockedSession->environment)
        ) {
            $failIncomingOnly('fedex_reconnect_environment_mismatch', 'FedEx reconnect accounts must use the same environment.');

            return;
        }

        if ($outgoing->disconnected_at !== null || $outgoing->replaced_at !== null) {
            $failIncomingOnly('fedex_reconnect_outgoing_inactive', 'The previous FedEx account is no longer active. Start reconnect again.');

            return;
        }

        if ($outgoing->fedex_active_store_key !== $activeKey) {
            $failIncomingOnly('fedex_reconnect_outgoing_key_mismatch', 'The previous FedEx account is not the active connection. Start reconnect again.');

            return;
        }

        if ((int) ($lockedSession->replacing_carrier_account_id ?? 0) !== (int) $outgoing->id) {
            $failIncomingOnly('fedex_reconnect_target_stale', 'This reconnect session no longer matches the active FedEx account. Start reconnect again.');

            return;
        }

        $third = CarrierAccount::query()
            ->where('fedex_active_store_key', $activeKey)
            ->whereNotIn('id', [(int) $outgoing->id, (int) $incoming->id])
            ->lockForUpdate()
            ->first();

        if ($third !== null) {
            $failIncomingOnly('fedex_active_account_exists', 'Another FedEx account already holds the active connection for this store.');

            return;
        }

        if ($outgoing->hasLegacyFedExChildCredentials()) {
            $outgoingTokenCacheKey = $this->childOAuth->tokenCacheKey($outgoing);
        }

        // From this point, any failure must roll back the entire replacement transaction.
        $this->retireReplacedAccount($outgoing, $incoming);
        $this->activateIncomingAccount($incoming, $lockedSession, $activeKey, throwOnFailure: true);
    }

    /**
     * Validates incoming account before any outgoing retirement. On failure, marks incoming only.
     */
    private function incomingReadyForActivation(
        CarrierAccountRegistrationSession $lockedSession,
        CarrierAccount $incoming,
        string $activeKey,
    ): bool {
        $fail = function (string $code, string $fallback) use ($lockedSession, $incoming): void {
            $this->failIncomingReplacement($lockedSession, $incoming, $code, $fallback);
        };

        if ($incoming->disconnected_at !== null) {
            $fail('fedex_reconnect_incoming_disconnected', 'The new FedEx connection was disconnected before it could activate. Start reconnect again.');

            return false;
        }

        if ($incoming->replaced_at !== null || $incoming->replaced_by_carrier_account_id !== null) {
            $fail('fedex_reconnect_incoming_replaced', 'The new FedEx connection was already replaced. Start reconnect again.');

            return false;
        }

        if (! $incoming->hasLegacyFedExChildCredentials()) {
            $fail('child_credentials_missing', 'FedEx child credentials are missing on the new connection. Start reconnect again.');

            return false;
        }

        if (filled($incoming->fedex_active_store_key)) {
            $fail('fedex_reconnect_incoming_already_active', 'The new FedEx connection is already active. Refresh Shipping & Delivery.');

            return false;
        }

        $allowedStatuses = [
            CarrierAccount::CONNECTION_SETUP_REQUIRED,
            CarrierAccount::CONNECTION_PENDING_VALIDATION,
            CarrierAccount::CONNECTION_FAILED,
            CarrierAccount::CONNECTION_NOT_CONNECTED,
        ];
        if (! in_array((string) $incoming->connection_status, $allowedStatuses, true)) {
            $fail('fedex_reconnect_incoming_state', 'The new FedEx connection is not ready to activate. Start reconnect again.');

            return false;
        }

        if (! $incoming->usesFedExIntegratorProvider()) {
            $fail('fedex_reconnect_model_mismatch', 'FedEx reconnect requires Model A accounts on both sides.');

            return false;
        }

        if ((int) $incoming->store_id !== (int) $lockedSession->store_id) {
            $fail('fedex_reconnect_store_mismatch', 'FedEx reconnect accounts must belong to the same store.');

            return false;
        }

        if (strtolower((string) $incoming->environment) !== strtolower((string) $lockedSession->environment)) {
            $fail('fedex_reconnect_environment_mismatch', 'FedEx reconnect accounts must use the same environment.');

            return false;
        }

        if ((int) $lockedSession->carrier_account_id !== (int) $incoming->id
            || (int) $incoming->registration_session_id !== (int) $lockedSession->id
        ) {
            $fail('fedex_reconnect_session_pairing', 'FedEx registration session and carrier account are out of sync.');

            return false;
        }

        // Ensure expected key format matches store/environment of the incoming account.
        $expected = CarrierAccount::fedExActiveStoreKeyFor(
            (int) $incoming->store_id,
            (string) $incoming->environment,
        );
        if ($expected !== $activeKey) {
            $fail('fedex_reconnect_key_mismatch', 'FedEx reconnect active key does not match this store. Start reconnect again.');

            return false;
        }

        return true;
    }

    private function activateIncomingAccount(
        CarrierAccount $lockedAccount,
        CarrierAccountRegistrationSession $lockedSession,
        string $activeKey,
        bool $throwOnFailure = false,
    ): void {
        $capabilities = is_array($lockedAccount->capabilities) ? $lockedAccount->capabilities : [];
        $capabilities = array_merge($capabilities, [
            'rates' => false,
            'labels' => false,
            'tracking' => false,
            'pickup' => false,
            'checkout_rates' => false,
        ]);

        $lockedAccount->markConnected($capabilities);
        $lockedAccount->refresh();

        if (! $lockedAccount->isConnected() || ! $lockedAccount->hasLegacyFedExChildCredentials()) {
            if ($throwOnFailure) {
                throw new FedExReplacementRollbackException(
                    'child_oauth_activation_failed',
                    'FedEx child OAuth succeeded but the account did not reach connected state.',
                );
            }

            $message = $this->failurePresenter->message(
                $lockedSession,
                'child_oauth_activation_failed',
                'FedEx child OAuth succeeded but the account did not reach connected state.',
            );
            $lockedAccount->markFailed($message, 'child_oauth_activation_failed');
            $lockedAccount->clearFedExActiveStoreKey();
            $lockedSession->forceFill([
                'status' => CarrierAccountRegistrationSession::STATUS_CHILD_OAUTH_FAILED,
                'completed_at' => null,
                'last_error_code' => 'child_oauth_activation_failed',
                'last_error_message' => $message,
            ])->save();
            $lockedSession->clearTransientFedExSecrets();

            return;
        }

        $lockedAccount->forceFill([
            'fedex_active_store_key' => $activeKey,
            'enabled_for_checkout' => false,
            'capabilities' => $capabilities,
        ])->save();

        $lockedSession->forceFill([
            'status' => CarrierAccountRegistrationSession::STATUS_REGISTERED,
            'completed_at' => now(),
            'last_error_code' => null,
            'last_error_message' => null,
        ])->save();
        $lockedSession->clearTransientFedExSecrets();
    }

    private function failIncomingReplacement(
        CarrierAccountRegistrationSession $lockedSession,
        CarrierAccount $lockedAccount,
        string $code,
        string $fallback,
    ): void {
        $message = $this->failurePresenter->message($lockedSession, $code, $fallback);
        $lockedAccount->markFailed($message, $code);
        $lockedAccount->clearFedExActiveStoreKey();
        $lockedSession->forceFill([
            'status' => CarrierAccountRegistrationSession::STATUS_CHILD_OAUTH_FAILED,
            'completed_at' => null,
            'last_error_code' => $code,
            'last_error_message' => $message,
        ])->save();
        $lockedSession->clearTransientFedExSecrets();
    }

    private function markActivationConflictAfterRollback(
        CarrierAccountRegistrationSession $session,
        CarrierAccount $account,
        string $code = 'fedex_active_account_exists',
        string $fallback = 'A FedEx account is already active for this store and environment.',
    ): void {
        DB::transaction(function () use ($session, $account, $code, $fallback): void {
            $lockedSession = CarrierAccountRegistrationSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->first();
            $lockedAccount = CarrierAccount::query()
                ->whereKey($account->id)
                ->lockForUpdate()
                ->first();

            if ($lockedSession === null || $lockedAccount === null) {
                return;
            }

            // Never mutate an account that still owns the active key after a rolled-back replacement.
            if (filled($lockedAccount->fedex_active_store_key) && $lockedAccount->isConnected()) {
                return;
            }

            $message = $this->failurePresenter->message($lockedSession, $code, $fallback);
            $lockedAccount->markFailed($message, $code);
            $lockedAccount->clearFedExActiveStoreKey();
            $lockedSession->forceFill([
                'status' => CarrierAccountRegistrationSession::STATUS_CHILD_OAUTH_FAILED,
                'completed_at' => null,
                'last_error_code' => $code,
                'last_error_message' => $message,
            ])->save();
            $lockedSession->clearTransientFedExSecrets();
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
            // Direct-child registration and every MFA completion path converge here.
            $this->finalizeRegistrationWithChildCredentials($session, $result);

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

    private function retireReplacedAccount(CarrierAccount $outgoing, CarrierAccount $incoming): void
    {
        $capabilities = is_array($outgoing->capabilities) ? $outgoing->capabilities : [];
        $capabilities = array_merge($capabilities, [
            'rates' => false,
            'labels' => false,
            'tracking' => false,
            'pickup' => false,
            'checkout_rates' => false,
        ]);

        $outgoing->forceFill([
            'connection_status' => CarrierAccount::CONNECTION_DISABLED,
            'status' => CarrierAccount::STATUS_DISABLED,
            'enabled_for_checkout' => false,
            'fedex_active_store_key' => null,
            'credentials_encrypted' => null,
            'provider_account_number_encrypted' => null,
            'provider_account_number' => null,
            'capabilities' => $capabilities,
            'replaced_at' => now(),
            'replaced_by_carrier_account_id' => $incoming->id,
            'disconnected_at' => now(),
            'last_error_code' => null,
            'last_error_message' => null,
        ])->save();
    }

    private function extractChildCredentials(?array $data): ?array
    {
        return $this->responseAnalyzer->extractChildCredentials($data);
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitizedRegistrationSettings(CarrierAccountRegistrationSession $session): array
    {
        $registration = $session->registrationAddress();
        unset($registration['provider_account_number'], $registration['account_number']);

        if (filled($session->account_last4)) {
            $registration['account_last4'] = (string) $session->account_last4;
        }

        return $registration;
    }

    private function backfillOriginLocationPhoneFromRegistration(
        CarrierAccountRegistrationSession $session,
        CarrierAccount $account,
    ): void {
        $locationId = (int) ($session->origin_location_id ?: $account->default_origin_location_id);
        if ($locationId <= 0) {
            return;
        }

        $location = Location::query()
            ->where('store_id', $session->store_id)
            ->whereKey($locationId)
            ->first();

        if ($location === null) {
            return;
        }

        app(FedExShipperPhoneResolver::class)
            ->resolveAndBackfill($location, $account);
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

    private function markChildOAuthFailed(
        CarrierAccountRegistrationSession $session,
        CarrierAccount $account,
        CarrierApiResult $oauthResult,
    ): void {
        $technicalMessage = $oauthResult->errorMessage
            ?? 'FedEx child credential OAuth failed after registration.';
        $code = 'child_oauth_failed';
        $providerCode = $oauthResult->errorCode;
        $safeMessage = $this->failurePresenter->message($session, $code, $technicalMessage);
        $preservedResponse = is_array($session->response_summary_json)
            ? $session->response_summary_json
            : [];
        $preservedResponse['child_oauth_verification'] = [
            'success' => false,
            'error_code' => $code,
            'provider_error_code' => $providerCode,
            'request_summary' => $oauthResult->requestSummary,
            'response_summary' => $oauthResult->responseSummary,
        ];
        $preservedResponse['technical_error_code'] = $code;
        $preservedResponse['technical_error_message'] = $technicalMessage;

        $account->markFailed($safeMessage, $code);
        $session->forceFill([
            'status' => CarrierAccountRegistrationSession::STATUS_CHILD_OAUTH_FAILED,
            'completed_at' => null,
            'last_error_code' => $code,
            'last_error_message' => $safeMessage,
            'response_summary_json' => $preservedResponse,
        ])->save();
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
        $safeMessage = $this->failurePresenter->message($session, $code, $message);

        $session->forceFill([
            'status' => CarrierAccountRegistrationSession::STATUS_FAILED,
            'last_error_code' => $code,
            'last_error_message' => $safeMessage,
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
        $safeMessage = $this->failurePresenter->message($session, 'mfa_locked', $message);

        $session->forceFill([
            'status' => CarrierAccountRegistrationSession::STATUS_LOCKED,
            'last_error_message' => $safeMessage,
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

        if ($session->status === CarrierAccountRegistrationSession::STATUS_CHILD_OAUTH_FAILED) {
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
