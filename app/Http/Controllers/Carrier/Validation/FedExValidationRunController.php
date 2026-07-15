<?php

namespace App\Http\Controllers\Carrier\Validation;

use App\Http\Controllers\Carrier\Validation\Concerns\ResolvesFedExValidationAccount;
use App\Http\Controllers\Controller;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Location;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\Connection\FedExIntegratorRegistrationOrchestrator;
use App\Services\Carriers\FedEx\Operations\FedExAddressValidationService;
use App\Services\Carriers\FedEx\Operations\FedExBasicIntegratedVisibilityService;
use App\Services\Carriers\FedEx\Operations\FedExComprehensiveRateAccessClassifier;
use App\Services\Carriers\FedEx\Operations\FedExComprehensiveRateQuoteService;
use App\Services\Carriers\FedEx\Operations\FedExServiceAvailabilityService;
use App\Services\Carriers\FedEx\Operations\FedExFreightLtlService;
use App\Services\Carriers\FedEx\Operations\FedExConsolidationService;
use App\Services\Carriers\FedEx\Operations\FedExShipValidationService;
use App\Services\Carriers\FedEx\Operations\FedExTradeDocumentsUploadService;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Validation\FedExShipFixtureResolver;
use App\Services\Carriers\FedEx\Validation\FedExShipTestCaseFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExUs09OperatorService;
use App\Services\Carriers\FedEx\Validation\FedExValidationAuthorizationEvidenceService;
use App\Services\Carriers\FedEx\Validation\FedExValidationMfaEvidenceService;
use App\Services\Carriers\FedEx\Validation\FedExValidationScopeService;
use App\Services\Carriers\FedEx\Validation\FedExValidationSwedenPassthroughService;
use App\Services\Carriers\FedEx\Validation\FedExValidationSwedenPassthroughSupport;
use App\Services\SecurityLogRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FedExValidationRunController extends Controller
{
    use ResolvesFedExValidationAccount;

    public function beginEulaValidationReview(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExIntegratorRegistrationOrchestrator $orchestrator,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);

        $session = $orchestrator->beginValidationEulaReview(
            $account->store,
            $request->user(),
            $account,
        );

        $securityLogRecorder->record($request, 'shipping.fedex_validation_eula_review_started', store: $account->store, metadata: [
            'carrier_account_id' => $account->id,
            'registration_session_id' => $session->id,
        ]);

        return redirect()->route('settings.shipping.fedex-integrator.eula', $session);
    }

    public function runAuthorizationEvidence(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExValidationAuthorizationEvidenceService $authorizationEvidenceService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);

        $outcome = $authorizationEvidenceService->runBoth($account);

        $securityLogRecorder->record(
            $request,
            'shipping.fedex_validation_run_authorization',
            store: $account->store,
            metadata: [
                'carrier_account_id' => $account->id,
                'blocked' => $outcome['blocked'],
                'parent_success' => $outcome['parent'] ? $outcome['parent']['result']->success : null,
                'child_success' => $outcome['child'] ? $outcome['child']['result']->success : null,
                'parent_event_id' => $outcome['parent']['event']->id ?? null,
                'child_event_id' => $outcome['child']['event']->id ?? null,
            ],
        );

        $message = (string) ($outcome['message'] ?? 'Authorization evidence run completed.');
        $parentEventId = $outcome['parent']['event']->id ?? null;
        $childEventId = $outcome['child']['event']->id ?? null;

        if ($parentEventId) {
            $message .= ' Parent evidence event #'.$parentEventId.'.';
        }

        if ($childEventId) {
            $message .= ' Child evidence event #'.$childEventId.'.';
        }

        $success = ! ($outcome['blocked'] ?? false)
            && ($outcome['parent']['result']->success ?? false)
            && ($outcome['child']['result']->success ?? false);

        return redirect()
            ->route('settings.shipping.carrier-accounts.fedex.validation', $account)
            ->with($success ? 'success' : 'error', $message);
    }

    public function runSwedenPassthrough(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExValidationSwedenPassthroughService $swedenPassthroughService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);

        $outcome = $swedenPassthroughService->run($account->store, $account);

        $securityLogRecorder->record(
            $request,
            'shipping.fedex_validation_run_sweden_passthrough',
            store: $account->store,
            metadata: [
                'carrier_account_id' => $account->id,
                'validation_run_id' => $outcome['validation_run_id'] ?? null,
                'address_event_id' => $outcome['address_event']?->id,
                'child_authorization_event_id' => $outcome['child_event']?->id,
                'success' => $outcome['success'] ?? false,
                'failure_code' => $outcome['failure_code'] ?? null,
                'account_last4' => data_get($outcome['address_event']?->request_summary, 'account_last4'),
                'country_code' => 'SE',
            ],
        );

        if ($outcome['blocked'] ?? false) {
            return redirect()
                ->route('settings.shipping.carrier-accounts.fedex.validation', $account)
                ->with('error', (string) ($outcome['public_message'] ?? FedExValidationSwedenPassthroughSupport::FAILURE_MESSAGE));
        }

        if ($outcome['success'] ?? false) {
            $message = (string) ($outcome['public_message'] ?? 'Sweden MFA passthrough validation completed successfully.');
            if ($outcome['address_event']?->id) {
                $message .= ' Address evidence event #'.$outcome['address_event']->id.'.';
            }
            if ($outcome['child_event']?->id) {
                $message .= ' Child authorization evidence event #'.$outcome['child_event']->id.'.';
            }

            return redirect()
                ->route('settings.shipping.carrier-accounts.fedex.validation', $account)
                ->with('success', $message);
        }

        return redirect()
            ->route('settings.shipping.carrier-accounts.fedex.validation', $account)
            ->with('error', FedExValidationSwedenPassthroughSupport::FAILURE_MESSAGE);
    }

    public function runAddressValidation(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExAddressValidationService $addressValidationService,
        FedExShipTestCaseFixtureService $fixtureService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        $fixture = $fixtureService->fixture('IntegratorUS02');
        $recipient = $fixture['recipient'] ?? [];

        ['result' => $result] = $addressValidationService->validateAddress($account->store, $account, [
            'address_line1' => $recipient['street_lines'][0] ?? '20 FedEx Pkwy',
            'city' => $recipient['city'] ?? 'Collierville',
            'state' => $recipient['state'] ?? 'TN',
            'postal_code' => $recipient['postal_code'] ?? '38017',
            'country_code' => $recipient['country_code'] ?? 'US',
            'residential' => (bool) ($recipient['residential'] ?? false),
        ]);

        $securityLogRecorder->record($request, 'shipping.fedex_validation_run_address', store: $account->store, metadata: [
            'carrier_account_id' => $account->id,
            'success' => $result->success,
            'http_status' => data_get($result->responseSummary, 'http_status'),
        ]);

        return $this->redirectWithRunResult($account, 'Address validation', $result);
    }

    public function runServiceAvailability(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExServiceAvailabilityService $serviceAvailabilityService,
        FedExShipTestCaseFixtureService $fixtureService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        $origin = $this->resolveOriginLocation($account);
        $fixture = $fixtureService->fixture('IntegratorUS02');
        $recipient = $fixture['recipient'] ?? [];

        ['result' => $result] = $serviceAvailabilityService->checkAvailability(
            store: $account->store,
            account: $account,
            originLocation: $origin,
            destinationInput: [
                'country_code' => $recipient['country_code'] ?? 'US',
                'postal_code' => $recipient['postal_code'] ?? '38017',
                'state' => $recipient['state'] ?? 'TN',
                'city' => $recipient['city'] ?? 'Collierville',
            ],
            shipDate: now()->toDateString(),
            packagingType: $fixture['packaging_type'] ?? 'YOUR_PACKAGING',
        );

        $securityLogRecorder->record($request, 'shipping.fedex_validation_run_service_availability', store: $account->store, metadata: [
            'carrier_account_id' => $account->id,
            'success' => $result->success,
            'http_status' => data_get($result->responseSummary, 'http_status'),
        ]);

        return $this->redirectWithRunResult($account, 'Service availability', $result);
    }

    public function runRateQuote(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExComprehensiveRateQuoteService $comprehensiveRateQuoteService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        return $this->runComprehensiveRateQuote(
            $request,
            $carrierAccount,
            $config,
            $comprehensiveRateQuoteService,
            $securityLogRecorder,
        );
    }

    public function runComprehensiveRateQuote(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExComprehensiveRateQuoteService $comprehensiveRateQuoteService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);

        $result = $comprehensiveRateQuoteService->quote(
            store: $account->store,
            account: $account,
        );

        $securityLogRecorder->record($request, 'shipping.fedex_validation_run_comprehensive_rate_quote', store: $account->store, metadata: [
            'carrier_account_id' => $account->id,
            'success' => $result->successful,
            'http_status' => $result->httpStatus,
            'access_state' => $result->accessState,
            'event_id' => $result->eventId,
        ]);

        $message = match ($result->accessState) {
            FedExComprehensiveRateAccessClassifier::STATE_BLOCKED_ENTITLEMENT,
            FedExComprehensiveRateAccessClassifier::STATE_BLOCKED_ACCESS => 'Comprehensive Rates access is blocked by FedEx. Review the sanitized response evidence in the workspace.',
            default => $result->successful
                ? 'Comprehensive rate quote completed successfully.'
                : 'Comprehensive rate quote did not complete successfully. Review the workspace evidence.',
        };

        return redirect()
            ->route('settings.shipping.carrier-accounts.fedex.validation', $account)
            ->with($result->successful ? 'success' : 'warning', $message);
    }

    public function runLockedShipLabel(
        Request $request,
        CarrierAccount $carrierAccount,
        string $testCaseKey,
        FedExConfig $config,
        FedExShipValidationService $shipValidationService,
        FedExShipTestCaseFixtureService $fixtureService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        abort_unless(in_array($testCaseKey, $fixtureService->testCaseKeys(), true), 404);
        abort_if(
            $testCaseKey === 'IntegratorUS08',
            422,
            'IntegratorUS08 must use the dedicated Freight LTL validation route.',
        );
        abort_if(
            in_array($testCaseKey, ['IntegratorUS09_IMAGE', 'IntegratorUS09_DOCUMENT'], true),
            422,
            'IntegratorUS09 must use the dedicated US09 ETD validation routes.',
        );

        $lockedFormat = $fixtureService->lockedLabelFormat($testCaseKey);

        if ($request->filled('label_format') && strtoupper((string) $request->input('label_format')) !== strtoupper($lockedFormat)) {
            abort(422, 'This validation scenario requires '.$lockedFormat.' labels. Arbitrary format pairing is not allowed.');
        }

        ['result' => $result, 'artifacts' => $artifacts] = $shipValidationService->createSandboxLabel(
            store: $account->store,
            account: $account,
            testCaseKey: $testCaseKey,
            labelFormat: $lockedFormat,
            actor: $request->user(),
        );

        $securityLogRecorder->record($request, 'shipping.fedex_validation_run_ship_label', store: $account->store, metadata: [
            'carrier_account_id' => $account->id,
            'test_case_key' => $testCaseKey,
            'label_format' => $lockedFormat,
            'success' => $result->success,
            'artifact_count' => count($artifacts),
        ]);

        return $this->redirectWithRunResult($account, $testCaseKey.' locked ship label ('.$lockedFormat.')', $result);
    }

    public function runFreightUs08(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExFreightLtlService $freightLtlService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        abort_unless($account->isSandbox(), 422, 'IntegratorUS08 Freight validation is sandbox-only.');
        abort_unless(
            $config->freightLtlApiEnabled(),
            422,
            $config->freightLtlApiDisabledMessage(),
        );
        abort_unless(
            $config->validationUs08Enabled(),
            422,
            'IntegratorUS08 Freight LTL validation is disabled.',
        );

        $request->validate([
            'confirm_freight_creation' => ['required', 'accepted'],
        ], [
            'confirm_freight_creation.required' => 'Confirm that this creates a sandbox Freight shipment.',
            'confirm_freight_creation.accepted' => 'Confirm that this creates a sandbox Freight shipment.',
        ]);

        $outcome = $freightLtlService->createShipment(
            store: $account->store,
            account: $account,
            testCaseKey: 'IntegratorUS08',
            actor: $request->user(),
        );

        /** @var \App\Services\Carriers\Core\DTO\CarrierApiResult $result */
        $result = $outcome['result'];
        $evidenceReady = (bool) ($outcome['evidence_ready'] ?? false);
        $evidenceReasons = array_values($outcome['evidence_reasons'] ?? []);

        $securityLogRecorder->record($request, 'shipping.fedex_validation_run_freight_us08', store: $account->store, metadata: [
            'carrier_account_id' => $account->id,
            'test_case_key' => 'IntegratorUS08',
            'label_format' => 'ZPLII',
            'api_family' => 'freight_ltl',
            'success' => $result->success,
            'evidence_ready' => $evidenceReady,
            'evidence_reasons' => $evidenceReasons,
            'label_artifact_count' => count($outcome['label_artifacts'] ?? []),
            'document_artifact_count' => count($outcome['document_artifacts'] ?? []),
        ]);

        if (! $result->success) {
            $code = is_string($result->errorCode) && $result->errorCode !== '' ? $result->errorCode : null;
            $httpStatus = data_get($result->responseSummary, 'http_status');
            $fedexErrors = collect((array) data_get($result->responseSummary, 'errors', []))
                ->map(static function (mixed $error): ?string {
                    if (! is_array($error)) {
                        return null;
                    }
                    $parts = array_filter([
                        is_string($error['code'] ?? null) ? (string) $error['code'] : null,
                        is_string($error['message'] ?? null) ? (string) $error['message'] : null,
                    ]);

                    return $parts !== [] ? implode(' — ', $parts) : null;
                })
                ->filter()
                ->values()
                ->all();

            $message = 'IntegratorUS08 Freight LTL shipment did not pass.';
            if ($httpStatus) {
                $message .= ' HTTP '.$httpStatus.'.';
            }
            if ($code) {
                $message .= ' Code '.$code.'.';
            }
            if ($result->errorMessage) {
                $message .= ' '.$result->errorMessage;
            } elseif ($fedexErrors !== []) {
                $message .= ' '.implode(' | ', $fedexErrors);
            }

            $eventId = data_get($result->responseSummary, 'carrier_api_event_id');
            if ($eventId) {
                $message .= ' Evidence event #'.$eventId.' recorded.';
            }

            if ((int) $httpStatus === 403 || $code === 'FORBIDDEN.ERROR' || $code === 'fedex_authorization_blocked') {
                $message .= ' This is an exact FedEx authorization response — no local code change can bypass it. Confirm Freight LTL project/API entitlement and that FEDEX_VALIDATION_US08_FREIGHT_ACCOUNT is linked to the connected Integrator child credentials.';
            }

            return redirect()
                ->route('settings.shipping.carrier-accounts.fedex.validation', $account)
                ->with('error', $message);
        }

        if ($evidenceReady) {
            return $this->redirectWithRunResult($account, 'IntegratorUS08 Freight LTL shipment', $result);
        }

        $eventId = data_get($result->responseSummary, 'carrier_api_event_id');
        $reasons = $evidenceReasons !== [] ? ' Reasons: '.implode(', ', $evidenceReasons).'.' : '';
        $message = 'FedEx created the sandbox Freight shipment, but the required validation evidence was incomplete. Do not retry blindly.'.$reasons
            .($eventId ? ' Review evidence event #'.$eventId.'.' : '');

        return redirect()
            ->route('settings.shipping.carrier-accounts.fedex.validation', $account)
            ->with('warning', $message);
    }

    public function runUs09UploadLetterhead(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExUs09OperatorService $operatorService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        return $this->runUs09Upload(
            $request,
            $carrierAccount,
            $config,
            $operatorService,
            $securityLogRecorder,
            'letterhead',
            'IntegratorUS09 letterhead image upload',
            'shipping.fedex_validation_run_us09_upload_letterhead',
        );
    }

    public function runUs09UploadSignature(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExUs09OperatorService $operatorService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        return $this->runUs09Upload(
            $request,
            $carrierAccount,
            $config,
            $operatorService,
            $securityLogRecorder,
            'signature',
            'IntegratorUS09 signature image upload',
            'shipping.fedex_validation_run_us09_upload_signature',
        );
    }

    public function runUs09UploadDocument(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExUs09OperatorService $operatorService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        return $this->runUs09Upload(
            $request,
            $carrierAccount,
            $config,
            $operatorService,
            $securityLogRecorder,
            'document',
            'IntegratorUS09 commercial invoice upload',
            'shipping.fedex_validation_run_us09_upload_document',
        );
    }

    public function runUs09ShipImage(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExUs09OperatorService $operatorService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        return $this->runUs09Ship(
            $request,
            $carrierAccount,
            $config,
            $operatorService,
            $securityLogRecorder,
            'image',
            'IntegratorUS09 image ETD shipment',
            'shipping.fedex_validation_run_us09_ship_image',
        );
    }

    public function runUs09ShipDocument(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExUs09OperatorService $operatorService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        return $this->runUs09Ship(
            $request,
            $carrierAccount,
            $config,
            $operatorService,
            $securityLogRecorder,
            'document',
            'IntegratorUS09 document ETD shipment',
            'shipping.fedex_validation_run_us09_ship_document',
        );
    }

    public function runUs10Consolidation(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExConsolidationService $consolidationService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        abort_unless($account->isSandbox(), 422, 'IntegratorUS10 Consolidation validation is sandbox-only.');
        abort_unless($config->us10LiveRunEnabled(), 422, $config->us10ExclusionNote());

        $request->validate([
            'confirm_us10_consolidation' => ['required', 'accepted'],
        ], [
            'confirm_us10_consolidation.required' => 'Confirm that this creates a sandbox Consolidation / IPD workflow.',
            'confirm_us10_consolidation.accepted' => 'Confirm that this creates a sandbox Consolidation / IPD workflow.',
        ]);

        $outcome = $consolidationService->execute(
            store: $account->store,
            account: $account,
            allowLive: true,
            actor: $request->user(),
        );

        $success = (bool) ($outcome['success'] ?? false);
        $evidenceReady = (bool) ($outcome['evidence_ready'] ?? false);
        $evidenceReasons = array_values($outcome['evidence_reasons'] ?? []);

        $securityLogRecorder->record($request, 'shipping.fedex_validation_run_us10_consolidation', store: $account->store, metadata: [
            'carrier_account_id' => $account->id,
            'test_case_key' => 'IntegratorUS10',
            'api_family' => 'consolidation',
            'success' => $success,
            'evidence_ready' => $evidenceReady,
            'evidence_reasons' => $evidenceReasons,
            'completed_steps' => $outcome['completed_steps'] ?? [],
            'failed_step' => $outcome['failed_step'] ?? null,
            'label_count' => (int) ($outcome['label_count'] ?? 0),
            'document_count' => (int) ($outcome['document_count'] ?? 0),
            'event_ids' => $outcome['event_ids'] ?? [],
        ]);

        if (! $success) {
            $failedStep = (string) ($outcome['failed_step'] ?? ($outcome['halted_reason'] ?? 'unknown'));
            $detail = '';
            $eventIds = array_values(array_filter($outcome['event_ids'] ?? []));
            $lastEventId = $eventIds !== [] ? (int) end($eventIds) : null;
            if ($lastEventId > 0) {
                $failedEvent = CarrierApiEvent::query()->find($lastEventId);
                if ($failedEvent !== null) {
                    $httpStatus = is_numeric($failedEvent->http_status) ? (int) $failedEvent->http_status : null;
                    $errorCode = data_get($failedEvent->response_summary, 'errors.0.code')
                        ?? data_get($failedEvent->response_body_encrypted, 'errors.0.code');
                    $errorMessage = $failedEvent->error_message
                        ?? data_get($failedEvent->response_summary, 'errors.0.message')
                        ?? data_get($failedEvent->response_body_encrypted, 'errors.0.message');
                    $parts = array_filter([
                        $httpStatus !== null ? 'HTTP '.$httpStatus : null,
                        is_string($errorCode) && $errorCode !== '' ? $errorCode : null,
                        is_string($errorMessage) && $errorMessage !== '' ? trim($errorMessage) : null,
                    ]);
                    if ($parts !== []) {
                        $detail = ' '.implode(' · ', $parts);
                        if (strlen($detail) > 240) {
                            $detail = substr($detail, 0, 237).'...';
                        }
                    }
                }
            }

            return redirect()
                ->route('settings.shipping.carrier-accounts.fedex.validation', $account)
                ->with('error', 'IntegratorUS10 Consolidation did not complete. Stopped at '.$failedStep.'.'.$detail.' Do not retry blindly.');
        }

        if ($evidenceReady) {
            return redirect()
                ->route('settings.shipping.carrier-accounts.fedex.validation', $account)
                ->with('success', 'IntegratorUS10 Consolidation completed successfully.');
        }

        return redirect()
            ->route('settings.shipping.carrier-accounts.fedex.validation', $account)
            ->with('warning', 'FedEx completed the sandbox Consolidation chain, but the required validation evidence was incomplete. Do not retry blindly.');
    }

    private function runUs09Upload(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExUs09OperatorService $operatorService,
        SecurityLogRecorder $securityLogRecorder,
        string $role,
        string $label,
        string $securityAction,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        abort_unless($account->isSandbox(), 422, 'IntegratorUS09 Trade Documents upload is sandbox-only.');

        $request->validate([
            'confirm_us09_upload' => ['required', 'accepted'],
        ], [
            'confirm_us09_upload.required' => 'Confirm that this uploads a sandbox Trade Document.',
            'confirm_us09_upload.accepted' => 'Confirm that this uploads a sandbox Trade Document.',
        ]);

        $outcome = match ($role) {
            'letterhead' => $operatorService->uploadLetterhead($account->store, $account),
            'signature' => $operatorService->uploadSignature($account->store, $account),
            default => $operatorService->uploadDocument($account->store, $account),
        };

        /** @var CarrierApiResult $result */
        $result = $outcome['result'];

        $securityLogRecorder->record($request, $securityAction, store: $account->store, metadata: [
            'carrier_account_id' => $account->id,
            'upload_role' => $role,
            'success' => $result->success,
            'scenario_key' => data_get($outcome, 'prepared.scenario_key'),
            'returned_image_index' => $outcome['returned_image_index'] ?? null,
            'event_id' => data_get($result->responseSummary, 'carrier_api_event_id')
                ?? $outcome['event']?->id,
        ]);

        return $this->redirectWithRunResult($account, $label, $result);
    }

    private function runUs09Ship(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExUs09OperatorService $operatorService,
        SecurityLogRecorder $securityLogRecorder,
        string $mode,
        string $label,
        string $securityAction,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        abort_unless($account->isSandbox(), 422, 'IntegratorUS09 ETD ship validation is sandbox-only.');

        $request->validate([
            'confirm_us09_ship' => ['required', 'accepted'],
        ], [
            'confirm_us09_ship.required' => 'Confirm that this creates a sandbox ETD shipment.',
            'confirm_us09_ship.accepted' => 'Confirm that this creates a sandbox ETD shipment.',
        ]);

        $outcome = $mode === 'image'
            ? $operatorService->createImageShipment($account->store, $account, $request->user())
            : $operatorService->createDocumentShipment($account->store, $account, $request->user());

        /** @var CarrierApiResult $result */
        $result = $outcome['result'];
        $evidenceReady = (bool) ($outcome['evidence_ready'] ?? false);
        $evidenceReasons = array_values($outcome['evidence_reasons'] ?? []);

        $securityLogRecorder->record($request, $securityAction, store: $account->store, metadata: [
            'carrier_account_id' => $account->id,
            'test_case_key' => $mode === 'image' ? 'IntegratorUS09_IMAGE' : 'IntegratorUS09_DOCUMENT',
            'label_format' => 'PDF',
            'api_family' => 'parcel_etd',
            'success' => $result->success,
            'evidence_ready' => $evidenceReady,
            'evidence_reasons' => $evidenceReasons,
            'artifact_count' => count($outcome['artifacts'] ?? []),
        ]);

        if (! $result->success) {
            return $this->redirectWithRunResult($account, $label, $result);
        }

        if ($evidenceReady) {
            return $this->redirectWithRunResult($account, $label, $result);
        }

        $eventId = data_get($result->responseSummary, 'carrier_api_event_id');
        $message = 'FedEx created the sandbox ETD shipment, but the required validation evidence was incomplete. Do not retry blindly.'
            .($eventId ? ' Review evidence event #'.$eventId.'.' : '');

        return redirect()
            ->route('settings.shipping.carrier-accounts.fedex.validation', $account)
            ->with('warning', $message);
    }

    public function runGlobalShipCase(
        Request $request,
        CarrierAccount $carrierAccount,
        string $region,
        string $caseKey,
        FedExConfig $config,
        FedExShipValidationService $shipValidationService,
        FedExShipFixtureResolver $fixtureResolver,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        $region = strtoupper($region);
        $caseKey = trim($caseKey);

        abort_unless(
            in_array($caseKey, $fixtureResolver->testCaseKeysForRegion($region), true),
            422,
            'Unknown FedEx global ship case for this region.',
        );

        abort_if(
            $caseKey === 'IntegratorCA06',
            422,
            'IntegratorCA06 is a Rate-only workbook case and is excluded from Ship validation.',
        );

        $lockedFormat = $fixtureResolver->lockedLabelFormat($caseKey);

        ['result' => $result, 'artifacts' => $artifacts] = $shipValidationService->createSandboxLabel(
            store: $account->store,
            account: $account,
            testCaseKey: $caseKey,
            labelFormat: $lockedFormat,
            actor: $request->user(),
        );

        $securityLogRecorder->record($request, 'shipping.fedex_validation_run_global_ship_label', store: $account->store, metadata: [
            'carrier_account_id' => $account->id,
            'validation_region' => $region,
            'test_case_key' => $caseKey,
            'label_format' => $lockedFormat,
            'success' => $result->success,
            'artifact_count' => count($artifacts),
        ]);

        return $this->redirectWithRunResult($account, $caseKey.' '.$region.' ship label ('.$lockedFormat.')', $result);
    }

    public function runTracking(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExBasicIntegratedVisibilityService $trackingService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);

        $validated = $request->validate([
            'tracking_number' => ['required', 'string', 'max:64'],
        ]);

        abort_unless(
            filled($config->basicIntegratedVisibilityPath()),
            422,
            'FedEx tracking path is not configured for this environment.',
        );

        ['result' => $result] = $trackingService->trackShipment(
            $account->store,
            $account,
            $validated['tracking_number'],
        );

        $securityLogRecorder->record($request, 'shipping.fedex_validation_run_tracking', store: $account->store, metadata: [
            'carrier_account_id' => $account->id,
            'success' => $result->success,
            'http_status' => data_get($result->responseSummary, 'http_status'),
        ]);

        return $this->redirectWithRunResult($account, 'Basic Integrated Visibility / Tracking', $result);
    }

    public function runShipCancel(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExShipValidationService $shipValidationService,
        FedExValidationScopeService $scopeService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);

        abort_unless(
            $scopeService->shipCancelRequired(),
            422,
            'Shipment cancellation is not selected in the current validation scope.',
        );

        $validated = $request->validate([
            'tracking_number' => ['required', 'string', 'max:64'],
        ]);

        ['result' => $result] = $shipValidationService->cancelShipment(
            $account->store,
            $account,
            $validated['tracking_number'],
        );

        $securityLogRecorder->record($request, 'shipping.fedex_validation_run_ship_cancel', store: $account->store, metadata: [
            'carrier_account_id' => $account->id,
            'success' => $result->success,
            'http_status' => data_get($result->responseSummary, 'http_status'),
        ]);

        return $this->redirectWithRunResult($account, 'Shipment cancellation', $result);
    }

    public function runTradeDocuments(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExTradeDocumentsUploadService $tradeDocumentsService,
        FedExValidationScopeService $scopeService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);

        abort_unless(
            $scopeService->tradeDocumentsRequired(),
            422,
            'Trade Documents is not selected in the current validation scope.',
        );

        abort_unless(
            filled($config->tradeDocumentsUploadPath()),
            422,
            'FedEx Trade Documents upload path is not configured for this environment.',
        );

        $validated = $request->validate([
            'tracking_number' => ['required', 'string', 'max:64'],
        ]);

        ['result' => $result] = $tradeDocumentsService->uploadTradeDocument(
            $account->store,
            $account,
            $validated['tracking_number'],
        );

        $securityLogRecorder->record($request, 'shipping.fedex_validation_run_trade_documents', store: $account->store, metadata: [
            'carrier_account_id' => $account->id,
            'success' => $result->success,
            'http_status' => data_get($result->responseSummary, 'http_status'),
        ]);

        return $this->redirectWithRunResult($account, 'Trade Documents upload', $result);
    }

    public function runInvoiceValidation(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExValidationMfaEvidenceService $mfaEvidenceService,
        FedExShipTestCaseFixtureService $fixtureService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);

        $validated = $request->validate([
            'invoice_number' => ['required', 'string', 'max:64'],
            'invoice_date' => ['required', 'date'],
            'invoice_currency' => ['nullable', 'string', 'size:3'],
            'invoice_amount' => ['required', 'string', 'max:32'],
        ]);

        $result = $mfaEvidenceService->runInvoiceValidation($account, $validated);

        $securityLogRecorder->record($request, 'shipping.fedex_validation_run_mfa_invoice', store: $account->store, metadata: [
            'carrier_account_id' => $account->id,
            'success' => $result->success,
            'http_status' => data_get($result->responseSummary, 'http_status'),
        ]);

        return $this->redirectWithRunResult($account, 'Invoice validation', $result);
    }

    public function runRegistrationAddressValidation(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExValidationMfaEvidenceService $mfaEvidenceService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);

        $result = $mfaEvidenceService->runRegistrationAddressValidation($account);
        $success = $mfaEvidenceService->registrationAddressResultIsEvidenceSuccess($result);

        $securityLogRecorder->record($request, 'shipping.fedex_validation_run_registration_address', store: $account->store, metadata: [
            'carrier_account_id' => $account->id,
            'success' => $success,
            'http_status' => data_get($result->responseSummary, 'http_status'),
            'event_id' => data_get($result->responseSummary, 'carrier_api_event_id'),
            'error_code' => $result->errorCode,
        ]);

        $httpStatus = data_get($result->responseSummary, 'http_status');
        $eventId = data_get($result->responseSummary, 'carrier_api_event_id');
        $message = $success
            ? 'Registration address validation completed successfully. FedEx accepted the address and returned verification options.'.($httpStatus ? ' HTTP '.$httpStatus.'.' : '')
            : 'Registration address validation did not pass.'.($result->errorMessage ? ' '.$result->errorMessage : '');

        if ($eventId) {
            $message .= ' Evidence event #'.$eventId.' recorded.';
        }

        return redirect()
            ->route('settings.shipping.carrier-accounts.fedex.validation', $account)
            ->with($success ? 'success' : 'error', $message);
    }

    public function runPinGeneration(
        Request $request,
        CarrierAccount $carrierAccount,
        string $method,
        FedExConfig $config,
        FedExValidationMfaEvidenceService $mfaEvidenceService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);

        $result = $mfaEvidenceService->runPinGeneration($account, $method);

        $securityLogRecorder->record($request, 'shipping.fedex_validation_run_mfa_pin_generation', store: $account->store, metadata: [
            'carrier_account_id' => $account->id,
            'mfa_method' => strtolower($method),
            'success' => $result->success,
            'http_status' => data_get($result->responseSummary, 'http_status'),
            'event_id' => data_get($result->responseSummary, 'carrier_api_event_id'),
        ]);

        $label = strtolower($method) === 'call' ? 'Phone-call PIN generation' : 'Email PIN generation';

        return $this->redirectWithRunResult($account, $label, $result);
    }

    public function runPinValidation(
        Request $request,
        CarrierAccount $carrierAccount,
        string $method,
        FedExConfig $config,
        FedExValidationMfaEvidenceService $mfaEvidenceService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);

        $validated = $request->validate([
            'pin' => ['required', 'string', 'min:4', 'max:12'],
        ]);

        $result = $mfaEvidenceService->runPinValidation($account, $method, $validated['pin']);

        $securityLogRecorder->record($request, 'shipping.fedex_validation_run_mfa_pin_validation', store: $account->store, metadata: [
            'carrier_account_id' => $account->id,
            'mfa_method' => strtolower($method),
            'success' => $result->success,
            'http_status' => data_get($result->responseSummary, 'http_status'),
            'event_id' => data_get($result->responseSummary, 'carrier_api_event_id'),
        ]);

        $label = strtolower($method) === 'call' ? 'Phone-call PIN validation' : 'Email PIN validation';

        return $this->redirectWithRunResult($account, $label, $result);
    }

    private function resolveOriginLocation(CarrierAccount $account): Location
    {
        $locationId = $account->default_origin_location_id ?? data_get($account->settings, 'default_origin_location_id');

        return Location::query()
            ->where('store_id', $account->store_id)
            ->whereKey((int) $locationId)
            ->firstOrFail();
    }

    private function redirectWithRunResult(CarrierAccount $account, string $label, CarrierApiResult $result): RedirectResponse
    {
        $httpStatus = data_get($result->responseSummary, 'http_status');
        $eventId = data_get($result->responseSummary, 'carrier_api_event_id');
        $message = $result->success
            ? $label.' completed successfully.'.($httpStatus ? ' HTTP '.$httpStatus.'.' : '')
            : $label.' did not pass.'.($result->errorMessage ? ' '.$result->errorMessage : '');

        if ($eventId) {
            $message .= ' Evidence event #'.$eventId.' recorded.';
        }

        return redirect()
            ->route('settings.shipping.carrier-accounts.fedex.validation', $account)
            ->with($result->success ? 'success' : 'error', $message);
    }
}
