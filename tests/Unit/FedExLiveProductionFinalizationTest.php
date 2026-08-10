<?php

namespace Tests\Unit;

use App\Models\Checkout;
use App\Models\CheckoutItem;
use App\Models\Role;
use App\Models\ShippingPackagePreset;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Operations\FedExCheckoutPackageBuilder;
use App\Services\Carriers\FedEx\Operations\FedExProductionShipRequestBuilder;
use App\Services\Carriers\FedEx\Operations\FedExServiceAvailabilityService;
use App\Services\Carriers\FedEx\Support\FedExCheckoutServiceCatalog;
use App\Services\Delivery\StoreShippingPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FedExLiveProductionFinalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pre_shipment_etd_uses_document_id_not_post_shipment_flag(): void
    {
        [$store, $order, $origin, $recipient] = $this->minimalOrderContext();

        $fixture = app(FedExProductionShipRequestBuilder::class)->buildFixture(
            $store,
            $order,
            $origin,
            $recipient,
            [
                'service_type' => 'FEDEX_INTERNATIONAL_PRIORITY',
                'packages' => [['weight' => 2, 'length' => 10, 'width' => 8, 'height' => 4]],
                'etd_enabled' => true,
                'etd_document_id' => 'DOC-LIVE-12345',
                'etd_document_type' => 'COMMERCIAL_INVOICE',
            ],
        );

        $this->assertContains('ELECTRONIC_TRADE_DOCUMENTS', (array) data_get($fixture, 'shipment_special_services.specialServiceTypes'));
        $this->assertSame('DOC-LIVE-12345', data_get($fixture, 'shipment_special_services.etdDetail.attachedDocuments.0.documentId'));
        $this->assertSame('COMMERCIAL_INVOICE', data_get($fixture, 'shipment_special_services.etdDetail.attachedDocuments.0.documentType'));
        $this->assertArrayNotHasKey('attributes', (array) data_get($fixture, 'shipment_special_services.etdDetail', []));
        $this->assertNull(data_get($fixture, 'shipment_special_services.etdDetail.attachedDocuments.0.documentReference'));
    }

    public function test_checkout_package_builder_aggregates_quantity_into_one_package(): void
    {
        $owner = User::factory()->create([
            'email' => 'pkg-agg-owner@example.test',
            'role_id' => Role::firstOrCreate(['name' => 'user'])->id,
        ]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'Package Aggregate Store',
            'slug' => 'package-aggregate-'.Str::lower(Str::random(6)),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->syncWithoutDetaching([$owner->id => ['role' => Store::ROLE_OWNER]]);

        $preset = ShippingPackagePreset::query()->create([
            'store_id' => $store->id,
            'name' => 'Default Box',
            'length' => 12,
            'width' => 9,
            'height' => 3,
            'dimension_unit' => 'IN',
            'weight_value' => 1,
            'weight_unit' => 'LB',
            'is_default' => true,
            'is_active' => true,
        ]);
        app(StoreShippingPreferences::class)->update($store, [
            'default_package_preset_id' => $preset->id,
            'weight_unit' => 'LB',
        ]);
        $store->refresh();

        $checkout = new Checkout(['store_id' => $store->id]);
        $checkout->setRelation('store', $store);
        $item = new CheckoutItem([
            'id' => 1,
            'quantity' => 3,
            'product_variant_id' => 42,
            'product_name' => 'Shirt',
            'product_type_snapshot' => 'physical',
        ]);
        $item->forceFill(['weight' => 0.5]);
        $checkout->setRelation('items', collect([$item]));

        $build = app(FedExCheckoutPackageBuilder::class)->buildFromCheckout($checkout);

        $this->assertTrue($build['ready'], 'Expected ready package build, got reason: '.($build['reason'] ?? 'null'));
        $this->assertCount(1, $build['packages']);
        $this->assertSame(3, $build['total_quantity']);
        $this->assertEqualsWithDelta(1.5, (float) $build['packages'][0]['weight'], 0.001);
        $this->assertSame(12.0, (float) $build['packages'][0]['length']);
        $this->assertSame(9.0, (float) $build['packages'][0]['width']);
        $this->assertSame(3.0, (float) $build['packages'][0]['height']);
    }

    public function test_service_catalog_includes_international_priority(): void
    {
        $this->assertContains('FEDEX_INTERNATIONAL_PRIORITY', FedExCheckoutServiceCatalog::codes());
        $this->assertContains('INTERNATIONAL_ECONOMY', FedExCheckoutServiceCatalog::internationalCodes());
        $this->assertSame(
            'FedEx International Priority',
            FedExCheckoutServiceCatalog::nameFor('FEDEX_INTERNATIONAL_PRIORITY'),
        );
    }

    public function test_ships_separately_over_max_packages_does_not_merge_overflow(): void
    {
        config(['carriers.fedex.checkout_max_package_lines' => 2]);

        $owner = User::factory()->create([
            'email' => 'pkg-cap-owner@example.test',
            'role_id' => Role::firstOrCreate(['name' => 'user'])->id,
        ]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'Package Cap Store',
            'slug' => 'package-cap-'.Str::lower(Str::random(6)),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->syncWithoutDetaching([$owner->id => ['role' => Store::ROLE_OWNER]]);

        $preset = ShippingPackagePreset::query()->create([
            'store_id' => $store->id,
            'name' => 'Default Box',
            'length' => 12,
            'width' => 9,
            'height' => 3,
            'dimension_unit' => 'IN',
            'weight_value' => 1,
            'weight_unit' => 'LB',
            'is_default' => true,
            'is_active' => true,
        ]);
        app(StoreShippingPreferences::class)->update($store, [
            'default_package_preset_id' => $preset->id,
            'weight_unit' => 'LB',
        ]);
        $store->refresh();

        $checkout = new Checkout(['store_id' => $store->id]);
        $checkout->setRelation('store', $store);
        $item = new CheckoutItem([
            'id' => 1,
            'quantity' => 3,
            'product_variant_id' => 7,
            'product_name' => 'Fragile item',
            'product_type_snapshot' => 'physical',
        ]);
        $item->forceFill([
            'weight' => 1,
            'meta' => ['ships_separately' => true],
        ]);
        $checkout->setRelation('items', collect([$item]));

        $build = app(FedExCheckoutPackageBuilder::class)->buildFromCheckout($checkout);

        $this->assertFalse($build['ready']);
        $this->assertSame('too_many_packages', $build['reason']);
        $this->assertSame([], $build['packages']);
    }

    public function test_availability_rate_intersection_keeps_rates_when_empty(): void
    {
        $rates = [
            ['service_type' => 'FEDEX_GROUND', 'amount' => '10.00'],
            ['service_type' => 'FEDEX_2_DAY', 'amount' => '20.00'],
        ];
        $quoteIds = [11, 22];

        $empty = FedExServiceAvailabilityService::intersectRatesWithAvailability($rates, $quoteIds, []);
        $this->assertSame($rates, $empty['rates']);

        $filtered = FedExServiceAvailabilityService::intersectRatesWithAvailability(
            $rates,
            $quoteIds,
            ['FEDEX_2_DAY'],
        );
        $this->assertCount(1, $filtered['rates']);
        $this->assertSame('FEDEX_2_DAY', $filtered['rates'][0]['service_type']);
        $this->assertSame([22], $filtered['quote_ids']);
    }

    /**
     * @return array{0: Store, 1: \App\Models\Order, 2: \App\Models\Location, 3: \App\Models\OrderAddress}
     */
    private function minimalOrderContext(): array
    {
        $owner = User::factory()->create([
            'email' => 'etd-fix-owner@example.test',
            'role_id' => Role::firstOrCreate(['name' => 'user'])->id,
        ]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'ETD Fix Store',
            'slug' => 'etd-fix-'.Str::lower(Str::random(6)),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->syncWithoutDetaching([$owner->id => ['role' => Store::ROLE_OWNER]]);

        $origin = \App\Models\Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Warehouse',
            'type' => \App\Models\Location::TYPE_WAREHOUSE,
            'address_line1' => '100 Commerce',
            'city' => 'Dallas',
            'state' => 'TX',
            'postal_code' => '75001',
            'country_code' => 'US',
            'phone' => '2145550100',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);

        $order = \App\Models\Order::query()->create([
            'store_id' => $store->id,
            'order_number' => 'ORD-ETD-'.Str::upper(Str::random(6)),
            'status' => 'paid',
            'currency_code' => 'USD',
            'subtotal' => 25,
            'shipping' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 25,
            'grand_total' => 25,
        ]);

        $recipient = \App\Models\OrderAddress::query()->create([
            'order_id' => $order->id,
            'type' => 'shipping',
            'name' => 'Customer',
            'address_line1' => '10 King St',
            'city' => 'Toronto',
            'province_code' => 'ON',
            'postal_code' => 'M5H2N2',
            'country_code' => 'CA',
            'phone' => '4165550100',
        ]);

        return [$store, $order, $origin, $recipient];
    }
}
