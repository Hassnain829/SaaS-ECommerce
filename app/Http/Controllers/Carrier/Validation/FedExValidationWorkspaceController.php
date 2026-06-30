<?php

namespace App\Http\Controllers\Carrier\Validation;

use App\Http\Controllers\Carrier\Validation\Concerns\ResolvesFedExValidationAccount;
use App\Http\Controllers\Controller;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Services\Carriers\FedEx\Presenters\FedExValidationStatusPresenter;
use App\Services\Carriers\FedEx\Presenters\FedExValidationWorkspaceCardPresenter;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Validation\FedExComprehensiveRateEvidenceService;
use App\Services\Carriers\FedEx\Validation\FedExHostedEulaEvidenceService;
use App\Services\Carriers\FedEx\Validation\FedExShipTestCaseFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExTestCaseFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceQueryService;
use App\Services\Carriers\FedEx\Validation\FedExValidationPreflightService;
use App\Services\Carriers\FedEx\Validation\FedExValidationScopeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FedExValidationWorkspaceController extends Controller
{
    use ResolvesFedExValidationAccount;

    public function show(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExValidationPreflightService $preflight,
        FedExValidationStatusPresenter $statusPresenter,
        FedExValidationScopeService $scopeService,
        FedExValidationWorkspaceCardPresenter $cardPresenter,
        FedExShipTestCaseFixtureService $fixtureService,
        FedExTestCaseFixtureService $testCaseFixtures,
        FedExValidationEvidenceQueryService $evidenceQuery,
        FedExHostedEulaEvidenceService $hostedEulaEvidence,
        FedExComprehensiveRateEvidenceService $comprehensiveRateEvidence,
    ): View {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        $store = $account->store;

        $assessment = $preflight->assess($store, $account);
        $swedenFixture = $testCaseFixtures->swedenMfaPassthroughAccount();
        $canonicalSwedenRun = $evidenceQuery->canonicalSwedenPassthroughRun($store, $account);
        $latestSwedenAttempt = $evidenceQuery->latestSwedenPassthroughAttempt($store, $account);

        return view('user_view.fedex_validation.workspace', [
            'selectedStore' => $store,
            'account' => $account,
            'preflight' => $assessment,
            'capabilityMatrix' => $statusPresenter->capabilityMatrix($store, $account),
            'requiredScopes' => $scopeService->resolveRequiredScopes(),
            'lockedShipScenarios' => \App\Services\Carriers\FedEx\Validation\FedExValidationScenarioCatalog::lockedShipScenarios(),
            'checksByKey' => collect($assessment['checks'] ?? [])->keyBy('key'),
            'trackingNumbers' => $this->trackingNumbersFromShipEvents($store, $account),
            'trackingConfigured' => filled($config->basicIntegratedVisibilityPath()),
            'shipCancelRequired' => $scopeService->shipCancelRequired(),
            'tradeDocumentsRequired' => $scopeService->tradeDocumentsRequired(),
            'tradeDocumentsConfigured' => filled($config->tradeDocumentsUploadPath()),
            'validationCards' => $cardPresenter->cards($store, $account, $assessment),
            'invoiceEndpointConfigured' => $config->mfaInvoiceValidationPath() !== null,
            'mfaInvoicePrefill' => $fixtureService->mfaInvoice(),
            'sandboxAccountEnding' => $this->maskedSandboxAccountEnding($account),
            'swedenPassthroughAvailable' => $swedenFixture !== null,
            'swedenAccountLast4' => $swedenFixture !== null
                ? ($swedenFixture['account_last4'] ?? substr(preg_replace('/\D+/', '', (string) ($swedenFixture['account_number'] ?? '')) ?: '9268', -4))
                : '9268',
            'swedenPassthroughStatus' => $this->swedenPassthroughStatus($canonicalSwedenRun, $latestSwedenAttempt, $assessment),
            'swedenScreenshotsUploadAllowed' => $canonicalSwedenRun !== null,
            'hostedEulaStatus' => $hostedEulaEvidence->workspaceStatus($account),
            'comprehensiveRateStatus' => $comprehensiveRateEvidence->workspaceStatus($store, $account),
        ]);
    }

    /**
     * @param  array{validation_run_id: string, address_event: CarrierApiEvent, child_authorization_event: CarrierApiEvent}|null  $canonicalRun
     * @param  array{validation_run_id: ?string, address_event: CarrierApiEvent, child_authorization_event: ?CarrierApiEvent}|null  $latestAttempt
     * @param  array<string, mixed>  $assessment
     * @return array<string, string>
     */
    private function swedenPassthroughStatus(?array $canonicalRun, ?array $latestAttempt, array $assessment): array
    {
        $attemptAddress = $latestAttempt['address_event'] ?? null;
        $attemptChild = $latestAttempt['child_authorization_event'] ?? null;
        $checks = collect($assessment['checks'] ?? [])->keyBy('key');

        $addressStatus = 'Not tested';
        if ($canonicalRun !== null) {
            $addressStatus = 'Passed';
        } elseif ($attemptAddress !== null) {
            $addressStatus = 'Failed';
        }

        $childCredentials = 'No';
        if ($canonicalRun !== null || ($attemptAddress !== null && data_get($attemptAddress->response_summary, 'child_credentials_detected'))) {
            $childCredentials = 'Yes';
        }

        $mfaStatus = 'Not tested';
        if ($canonicalRun !== null) {
            $mfaStatus = 'Bypassed';
        } elseif ($attemptAddress !== null) {
            $mfaStatus = data_get($attemptAddress->response_summary, 'mfa_detected') ? 'Unexpected' : 'Bypassed';
        }

        $childAuthStatus = 'Not tested';
        if ($canonicalRun !== null) {
            $childAuthStatus = 'Passed';
        } elseif ($attemptChild !== null) {
            $childAuthStatus = 'Failed';
        }

        $addressScreenshotPassed = ($checks->get('sweden_passthrough_address_screenshot')['status'] ?? '') === 'passed';
        $childScreenshotPassed = ($checks->get('sweden_passthrough_child_authorization_screenshot')['status'] ?? '') === 'passed';
        $screenshots = ($addressScreenshotPassed && $childScreenshotPassed) ? 'Complete' : 'Missing';

        return [
            'address_validation' => $addressStatus,
            'child_credentials_returned' => $childCredentials,
            'mfa_challenge' => $mfaStatus,
            'direct_child_authorization' => $childAuthStatus,
            'screenshots' => $screenshots,
        ];
    }

    private function maskedSandboxAccountEnding(CarrierAccount $account): string
    {
        $masked = $account->maskedAccountNumber();

        if ($masked !== '—' && str_contains($masked, '*')) {
            return $masked;
        }

        $number = (string) ($account->provider_account_number ?? '');

        return strlen($number) >= 4 ? '****'.substr($number, -4) : '****';
    }

    /**
     * @return list<string>
     */
    private function trackingNumbersFromShipEvents(\App\Models\Store $store, CarrierAccount $account): array
    {
        return CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->where('action', CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL)
            ->where('status', CarrierApiEvent::STATUS_SUCCEEDED)
            ->whereBetween('http_status', [200, 299])
            ->orderByDesc('id')
            ->get()
            ->flatMap(function (CarrierApiEvent $event): array {
                $numbers = [];
                $body = $event->response_body_encrypted ?? [];

                foreach ((array) data_get($body, 'output.transactionShipments', []) as $shipment) {
                    if (is_string($master = data_get($shipment, 'masterTrackingNumber')) && $master !== '') {
                        $numbers[] = $master;
                    }

                    foreach ((array) data_get($shipment, 'pieceResponses', []) as $piece) {
                        if (is_string($tracking = data_get($piece, 'trackingNumber')) && $tracking !== '') {
                            $numbers[] = $tracking;
                        }
                    }
                }

                return $numbers;
            })
            ->unique()
            ->values()
            ->all();
    }
}
