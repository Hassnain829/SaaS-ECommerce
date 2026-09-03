<?php

namespace Tests\Unit;

use App\Models\Checkout;
use App\Models\CheckoutItem;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariationOption;
use App\Models\ProductVariationType;
use App\Models\Role;
use App\Models\ShippingPackagePreset;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Operations\FedExCheckoutPackageBuilder;
use App\Services\Carriers\FedEx\Operations\FedExProductionShipRequestBuilder;
use App\Services\Carriers\FedEx\Operations\FedExServiceAvailabilityService;
use App\Services\Carriers\FedEx\Support\FedExCheckoutServiceCatalog;
use App\Services\Delivery\StoreShippingPreferences;
use App\Services\Delivery\VariantShippingWeightBulkService;
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

    public function test_checkout_package_builder_mixed_cart_resolves_variant_product_and_fallback_weights(): void
    {
        [$store, $checkout] = $this->mixedWeightCheckoutContext();

        $build = app(FedExCheckoutPackageBuilder::class)->buildFromCheckout($checkout);

        $this->assertTrue($build['ready'], 'Expected ready package build, got reason: '.($build['reason'] ?? 'null'));
        $this->assertCount(1, $build['packages']);
        $this->assertEqualsWithDelta(13.4, (float) $build['packages'][0]['weight'], 0.001);
        $this->assertSame('LB', strtoupper((string) $build['packages'][0]['weight_unit']));
    }

    public function test_checkout_package_builder_uses_bulk_assigned_variant_option_weights(): void
    {
        $owner = User::factory()->create([
            'email' => 'bulk-variant-weight-owner@example.test',
            'role_id' => Role::firstOrCreate(['name' => 'user'])->id,
        ]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'Bulk Variant Weight Store',
            'slug' => 'bulk-variant-weight-'.Str::lower(Str::random(6)),
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

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Dumbbell Set',
            'slug' => 'dumbbell-set',
            'base_price' => 20,
            'sku' => 'DB-1',
            'product_type' => 'physical',
            'status' => true,
            'requires_shipping' => true,
            'meta' => [],
        ]);
        $variationType = ProductVariationType::query()->create([
            'product_id' => $product->id,
            'name' => 'Weight',
            'type' => 'select',
        ]);
        $five = ProductVariationOption::query()->create([
            'variation_type_id' => $variationType->id,
            'value' => '5 lb',
            'sort_order' => 0,
        ]);
        $ten = ProductVariationOption::query()->create([
            'variation_type_id' => $variationType->id,
            'value' => '10 lb',
            'sort_order' => 1,
        ]);
        $variantFive = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'DB-5',
            'price' => 20,
            'stock' => 5,
            'stock_alert' => 0,
            'meta' => [],
        ]);
        $variantTen = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'DB-10',
            'price' => 30,
            'stock' => 5,
            'stock_alert' => 0,
            'meta' => [],
        ]);
        $variantFive->options()->sync([$five->id]);
        $variantTen->options()->sync([$ten->id]);

        app(VariantShippingWeightBulkService::class)->apply(
            $store,
            [$product->id],
            'use_option_values',
            'Weight',
            [],
            'replace_all',
        );

        $variantFive->refresh();
        $variantTen->refresh();
        $this->assertSame(5.0, (float) data_get($variantFive->meta, 'shipping_weight'));
        $this->assertSame(10.0, (float) data_get($variantTen->meta, 'shipping_weight'));

        $checkout = new Checkout(['store_id' => $store->id]);
        $checkout->setRelation('store', $store->refresh());
        $itemFive = new CheckoutItem([
            'id' => 1,
            'quantity' => 2,
            'product_id' => $product->id,
            'product_variant_id' => $variantFive->id,
            'product_name' => 'Dumbbell 5 lb',
            'product_type_snapshot' => 'physical',
        ]);
        $itemFive->setRelation('product', $product);
        $itemFive->setRelation('variant', $variantFive);
        $itemTen = new CheckoutItem([
            'id' => 2,
            'quantity' => 1,
            'product_id' => $product->id,
            'product_variant_id' => $variantTen->id,
            'product_name' => 'Dumbbell 10 lb',
            'product_type_snapshot' => 'physical',
        ]);
        $itemTen->setRelation('product', $product);
        $itemTen->setRelation('variant', $variantTen);
        $checkout->setRelation('items', collect([$itemFive, $itemTen]));

        $build = app(FedExCheckoutPackageBuilder::class)->buildFromCheckout($checkout);

        $this->assertTrue($build['ready'], 'Expected ready package build, got reason: '.($build['reason'] ?? 'null'));
        $this->assertEqualsWithDelta(20.0, (float) $build['packages'][0]['weight'], 0.001);
    }

    public function test_checkout_package_builder_uses_store_fallback_when_item_weight_missing(): void
    {
        $owner = User::factory()->create([
            'email' => 'pkg-fallback-owner@example.test',
            'role_id' => Role::firstOrCreate(['name' => 'user'])->id,
        ]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'Package Fallback Store',
            'slug' => 'package-fallback-'.Str::lower(Str::random(6)),
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
            'length' => 10,
            'width' => 8,
            'height' => 4,
            'dimension_unit' => 'IN',
            'weight_value' => 99,
            'weight_unit' => 'LB',
            'is_default' => true,
            'is_active' => true,
        ]);
        app(StoreShippingPreferences::class)->update($store, [
            'default_package_preset_id' => $preset->id,
            'weight_unit' => 'LB',
            'fallback_item_weight' => 1.0,
        ]);
        $store->refresh();

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'No Weight Shirt',
            'slug' => 'no-weight-shirt-'.Str::lower(Str::random(4)),
            'base_price' => 20,
            'sku' => 'NWS-'.strtoupper(Str::random(4)),
            'product_type' => 'physical',
            'status' => true,
            'requires_shipping' => true,
            'meta' => [],
        ]);
        $variant = $product->variants()->create([
            'sku' => $product->sku,
            'price' => 20,
            'stock' => 5,
            'stock_alert' => 0,
            'meta' => [],
        ]);

        $checkout = new Checkout(['store_id' => $store->id]);
        $checkout->setRelation('store', $store);
        $item = new CheckoutItem([
            'id' => 11,
            'quantity' => 2,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'No Weight Shirt',
            'product_type_snapshot' => 'physical',
        ]);
        $item->setRelation('product', $product);
        $item->setRelation('variant', $variant);
        $checkout->setRelation('items', collect([$item]));

        $build = app(FedExCheckoutPackageBuilder::class)->buildFromCheckout($checkout);

        $this->assertTrue($build['ready'], 'Expected ready package build, got reason: '.($build['reason'] ?? 'null'));
        $this->assertEqualsWithDelta(2.0, (float) $build['packages'][0]['weight'], 0.001);
    }

    public function test_checkout_package_builder_rejects_unsupported_aggregate_weight(): void
    {
        config(['carriers.fedex.checkout_max_package_weight' => 10]);

        $owner = User::factory()->create([
            'email' => 'pkg-heavy-owner@example.test',
            'role_id' => Role::firstOrCreate(['name' => 'user'])->id,
        ]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'Package Heavy Store',
            'slug' => 'package-heavy-'.Str::lower(Str::random(6)),
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
            'length' => 10,
            'width' => 8,
            'height' => 4,
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
            'id' => 22,
            'quantity' => 1,
            'product_name' => 'Anvil',
            'product_type_snapshot' => 'physical',
        ]);
        $item->forceFill(['weight' => 25]);
        $checkout->setRelation('items', collect([$item]));

        $build = app(FedExCheckoutPackageBuilder::class)->buildFromCheckout($checkout);

        $this->assertFalse($build['ready']);
        $this->assertSame('package_weight_unsupported', $build['reason']);
    }

    public function test_checkout_package_builder_kg_store_allows_aggregate_under_converted_max(): void
    {
        config(['carriers.fedex.checkout_max_package_weight' => 150]);

        [$store, $checkout] = $this->kgAggregateCheckoutContext(40.0, 20.0);

        $build = app(FedExCheckoutPackageBuilder::class)->buildFromCheckout($checkout);

        $this->assertTrue($build['ready'], 'Expected ready package build, got reason: '.($build['reason'] ?? 'null'));
        $this->assertEqualsWithDelta(60.0, (float) $build['packages'][0]['weight'], 0.001);
        $this->assertSame('KG', strtoupper((string) $build['packages'][0]['weight_unit']));
    }

    public function test_checkout_package_builder_kg_store_rejects_aggregate_over_converted_max(): void
    {
        config(['carriers.fedex.checkout_max_package_weight' => 150]);

        [$store, $checkout] = $this->kgAggregateCheckoutContext(40.0, 35.0);

        $build = app(FedExCheckoutPackageBuilder::class)->buildFromCheckout($checkout);

        $this->assertFalse($build['ready']);
        $this->assertSame('package_weight_unsupported', $build['reason']);
        $this->assertEqualsWithDelta(75.0, 40.0 + 35.0, 0.001);
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
     * @return array{0: Store, 1: Order, 2: Location, 3: OrderAddress}
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

        $origin = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Warehouse',
            'type' => Location::TYPE_WAREHOUSE,
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

        $order = Order::query()->create([
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

        $recipient = OrderAddress::query()->create([
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

    /**
     * @return array{0: Store, 1: Checkout}
     */
    private function mixedWeightCheckoutContext(): array
    {
        $owner = User::factory()->create([
            'email' => 'mixed-weight-owner@example.test',
            'role_id' => Role::firstOrCreate(['name' => 'user'])->id,
        ]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'Mixed Weight Store',
            'slug' => 'mixed-weight-'.Str::lower(Str::random(6)),
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
            'fallback_item_weight' => 1.0,
            'weight_unit' => 'LB',
        ]);

        $variantProduct = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Variant Weighted',
            'slug' => 'variant-weighted',
            'base_price' => 10,
            'sku' => 'VW-1',
            'product_type' => 'physical',
            'status' => true,
            'requires_shipping' => true,
            'meta' => [],
        ]);
        $variantWeighted = ProductVariant::query()->create([
            'product_id' => $variantProduct->id,
            'sku' => 'VW-1-A',
            'price' => 10,
            'stock' => 5,
            'stock_alert' => 0,
            'meta' => ['shipping_weight' => 4.2],
        ]);
        $variantWeighted->setRelation('product', $variantProduct);

        $productWeighted = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Product Weighted',
            'slug' => 'product-weighted',
            'base_price' => 12,
            'sku' => 'PW-1',
            'product_type' => 'physical',
            'status' => true,
            'requires_shipping' => true,
            'meta' => ['shipping_weight' => 3.0],
        ]);
        $productVariant = ProductVariant::query()->create([
            'product_id' => $productWeighted->id,
            'sku' => 'PW-1-A',
            'price' => 12,
            'stock' => 5,
            'stock_alert' => 0,
            'meta' => [],
        ]);
        $productVariant->setRelation('product', $productWeighted);

        $fallbackProduct = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Fallback Weighted',
            'slug' => 'fallback-weighted',
            'base_price' => 8,
            'sku' => 'FB-1',
            'product_type' => 'physical',
            'status' => true,
            'requires_shipping' => true,
            'meta' => [],
        ]);
        $fallbackVariant = ProductVariant::query()->create([
            'product_id' => $fallbackProduct->id,
            'sku' => 'FB-1-A',
            'price' => 8,
            'stock' => 5,
            'stock_alert' => 0,
            'meta' => [],
        ]);
        $fallbackVariant->setRelation('product', $fallbackProduct);

        $checkout = new Checkout(['store_id' => $store->id]);
        $checkout->setRelation('store', $store->fresh());

        $items = collect([
            tap(new CheckoutItem([
                'id' => 1,
                'quantity' => 2,
                'product_variant_id' => $variantWeighted->id,
                'product_name' => $variantProduct->name,
                'product_type_snapshot' => 'physical',
            ]), function (CheckoutItem $item) use ($variantWeighted): void {
                $item->setRelation('variant', $variantWeighted);
            }),
            tap(new CheckoutItem([
                'id' => 2,
                'quantity' => 1,
                'product_variant_id' => $productVariant->id,
                'product_name' => $productWeighted->name,
                'product_type_snapshot' => 'physical',
            ]), function (CheckoutItem $item) use ($productVariant): void {
                $item->setRelation('variant', $productVariant);
            }),
            tap(new CheckoutItem([
                'id' => 3,
                'quantity' => 2,
                'product_variant_id' => $fallbackVariant->id,
                'product_name' => $fallbackProduct->name,
                'product_type_snapshot' => 'physical',
            ]), function (CheckoutItem $item) use ($fallbackVariant): void {
                $item->setRelation('variant', $fallbackVariant);
            }),
        ]);
        $checkout->setRelation('items', $items);

        return [$store->fresh(), $checkout];
    }

    /**
     * @return array{0: Store, 1: Checkout}
     */
    private function kgAggregateCheckoutContext(float $weightA, float $weightB): array
    {
        $owner = User::factory()->create([
            'email' => 'kg-agg-owner-'.Str::lower(Str::random(4)).'@example.test',
            'role_id' => Role::firstOrCreate(['name' => 'user'])->id,
        ]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'KG Aggregate Store',
            'slug' => 'kg-aggregate-'.Str::lower(Str::random(6)),
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
            'weight_unit' => 'KG',
            'is_default' => true,
            'is_active' => true,
        ]);
        app(StoreShippingPreferences::class)->update($store, [
            'default_package_preset_id' => $preset->id,
            'weight_unit' => 'KG',
        ]);

        $productA = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Product A',
            'slug' => 'product-a-'.Str::lower(Str::random(4)),
            'base_price' => 10,
            'sku' => 'KGA-'.strtoupper(Str::random(4)),
            'product_type' => 'physical',
            'status' => true,
            'requires_shipping' => true,
            'meta' => ['shipping_weight' => $weightA],
        ]);
        $variantA = ProductVariant::query()->create([
            'product_id' => $productA->id,
            'sku' => $productA->sku,
            'price' => 10,
            'stock' => 5,
            'stock_alert' => 0,
            'meta' => [],
        ]);
        $variantA->setRelation('product', $productA);

        $productB = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Product B',
            'slug' => 'product-b-'.Str::lower(Str::random(4)),
            'base_price' => 12,
            'sku' => 'KGB-'.strtoupper(Str::random(4)),
            'product_type' => 'physical',
            'status' => true,
            'requires_shipping' => true,
            'meta' => ['shipping_weight' => $weightB],
        ]);
        $variantB = ProductVariant::query()->create([
            'product_id' => $productB->id,
            'sku' => $productB->sku,
            'price' => 12,
            'stock' => 5,
            'stock_alert' => 0,
            'meta' => [],
        ]);
        $variantB->setRelation('product', $productB);

        $checkout = new Checkout(['store_id' => $store->id]);
        $checkout->setRelation('store', $store->fresh());
        $itemA = new CheckoutItem([
            'id' => 1,
            'quantity' => 1,
            'product_id' => $productA->id,
            'product_variant_id' => $variantA->id,
            'product_name' => $productA->name,
            'product_type_snapshot' => 'physical',
        ]);
        $itemA->setRelation('product', $productA);
        $itemA->setRelation('variant', $variantA);
        $itemB = new CheckoutItem([
            'id' => 2,
            'quantity' => 1,
            'product_id' => $productB->id,
            'product_variant_id' => $variantB->id,
            'product_name' => $productB->name,
            'product_type_snapshot' => 'physical',
        ]);
        $itemB->setRelation('product', $productB);
        $itemB->setRelation('variant', $variantB);
        $checkout->setRelation('items', collect([$itemA, $itemB]));

        return [$store->fresh(), $checkout];
    }
}
