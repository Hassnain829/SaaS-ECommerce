<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\ConnectedSiteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WordPressConnectorHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_diagnostics_are_stored_and_shown_on_the_website_page(): void
    {
        [$owner, $store, $token] = $this->connectedStore('Conflict Store');

        $this->withToken($token)
            ->withHeaders([
                'X-Eco-Site-Url' => 'http://127.0.0.1:8080',
                'X-Eco-Plugin-Version' => '1.6.0',
            ])
            ->postJson('/api/v1/site/health', [
                'production_ready' => false,
                'conflicts' => [[
                    'code' => 'woocommerce_active',
                    'severity' => 'block',
                    'title' => 'WooCommerce is still active',
                    'instruction' => 'In WordPress: Plugins → installed plugins → Deactivate WooCommerce.',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('site_diagnostics.production_ready', false)
            ->assertJsonPath('site_diagnostics.conflicts.0.code', 'woocommerce_active')
            ->assertJsonPath('readiness.production', false);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.settings'))
            ->assertOk()
            ->assertSeeText('WordPress is not ready for live shoppers')
            ->assertSeeText('WooCommerce is still active')
            ->assertSeeText('Deactivate WooCommerce')
            ->assertDontSeeText('This portal turned WooCommerce off');
    }

    public function test_catalog_product_detail_binds_published_fields_and_404s_drafts(): void
    {
        [, $store, $token] = $this->connectedStore('Detail Store');
        $live = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Live Detail Shirt',
            'slug' => 'live-detail-'.Str::random(6),
            'base_price' => 18,
            'sku' => 'DET-'.Str::random(6),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $live->id,
            'sku' => $live->sku.'-D',
            'price' => 18,
            'stock' => 4,
        ]);
        $draft = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Draft Detail Shirt',
            'slug' => 'draft-detail-'.Str::random(6),
            'base_price' => 18,
            'sku' => 'DRF-'.Str::random(6),
            'product_type' => 'physical',
            'status' => false,
            'meta' => [],
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/catalog/products/'.$live->id)
            ->assertOk()
            ->assertJsonPath('data.id', $live->id)
            ->assertJsonPath('data.name', 'Live Detail Shirt')
            ->assertJsonPath('data.variants.0.stock', 4);

        $this->withToken($token)
            ->getJson('/api/v1/catalog/products/'.$draft->id)
            ->assertNotFound();
    }

    public function test_wordpress_connector_is_a_presentation_client_without_woocommerce_or_external_checkout(): void
    {
        $pluginDir = base_path('dev-test-wordpress/wp-content/plugins/eco-portal-connector');
        $files = [
            'includes/class-api-client.php',
            'includes/class-admin.php',
            'includes/class-storefront.php',
            'includes/class-conflicts.php',
            'includes/class-catalog-cache.php',
            'includes/class-events.php',
            'templates/checkout.php',
            'templates/cart.php',
            'templates/catalog.php',
            'templates/product.php',
            'templates/order.php',
            'assets/js/checkout.js',
        ];

        foreach ($files as $relative) {
            $contents = file_get_contents($pluginDir.'/'.$relative);
            $this->assertIsString($contents, $relative);
            $this->assertStringNotContainsString('deactivate_plugins', $contents, $relative);
            $this->assertStringNotContainsString('/api/v1/external/orders', $contents, $relative);
            $this->assertStringNotContainsString('eco_portal_place_order', $contents, $relative);
            $this->assertStringNotContainsString('sk_live', $contents, $relative);
            $this->assertStringNotContainsString('sk_test', $contents, $relative);
            $this->assertStringNotContainsString('WC()', $contents, $relative);
            $this->assertStringNotContainsString('wc_get_product', $contents, $relative);
        }

        $storefront = file_get_contents($pluginDir.'/includes/class-storefront.php');
        $client = file_get_contents($pluginDir.'/includes/class-api-client.php');
        $admin = file_get_contents($pluginDir.'/includes/class-admin.php');
        $cart = file_get_contents($pluginDir.'/templates/cart.php');
        $checkout = file_get_contents($pluginDir.'/templates/checkout.php');
        $js = file_get_contents($pluginDir.'/assets/js/checkout.js');
        $conflicts = file_get_contents($pluginDir.'/includes/class-conflicts.php');

        $this->assertStringContainsString('intent_cart', $storefront);
        $this->assertStringContainsString('hydrate_cart', $storefront);
        $this->assertStringContainsString('get_product', $storefront);
        $this->assertStringContainsString('eco_portal_order', $storefront);
        $this->assertStringContainsString('Checkout is blocked until Stripe is connected', $storefront);
        $this->assertStringContainsString('report_diagnostics', $client);
        $this->assertStringContainsString('/api/v1/catalog/products', $client);
        $this->assertStringContainsString('Live-shopper readiness', $admin);
        $this->assertStringContainsString('This plugin never turns other plugins off', $admin);
        $this->assertStringContainsString('Catalog subtotal', $cart);
        $this->assertStringContainsString('Not the checkout total', $cart);
        $this->assertStringContainsString('This site will not take payment itself', $checkout);
        $this->assertStringContainsString('confirmPayment', $js);
        $this->assertStringContainsString('woocommerce/woocommerce.php', $conflicts);
        $this->assertStringContainsString('unsafe_checkout_cache', $conflicts);
        $this->assertStringContainsString('Eco_Portal_Catalog_Cache::get', $client);
        $this->assertStringContainsString('hash_hmac', file_get_contents($pluginDir.'/includes/class-events.php'));
        $this->assertStringNotContainsString('/api/v1/checkout', file_get_contents($pluginDir.'/includes/class-catalog-cache.php'));
    }

    /**
     * @return array{0: User, 1: Store, 2: string}
     */
    private function connectedStore(string $name): array
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
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);
        $issued = app(ConnectedSiteService::class)->issuePrimaryCredential($store);
        app(ConnectedSiteService::class)->bindWebsiteUrl($store, 'http://127.0.0.1:8080');

        return [$owner, $store->fresh(), $issued['plain']];
    }
}
