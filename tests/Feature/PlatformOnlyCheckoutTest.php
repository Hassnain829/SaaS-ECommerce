<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\ConnectedSiteService;
use App\Support\CheckoutMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformOnlyCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_order_and_shipment_endpoints_are_gone(): void
    {
        [, , $token] = $this->tokenedStore('Gone External Store');

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', ['external_order_number' => 'WEB-GONE'])
            ->assertNotFound();

        $this->withToken($token)
            ->postJson('/api/v1/external/shipments', ['external_order_number' => 'WEB-GONE'])
            ->assertNotFound();
    }

    public function test_merchant_cannot_switch_to_external_checkout(): void
    {
        [$owner, $store] = $this->ownerStore('Mode Lock Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post('/settings/payments/mode', ['checkout_mode' => CheckoutMode::EXTERNAL])
            ->assertNotFound();

        $this->assertSame(CheckoutMode::PLATFORM, CheckoutMode::forStore($store->fresh()));
    }

    public function test_stored_external_checkout_mode_is_ignored_at_runtime(): void
    {
        [$owner, $store] = $this->ownerStore('Legacy Mode Store');
        $store->forceFill([
            'settings' => array_merge($store->settings ?? [], [
                'checkout_mode' => CheckoutMode::EXTERNAL,
            ]),
        ])->save();

        $this->assertSame(CheckoutMode::PLATFORM, CheckoutMode::forStore($store->fresh()));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index'))
            ->assertOk()
            ->assertSeeText('How this store accepts payments')
            ->assertSeeText('Checkout is blocked until Stripe is connected')
            ->assertDontSeeText('Switch to external checkout')
            ->assertDontSeeText('External checkout')
            ->assertDontSeeText('Inventory for external orders');
    }

    public function test_catalog_advertises_platform_checkout_only(): void
    {
        [, $store, $token] = $this->tokenedStore('Catalog Mode Store');
        $this->product($store);

        $this->withToken($token)
            ->getJson('/api/developer-storefront/catalog')
            ->assertOk()
            ->assertJsonPath('store.checkout_mode', CheckoutMode::PLATFORM)
            ->assertJsonMissingPath('store.external_checkout');
    }

    public function test_wordpress_connector_has_no_external_order_sync(): void
    {
        $client = file_get_contents(base_path('dev-test-wordpress/wp-content/plugins/eco-portal-connector/includes/class-api-client.php'));
        $storefront = file_get_contents(base_path('dev-test-wordpress/wp-content/plugins/eco-portal-connector/includes/class-storefront.php'));
        $checkout = file_get_contents(base_path('dev-test-wordpress/wp-content/plugins/eco-portal-connector/templates/checkout.php'));

        $this->assertIsString($client);
        $this->assertIsString($storefront);
        $this->assertIsString($checkout);
        $this->assertStringNotContainsString('/api/v1/external/orders', $client);
        $this->assertStringNotContainsString('sync_external_order', $client);
        $this->assertStringNotContainsString('eco_portal_place_order', $storefront);
        $this->assertStringContainsString('/api/v1/checkout', $client);
        $this->assertStringContainsString('Get delivery rates', $checkout);
        $this->assertStringNotContainsString('Place order & sync to portal', $checkout);
    }

    public function test_external_inventory_route_is_gone(): void
    {
        [$owner, $store] = $this->ownerStore('Inventory Route Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post('/settings/payments/external-inventory', ['inventory_owner' => 'external'])
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name): array
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['role_id' => $role->id]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => ['checkout_mode' => CheckoutMode::EXTERNAL],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        return [$owner, $store];
    }

    /**
     * @return array{0: User, 1: Store, 2: string}
     */
    private function tokenedStore(string $name): array
    {
        [$owner, $store] = $this->ownerStore($name);
        $token = app(ConnectedSiteService::class)->issuePrimaryCredential($store)['plain'];

        return [$owner, $store->fresh(), $token];
    }

    private function product(Store $store): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Platform Only Product',
            'slug' => 'platform-only-'.Str::random(6),
            'base_price' => 12,
            'sku' => 'POP-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $variant = ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => $product->sku.'-D',
            'price' => 12,
            'stock' => 5,
        ]);

        return [$product, $variant];
    }
}
