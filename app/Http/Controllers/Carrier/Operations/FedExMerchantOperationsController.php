<?php

namespace App\Http\Controllers\Carrier\Operations;

use App\Http\Controllers\Controller;
use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Carriers\FedEx\Operations\FedExAddressValidationService;
use App\Services\Carriers\FedEx\Operations\FedExNegotiatedRateService;
use App\Services\Carriers\FedEx\Operations\FedExOperationGuard;
use App\Services\Carriers\FedEx\Operations\FedExOrderPackageSnapshotService;
use App\Services\Carriers\FedEx\Operations\FedExOrderTrackingSyncService;
use App\Services\Carriers\FedEx\Operations\FedExProductionEtdUploadService;
use App\Services\Carriers\FedEx\Operations\FedExReturnLabelService;
use App\Services\Carriers\FedEx\Operations\FedExServiceAvailabilityService;
use App\Services\Carriers\FedEx\Operations\FedExShipmentCancelService;
use App\Services\Carriers\FedEx\Operations\FedExShipmentPurchaseService;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Support\FedExShipperPhoneResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FedExMerchantOperationsController extends Controller
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExOperationGuard $guard,
        private readonly FedExShipperPhoneResolver $shipperPhoneResolver,
    ) {}

    public function validateOrderAddress(
        Request $request,
        Order $order,
        FedExAddressValidationService $addressValidation,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless((int) $order->store_id === (int) $store->id, 404);
        abort_unless($this->config->modelAEnabled(), 404);

        $account = $this->resolveAccount($store, $request);
        $shipping = $order->addresses->firstWhere('type', 'shipping') ?? $order->addresses->first();
        abort_unless($shipping, 422, 'This order does not have a shipping address to validate.');

        $validated = $request->validate([
            'address_line1' => ['nullable', 'string', 'max:100'],
            'address_line2' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:10'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'size:2'],
        ]);

        $input = [
            'address_line1' => $validated['address_line1'] ?? $shipping->address_line1,
            'address_line2' => $validated['address_line2'] ?? $shipping->address_line2,
            'city' => $validated['city'] ?? $shipping->city,
            'state' => $validated['state'] ?? ($shipping->province_code ?: $shipping->state),
            'postal_code' => $validated['postal_code'] ?? $shipping->postal_code,
            'country_code' => strtoupper((string) ($validated['country_code'] ?? $shipping->country_code ?? 'US')),
        ];

        $outcome = $addressValidation->validateAddress($store, $account, $input, enforceProductionGuard: true);

        if (! $outcome['result']->success) {
            return back()->withErrors([
                'fedex_address' => $outcome['result']->errorMessage ?? 'FedEx could not validate this address.',
            ])->withInput();
        }

        return back()->with([
            'success' => 'FedEx returned address suggestions. Review the corrected address before shipping.',
            'fedex_address_review' => [
                'order_id' => $order->id,
                'suggestions' => $outcome['suggestions'],
                'normalized' => $outcome['normalized'],
                'messages' => $outcome['presentation']['messages'] ?? [],
                'warnings' => $outcome['presentation']['warnings'] ?? [],
            ],
        ]);
    }

    public function checkOrderServiceAvailability(
        Request $request,
        Order $order,
        FedExServiceAvailabilityService $availability,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless((int) $order->store_id === (int) $store->id, 404);
        abort_unless($this->config->modelAEnabled(), 404);

        $account = $this->resolveAccount($store, $request);
        $validated = $request->validate([
            'origin_location_id' => ['required', 'integer'],
            'ship_date' => ['nullable', 'date_format:Y-m-d'],
            'packaging_type' => ['nullable', 'string', 'max:40'],
        ]);

        $origin = Location::query()
            ->where('store_id', $store->id)
            ->whereKey($validated['origin_location_id'])
            ->firstOrFail();

        $shipping = $order->addresses->firstWhere('type', 'shipping') ?? $order->addresses->first();
        abort_unless($shipping, 422, 'This order does not have a shipping address.');

        $outcome = $availability->checkAvailability(
            store: $store,
            account: $account,
            originLocation: $origin,
            destinationInput: [
                'country_code' => $shipping->country_code ?? 'US',
                'postal_code' => $shipping->postal_code,
                'state' => $shipping->province_code ?: $shipping->state,
                'city' => $shipping->city,
            ],
            shipDate: $validated['ship_date'] ?? null,
            packagingType: $validated['packaging_type'] ?? 'YOUR_PACKAGING',
            enforceProductionGuard: true,
        );

        if (! $outcome['result']->success) {
            return back()->withErrors([
                'fedex_availability' => $outcome['result']->errorMessage ?? 'FedEx could not check service availability.',
            ])->withInput();
        }

        return back()->with([
            'success' => 'FedEx service options are ready to review.',
            'fedex_service_availability' => [
                'order_id' => $order->id,
                'services' => $outcome['presentation']['services'] ?? [],
                'package_types' => $outcome['presentation']['package_types'] ?? [],
                'service_count' => $outcome['presentation']['service_count'] ?? 0,
            ],
        ]);
    }

    public function quoteOrderRates(
        Request $request,
        Order $order,
        FedExNegotiatedRateService $rates,
        FedExAddressValidationService $addressValidation,
        FedExServiceAvailabilityService $availability,
        FedExOrderPackageSnapshotService $packageSnapshots,
        \App\Services\Carriers\FedEx\Support\FedExHandoffTypeResolver $handoffResolver,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless((int) $order->store_id === (int) $store->id, 404);
        abort_unless($this->config->modelAEnabled(), 404);

        $account = $this->resolveAccount($store, $request);
        $validated = $request->validate([
            'origin_location_id' => ['required', 'integer'],
            'ship_date' => ['nullable', 'date_format:Y-m-d'],
            'residential' => ['nullable', 'boolean'],
            'pickup_type' => ['nullable', 'string', 'max:40'],
            'package_source' => ['nullable', 'in:preset,custom'],
            'shipping_package_preset_id' => ['nullable', 'integer'],
            'weight' => ['nullable', 'numeric', 'min:0.01', 'max:150'],
            'length' => ['nullable', 'numeric', 'min:1', 'max:108'],
            'width' => ['nullable', 'numeric', 'min:1', 'max:108'],
            'height' => ['nullable', 'numeric', 'min:1', 'max:108'],
            'weight_unit' => ['nullable', 'string', 'max:8'],
            'dimension_unit' => ['nullable', 'string', 'max:8'],
            'recipient_phone' => ['nullable', 'string', 'max:40'],
            'address_choice' => ['nullable', 'in:entered,suggested'],
            'suggested_address_line1' => ['nullable', 'string', 'max:100'],
            'suggested_address_line2' => ['nullable', 'string', 'max:100'],
            'suggested_city' => ['nullable', 'string', 'max:80'],
            'suggested_state' => ['nullable', 'string', 'max:10'],
            'suggested_postal_code' => ['nullable', 'string', 'max:20'],
            'suggested_country_code' => ['nullable', 'string', 'size:2'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.selected' => ['nullable', 'boolean'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
        ]);

        $selectedItems = collect($validated['items'] ?? [])
            ->filter(function (array $row): bool {
                if (array_key_exists('selected', $row) && ! filter_var($row['selected'], FILTER_VALIDATE_BOOL)) {
                    return false;
                }

                return (int) ($row['quantity'] ?? 0) > 0;
            })
            ->values()
            ->all();

        if ($selectedItems === []) {
            return back()->withErrors([
                'items' => 'Choose at least one order item before requesting FedEx options.',
            ])->withInput();
        }

        $origin = Location::query()
            ->where('store_id', $store->id)
            ->whereKey($validated['origin_location_id'])
            ->firstOrFail();

        $shipperPhone = $this->shipperPhoneResolver->resolveAndBackfill($origin, $account);
        if ($shipperPhone === '') {
            return back()->withErrors([
                'origin_location_id' => $origin->name.' needs a phone number before FedEx can create a label. Add it on the location, or include it when connecting FedEx.',
            ])->withInput();
        }

        $shipping = $order->addresses->firstWhere('type', 'shipping') ?? $order->addresses->first();
        abort_unless($shipping, 422, 'This order does not have a shipping address.');

        $recipientPhone = trim((string) ($validated['recipient_phone'] ?? $shipping->phone ?? ''));
        if ($recipientPhone === '') {
            return back()->withErrors([
                'recipient_phone' => 'Add a recipient phone number before requesting FedEx options.',
            ])->withInput();
        }

        if ($recipientPhone !== (string) ($shipping->phone ?? '')) {
            $shipping->forceFill(['phone' => $recipientPhone])->save();
        }

        $enteredDestination = [
            'address_line1' => $shipping->address_line1,
            'address_line2' => $shipping->address_line2,
            'city' => $shipping->city,
            'state' => $shipping->province_code ?: $shipping->state,
            'postal_code' => $shipping->postal_code,
            'country_code' => strtoupper((string) ($shipping->country_code ?? 'US')),
        ];

        $addressOutcome = $addressValidation->validateAddress(
            $store,
            $account,
            $enteredDestination,
            enforceProductionGuard: true,
        );

        $suggestions = is_array($addressOutcome['suggestions'] ?? null) ? $addressOutcome['suggestions'] : [];
        $primarySuggestion = is_array($suggestions[0] ?? null) ? $suggestions[0] : null;
        $addressChoice = (string) ($validated['address_choice'] ?? 'entered');

        $destination = $enteredDestination;
        if ($addressChoice === 'suggested') {
            $destination = [
                'address_line1' => $validated['suggested_address_line1']
                    ?? data_get($primarySuggestion, 'address_line1')
                    ?? $enteredDestination['address_line1'],
                'address_line2' => $validated['suggested_address_line2']
                    ?? data_get($primarySuggestion, 'address_line2')
                    ?? $enteredDestination['address_line2'],
                'city' => $validated['suggested_city']
                    ?? data_get($primarySuggestion, 'city')
                    ?? $enteredDestination['city'],
                'state' => $validated['suggested_state']
                    ?? data_get($primarySuggestion, 'state')
                    ?? $enteredDestination['state'],
                'postal_code' => $validated['suggested_postal_code']
                    ?? data_get($primarySuggestion, 'postal_code')
                    ?? $enteredDestination['postal_code'],
                'country_code' => strtoupper((string) (
                    $validated['suggested_country_code']
                    ?? data_get($primarySuggestion, 'country_code')
                    ?? $enteredDestination['country_code']
                )),
            ];
        }

        $pickupType = $handoffResolver->resolve($store, $validated['pickup_type'] ?? null);
        $package = $packageSnapshots->createFromOrderInput(
            store: $store,
            order: $order,
            originLocationId: (int) $origin->id,
            input: $validated,
            actor: $request->user(),
        );
        $packageLines = $packageSnapshots->toRatePackages($package);

        $residential = array_key_exists('residential', $validated)
            ? (bool) $validated['residential']
            : null;

        // Internal orchestration: Address Validation (above) → Service Availability → ACCOUNT rates.
        // Service Availability enhances filtering; rates remain the pricing source of truth.
        $availableServiceTypes = [];
        $availabilityChecked = false;
        try {
            $availabilityOutcome = $availability->checkAvailability(
                store: $store,
                account: $account,
                originLocation: $origin,
                destinationInput: [
                    'country_code' => $destination['country_code'] ?? 'US',
                    'postal_code' => $destination['postal_code'] ?? null,
                    'state' => $destination['state'] ?? null,
                    'city' => $destination['city'] ?? null,
                ],
                shipDate: $validated['ship_date'] ?? null,
                packagingType: $package->package_type ?: 'YOUR_PACKAGING',
                enforceProductionGuard: true,
                pickupType: $pickupType,
            );
            $availabilityChecked = (bool) ($availabilityOutcome['result']->success ?? false);
            if ($availabilityChecked) {
                $availableServiceTypes = $availabilityOutcome['service_types'] ?? [];
            }
        } catch (\Throwable) {
            $availabilityChecked = false;
            $availableServiceTypes = [];
        }

        $outcome = $rates->quoteForOriginDestination(
            store: $store,
            account: $account,
            originLocation: $origin,
            destinationInput: $destination,
            packageInput: $packageLines,
            shipDate: $validated['ship_date'] ?? null,
            serviceType: null,
            residential: $residential,
            packagingType: $package->package_type ?: 'YOUR_PACKAGING',
            orderId: $order->id,
            forCheckout: false,
            shipmentPackageId: $package->id,
            pickupType: $pickupType,
        );

        if (! $outcome['result']->successful) {
            return back()->withErrors([
                'fedex_rates' => $outcome['result']->merchantMessage
                    ?? 'FedEx could not return shipping options for this shipment.',
            ])->withInput()->with([
                'fedex_address_review' => [
                    'order_id' => $order->id,
                    'suggestions' => $suggestions,
                    'entered' => $enteredDestination,
                    'choice' => $addressChoice,
                    'messages' => $addressOutcome['presentation']['messages'] ?? [],
                    'warnings' => $addressOutcome['presentation']['warnings'] ?? [],
                ],
            ]);
        }

        $ratesList = is_array($outcome['rates'] ?? null) ? $outcome['rates'] : [];
        $quoteIds = is_array($outcome['quote_ids'] ?? null) ? $outcome['quote_ids'] : [];
        if ($availabilityChecked && $availableServiceTypes !== []) {
            $intersected = FedExServiceAvailabilityService::intersectRatesWithAvailability(
                $ratesList,
                $quoteIds,
                $availableServiceTypes,
            );
            $ratesList = $intersected['rates'];
            $quoteIds = $intersected['quote_ids'];
        }

        // Persist destination snapshot onto each quote for label purchase.
        if ($quoteIds !== []) {
            \App\Models\CarrierRateQuote::query()
                ->where('store_id', $store->id)
                ->whereIn('id', array_values(array_filter($quoteIds)))
                ->get()
                ->each(function (\App\Models\CarrierRateQuote $quote) use ($destination, $addressChoice, $pickupType, $selectedItems, $availabilityChecked, $availableServiceTypes): void {
                    $summary = is_array($quote->request_summary) ? $quote->request_summary : [];
                    $summary['destination_address'] = $destination;
                    $summary['address_choice'] = $addressChoice;
                    $summary['pickup_type'] = $pickupType;
                    $summary['selected_items'] = $selectedItems;
                    $summary['service_availability_checked'] = $availabilityChecked;
                    if ($availabilityChecked) {
                        $summary['available_service_types'] = $availableServiceTypes;
                    }
                    $quote->forceFill(['request_summary' => $summary])->save();
                });
        }

        return back()->with([
            'success' => 'FedEx shipping options are ready. Choose a service, then buy the label.',
            'fedex_address_review' => [
                'order_id' => $order->id,
                'suggestions' => $suggestions,
                'entered' => $enteredDestination,
                'choice' => $addressChoice,
                'active_destination' => $destination,
                'messages' => $addressOutcome['presentation']['messages'] ?? [],
                'warnings' => $addressOutcome['presentation']['warnings'] ?? [],
            ],
            'fedex_rate_quotes' => [
                'order_id' => $order->id,
                'shipment_package_id' => $package->id,
                'package_summary' => [
                    'name' => $package->name,
                    'weight' => (float) $package->weight_value,
                    'weight_unit' => $package->weight_unit,
                    'length' => (float) $package->length,
                    'width' => (float) $package->width,
                    'height' => (float) $package->height,
                    'dimension_unit' => $package->dimension_unit,
                ],
                'pickup_type' => $pickupType,
                'origin_location_id' => $origin->id,
                'ship_date' => $validated['ship_date'] ?? null,
                'residential' => $residential,
                'selected_items' => $selectedItems,
                'service_availability_checked' => $availabilityChecked,
                'rates' => collect($ratesList)->values()->map(function (array $rate, int $index) use ($quoteIds): array {
                    $rate['quote_id'] = $quoteIds[$index] ?? null;

                    return $rate;
                })->all(),
                'quote_ids' => $quoteIds,
                'transaction_id' => $outcome['result']->transactionId,
            ],
        ]);
    }

    public function createOrderShipment(
        Request $request,
        Order $order,
        FedExShipmentPurchaseService $purchase,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless((int) $order->store_id === (int) $store->id, 404);
        abort_unless($this->config->modelAEnabled(), 404);

        $account = $this->resolveAccount($store, $request);
        $validated = $request->validate([
            'origin_location_id' => ['required', 'integer'],
            'carrier_rate_quote_id' => ['required', 'integer'],
            'service_type' => ['nullable', 'string', 'max:40'],
            'label_format' => ['nullable', 'in:PDF,PNG,ZPL'],
            'ship_date' => ['nullable', 'date_format:Y-m-d'],
            'residential' => ['nullable', 'boolean'],
            'saturday_delivery' => ['nullable', 'boolean'],
            'signature_option' => ['nullable', 'string', 'max:40'],
            'shipping_reference' => ['nullable', 'string', 'max:40'],
            'email_notification' => ['nullable', 'email', 'max:120'],
            'declared_value_amount' => ['nullable', 'numeric', 'min:0'],
            'recipient_phone' => ['nullable', 'string', 'max:40'],
            'pickup_type' => ['nullable', 'string', 'max:40'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.selected' => ['nullable', 'boolean'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'customs_clearance' => ['nullable', 'array'],
            'customs_clearance.duties_payment_type' => ['nullable', 'string', 'max:40'],
            'customs_clearance.total_customs_value.amount' => ['nullable', 'numeric', 'min:0.01'],
            'customs_clearance.total_customs_value.currency' => ['nullable', 'string', 'size:3'],
            'customs_clearance.commercial_invoice.shipment_purpose' => ['nullable', 'string', 'max:40'],
            'customs_clearance.commodities' => ['nullable', 'array'],
            'customs_clearance.commodities.*.order_item_id' => ['nullable', 'integer'],
            'customs_clearance.commodities.*.description' => ['nullable', 'string', 'max:450'],
            'customs_clearance.commodities.*.quantity' => ['nullable', 'numeric', 'min:1'],
            'customs_clearance.commodities.*.weight' => ['nullable', 'numeric', 'min:0.01'],
            'customs_clearance.commodities.*.weight_unit' => ['nullable', 'in:LB,KG'],
            'customs_clearance.commodities.*.customs_value.amount' => ['nullable', 'numeric', 'min:0.01'],
            'customs_clearance.commodities.*.customs_value.currency' => ['nullable', 'string', 'size:3'],
            'customs_clearance.commodities.*.country_of_manufacture' => ['nullable', 'string', 'size:2'],
            'customs_clearance.commodities.*.harmonized_code' => ['nullable', 'string', 'max:18'],
            'fedex_trade_document_id' => ['nullable', 'integer'],
            'etd_document_id' => ['nullable', 'string', 'max:120'],
            'etd_enabled' => ['nullable', 'boolean'],
        ]);

        // Keep only checked / positive-quantity lines for the purchase service.
        $validated['items'] = collect($validated['items'] ?? [])
            ->filter(function (array $row): bool {
                if (array_key_exists('selected', $row) && ! filter_var($row['selected'], FILTER_VALIDATE_BOOL)) {
                    return false;
                }

                return (int) ($row['quantity'] ?? 0) > 0;
            })
            ->values()
            ->all();

        if ($validated['items'] === []) {
            return back()->withErrors([
                'items' => 'Choose at least one order item to include on the FedEx shipment.',
            ])->withInput();
        }

        $selectedItemIds = collect($validated['items'])
            ->mapWithKeys(fn (array $row): array => [(int) $row['order_item_id'] => (int) $row['quantity']])
            ->all();

        if (isset($validated['customs_clearance']['commodities']) && is_array($validated['customs_clearance']['commodities'])) {
            $validated['customs_clearance']['commodities'] = collect($validated['customs_clearance']['commodities'])
                ->filter(function ($row) use ($selectedItemIds): bool {
                    if (! is_array($row)) {
                        return false;
                    }
                    $orderItemId = (int) ($row['order_item_id'] ?? 0);

                    return $orderItemId > 0 && array_key_exists($orderItemId, $selectedItemIds);
                })
                ->map(function (array $row) use ($selectedItemIds): array {
                    $orderItemId = (int) $row['order_item_id'];
                    $row['quantity'] = $selectedItemIds[$orderItemId];

                    return $row;
                })
                ->values()
                ->all();
        }

        $origin = Location::query()
            ->where('store_id', $store->id)
            ->whereKey($validated['origin_location_id'])
            ->firstOrFail();

        $quote = \App\Models\CarrierRateQuote::query()
            ->where('store_id', $store->id)
            ->where('order_id', $order->id)
            ->whereKey($validated['carrier_rate_quote_id'])
            ->with('package')
            ->firstOrFail();

        if (! $quote->package && ! is_array(data_get($quote->request_summary, 'packages'))) {
            return back()->withErrors([
                'carrier_rate_quote_id' => 'That rate is missing package details. Get fresh FedEx shipping options.',
            ])->withInput();
        }

        $validated['service_type'] = (string) ($quote->service_code ?: ($validated['service_type'] ?? 'FEDEX_GROUND'));

        if ($quote->package) {
            $validated['packages'] = [[
                'weight' => (float) $quote->package->weight_value,
                'weight_unit' => strtoupper((string) ($quote->package->weight_unit ?: 'LB')),
                'length' => (float) $quote->package->length,
                'width' => (float) $quote->package->width,
                'height' => (float) $quote->package->height,
                'dimension_unit' => strtoupper((string) ($quote->package->dimension_unit ?: 'IN')),
            ]];
            $validated['shipment_package_id'] = $quote->package->id;
        } else {
            $validated['packages'] = data_get($quote->request_summary, 'packages');
        }

        if (! array_key_exists('pickup_type', $validated) || ! filled($validated['pickup_type'] ?? null)) {
            $validated['pickup_type'] = data_get($quote->request_summary, 'pickup_type');
        }
        if (! array_key_exists('residential', $validated) && array_key_exists('destination_residential', (array) ($quote->request_summary ?? []))) {
            $validated['residential'] = (bool) data_get($quote->request_summary, 'destination_residential');
        }
        if (! filled($validated['ship_date'] ?? null) && filled(data_get($quote->request_summary, 'ship_date'))) {
            $validated['ship_date'] = (string) data_get($quote->request_summary, 'ship_date');
        }

        $shipping = $order->addresses->firstWhere('type', 'shipping') ?? $order->addresses->first();
        $recipientPhone = trim((string) ($validated['recipient_phone'] ?? $shipping?->phone ?? ''));
        if ($recipientPhone !== '' && $shipping && $recipientPhone !== (string) ($shipping->phone ?? '')) {
            $shipping->forceFill(['phone' => $recipientPhone])->save();
        }
        $validated['recipient_phone'] = $recipientPhone;

        $outcome = $purchase->purchase(
            store: $store,
            order: $order,
            account: $account,
            origin: $origin,
            input: $validated,
            actor: $request->user(),
        );

        if ($outcome['state'] !== FedExShipmentPurchaseService::STATE_SUCCEEDED || ! $outcome['shipment']) {
            return back()->withErrors([
                'fedex_ship' => $outcome['merchant_message'],
            ])->withInput();
        }

        return back()->with([
            'success' => $outcome['merchant_message'],
            'fedex_shipment_created' => [
                'shipment_id' => $outcome['shipment']->id,
                'tracking_number' => $outcome['shipment']->tracking_number,
                'labels' => $outcome['labels'],
                'replayed' => $outcome['replayed'],
            ],
        ]);
    }

    public function createReturnLabel(
        Request $request,
        Order $order,
        FedExReturnLabelService $returns,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless((int) $order->store_id === (int) $store->id, 404);
        abort_unless($this->config->modelAEnabled(), 404);

        $account = $this->resolveAccount($store, $request);
        $validated = $request->validate([
            'order_return_id' => ['required', 'integer'],
            'origin_location_id' => ['required', 'integer'],
            'service_type' => ['nullable', 'string', 'max:40'],
            'label_format' => ['nullable', 'in:PDF,PNG,ZPL'],
            'weight' => ['nullable', 'numeric', 'min:0.01'],
            'length' => ['nullable', 'numeric', 'min:1'],
            'width' => ['nullable', 'numeric', 'min:1'],
            'height' => ['nullable', 'numeric', 'min:1'],
            'items' => ['nullable', 'array'],
            'items.*.selected' => ['nullable', 'boolean'],
            'items.*.order_item_id' => ['required_with:items', 'integer'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
        ]);

        if (isset($validated['items']) && is_array($validated['items'])) {
            $validated['items'] = collect($validated['items'])
                ->filter(function (array $row): bool {
                    if (array_key_exists('selected', $row) && ! filter_var($row['selected'], FILTER_VALIDATE_BOOL)) {
                        return false;
                    }

                    return (int) ($row['quantity'] ?? 0) > 0;
                })
                ->values()
                ->all();
        }

        $origin = Location::query()
            ->where('store_id', $store->id)
            ->whereKey($validated['origin_location_id'])
            ->firstOrFail();

        $packages = null;
        if (
            isset($validated['weight'], $validated['length'], $validated['width'], $validated['height'])
            && is_numeric($validated['weight'])
            && is_numeric($validated['length'])
            && is_numeric($validated['width'])
            && is_numeric($validated['height'])
        ) {
            $packages = [[
                'weight' => (float) $validated['weight'],
                'length' => (float) $validated['length'],
                'width' => (float) $validated['width'],
                'height' => (float) $validated['height'],
                'weight_unit' => 'LB',
                'dimension_unit' => 'IN',
            ]];
        }

        $outcome = $returns->createReturnLabel(
            store: $store,
            order: $order,
            account: $account,
            origin: $origin,
            input: array_filter(array_merge($validated, [
                'packages' => $packages,
            ]), static fn ($value) => $value !== null),
            actor: $request->user(),
        );

        if ($outcome['state'] !== FedExShipmentPurchaseService::STATE_SUCCEEDED || ! $outcome['shipment']) {
            return back()->withErrors(['fedex_return' => $outcome['merchant_message']])->withInput();
        }

        return back()->with('success', 'FedEx return label created'
            .(! empty($outcome['resolved_service_name']) ? ' · '.$outcome['resolved_service_name'] : '')
            .'.');
    }

    public function cancelShipment(
        Request $request,
        Shipment $shipment,
        FedExShipmentCancelService $cancel,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless((int) $shipment->store_id === (int) $store->id, 404);
        abort_unless($this->config->modelAEnabled(), 404);

        // Prefer the active connection; cancel service follows reconnect replacement lineage
        // when the original purchasing account was retired.
        $account = $this->guard->resolveActiveModelAAccount($store)
            ?? $this->resolveAccount($store, $request);
        $outcome = $cancel->cancel($store, $account, $shipment);

        if (! $outcome['result']->success) {
            return back()->withErrors(['fedex_cancel' => $outcome['merchant_message']]);
        }

        return back()->with('success', $outcome['merchant_message']);
    }

    public function refreshTracking(
        Request $request,
        Shipment $shipment,
        FedExOrderTrackingSyncService $sync,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless((int) $shipment->store_id === (int) $store->id, 404);
        abort_unless($this->config->modelAEnabled(), 404);

        $outcome = $sync->refreshShipment($store, $shipment);
        if (! ($outcome['result']->success ?? false)) {
            return back()->withErrors(['fedex_tracking' => $outcome['merchant_message'] ?? 'Tracking refresh failed.']);
        }

        return back()->with([
            'success' => 'FedEx tracking refreshed.',
            'fedex_tracking' => [
                'shipment_id' => $shipment->id,
                'status' => $outcome['status'] ?? null,
                'timeline' => $outcome['timeline'] ?? [],
                'estimated_delivery' => $outcome['estimated_delivery'] ?? null,
            ],
        ]);
    }

    public function downloadLabel(Request $request, Shipment $shipment): StreamedResponse|RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless((int) $shipment->store_id === (int) $store->id, 404);

        $labels = array_values((array) data_get($shipment->metadata, 'fedex.labels', []));
        $index = max(0, (int) $request->query('index', 0));
        $label = $labels[$index] ?? null;
        abort_unless(is_array($label) && filled($label['path'] ?? null), 404);

        $disk = (string) ($label['disk'] ?? 'local');
        abort_unless(Storage::disk($disk)->exists($label['path']), 404);

        $suffix = count($labels) > 1 ? '-'.($index + 1) : '';

        return Storage::disk($disk)->download(
            $label['path'],
            'fedex-label-'.$shipment->tracking_number.$suffix.'.'.strtolower((string) ($label['image_type'] ?? 'pdf')),
        );
    }

    public function uploadEtdDocument(
        Request $request,
        Order $order,
        FedExProductionEtdUploadService $etd,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless((int) $order->store_id === (int) $store->id, 404);
        abort_unless($this->config->modelAEnabled(), 404);

        $account = $this->resolveAccount($store, $request);
        $shipping = $order->addresses->firstWhere('type', 'shipping') ?? $order->addresses->first();
        $validated = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'origin_country_code' => ['nullable', 'string', 'size:2'],
            'destination_country_code' => ['required', 'string', 'size:2'],
            'shipment_id' => ['nullable', 'integer'],
        ]);

        $shipment = null;
        if (filled($validated['shipment_id'] ?? null)) {
            $shipment = Shipment::query()
                ->where('store_id', $store->id)
                ->where('order_id', $order->id)
                ->whereKey($validated['shipment_id'])
                ->firstOrFail();
        }

        $outcome = $etd->uploadCommercialInvoice(
            store: $store,
            account: $account,
            file: $validated['document'],
            meta: [
                'origin_country_code' => $validated['origin_country_code'] ?? 'US',
                'destination_country_code' => $validated['destination_country_code']
                    ?? strtoupper((string) ($shipping?->country_code ?: '')),
            ],
            order: $order,
            shipment: $shipment,
        );

        if (! $outcome['result_success']) {
            return back()->withErrors([
                'fedex_etd' => $outcome['merchant_message']
                    ?: 'We couldn\'t prepare the customs document for FedEx. Review the document and try again.',
            ]);
        }

        return back()->with([
            'success' => $outcome['merchant_message'] ?: 'Commercial Invoice Ready ✓',
            'fedex_etd_document_id' => $outcome['document_id'],
            'fedex_trade_document_id' => $outcome['document']?->id,
        ]);
    }

    private function resolveAccount($store, Request $request): CarrierAccount
    {
        $accountId = $request->input('carrier_account_id');
        if (filled($accountId)) {
            $account = CarrierAccount::query()
                ->where('store_id', $store->id)
                ->whereKey($accountId)
                ->firstOrFail();

            return $account;
        }

        $account = $this->guard->resolveActiveModelAAccount($store);
        abort_unless($account, 422, 'Connect an active FedEx account before running FedEx shipping checks.');

        return $account;
    }
}
