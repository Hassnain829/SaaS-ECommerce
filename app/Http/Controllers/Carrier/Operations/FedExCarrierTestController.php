<?php

namespace App\Http\Controllers\Carrier\Operations;

use App\Http\Controllers\Controller;
use App\Models\CarrierAccount;
use App\Models\Location;
use App\Services\Carriers\FedEx\Operations\FedExAddressValidationService;
use App\Services\Carriers\FedEx\Operations\FedExBasicIntegratedVisibilityService;
use App\Services\Carriers\FedEx\Operations\FedExDestinationInputValidator;
use App\Services\Carriers\FedEx\Operations\FedExRateQuoteService;
use App\Services\Carriers\FedEx\Operations\FedExServiceAvailabilityService;
use App\Services\Carriers\FedEx\Operations\FedExShipValidationService;
use App\Services\Carriers\FedEx\Presenters\FedExCarrierTestResponsePresenter;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Validation\FedExShipTestCaseFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExValidationScenarioCatalog;
use App\Services\SecurityLogRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FedExCarrierTestController extends Controller
{
    public function __construct(
        private readonly FedExCarrierTestResponsePresenter $responses,
    ) {}

    public function testAddressValidation(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExAddressValidationService $addressValidationService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $this->resolveStore($request);
        $account = $this->resolveMerchantFedExAccount($store, $carrierAccount);

        $validated = $request->validate([
            'address_line1' => ['required', 'string', 'max:120'],
            'address_line2' => ['nullable', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:80'],
            'state' => ['required', 'string', 'max:32'],
            'postal_code' => ['required', 'string', 'max:16'],
            'country_code' => ['required', 'string', 'size:2'],
            'residential' => ['nullable', 'boolean'],
        ]);

        ['result' => $result, 'presentation' => $presentation] = $addressValidationService->validateAddress(
            $store,
            $account,
            $validated,
        );

        $securityLogRecorder->record(
            $request,
            'shipping.fedex_address_validation_test',
            store: $store,
            metadata: [
                'carrier_account_id' => $account->id,
                'success' => $result->success,
                'matching_suggestions' => $presentation['matching_count'] ?? 0,
            ],
        );

        return $this->responses->redirectWithFedExTestResult(
            account: $account,
            tool: 'address_validation',
            label: 'Address check',
            result: $result,
            presentation: $presentation,
            inputSummary: [
                'requested_country' => strtoupper($validated['country_code']),
                'requested_state' => strtoupper($validated['state']),
                'requested_city' => $validated['city'],
                'requested_postal' => $validated['postal_code'],
                'address' => collect([
                    $validated['address_line1'],
                    $validated['address_line2'] ?? null,
                    $validated['city'],
                    $validated['state'],
                    $validated['postal_code'],
                    strtoupper($validated['country_code']),
                ])->filter()->implode(', '),
            ],
            resultKind: $this->responses->addressValidationResultKind($result, $presentation),
        );
    }

    public function testServiceAvailability(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExServiceAvailabilityService $serviceAvailabilityService,
        FedExDestinationInputValidator $destinationValidator,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $this->resolveStore($request);
        $account = $this->resolveMerchantFedExAccount($store, $carrierAccount);

        $validated = $request->validate([
            'origin_location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where('store_id', $store->id),
            ],
            'destination_country' => ['required', 'string', 'size:2'],
            'destination_postal_code' => ['required', 'string', 'max:16'],
            'destination_state' => ['nullable', 'string', 'max:32'],
            'destination_city' => ['nullable', 'string', 'max:80'],
            'ship_date' => ['nullable', 'date'],
            'packaging_type' => ['nullable', 'string', 'max:64'],
        ]);

        $destinationCheck = $destinationValidator->validate([
            'country_code' => $validated['destination_country'],
            'postal_code' => $validated['destination_postal_code'],
            'state' => $validated['destination_state'] ?? null,
            'city' => $validated['destination_city'] ?? null,
        ]);

        if ($destinationCheck['errors'] !== []) {
            return redirect()
                ->route('shippingAutomation', ['tab' => 'carriers'])
                ->withErrors($destinationCheck['errors'])
                ->with('error_title', 'FedEx validation tools');
        }

        $destination = $destinationCheck['normalized'];

        $originLocation = Location::query()
            ->where('store_id', $store->id)
            ->whereKey((int) $validated['origin_location_id'])
            ->firstOrFail();

        ['result' => $result, 'presentation' => $presentation] = $serviceAvailabilityService->checkAvailability(
            store: $store,
            account: $account,
            originLocation: $originLocation,
            destinationInput: [
                'country_code' => $destination['country_code'],
                'postal_code' => $destination['postal_code'],
                'state' => $destination['state'] ?? null,
                'city' => $destination['city'] ?? null,
            ],
            shipDate: $validated['ship_date'] ?? null,
            packagingType: $validated['packaging_type'] ?? null,
        );

        $securityLogRecorder->record(
            $request,
            'shipping.fedex_service_availability_test',
            store: $store,
            metadata: [
                'carrier_account_id' => $account->id,
                'origin_location_id' => $originLocation->id,
                'success' => $result->success,
                'http_status' => data_get($result->responseSummary, 'http_status'),
            ],
        );

        return $this->responses->redirectWithFedExTestResult(
            account: $account,
            tool: 'service_availability',
            label: 'Service availability check',
            result: $result,
            presentation: $presentation,
            inputSummary: $this->responses->destinationInputSummary($originLocation->name, $destination),
        );
    }

    public function testRateQuote(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExRateQuoteService $rateQuoteService,
        FedExDestinationInputValidator $destinationValidator,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $this->resolveStore($request);
        $account = $this->resolveMerchantFedExAccount($store, $carrierAccount);

        $validated = $request->validate([
            'origin_location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where('store_id', $store->id),
            ],
            'destination_country' => ['required', 'string', 'size:2'],
            'destination_postal_code' => ['required', 'string', 'max:16'],
            'destination_state' => ['nullable', 'string', 'max:32'],
            'destination_city' => ['nullable', 'string', 'max:80'],
            'weight_value' => ['required', 'numeric', 'gt:0'],
            'length' => ['required', 'numeric', 'gt:0'],
            'width' => ['required', 'numeric', 'gt:0'],
            'height' => ['required', 'numeric', 'gt:0'],
            'ship_date' => ['nullable', 'date'],
            'service_type' => ['nullable', 'string', 'max:64'],
            'packaging_type' => ['nullable', 'string', 'max:64'],
            'residential' => ['nullable', 'boolean'],
        ]);

        $destinationCheck = $destinationValidator->validate([
            'country_code' => $validated['destination_country'],
            'postal_code' => $validated['destination_postal_code'],
            'state' => $validated['destination_state'] ?? null,
            'city' => $validated['destination_city'] ?? null,
        ]);

        if ($destinationCheck['errors'] !== []) {
            return redirect()
                ->route('shippingAutomation', ['tab' => 'carriers'])
                ->withErrors($destinationCheck['errors'])
                ->with('error_title', 'FedEx validation tools');
        }

        $destination = $destinationCheck['normalized'];

        $originLocation = Location::query()
            ->where('store_id', $store->id)
            ->whereKey((int) $validated['origin_location_id'])
            ->firstOrFail();

        ['result' => $result, 'presentation' => $presentation] = $rateQuoteService->quoteRate(
            store: $store,
            account: $account,
            originLocation: $originLocation,
            destinationInput: [
                'country_code' => $destination['country_code'],
                'postal_code' => $destination['postal_code'],
                'state' => $destination['state'] ?? null,
                'city' => $destination['city'] ?? null,
            ],
            packageInput: [
                'weight' => $validated['weight_value'],
                'weight_unit' => 'LB',
                'length' => $validated['length'],
                'width' => $validated['width'],
                'height' => $validated['height'],
                'dimension_unit' => 'IN',
                'packaging_type' => $validated['packaging_type'] ?? 'YOUR_PACKAGING',
            ],
            shipDate: $validated['ship_date'] ?? null,
            serviceType: $validated['service_type'] ?? null,
            residential: array_key_exists('residential', $validated) ? (bool) $validated['residential'] : null,
            packagingType: $validated['packaging_type'] ?? null,
        );

        $securityLogRecorder->record(
            $request,
            'shipping.fedex_rate_quote_test',
            store: $store,
            metadata: [
                'carrier_account_id' => $account->id,
                'origin_location_id' => $originLocation->id,
                'success' => $result->success,
                'http_status' => data_get($result->responseSummary, 'http_status'),
                'authorization_blocked' => $result->errorCode === 'fedex_authorization_blocked',
            ],
        );

        return $this->responses->redirectWithFedExTestResult(
            account: $account,
            tool: 'rate_quote',
            label: 'Rate quote test',
            result: $result,
            presentation: $presentation,
            inputSummary: array_filter([
                'origin' => $originLocation->name,
                'destination_country' => $destination['country_code'] ?? null,
                'destination_state' => $destination['state'] ?? null,
                'destination_city' => $destination['city'] ?? null,
                'destination_postal' => $destination['postal_code'] ?? null,
                'service_type' => $validated['service_type'] ?? 'FEDEX_GROUND',
                'packaging_type' => strtoupper($validated['packaging_type'] ?? 'YOUR_PACKAGING'),
                'package' => $validated['weight_value'].' lb · '.$validated['length'].'×'.$validated['width'].'×'.$validated['height'].' in',
            ]),
        );
    }

    public function testShipValidate(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExShipValidationService $shipValidationService,
        FedExShipTestCaseFixtureService $fixtureService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $this->resolveStore($request);
        $account = $this->resolveMerchantFedExAccount($store, $carrierAccount);

        $validated = $request->validate([
            'test_case' => ['required', 'string', Rule::in($fixtureService->testCaseKeys())],
            'ship_date' => ['nullable', 'date'],
        ]);

        $overrides = array_filter([
            'ship_date' => $validated['ship_date'] ?? null,
        ]);

        ['result' => $result, 'presentation' => $presentation] = $shipValidationService->validateShipment(
            store: $store,
            account: $account,
            testCaseKey: $validated['test_case'],
            overrides: $overrides,
        );

        $securityLogRecorder->record(
            $request,
            'shipping.fedex_ship_validate_test',
            store: $store,
            metadata: [
                'carrier_account_id' => $account->id,
                'test_case' => $validated['test_case'],
                'success' => $result->success,
                'authorization_blocked' => $result->errorCode === 'fedex_authorization_blocked',
            ],
        );

        return $this->responses->redirectWithFedExTestResult(
            account: $account,
            tool: 'ship_validate',
            label: 'Ship validate test',
            result: $result,
            presentation: $presentation,
            inputSummary: [
                'test_case' => $validated['test_case'],
                'service_type' => $presentation['service_type'] ?? null,
            ],
        );
    }

    public function testShipLabel(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExShipValidationService $shipValidationService,
        FedExShipTestCaseFixtureService $fixtureService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $this->resolveStore($request);
        $account = $this->resolveMerchantFedExAccount($store, $carrierAccount);

        $validated = $request->validate([
            'test_case' => ['required', 'string', Rule::in($fixtureService->testCaseKeys())],
            'label_format' => ['nullable', 'string', Rule::in(['PDF', 'PNG', 'ZPL', 'ZPLII'])],
            'ship_date' => ['nullable', 'date'],
        ]);

        $lockedFormat = FedExValidationScenarioCatalog::lockedLabelFormat($validated['test_case']);
        $labelFormat = strtoupper(trim((string) ($validated['label_format'] ?? $lockedFormat ?? 'PDF')));
        if (app(FedExConfig::class)->validationModeEnabled() && $lockedFormat !== null) {
            $labelFormat = strtoupper($lockedFormat);
        }

        $overrides = array_filter([
            'ship_date' => $validated['ship_date'] ?? null,
        ]);

        ['result' => $result, 'presentation' => $presentation] = $shipValidationService->createSandboxLabel(
            store: $store,
            account: $account,
            testCaseKey: $validated['test_case'],
            labelFormat: $labelFormat,
            overrides: $overrides,
            actor: $request->user(),
        );

        $securityLogRecorder->record(
            $request,
            'shipping.fedex_ship_label_test',
            store: $store,
            metadata: [
                'carrier_account_id' => $account->id,
                'test_case' => $validated['test_case'],
                'label_format' => $labelFormat,
                'success' => $result->success,
                'label_saved' => $presentation['label_saved'] ?? false,
                'authorization_blocked' => $result->errorCode === 'fedex_authorization_blocked',
            ],
        );

        return $this->responses->redirectWithFedExTestResult(
            account: $account,
            tool: 'ship_label',
            label: 'Sandbox label test ('.$labelFormat.')',
            result: $result,
            presentation: $presentation,
            inputSummary: [
                'test_case' => $validated['test_case'],
                'label_format' => $labelFormat,
            ],
        );
    }

    public function testTracking(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExBasicIntegratedVisibilityService $trackingService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $this->resolveStore($request);
        $account = $this->resolveMerchantFedExAccount($store, $carrierAccount);

        $validated = $request->validate([
            'tracking_number' => ['required', 'string', 'max:32'],
        ]);

        ['result' => $result, 'presentation' => $presentation] = $trackingService->trackShipment(
            $store,
            $account,
            $validated['tracking_number'],
        );

        $securityLogRecorder->record(
            $request,
            'shipping.fedex_tracking_test',
            store: $store,
            metadata: [
                'carrier_account_id' => $account->id,
                'success' => $result->success,
            ],
        );

        return $this->responses->redirectWithFedExTestResult(
            account: $account,
            tool: 'tracking',
            label: 'Tracking / BIV',
            result: $result,
            presentation: $presentation,
            inputSummary: [
                'tracking_number_last4' => strlen($validated['tracking_number']) >= 4
                    ? substr($validated['tracking_number'], -4)
                    : null,
            ],
        );
    }

    public function cancelTestShipment(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExShipValidationService $shipValidationService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $this->resolveStore($request);
        $account = $this->resolveMerchantFedExAccount($store, $carrierAccount);

        $validated = $request->validate([
            'tracking_number' => ['required', 'string', 'max:64'],
        ]);

        ['result' => $result, 'presentation' => $presentation] = $shipValidationService->cancelShipment(
            store: $store,
            account: $account,
            trackingNumber: $validated['tracking_number'],
        );

        $securityLogRecorder->record(
            $request,
            'shipping.fedex_ship_cancel_test',
            store: $store,
            metadata: [
                'carrier_account_id' => $account->id,
                'success' => $result->success,
                'authorization_blocked' => $result->errorCode === 'fedex_authorization_blocked',
            ],
        );

        return $this->responses->redirectWithFedExTestResult(
            account: $account,
            tool: 'ship_cancel',
            label: 'Cancel test shipment',
            result: $result,
            presentation: $presentation,
            inputSummary: [
                'tracking_number_last4' => $presentation['tracking_number_last4'] ?? null,
            ],
        );
    }

    private function resolveStore(Request $request): \App\Models\Store
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        return $store;
    }

    private function resolveMerchantFedExAccount(\App\Models\Store $store, CarrierAccount $carrierAccount): CarrierAccount
    {
        abort_unless((int) $carrierAccount->store_id === (int) $store->id, 404);
        abort_unless($carrierAccount->isFedEx(), 404);
        abort_unless($carrierAccount->canUseFedExApiChecks(), 404);

        return $carrierAccount->load('store');
    }
}
