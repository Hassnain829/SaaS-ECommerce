<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MerchantWebsiteConnectTest extends TestCase
{
    use RefreshDatabase;

    public function test_website_page_leads_with_wordpress_steps_and_keeps_react_env_in_advanced_details(): void
    {
        [$owner, $store] = $this->ownerStore('PKR Store', 'PKR');

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.settings'))
            ->assertOk()
            ->assertSeeInOrder([
                'Connect your website',
                'Website for PKR Store',
                'Get the WordPress plugin',
                'Create a connection key',
                'Paste the key in WordPress',
                'Settings → Eco Portal',
                'Open the shop and place a test order',
                'Advanced details',
                'Local React test app',
                'VITE_API_BASE=',
                'VITE_EXTERNAL_API_BASE=',
                'VITE_CHECKOUT_API_BASE=',
                'VITE_STOREFRONT_TOKEN=',
            ])
            ->assertSee('PKR')
            ->assertSee('Website')
            ->assertDontSee('Connect a React dev app')
            ->assertDontSee('Developer test storefront')
            ->assertDontSee('Test storefront')
            ->content();

        $advancedPos = strpos($html, 'Advanced details');
        $vitePos = strpos($html, 'VITE_STOREFRONT_TOKEN=');
        $wordpressPos = strpos($html, 'Get the WordPress plugin');

        $this->assertNotFalse($advancedPos);
        $this->assertNotFalse($vitePos);
        $this->assertNotFalse($wordpressPos);
        $this->assertLessThan($advancedPos, $wordpressPos);
        $this->assertLessThan($vitePos, $advancedPos);
    }

    public function test_staff_cannot_generate_or_revoke_the_connection_key(): void
    {
        $owner = $this->merchant('website-owner@example.com');
        $staff = $this->merchant('website-staff@example.com');
        $store = $this->store($owner, 'Staff Website Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($store, $staff, Store::ROLE_STAFF);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.settings'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('developer-storefront.token.generate'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('developer-storefront.token.revoke'))
            ->assertForbidden();
    }

    public function test_manager_can_view_website_page_but_cannot_generate_or_save_url(): void
    {
        $owner = $this->merchant('website-manager-owner@example.com');
        $manager = $this->merchant('website-manager@example.com');
        $store = $this->store($owner, 'Manager Website Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($store, $manager, Store::ROLE_MANAGER);

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.settings'))
            ->assertOk()
            ->assertSee('Only store owners can create or remove a connection key.');

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('developer-storefront.token.generate'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('developer-storefront.website.update'), [
                'website_url' => 'http://127.0.0.1:8080',
            ])
            ->assertForbidden();
    }

    public function test_owner_can_save_website_url_and_download_plugin(): void
    {
        [$owner, $store] = $this->ownerStore('Download Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('developer-storefront.website.update'), [
                'website_url' => 'http://127.0.0.1:8080',
            ])
            ->assertRedirect(route('developer-storefront.settings'));

        $this->assertSame('http://127.0.0.1:8080', $store->fresh()->connectedWebsiteUrl());

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.plugin.download'))
            ->assertOk()
            ->assertDownload('eco-portal-connector.zip');
    }

    public function test_catalog_request_stamps_last_seen_and_page_shows_connected_state(): void
    {
        [$owner, $store, $token] = $this->tokenedStore('Last Seen Store');

        $this->assertNull($store->fresh()->developer_storefront_last_seen_at);
        $this->assertSame(Store::WEBSITE_WAITING, $store->fresh()->websiteConnectionState());

        $this->withToken($token)
            ->getJson('/api/developer-storefront/catalog')
            ->assertOk();

        $store->refresh();
        $this->assertNotNull($store->developer_storefront_last_seen_at);
        $this->assertSame(Store::WEBSITE_CONNECTED, $store->websiteConnectionState());

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.settings'))
            ->assertOk()
            ->assertSee('Connected')
            ->assertSee('WordPress last checked your products');
    }

    public function test_external_order_rejects_currency_that_does_not_match_the_store(): void
    {
        [, $store, $token] = $this->tokenedStore('Currency Store', 'PKR');
        [, $variant] = $this->product($store);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->externalPayload($variant, [
                'currency_code' => 'USD',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['currency_code']);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->externalPayload($variant, [
                'external_order_number' => 'WEB-PKR-OK',
                'currency_code' => 'PKR',
            ]))
            ->assertCreated()
            ->assertJsonPath('order.external_order_number', 'WEB-PKR-OK');
    }

    public function test_wordpress_checkout_template_uses_store_currency_instead_of_hardcoded_usd(): void
    {
        $checkout = file_get_contents(base_path('dev-test-wordpress/wp-content/plugins/eco-portal-connector/templates/checkout.php'));

        $this->assertIsString($checkout);
        $this->assertStringNotContainsString('value="USD"', $checkout);
        $this->assertStringContainsString('name="currency_code"', $checkout);
        $this->assertStringContainsString('$currency', $checkout);
        $this->assertStringContainsString('eco_portal_start_checkout', $checkout);
        $this->assertStringContainsString('Get delivery rates', $checkout);

        $client = file_get_contents(base_path('dev-test-wordpress/wp-content/plugins/eco-portal-connector/includes/class-api-client.php'));
        $this->assertIsString($client);
        $this->assertStringContainsString('/api/v1/checkout', $client);
        $this->assertStringContainsString('delivery-options', $client);
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name, string $currency = 'USD'): array
    {
        $owner = $this->merchant(str($name)->slug().'@example.com');
        $store = $this->store($owner, $name, $currency);
        $this->attach($store, $owner, Store::ROLE_OWNER);

        return [$owner, $store];
    }

    /**
     * @return array{0: User, 1: Store, 2: string}
     */
    private function tokenedStore(string $name, string $currency = 'USD'): array
    {
        [$owner, $store] = $this->ownerStore($name, $currency);
        $token = 'baa_dev_test_'.Str::random(32);
        $store->forceFill([
            'developer_storefront_token_hash' => hash('sha256', $token),
            'developer_storefront_token_created_at' => now(),
        ])->save();

        return [$owner, $store->fresh(), $token];
    }

    private function merchant(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);
    }

    private function store(User $owner, string $name, string $currency = 'USD'): Store
    {
        return Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'logo' => null,
            'address' => 'Test Address',
            'currency' => $currency,
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
    }

    private function attach(Store $store, User $user, string $role): void
    {
        $store->members()->syncWithoutDetaching([
            $user->id => ['role' => $role],
        ]);
    }

    /**
     * @return array{0: Product, 1: ProductVariant}
     */
    private function product(Store $store): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Website Product',
            'slug' => 'website-product-'.Str::random(6),
            'base_price' => 12,
            'sku' => 'WEB-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'is_taxable' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => $product->sku.'-D',
            'price' => 12,
            'stock' => 5,
        ]);

        return [$product, $variant];
    }

    private function externalPayload(ProductVariant $variant, array $overrides = []): array
    {
        return array_replace_recursive([
            'external_order_number' => 'WEB-'.Str::upper(Str::random(8)),
            'external_checkout_reference' => 'checkout-'.Str::random(8),
            'payment_status' => 'paid',
            'payment_gateway' => 'external_test',
            'payment_method' => 'card',
            'payment_reference' => 'pay-'.Str::random(8),
            'placed_at' => now()->toISOString(),
            'currency_code' => 'USD',
            'shipping_total' => 4.50,
            'tax_total' => 1.50,
            'discount_total' => 0,
            'customer' => [
                'full_name' => 'Website Buyer',
                'email' => 'website.buyer@example.test',
                'phone' => '+15550199',
            ],
            'shipping_address' => [
                'name' => 'Website Buyer',
                'address_line1' => '45 Website Road',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '73301',
                'country' => 'US',
            ],
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'unit_price' => 12,
                ],
            ],
        ], $overrides);
    }
}
