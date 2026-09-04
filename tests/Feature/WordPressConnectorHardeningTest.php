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
            ->assertSeeText('Your website reported a problem')
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
            'includes/class-checkout-attempt.php',
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
        $attempt = file_get_contents($pluginDir.'/includes/class-checkout-attempt.php');

        $this->assertStringContainsString('intent_cart', $storefront);
        $this->assertStringContainsString('hydrate_cart', $storefront);
        $this->assertStringContainsString('get_product', $storefront);
        $this->assertStringContainsString('eco_portal_order', $storefront);
        $this->assertStringContainsString('Checkout is blocked until Stripe is connected', $storefront);
        $this->assertStringContainsString('report_diagnostics', $client);
        $this->assertStringContainsString('/api/v1/catalog/products', $client);
        $this->assertStringContainsString('Live-shopper readiness', $admin);
        $this->assertStringContainsString('This plugin never turns other plugins off', $admin);
        $this->assertStringContainsString('<strong>Subtotal:</strong>', $cart);
        $this->assertStringContainsString('Shipping and tax are calculated at checkout.', $cart);
        $this->assertStringContainsString('This website will not take payment itself.', $storefront);
        $this->assertStringContainsString('confirmPayment', $js);
        $this->assertStringContainsString('request_fingerprint', $attempt);
        $this->assertStringContainsString('idempotency_key', $storefront);
        $this->assertStringContainsString('self::ensure_checkout_attempt();', $storefront);
        $this->assertStringContainsString('Eco_Portal_Checkout_Attempt::begin(', $storefront);
        $this->assertStringContainsString('checkout_attempt_token', $checkout);
        $this->assertStringContainsString('hash_equals($token, $posted_token)', $attempt);
        $this->assertStringContainsString('$client->create_checkout($payload, $idempotency_key)', $storefront);
        $this->assertStringContainsString('self::save_checkout_state($attempt_state)', $storefront);
        $this->assertStringNotContainsString('usleep(', $storefront);
        $this->assertStringNotContainsString('for ($attempt = 0; $attempt < 12; $attempt++)', $storefront);
        $this->assertStringContainsString('create_checkout(array $payload, string $idempotency_key)', $client);
        $this->assertStringContainsString('\'Idempotency-Key\' => $idempotency_key', $client);
        $this->assertStringNotContainsString('wp_generate_uuid4()', substr($client, strpos($client, 'function create_checkout'), 700));

        $startHandlerStart = strpos($storefront, 'public static function handle_start_checkout');
        $startHandlerEnd = strpos($storefront, 'public static function handle_select_shipping', $startHandlerStart ?: 0);
        $this->assertNotFalse($startHandlerStart);
        $this->assertNotFalse($startHandlerEnd);
        $startHandler = substr($storefront, (int) $startHandlerStart, (int) $startHandlerEnd - (int) $startHandlerStart);
        $this->assertStringNotContainsString('wp_checkout_', $startHandler);
        $this->assertStringNotContainsString('wp_generate_uuid4', $startHandler);

        $this->assertStringContainsString('woocommerce/woocommerce.php', $conflicts);
        $this->assertStringContainsString('unsafe_checkout_cache', $conflicts);
        $this->assertStringContainsString('Eco_Portal_Catalog_Cache::get', $client);
        $this->assertStringContainsString('hash_hmac', file_get_contents($pluginDir.'/includes/class-events.php'));
        $this->assertStringNotContainsString('/api/v1/checkout', file_get_contents($pluginDir.'/includes/class-catalog-cache.php'));
    }

    public function test_wordpress_checkout_uses_session_bound_durable_browser_polling(): void
    {
        $pluginDir = base_path('dev-test-wordpress/wp-content/plugins/eco-portal-connector');
        $storefront = file_get_contents($pluginDir.'/includes/class-storefront.php');
        $checkout = file_get_contents($pluginDir.'/templates/checkout.php');
        $js = file_get_contents($pluginDir.'/assets/js/checkout.js');

        $this->assertIsString($storefront);
        $this->assertIsString($checkout);
        $this->assertIsString($js);

        $this->assertStringContainsString("add_action('wp_ajax_nopriv_eco_portal_checkout_status'", $storefront);
        $this->assertStringContainsString("add_action('wp_ajax_eco_portal_checkout_status'", $storefront);
        $this->assertStringContainsString("check_ajax_referer('eco_portal_checkout_status', 'nonce')", $storefront);
        $this->assertStringContainsString("\$checkout_id = (int) (\$state['checkout_id'] ?? 0)", $storefront);
        $this->assertStringNotContainsString("\$_POST['checkout_id']", $storefront);
        $this->assertStringContainsString("'httponly' => true", $storefront);
        $this->assertStringContainsString("preg_match('/^[A-Za-z0-9]{20}$/', \$sid)", $storefront);
        $this->assertStringContainsString("\$state['step'] = 'confirming'", $storefront);
        $this->assertStringContainsString("\$state['step'] = 'processing'", $storefront);
        $this->assertStringContainsString('set_transient(\'eco_portal_co_\'.$sid, $state, HOUR_IN_SECONDS)', $storefront);
        $this->assertStringContainsString('eco-portal-checkout-status', $checkout);
        $this->assertStringContainsString('Do not pay again', $checkout);

        $handlerStart = strpos($storefront, 'public static function handle_checkout_status');
        $handlerEnd = strpos($storefront, 'public static function handle_reset_checkout', $handlerStart ?: 0);
        $this->assertNotFalse($handlerStart);
        $this->assertNotFalse($handlerEnd);
        $statusHandler = substr($storefront, (int) $handlerStart, (int) $handlerEnd - (int) $handlerStart);
        $this->assertStringContainsString('$status === 410', $statusHandler);
        $this->assertStringContainsString('$status === 422', $statusHandler);
        $this->assertStringContainsString('processing_checkout_state', $statusHandler);
        $this->assertStringNotContainsString('usleep(', $statusHandler);
        $this->assertSame(1, substr_count($statusHandler, 'self::save_cart([])'));
        $this->assertSame(1, substr_count($statusHandler, 'self::clear_checkout_state()'));

        $localizedStart = strpos($storefront, "wp_localize_script('eco-portal-checkout'");
        $localizedEnd = strpos($storefront, ']);', $localizedStart ?: 0);
        $this->assertNotFalse($localizedStart);
        $this->assertNotFalse($localizedEnd);
        $localizedConfig = substr($storefront, (int) $localizedStart, (int) $localizedEnd - (int) $localizedStart);
        $this->assertStringContainsString('statusNonce', $localizedConfig);
        $this->assertStringNotContainsString('token', strtolower($localizedConfig));
        $this->assertStringNotContainsString('Authorization', $js);
        $this->assertStringNotContainsString('Bearer', $js);
        $this->assertStringNotContainsString('checkout_id', $js);

        $this->assertSame(1, substr_count($js, 'stripe.confirmPayment('));
        $this->assertStringContainsString("await requestStatus('begin')", $js);
        $this->assertTrue(strpos($js, "await requestStatus('begin')") < strpos($js, 'stripe.confirmPayment('));
        $this->assertStringContainsString('return_url: config.returnUrl', $js);
        $this->assertStringContainsString('if (statusRoot) {', $js);
        $this->assertStringContainsString("requestStatus('poll')", $js);
        $this->assertStringContainsString("requestStatus('complete')", $js);
        $this->assertStringContainsString('pollingBudgetMs = 120000', $js);
        $this->assertStringContainsString('Math.min(10000, Math.ceil(delayMs * 1.6))', $js);
        $this->assertStringContainsString('You can safely check the same order status again', $js);
        $this->assertStringNotContainsString('form.submit()', $js);
        $this->assertStringNotContainsString('create_checkout', $js);
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
