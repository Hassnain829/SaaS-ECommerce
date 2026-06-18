<?php

namespace App\Http\Controllers;

use App\Models\CarrierAccount;
use App\Models\Location;
use App\Services\Carriers\FedEx\FedExAddressValidationService;
use App\Services\Carriers\FedEx\FedExDestinationInputValidator;
use App\Services\Carriers\FedEx\FedExRateQuoteService;
use App\Services\Carriers\FedEx\FedExServiceAvailabilityService;
use App\Services\SecurityLogRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FedExCarrierTestController extends Controller
{
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

        return $this->redirectWithFedExTestResult(
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
            resultKind: $this->addressValidationResultKind($result, $presentation),
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
                ->with('error_title', 'FedEx testing tools');
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

        return $this->redirectWithFedExTestResult(
            account: $account,
            tool: 'service_availability',
            label: 'Service availability check',
            result: $result,
            presentation: $presentation,
            inputSummary: $this->destinationInputSummary($originLocation->name, $destination),
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
                ->with('error_title', 'FedEx testing tools');
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
            ],
        );

        return $this->redirectWithFedExTestResult(
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
            resultKind: $result->success ? 'success' : 'failure',
        );
    }

    /**
     * @param  array<string, mixed>  $presentation
     * @param  array<string, string>  $inputSummary
     */
    private function redirectWithFedExTestResult(
        CarrierAccount $account,
        string $tool,
        string $label,
        \App\Services\Carriers\DTO\CarrierApiResult $result,
        array $presentation,
        array $inputSummary,
        ?string $resultKind = null,
    ): RedirectResponse {
        $resultKind ??= $result->success ? 'success' : ($tool === 'service_availability' ? 'fedex_api' : 'failure');

        $redirect = redirect()
            ->route('shippingAutomation', ['tab' => 'carriers'])
            ->with('fedex_test_result', [
                'account_id' => $account->id,
                'tool' => $tool,
                'label' => $label,
                'success' => $resultKind === 'success',
                'result_kind' => $resultKind,
                'failure_kind' => $resultKind === 'fedex_api' ? 'fedex_api' : null,
                'message' => $resultKind === 'success'
                    ? $this->successMessage($tool, $presentation)
                    : ($resultKind === 'warning'
                        ? $this->warningMessage($tool, $presentation)
                        : ($result->errorMessage ?? 'FedEx request failed.')),
                'input_summary' => $inputSummary,
                'presentation' => $presentation,
                'request_summary' => $result->requestSummary,
                'response_summary' => $result->responseSummary,
                'duration_ms' => $result->durationMs,
                'fedex_transaction_id' => data_get($result->responseSummary, 'fedex_transaction_id'),
            ]);

        if ($resultKind === 'success') {
            return $redirect
                ->with('success', $this->successFlashMessage($tool))
                ->with('success_title', 'FedEx testing tools');
        }

        if ($resultKind === 'warning') {
            return $redirect
                ->with('success', $this->warningFlashMessage($tool))
                ->with('success_title', 'FedEx testing tools');
        }

        if ($tool === 'service_availability') {
            return $redirect;
        }

        return $redirect
            ->withErrors(['fedex' => $result->errorMessage ?? 'FedEx request failed.'])
            ->with('error_title', 'FedEx testing tools');
    }

    /**
     * @param  array<string, mixed>  $presentation
     */
    private function addressValidationResultKind(
        \App\Services\Carriers\DTO\CarrierApiResult $result,
        array $presentation,
    ): string {
        if (! $result->success) {
            return 'failure';
        }

        if (count($presentation['resolved_addresses'] ?? []) > 0) {
            return 'success';
        }

        return 'warning';
    }

    /**
     * @param  array<string, mixed>  $presentation
     */
    private function warningMessage(string $tool, array $presentation): string
    {
        return match ($tool) {
            'address_validation' => collect($presentation['warnings'] ?? [])->first()
                ?? 'FedEx address check connected successfully, but no country-matching resolved address was returned.',
            default => 'FedEx test completed with warnings. Review the response details below.',
        };
    }

    private function warningFlashMessage(string $tool): string
    {
        return match ($tool) {
            'address_validation' => 'FedEx address check connected, but no country-matching suggestion was returned.',
            default => 'FedEx test completed with warnings.',
        };
    }

    /**
     * @param  array<string, string|null>  $destination
     * @return array<string, string>
     */
    private function destinationInputSummary(string $originName, array $destination): array
    {
        return array_filter([
            'origin' => $originName,
            'destination_country' => $destination['country_code'] ?? null,
            'destination_state' => $destination['state'] ?? null,
            'destination_city' => $destination['city'] ?? null,
            'destination_postal' => $destination['postal_code'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $presentation
     */
    private function successMessage(string $tool, array $presentation): string
    {
        return match ($tool) {
            'address_validation' => count($presentation['resolved_addresses'] ?? []) > 0
                ? 'FedEx returned '.count($presentation['resolved_addresses']).' country-matching resolved address suggestion(s). Review before using in production.'
                : 'FedEx address check completed. Review the response details below.',
            'service_availability' => ($presentation['service_count'] ?? 0) > 0
                ? 'FedEx returned '.($presentation['service_count']).' available service option(s) for this route.'
                : 'FedEx service availability check completed. Review the response details below.',
            'rate_quote' => ($presentation['rate_count'] ?? 0) > 0
                ? 'FedEx returned '.($presentation['rate_count']).' test rate option(s). This does not create a shipment or change checkout totals.'
                : 'FedEx rate quote check completed. Review the response details below.',
            default => 'FedEx test completed.',
        };
    }

    private function successFlashMessage(string $tool): string
    {
        return match ($tool) {
            'address_validation' => 'FedEx address check completed. This is a validation suggestion only.',
            'service_availability' => 'FedEx service availability check completed.',
            'rate_quote' => 'FedEx test rate quote completed. No shipment was created and checkout totals were not changed.',
            default => 'FedEx test completed.',
        };
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
