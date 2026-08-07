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
use App\Services\Carriers\FedEx\Operations\FedExOrderTrackingSyncService;
use App\Services\Carriers\FedEx\Operations\FedExProductionEtdUploadService;
use App\Services\Carriers\FedEx\Operations\FedExReturnLabelService;
use App\Services\Carriers\FedEx\Operations\FedExServiceAvailabilityService;
use App\Services\Carriers\FedEx\Operations\FedExShipmentCancelService;
use App\Services\Carriers\FedEx\Operations\FedExShipmentPurchaseService;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FedExMerchantOperationsController extends Controller
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExOperationGuard $guard,
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
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless((int) $order->store_id === (int) $store->id, 404);
        abort_unless($this->config->modelAEnabled(), 404);

        $account = $this->resolveAccount($store, $request);
        $validated = $request->validate([
            'origin_location_id' => ['required', 'integer'],
            'ship_date' => ['nullable', 'date_format:Y-m-d'],
            'service_type' => ['nullable', 'string', 'max:60'],
            'packaging_type' => ['nullable', 'string', 'max:40'],
            'residential' => ['nullable', 'boolean'],
            'weight' => ['nullable', 'numeric', 'min:0.01', 'max:150'],
            'length' => ['nullable', 'numeric', 'min:1', 'max:108'],
            'width' => ['nullable', 'numeric', 'min:1', 'max:108'],
            'height' => ['nullable', 'numeric', 'min:1', 'max:108'],
        ]);

        $origin = Location::query()
            ->where('store_id', $store->id)
            ->whereKey($validated['origin_location_id'])
            ->firstOrFail();

        $shipping = $order->addresses->firstWhere('type', 'shipping') ?? $order->addresses->first();
        abort_unless($shipping, 422, 'This order does not have a shipping address.');

        $defaults = (array) config('carriers.fedex.checkout_default_package', []);
        $package = [
            'weight' => $validated['weight'] ?? ($defaults['weight'] ?? 1),
            'weight_unit' => 'LB',
            'length' => $validated['length'] ?? ($defaults['length'] ?? 9),
            'width' => $validated['width'] ?? ($defaults['width'] ?? 6),
            'height' => $validated['height'] ?? ($defaults['height'] ?? 2),
            'dimension_unit' => 'IN',
        ];

        $outcome = $rates->quoteForOriginDestination(
            store: $store,
            account: $account,
            originLocation: $origin,
            destinationInput: [
                'country_code' => $shipping->country_code ?? 'US',
                'postal_code' => $shipping->postal_code,
                'state' => $shipping->province_code ?: $shipping->state,
                'city' => $shipping->city,
                'address_line1' => $shipping->address_line1,
                'address_line2' => $shipping->address_line2,
            ],
            packageInput: $package,
            shipDate: $validated['ship_date'] ?? null,
            serviceType: $validated['service_type'] ?? null,
            residential: array_key_exists('residential', $validated) ? (bool) $validated['residential'] : null,
            packagingType: $validated['packaging_type'] ?? 'YOUR_PACKAGING',
            orderId: $order->id,
            forCheckout: false,
        );

        if (! $outcome['result']->successful) {
            return back()->withErrors([
                'fedex_rates' => $outcome['result']->merchantMessage
                    ?? 'FedEx could not return negotiated rates for this shipment.',
            ])->withInput();
        }

        return back()->with([
            'success' => 'FedEx negotiated rates are ready. Choose a service before creating a label.',
            'fedex_rate_quotes' => [
                'order_id' => $order->id,
                'rates' => collect($outcome['rates'])->values()->map(function (array $rate, int $index) use ($outcome): array {
                    $rate['quote_id'] = $outcome['quote_ids'][$index] ?? null;

                    return $rate;
                })->all(),
                'quote_ids' => $outcome['quote_ids'],
                'transaction_id' => $outcome['result']->transactionId,
                'selected' => [
                    'service_type' => $outcome['result']->serviceType,
                    'service_name' => $outcome['result']->serviceName,
                    'amount' => $outcome['result']->amount,
                    'currency' => $outcome['result']->currency,
                    'rate_type' => $outcome['result']->rateType,
                    'transit_days' => $outcome['result']->transitDays,
                    'delivery_date' => $outcome['result']->deliveryDate,
                    'surcharges' => $outcome['result']->surcharges,
                    'quote_id' => $outcome['quote_ids'][0] ?? null,
                ],
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
            'weight' => ['nullable', 'numeric', 'min:0.01'],
            'length' => ['nullable', 'numeric', 'min:1'],
            'width' => ['nullable', 'numeric', 'min:1'],
            'height' => ['nullable', 'numeric', 'min:1'],
            'packages' => ['nullable', 'array'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.selected' => ['nullable', 'boolean'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'customs_clearance' => ['nullable', 'array'],
            'customs_clearance.duties_payment_type' => ['nullable', 'string', 'max:40'],
            'customs_clearance.total_customs_value.amount' => ['nullable', 'numeric', 'min:0.01'],
            'customs_clearance.total_customs_value.currency' => ['nullable', 'string', 'size:3'],
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
            ->firstOrFail();

        $validated['service_type'] = (string) ($quote->service_code ?: ($validated['service_type'] ?? 'FEDEX_GROUND'));

        $packages = $validated['packages'] ?? [[
            'weight' => $validated['weight'] ?? 1,
            'length' => $validated['length'] ?? 9,
            'width' => $validated['width'] ?? 6,
            'height' => $validated['height'] ?? 2,
            'weight_unit' => 'LB',
            'dimension_unit' => 'IN',
        ]];

        $outcome = $purchase->purchase(
            store: $store,
            order: $order,
            account: $account,
            origin: $origin,
            input: array_merge($validated, ['packages' => $packages]),
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
            'origin_location_id' => ['required', 'integer'],
            'service_type' => ['required', 'string', 'max:40'],
            'label_format' => ['nullable', 'in:PDF,PNG,ZPL'],
            'weight' => ['nullable', 'numeric', 'min:0.01'],
            'length' => ['nullable', 'numeric', 'min:1'],
            'width' => ['nullable', 'numeric', 'min:1'],
            'height' => ['nullable', 'numeric', 'min:1'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.selected' => ['nullable', 'boolean'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
        ]);

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
                'items' => 'Choose at least one order item for the return label.',
            ])->withInput();
        }

        $origin = Location::query()
            ->where('store_id', $store->id)
            ->whereKey($validated['origin_location_id'])
            ->firstOrFail();

        $outcome = $returns->createReturnLabel(
            store: $store,
            order: $order,
            account: $account,
            origin: $origin,
            input: array_merge($validated, [
                'packages' => [[
                    'weight' => $validated['weight'] ?? 1,
                    'length' => $validated['length'] ?? 9,
                    'width' => $validated['width'] ?? 6,
                    'height' => $validated['height'] ?? 2,
                ]],
            ]),
            actor: $request->user(),
        );

        if ($outcome['state'] !== FedExShipmentPurchaseService::STATE_SUCCEEDED || ! $outcome['shipment']) {
            return back()->withErrors(['fedex_return' => $outcome['merchant_message']])->withInput();
        }

        return back()->with('success', 'FedEx return label created.');
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
            return back()->withErrors(['fedex_etd' => $outcome['merchant_message']]);
        }

        return back()->with([
            'success' => $outcome['merchant_message'],
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
