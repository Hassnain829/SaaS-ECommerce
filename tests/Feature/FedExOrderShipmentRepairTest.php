<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierRateQuote;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\ShipmentPackage;
use App\Models\ShippingMethod;
use App\Models\ShippingPackagePreset;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Operations\FedExOrderPackageSnapshotService;
use App\Services\Carriers\FedEx\Operations\FedExShipQuoteBindingService;
use App\Services\Delivery\StoreShippingPreferences;
use App\Services\Fulfillment\ShipmentService;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FedExOrderShipmentRepairTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierSeeder::class);
    }

    public function test_package_snapshot_from_preset_is_immutable_when_preset_changes(): void
    {
        [$store, $order, $origin, $user] = $this->baseOrderContext();

        $preset = ShippingPackagePreset::query()->create([
            'store_id' => $store->id,
            'name' => 'Medium Box',
            'weight_value' => 5,
            'weight_unit' => 'LB',
            'length' => 16,
            'width' => 12,
            'height' => 8,
            'dimension_unit' => 'IN',
            'is_default' => true,
            'is_active' => true,
        ]);

        $snapshot = app(FedExOrderPackageSnapshotService::class)->createFromOrderInput(
            store: $store,
            order: $order,
            originLocationId: $origin->id,
            input: [
                'package_source' => 'preset',
                'shipping_package_preset_id' => $preset->id,
                'weight' => 5.8,
            ],
            actor: $user,
        );

        $this->assertSame(5.8, (float) $snapshot->weight_value);
        $this->assertSame(16.0, (float) $snapshot->length);

        $preset->forceFill(['weight_value' => 99, 'length' => 1])->save();
        $snapshot->refresh();

        $this->assertSame(5.8, (float) $snapshot->weight_value);
        $this->assertSame(16.0, (float) $snapshot->length);
    }

    public function test_preset_snapshot_requires_actual_packed_weight_not_preset_weight(): void
    {
        [$store, $order, $origin, $user] = $this->baseOrderContext();

        $preset = ShippingPackagePreset::query()->create([
            'store_id' => $store->id,
            'name' => 'Small Box',
            'weight_value' => 2,
            'weight_unit' => 'LB',
            'length' => 10,
            'width' => 15,
            'height' => 7,
            'dimension_unit' => 'IN',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        app(FedExOrderPackageSnapshotService::class)->createFromOrderInput(
            store: $store,
            order: $order,
            originLocationId: $origin->id,
            input: [
                'package_source' => 'preset',
                'shipping_package_preset_id' => $preset->id,
            ],
            actor: $user,
        );
    }

    public function test_actual_packed_weight_uses_store_unit_not_preset_unit(): void
    {
        [$store, $order, $origin, $user] = $this->baseOrderContext();

        app(StoreShippingPreferences::class)->update($store, [
            'weight_unit' => 'LB',
        ]);

        $preset = ShippingPackagePreset::query()->create([
            'store_id' => $store->id,
            'name' => 'Metric Box',
            'weight_value' => 2,
            'weight_unit' => 'KG',
            'length' => 10,
            'width' => 15,
            'height' => 7,
            'dimension_unit' => 'IN',
            'is_default' => true,
            'is_active' => true,
        ]);

        $snapshot = app(FedExOrderPackageSnapshotService::class)->createFromOrderInput(
            store: $store->fresh(),
            order: $order,
            originLocationId: $origin->id,
            input: [
                'package_source' => 'preset',
                'shipping_package_preset_id' => $preset->id,
                'weight' => 5.8,
                'weight_unit' => 'KG',
            ],
            actor: $user,
        );

        $this->assertSame(5.8, (float) $snapshot->weight_value);
        $this->assertSame('LB', strtoupper((string) $snapshot->weight_unit));
    }

    public function test_five_pound_quote_package_binds_and_rejects_one_pound_purchase(): void
    {
        [$store, $order, $origin, $user] = $this->baseOrderContext();
        $account = $this->fedExAccount($store, $user);

        $package = ShipmentPackage::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'origin_location_id' => $origin->id,
            'name' => 'Rated package',
            'weight_value' => 5,
            'weight_unit' => 'LB',
            'length' => 16,
            'width' => 12,
            'height' => 8,
            'dimension_unit' => 'IN',
            'package_type' => 'YOUR_PACKAGING',
            'metadata' => ['source' => 'custom'],
            'created_by' => $user->id,
        ]);

        $packages = [[
            'weight' => 5.0,
            'weight_unit' => 'LB',
            'length' => 16.0,
            'width' => 12.0,
            'height' => 8.0,
            'dimension_unit' => 'IN',
        ]];

        $quote = CarrierRateQuote::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'order_id' => $order->id,
            'package_id' => $package->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => 'sandbox',
            'origin_postal_code' => '38118',
            'destination_postal_code' => '10001',
            'service_code' => 'FEDEX_GROUND',
            'service_name' => 'FedEx Ground',
            'amount' => 12.84,
            'currency' => 'USD',
            'estimated_days' => 3,
            'status' => CarrierRateQuote::STATUS_SUCCEEDED,
            'request_summary' => [
                'origin_location_id' => $origin->id,
                'origin_country' => 'US',
                'destination_country' => 'US',
                'destination_residential' => false,
                'ship_date' => now()->toDateString(),
                'pickup_type' => 'USE_SCHEDULED_PICKUP',
                'packages' => $packages,
                'package_fingerprint' => app(FedExShipQuoteBindingService::class)->packageFingerprint($packages),
            ],
            'response_summary' => [
                'selected_rate_type' => 'ACCOUNT',
            ],
            'created_by' => $user->id,
        ]);

        $binding = app(FedExShipQuoteBindingService::class);

        $bound = $binding->assertValidQuoteForPurchase(
            store: $store,
            order: $order,
            account: $account,
            origin: $origin,
            quoteId: $quote->id,
            serviceType: 'FEDEX_GROUND',
            packages: $packages,
            destinationPostal: '10001',
            destinationCountry: 'US',
            currency: 'USD',
            originCountry: 'US',
            residential: false,
            shipDate: now()->toDateString(),
            pickupType: 'USE_SCHEDULED_PICKUP',
        );

        $this->assertSame($quote->id, $bound->id);
        $this->assertSame($package->id, $bound->package_id);
        $this->assertSame(5.0, (float) $bound->package->weight_value);

        $this->expectException(ValidationException::class);
        $binding->assertValidQuoteForPurchase(
            store: $store,
            order: $order,
            account: $account,
            origin: $origin,
            quoteId: $quote->id,
            serviceType: 'FEDEX_GROUND',
            packages: [[
                'weight' => 1.0,
                'weight_unit' => 'LB',
                'length' => 9.0,
                'width' => 6.0,
                'height' => 2.0,
                'dimension_unit' => 'IN',
            ]],
            destinationPostal: '10001',
            destinationCountry: 'US',
            currency: 'USD',
            originCountry: 'US',
            residential: false,
            shipDate: now()->toDateString(),
            pickupType: 'USE_SCHEDULED_PICKUP',
        );
    }

    public function test_manual_shipment_rejects_fedex_live_shipping_method(): void
    {
        [$store, $order, $origin, $user] = $this->baseOrderContext();
        $account = $this->fedExAccount($store, $user);

        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'United States',
            'countries' => ['US'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $method = ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'name' => 'FedEx Ground',
            'code' => 'fedex-ground-'.Str::lower(Str::random(4)),
            'rate_type' => ShippingMethod::RATE_CARRIER_CALCULATED_LATER,
            'carrier_account_id' => $account->id,
            'carrier_service_code' => 'FEDEX_GROUND',
            'carrier_service_name' => 'FedEx Ground',
            'is_active' => true,
            'enabled_for_checkout' => true,
            'flat_rate' => 0,
        ]);

        $this->expectException(ValidationException::class);

        app(ShipmentService::class)->createShipment($order, [
            'origin_location_id' => $origin->id,
            'shipping_method_id' => $method->id,
            'items' => [
                ['order_item_id' => $order->items->first()->id, 'quantity' => 1],
            ],
        ], $user);
    }

    public function test_custom_package_requires_real_dims_without_fake_defaults(): void
    {
        [$store, $order, $origin, $user] = $this->baseOrderContext();

        $this->expectException(ValidationException::class);

        app(FedExOrderPackageSnapshotService::class)->createFromOrderInput(
            store: $store,
            order: $order,
            originLocationId: $origin->id,
            input: [
                'package_source' => 'custom',
                'weight' => 5,
                // missing dims on purpose
            ],
            actor: $user,
        );
    }

    /**
     * @return array{0: Store, 1: Order, 2: Location, 3: User}
     */
    private function baseOrderContext(): array
    {
        $role = Role::query()->firstOrCreate(['name' => 'owner'], ['name' => 'owner']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $store = Store::query()->create([
            'user_id' => $user->id,
            'name' => 'Repair Store',
            'slug' => 'repair-'.Str::lower(Str::random(8)),
            'currency' => 'USD',
        ]);
        $store->members()->syncWithoutDetaching([$user->id => ['role' => Store::ROLE_OWNER]]);

        $origin = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Main',
            'address_line1' => '10 FedEx Pkwy',
            'city' => 'Memphis',
            'state' => 'TN',
            'postal_code' => '38118',
            'country_code' => 'US',
            'phone' => '9015551212',
            'is_default' => true,
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'store_id' => $store->id,
            'order_number' => 'ORD-'.Str::upper(Str::random(6)),
            'status' => 'confirmed',
            'fulfillment_status' => 'unfulfilled',
            'payment_status' => 'paid',
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
            'name' => 'Customer',
            'phone' => '2125550199',
            'address_line1' => '350 5th Ave',
            'city' => 'New York',
            'state' => 'NY',
            'province_code' => 'NY',
            'postal_code' => '10001',
            'country_code' => 'US',
        ]);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Widget',
            'slug' => 'widget-'.Str::random(6),
            'base_price' => 20,
            'sku' => 'W-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Widget',
            'sku_snapshot' => 'W-1',
            'quantity' => 1,
            'unit_price' => 20,
            'subtotal' => 20,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 20,
            'line_total' => 20,
        ]);

        $order->load('items');

        return [$store, $order, $origin, $user];
    }

    private function fedExAccount(Store $store, User $user): CarrierAccount
    {
        $carrier = Carrier::query()->where('code', 'fedex')->firstOrFail();

        return CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $carrier->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'display_name' => 'FedEx Integrator',
            'connection_type' => 'oauth',
            'status' => CarrierAccount::STATUS_ENABLED,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'created_by' => $user->id,
        ], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));
    }
}
