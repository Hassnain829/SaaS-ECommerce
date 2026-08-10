<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierRateQuote;
use App\Models\FedExTradeDocument;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Operations\FedExShipmentPurchaseService;
use App\Services\Carriers\FedEx\Operations\FedExTradeDocumentUploadPayloadFactory;
use App\Support\OrderLifecycle;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class FedExLiveProductionEtdPurchaseChainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);

        config([
            'carriers.fedex.enabled' => true,
            'carriers.fedex.model_a_enabled' => true,
            'carriers.fedex.integrator_model_a_enabled' => true,
            'carriers.fedex.ops_ship_labels_enabled' => true,
            'carriers.fedex.ops_negotiated_rates_enabled' => true,
            'carriers.fedex.sandbox.base_url' => 'https://apis-sandbox.fedex.com',
            'carriers.fedex.sandbox.client_id' => 'parent-client-id-1234567890',
            'carriers.fedex.sandbox.client_secret' => 'parent-client-secret',
            'carriers.fedex.ship_create_path' => '/ship/v1/shipments',
        ]);
    }

    public function test_purchase_attaches_bound_trade_document_id_to_ship_http_payload(): void
    {
        [$store, $account, $location, $order] = $this->readyInternationalOrder();

        $docId = 'DOC-LIVE-BOUND-98765';
        $doc = FedExTradeDocument::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'carrier_account_id' => $account->id,
            'document_type' => 'COMMERCIAL_INVOICE',
            'status' => FedExTradeDocument::STATUS_UPLOADED,
            'fedex_document_id' => $docId,
            'origin_country_code' => 'US',
            'destination_country_code' => 'CA',
            'uploaded_at' => now(),
        ]);

        $packages = [['weight' => 2, 'length' => 10, 'width' => 8, 'height' => 4, 'weight_unit' => 'LB', 'dimension_unit' => 'IN']];
        $quote = $this->makeAccountQuote($store, $account, $order, $location, $packages, 'FEDEX_INTERNATIONAL_PRIORITY');

        $shipPayload = null;
        Http::fake(function (Request $request) use (&$shipPayload) {
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response([
                    'access_token' => 'ship-token-etd',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($request->url(), '/ship/v1/shipments')) {
                $shipPayload = $request->data();

                return Http::response([
                    'transactionId' => 'ship-txn-etd',
                    'output' => [
                        'transactionShipments' => [[
                            'masterTrackingNumber' => '794600011122',
                            'serviceType' => 'FEDEX_INTERNATIONAL_PRIORITY',
                            'pieceResponses' => [[
                                'packageSequenceNumber' => 1,
                                'trackingNumber' => '794600011122',
                                'packageDocuments' => [[
                                    'contentType' => 'LABEL',
                                    'docType' => 'LABEL',
                                    'imageType' => 'PDF',
                                    'encodedLabel' => base64_encode('%PDF-1.4 fedex-label'),
                                ]],
                            ]],
                        ]],
                    ],
                ], 200);
            }

            return Http::response(['message' => 'unexpected'], 500);
        });

        $outcome = app(FedExShipmentPurchaseService::class)->purchase(
            store: $store,
            order: $order->fresh(['addresses', 'items']),
            account: $account,
            origin: $location,
            input: [
                'carrier_rate_quote_id' => $quote->id,
                'service_type' => 'FEDEX_INTERNATIONAL_PRIORITY',
                'label_format' => 'PDF',
                'packages' => $packages,
                'fedex_trade_document_id' => $doc->id,
                'customs_clearance' => [
                    'commodities' => [[
                        'description' => 'Widget',
                        'quantity' => 1,
                        'customs_value' => ['amount' => 20, 'currency' => 'USD'],
                        'weight' => 2,
                        'weight_unit' => 'LB',
                        'country_of_manufacture' => 'US',
                    ]],
                    'total_customs_value' => ['amount' => 20, 'currency' => 'USD'],
                    'duties_payment_type' => 'SENDER',
                    'commercial_invoice' => ['shipment_purpose' => 'SOLD'],
                ],
            ],
        );

        $this->assertSame(FedExShipmentPurchaseService::STATE_SUCCEEDED, $outcome['state']);
        $this->assertNotNull($shipPayload);

        $specialTypes = (array) data_get($shipPayload, 'requestedShipment.shipmentSpecialServices.specialServiceTypes', []);
        $this->assertContains('ELECTRONIC_TRADE_DOCUMENTS', $specialTypes);
        $this->assertSame(
            $docId,
            data_get($shipPayload, 'requestedShipment.shipmentSpecialServices.etdDetail.attachedDocuments.0.documentId'),
        );
        $this->assertSame(
            'COMMERCIAL_INVOICE',
            data_get($shipPayload, 'requestedShipment.shipmentSpecialServices.etdDetail.attachedDocuments.0.documentType'),
        );
        $this->assertNull(
            data_get($shipPayload, 'requestedShipment.shipmentSpecialServices.etdDetail.attachedDocuments.0.documentReference'),
        );
        $this->assertNotContains(
            'POST_SHIPMENT_UPLOAD_REQUESTED',
            (array) data_get($shipPayload, 'requestedShipment.shipmentSpecialServices.etdDetail.attributes', []),
        );
    }

    public function test_production_etd_upload_payload_uses_official_preshipment_workflow(): void
    {
        $absolute = tempnam(sys_get_temp_dir(), 'etd').'.pdf';
        file_put_contents($absolute, "%PDF-1.4\n".str_repeat('commercial invoice content ', 50)."\n%%EOF\n");

        try {
            $prepared = app(FedExTradeDocumentUploadPayloadFactory::class)->buildDocumentUpload([
                'upload' => [
                    'mode' => 'document',
                    'ship_document_type' => 'COMMERCIAL_INVOICE',
                    'workflow_name' => 'ETDPreShipment',
                    'absolute_path' => $absolute,
                    'filename' => 'invoice.pdf',
                    'origin_country_code' => 'US',
                    'destination_country_code' => 'CA',
                ],
            ]);

            $this->assertSame('ETDPreShipment', data_get($prepared, 'document_json.workflowName'));
        } finally {
            @unlink($absolute);
        }
    }

    public function test_production_etd_default_workflow_is_official_preshipment_enum(): void
    {
        $absolute = tempnam(sys_get_temp_dir(), 'etd').'.pdf';
        file_put_contents($absolute, "%PDF-1.4\n".str_repeat('commercial invoice content ', 50)."\n%%EOF\n");

        try {
            $prepared = app(FedExTradeDocumentUploadPayloadFactory::class)->buildDocumentUpload([
                'upload' => [
                    'mode' => 'document',
                    'ship_document_type' => 'COMMERCIAL_INVOICE',
                    'absolute_path' => $absolute,
                    'filename' => 'invoice.pdf',
                    'origin_country_code' => 'US',
                    'destination_country_code' => 'CA',
                ],
            ]);

            $this->assertSame('ETDPreShipment', data_get($prepared, 'document_json.workflowName'));
            $this->assertNotSame('ETDPreshipment', data_get($prepared, 'document_json.workflowName'));
        } finally {
            @unlink($absolute);
        }
    }

    /**
     * @return array{0: Store, 1: CarrierAccount, 2: Location, 3: Order}
     */
    private function readyInternationalOrder(): array
    {
        $owner = User::factory()->create([
            'email' => 'etd-chain-owner@example.test',
            'role_id' => Role::firstOrCreate(['name' => 'user'])->id,
        ]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'ETD Chain Store',
            'slug' => 'etd-chain-'.Str::lower(Str::random(6)),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->syncWithoutDetaching([$owner->id => ['role' => Store::ROLE_OWNER]]);

        $location = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'US Warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '90 FedEx Pkwy',
            'city' => 'Collierville',
            'state' => 'TN',
            'postal_code' => '38017',
            'country_code' => 'US',
            'phone' => '9015550100',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);

        $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();
        $account = CarrierAccount::query()->create(array_merge(
            CarrierAccount::ownershipAttributesForFedExIntegratorProvider(),
            [
                'store_id' => $store->id,
                'carrier_id' => $fedEx->id,
                'provider' => CarrierAccount::PROVIDER_FEDEX,
                'display_name' => 'Model A FedEx',
                'provider_account_number' => '700257037',
                'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
                'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
                'status' => CarrierAccount::STATUS_ENABLED,
                'default_origin_location_id' => $location->id,
                'fedex_active_store_key' => CarrierAccount::fedExActiveStoreKeyFor(
                    (int) $store->id,
                    CarrierAccount::ENVIRONMENT_SANDBOX,
                ),
                'enabled_for_checkout' => false,
                'settings' => ['default_origin_location_id' => $location->id],
                'capabilities' => array_merge(
                    CarrierAccount::ownershipAttributesForFedExIntegratorProvider()['capabilities'],
                    ['labels' => true, 'tracking' => true, 'rates' => true],
                ),
            ],
        ));
        $account->setCredentials([
            'customer_key' => 'child-key-etd',
            'customer_password' => 'child-secret-etd',
        ]);
        $account->setFedExAccountNumber('700257037');
        $account->save();

        $order = Order::query()->create([
            'store_id' => $store->id,
            'order_number' => 'ORD-ETD-'.Str::upper(Str::random(6)),
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'currency_code' => 'USD',
            'subtotal' => 20,
            'shipping' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 20,
            'grand_total' => 20,
        ]);

        OrderAddress::query()->create([
            'order_id' => $order->id,
            'type' => 'shipping',
            'name' => 'CA Buyer',
            'address_line1' => '10 King St W',
            'city' => 'Toronto',
            'state' => 'ON',
            'province_code' => 'ON',
            'postal_code' => 'M5H2N2',
            'country_code' => 'CA',
            'phone' => '4165550100',
        ]);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Cross Border Item',
            'slug' => 'cross-border-'.Str::random(6),
            'base_price' => 20,
            'sku' => 'CB-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Cross Border Item',
            'quantity' => 1,
            'unit_price' => 20,
            'subtotal' => 20,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 20,
        ]);

        return [$store, $account->fresh(), $location, $order->fresh(['addresses', 'items'])];
    }

    /**
     * @param  list<array<string, mixed>>  $packages
     */
    private function makeAccountQuote(
        Store $store,
        CarrierAccount $account,
        Order $order,
        Location $location,
        array $packages,
        string $service = 'FEDEX_INTERNATIONAL_PRIORITY',
    ): CarrierRateQuote {
        $recipient = $order->addresses->first();
        $normalized = array_values(array_map(static function (array $package): array {
            return [
                'weight' => $package['weight'] ?? 1,
                'weight_unit' => $package['weight_unit'] ?? 'LB',
                'length' => $package['length'] ?? null,
                'width' => $package['width'] ?? null,
                'height' => $package['height'] ?? null,
                'dimension_unit' => $package['dimension_unit'] ?? 'IN',
            ];
        }, $packages));

        return CarrierRateQuote::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'order_id' => $order->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => $account->environment,
            'origin_postal_code' => $location->postal_code,
            'destination_postal_code' => $recipient->postal_code ?? 'M5H2N2',
            'service_code' => $service,
            'service_name' => 'FedEx International Priority',
            'amount' => '45.00',
            'currency' => 'USD',
            'status' => CarrierRateQuote::STATUS_SUCCEEDED,
            'request_summary' => [
                'origin_location_id' => $location->id,
                'origin_country' => 'US',
                'destination_country' => 'CA',
                'destination_residential' => false,
                'ship_date' => now()->toDateString(),
                'packages' => $normalized,
            ],
            'response_summary' => [
                'selected_rate_type' => 'ACCOUNT',
                'rate_type' => 'ACCOUNT',
            ],
        ]);
    }
}
