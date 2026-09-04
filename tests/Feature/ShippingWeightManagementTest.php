<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Product;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\ShippingPackagePreset;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\Delivery\DeliverySetupStatusService;
use App\Services\Delivery\ShippingWeightCoverageService;
use App\Services\Delivery\StoreShippingPreferences;
use App\Support\ProductEditPayload;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShippingWeightManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierSeeder::class);
        config([
            'carriers.fedex.checkout_rates_enabled' => true,
            'carriers.fedex.integrator_model_a_enabled' => true,
        ]);
    }

    public function test_products_filter_shipping_weight_has_and_missing(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $withWeight = $this->makeProduct($store, 'Has Weight');
        $withWeight->forceFill(['meta' => ['shipping_weight' => 1.25]])->save();
        $missing = $this->makeProduct($store, 'Missing Weight');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products', ['shipping_weight' => 'has']))
            ->assertOk()
            ->assertSee('Has Weight', false)
            ->assertDontSee('Missing Weight', false);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products', ['shipping_weight' => 'missing']))
            ->assertOk()
            ->assertSee('Missing Weight', false)
            ->assertDontSee('Has Weight', false);
    }

    public function test_fedex_only_missing_weight_with_fallback_is_recommendation_not_blocker(): void
    {
        [$owner, $store, $account] = $this->fedExOnlyStore();
        Product::query()->create([
            'store_id' => $store->id,
            'name' => 'No Weight',
            'slug' => 'no-weight-'.Str::lower(Str::random(4)),
            'status' => true,
            'requires_shipping' => true,
            'meta' => [],
        ]);
        app(StoreShippingPreferences::class)->update($store, ['fallback_item_weight' => 1.0]);

        $assessment = app(DeliverySetupStatusService::class)->assess(
            $store->fresh(),
            $store->locations()->get(),
            $store->shippingZones()->get(),
            $store->shippingMethods()->with(['shippingZone', 'carrierAccount'])->get(),
            collect([$account]),
            null,
        );

        $this->assertTrue($assessment['is_ready']);
        $this->assertTrue(collect($assessment['health_items'])->contains(
            fn (array $item): bool => ($item['id'] ?? '') === 'products_using_shipping_weight_fallback'
                && ($item['severity'] ?? '') === 'recommendation'
        ));
        $this->assertFalse(collect($assessment['health_items'])->contains(
            fn (array $item): bool => ($item['id'] ?? '') === 'products_missing_shipping_weight'
                && ($item['severity'] ?? '') === 'error'
        ));
    }

    public function test_packages_preferences_can_save_fallback_item_weight(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        app(StoreShippingPreferences::class)->update($store, [
            'default_label_format' => 'ZPL',
            'fallback_item_weight' => null,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('settings.shipping.preferences.update'), [
                'fallback_item_weight' => 1.25,
            ])
            ->assertRedirect();

        $prefs = app(StoreShippingPreferences::class)->get($store->fresh());
        $this->assertSame(1.25, $prefs['fallback_item_weight']);
        $this->assertSame('ZPL', $prefs['default_label_format']);
    }

    public function test_variant_only_weights_satisfy_exact_coverage(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, 'Dumbbell');
        $product->forceFill(['meta' => []])->save();
        $v1 = $product->variants()->first();
        $v1->forceFill(['meta' => ['shipping_weight' => 5]])->save();
        $product->variants()->create([
            'sku' => 'DB-10',
            'price' => 20,
            'stock' => 1,
            'stock_alert' => 0,
            'meta' => ['shipping_weight' => 10],
        ]);

        $coverage = app(ShippingWeightCoverageService::class);
        $this->assertTrue($coverage->productHasExactCoverage($product->fresh()->load('variants')));
        $this->assertSame(0, $coverage->countProductsMissingExactCoverage($store));
    }

    public function test_shipping_weight_filter_excludes_non_shippable_products(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $physical = $this->makeProduct($store, 'Physical Missing');
        $digital = $this->makeProduct($store, 'Digital Missing');
        $digital->forceFill(['requires_shipping' => false, 'product_type' => 'digital'])->save();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products', ['shipping_weight' => 'missing']))
            ->assertOk()
            ->assertSee('Physical Missing', false)
            ->assertDontSee('Digital Missing', false);

        $this->assertNotNull($physical->id);
    }

    public function test_weight_unit_cannot_be_silently_relabeled(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $prefs = app(StoreShippingPreferences::class);
        $prefs->update($store, ['weight_unit' => 'LB']);
        $prefs->update($store->fresh(), ['weight_unit' => 'KG']);

        $this->assertSame('LB', $prefs->weightUnitLabel($store->fresh()));
    }

    public function test_null_weight_unit_is_committed_when_fallback_is_saved(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $prefs = app(StoreShippingPreferences::class);

        $prefs->update($store, ['fallback_item_weight' => 1.25]);
        $this->assertSame('LB', $prefs->get($store->fresh())['weight_unit']);

        $prefs->update($store->fresh(), ['weight_unit' => 'KG']);
        $this->assertSame('LB', $prefs->weightUnitLabel($store->fresh()));
    }

    public function test_uses_fallback_filter_is_variant_aware(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        app(StoreShippingPreferences::class)->update($store, ['fallback_item_weight' => 1.0]);

        $missing = $this->makeProduct($store, 'Truly Missing');
        $missing->forceFill(['meta' => []])->save();

        $variantOnly = $this->makeProduct($store, 'Variant Weighted');
        $variantOnly->forceFill(['meta' => []])->save();
        $variantOnly->variants()->first()?->forceFill(['meta' => ['shipping_weight' => 4]])->save();

        $productLevelMissing = $this->makeProduct($store, 'Product Missing Variant Missing');
        $productLevelMissing->forceFill(['meta' => []])->save();
        $productLevelMissing->variants()->create([
            'sku' => 'VM-2',
            'price' => 10,
            'stock' => 1,
            'stock_alert' => 0,
            'meta' => [],
        ]);

        $coverage = app(ShippingWeightCoverageService::class);
        $expectedCount = $coverage->countProductsMissingExactCoverage($store);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products', ['shipping_weight' => 'uses_fallback']))
            ->assertOk();

        $html = $response->getContent();
        $shown = collect(['Truly Missing', 'Product Missing Variant Missing'])
            ->filter(fn (string $name): bool => str_contains((string) $html, $name))
            ->count();
        $this->assertSame($expectedCount, $shown);
        $response->assertDontSee('Variant Weighted', false);
    }

    public function test_delivery_review_link_uses_variant_aware_filter(): void
    {
        [$owner, $store, $account] = $this->fedExOnlyStore();
        Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Needs Fallback',
            'slug' => 'needs-fallback-'.Str::lower(Str::random(4)),
            'status' => true,
            'requires_shipping' => true,
            'meta' => [],
        ]);
        app(StoreShippingPreferences::class)->update($store, ['fallback_item_weight' => 1.0]);

        $assessment = app(DeliverySetupStatusService::class)->assess(
            $store->fresh(),
            $store->locations()->get(),
            $store->shippingZones()->get(),
            $store->shippingMethods()->with(['shippingZone', 'carrierAccount'])->get(),
            collect([$account]),
            null,
        );

        $item = collect($assessment['health_items'])->firstWhere('id', 'products_using_shipping_weight_fallback');
        $this->assertNotNull($item);
        $this->assertStringContainsString('shipping_weight=uses_fallback', (string) ($item['action_href'] ?? ''));
    }

    public function test_use_store_fallback_clears_legacy_meta_weight(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, 'Legacy Weight');
        $variant = $product->variants()->first();
        $product->forceFill(['meta' => ['weight' => 3.5]])->save();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), [
                'name' => $product->name,
                'description' => $product->description,
                'base_price' => $product->base_price,
                'product_type' => 'physical',
                'sku' => $product->sku,
                'stock_alert' => 0,
                'bulk_stock' => 0,
                'shipping_weight' => '',
                '_custom_fields_editor' => '1',
                'inventory_stock_allocation_mode' => 'manual',
                'variants' => [[
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'price' => 10,
                    'stock' => 0,
                    'stock_alert' => 0,
                    'option_map' => [],
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $meta = $product->fresh()->meta ?? [];
        $this->assertArrayNotHasKey('shipping_weight', $meta);
        $this->assertArrayNotHasKey('weight', $meta);
    }

    public function test_delivery_count_matches_uses_fallback_filter(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        app(StoreShippingPreferences::class)->update($store, ['fallback_item_weight' => 1.0]);

        $this->makeProduct($store, 'Missing A');
        $this->makeProduct($store, 'Missing B');

        $coverage = app(ShippingWeightCoverageService::class);
        $count = $coverage->countProductsMissingExactCoverage($store);
        $filterCount = $coverage->missingExactCoverageQuery($store)->count();

        $this->assertSame($count, $filterCount);
        $this->assertSame(2, $count);
    }

    public function test_product_edit_payload_exposes_legacy_meta_weight(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, 'Legacy Display');
        $product->forceFill(['meta' => ['weight' => 3.5]])->save();

        $payload = ProductEditPayload::forProduct($product->fresh());

        $this->assertSame('3.5', $payload['shipping_weight']);
    }

    public function test_variant_shipping_weight_round_trips_through_edit_payload(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, 'Kettlebell');
        $variant = $product->variants()->first();
        $variant->forceFill(['meta' => ['shipping_weight' => 12.5]])->save();

        $payload = ProductEditPayload::forProduct($product->fresh());

        $this->assertSame('12.5', $payload['variants'][0]['shipping_weight']);
    }

    public function test_digital_checkout_item_does_not_receive_weight_snapshot(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        app(StoreShippingPreferences::class)->update($store, ['fallback_item_weight' => 1.0]);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Ebook',
            'slug' => 'ebook-'.Str::lower(Str::random(4)),
            'base_price' => 9,
            'sku' => 'EB-1',
            'product_type' => 'digital',
            'status' => true,
            'requires_shipping' => false,
            'meta' => [],
        ]);
        $variant = $product->variants()->create([
            'sku' => 'EB-1-V',
            'price' => 9,
            'stock' => 999,
            'stock_alert' => 0,
        ]);

        $metadata = (new \ReflectionClass(CheckoutService::class))
            ->getMethod('shippingWeightMetadata');
        $metadata->setAccessible(true);
        $result = $metadata->invoke(app(CheckoutService::class), $store, $product, $variant);

        $this->assertSame([], $result);
    }

    /**
     * @return array{0: User, 1: Store, 2: CarrierAccount}
     */
    private function fedExOnlyStore(): array
    {
        $owner = $this->makeUser('fedex-weight-'.Str::lower(Str::random(4)).'@example.test');
        $store = $this->makeStore($owner, 'FedEx Weight Store');
        $store->forceFill(['delivery_setup_completed_at' => now()])->save();

        $location = $store->locations()->orderByDesc('is_default')->orderBy('id')->first();
        $locationAttributes = [
            'name' => 'Warehouse',
            'type' => 'warehouse',
            'address_line1' => '100 Main St',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'country_code' => 'US',
            'fulfills_online_orders' => true,
            'is_default' => true,
            'is_active' => true,
        ];
        if ($location !== null) {
            $location->update($locationAttributes);
        } else {
            Location::query()->create(['store_id' => $store->id, ...$locationAttributes]);
        }

        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'United States',
            'countries' => ['US'],
            'is_active' => true,
        ]);

        $fedex = Carrier::query()->where('code', 'fedex')->firstOrFail();
        $account = CarrierAccount::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $fedex->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'display_name' => 'FedEx',
            'ownership_mode' => CarrierAccount::OWNERSHIP_MERCHANT_OWNED,
            'credentials_source' => CarrierAccount::CREDENTIALS_MERCHANT_ACCOUNT,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_FEDEX_INTEGRATOR,
            'connection_model' => CarrierAccount::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
            'fedex_integrator_account' => true,
            'billing_owner' => CarrierAccount::BILLING_OWNER_MERCHANT,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'status' => CarrierAccount::STATUS_ENABLED,
            'enabled_for_checkout' => true,
            'capabilities' => [
                'rates' => true,
                'labels' => true,
                'tracking' => true,
                'checkout_rates' => true,
            ],
        ]);

        ShippingPackagePreset::query()->create([
            'store_id' => $store->id,
            'name' => 'Default Box',
            'length' => 12,
            'width' => 9,
            'height' => 4,
            'dimension_unit' => 'IN',
            'weight_value' => 1,
            'weight_unit' => 'LB',
            'is_default' => true,
            'is_active' => true,
        ]);

        ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'carrier_account_id' => $account->id,
            'name' => 'FedEx Ground',
            'code' => 'fedex-ground-'.Str::lower(Str::random(4)),
            'rate_type' => ShippingMethod::RATE_CARRIER_CALCULATED_LATER,
            'carrier_service_code' => 'FEDEX_GROUND',
            'carrier_service_name' => 'FedEx Ground',
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 0,
        ]);

        return [$owner, $store, $account];
    }

    private function makeUser(?string $email = null): User
    {
        return User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'role_id' => Role::firstOrCreate(['name' => 'user'])->id,
        ]);
    }

    private function makeStore(User $user, string $name = 'Test Store'): Store
    {
        $store = Store::create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Addr',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => false,
        ]);
        $store->members()->attach($user->id, ['role' => Store::ROLE_OWNER]);

        return $store;
    }

    private function makeProduct(Store $store, string $name): Product
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => null,
            'base_price' => 10,
            'sku' => 'SKU-'.strtoupper(Str::random(6)),
            'product_type' => 'physical',
            'status' => true,
            'requires_shipping' => true,
            'meta' => [],
        ]);
        $product->variants()->create([
            'sku' => $product->sku,
            'price' => 10,
            'stock' => 0,
            'stock_alert' => 0,
        ]);

        return $product;
    }
}
