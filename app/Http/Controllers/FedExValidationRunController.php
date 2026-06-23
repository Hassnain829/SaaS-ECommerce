<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesFedExValidationAccount;
use App\Models\CarrierAccount;
use App\Models\Location;
use App\Services\Carriers\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\FedExAddressValidationService;
use App\Services\Carriers\FedEx\FedExBasicIntegratedVisibilityService;
use App\Services\Carriers\FedEx\FedExConfig;
use App\Services\Carriers\FedEx\FedExRateQuoteService;
use App\Services\Carriers\FedEx\FedExServiceAvailabilityService;
use App\Services\Carriers\FedEx\FedExShipTestCaseFixtureService;
use App\Services\Carriers\FedEx\FedExShipValidationService;
use App\Services\Carriers\FedEx\FedExTradeDocumentsUploadService;
use App\Services\Carriers\FedEx\FedExValidationMfaEvidenceService;
use App\Services\Carriers\FedEx\FedExValidationScopeService;
use App\Services\SecurityLogRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FedExValidationRunController extends Controller
{
    use ResolvesFedExValidationAccount;

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
        FedExRateQuoteService $rateQuoteService,
        FedExShipTestCaseFixtureService $fixtureService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        $origin = $this->resolveOriginLocation($account);
        $fixture = $fixtureService->fixture('IntegratorUS02');
        $recipient = $fixture['recipient'] ?? [];
        $package = $fixture['packages'][0] ?? [];

        ['result' => $result] = $rateQuoteService->quoteRate(
            store: $account->store,
            account: $account,
            originLocation: $origin,
            destinationInput: [
                'country_code' => $recipient['country_code'] ?? 'US',
                'postal_code' => $recipient['postal_code'] ?? '38017',
                'state' => $recipient['state'] ?? 'TN',
                'city' => $recipient['city'] ?? 'Collierville',
            ],
            packageInput: $package,
            shipDate: now()->toDateString(),
            serviceType: $fixture['service_type'] ?? 'FEDEX_GROUND',
            residential: (bool) ($recipient['residential'] ?? false),
            packagingType: $fixture['packaging_type'] ?? 'YOUR_PACKAGING',
        );

        $securityLogRecorder->record($request, 'shipping.fedex_validation_run_rate_quote', store: $account->store, metadata: [
            'carrier_account_id' => $account->id,
            'success' => $result->success,
            'http_status' => data_get($result->responseSummary, 'http_status'),
            'authorization_blocked' => $result->errorCode === 'fedex_authorization_blocked',
        ]);

        return $this->redirectWithRunResult($account, 'Comprehensive rate quote', $result);
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
