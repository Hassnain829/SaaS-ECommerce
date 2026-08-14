<?php

namespace Tests\Feature;

use App\Console\Commands\ExpireAbandonedCheckoutsCommand;
use App\Data\Payments\PaymentIntentResult;
use App\Data\Payments\PaymentWebhookResult;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProviderWebhookEvent;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\User;
use App\Services\Payments\StripePlatformPaymentProvider;
use App\Support\CheckoutMode;
use App\Support\OrderLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformCheckoutHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.default_provider' => 'stripe',
            'payments.stripe.mode' => 'test',
            'payments.stripe.key' => 'pk_test_platform_checkout',
            'payments.stripe.secret' => 'sk_test_platform_checkout',
            'payments.stripe.webhook_secret' => 'whsec_platform_checkout',
            'payments.stripe.modes' => [
                'test' => [
                    'key' => 'pk_test_platform_checkout',
                    'secret' => 'sk_test_platform_checkout',
                    'webhook_secret' => 'whsec_platform_checkout',
                ],
                'live' => [
                    'key' => null,
                    'secret' => null,
                    'webhook_secret' => null,
                ],
            ],
        ]);

        $this->app->instance(StripePlatformPaymentProvider::class, new class(app(\App\Services\Payments\StripeConfig::class)) extends StripePlatformPaymentProvider
        {
            public function createPaymentIntent(Checkout $checkout, array $options = []): PaymentIntentResult
            {
                return new PaymentIntentResult(
                    provider: 'stripe',
                    providerIntentId: 'pi_test_checkout_'.$checkout->id,
                    clientSecret: 'pi_test_checkout_'.$checkout->id.'_secret_test',
                    status: 'requires_payment_method',
                    amount: (string) $checkout->grand_total,
                    currencyCode: $checkout->currency_code,
                    raw: [
                        'id' => 'pi_test_checkout_'.$checkout->id,
                        'status' => 'requires_payment_method',
                        'idempotency_key' => $options['idempotency_key'] ?? null,
                    ],
                );
            }

            public function retrievePaymentIntent(string $providerIntentId, ?string $mode = null): PaymentWebhookResult
            {
                return new PaymentWebhookResult(
                    eventType: 'payment_intent.succeeded',
                    providerIntentId: $providerIntentId,
                    status: 'succeeded',
                    amount: '12.00',
                    currencyCode: 'USD',
                    raw: [
                        'id' => 'client_confirm_'.$providerIntentId,
                        'type' => 'payment_intent.succeeded',
                    ],
                );
            }
        });
    }

    public function test_catalog_excludes_unpublished_deleted_and_cross_store_products(): void
    {
        [$store, $token] = $this->tokenedStore('Catalog Gate Store');
        [$otherStore] = $this->tokenedStore('Other Catalog Store');
        [$live] = $this->product($store, ['name' => 'Live Shirt']);
        [$draft] = $this->product($store, ['name' => 'Draft Shirt']);
        $draft->forceFill(['status' => false])->save();
        [$foreign] = $this->product($otherStore, ['name' => 'Foreign Shirt']);

        $list = $this->withToken($token)
            ->getJson('/api/v1/catalog/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $live->id)
            ->assertHeader('ETag');

        $this->assertStringStartsWith('cat-', (string) $list->json('meta.catalog_version'));

        $this->withToken($token)
            ->getJson('/api/v1/catalog/products/'.$draft->id)
            ->assertNotFound();

        $this->withToken($token)
            ->getJson('/api/v1/catalog/products/'.$foreign->id)
            ->assertNotFound();

        $live->delete();

        $this->withToken($token)
            ->getJson('/api/v1/catalog/products/'.$live->id)
            ->assertNotFound();
    }

    public function test_catalog_returns_not_modified_when_version_matches(): void
    {
        [$store, $token] = $this->tokenedStore('Catalog Cache Store');
        $this->product($store);

        $first = $this->withToken($token)
            ->getJson('/api/v1/catalog/products')
            ->assertOk();

        $etag = $first->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $this->withToken($token)
            ->withHeaders(['If-None-Match' => $etag])
            ->getJson('/api/v1/catalog/products')
            ->assertStatus(304);
    }

    public function test_catalog_includes_published_products_without_variants_and_store_identity(): void
    {
        [$store, $token] = $this->tokenedStore('Virtual Catalog Store');
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Warranty Plan',
            'slug' => 'warranty-plan-'.Str::random(6),
            'base_price' => 12,
            'sku' => 'WAR-'.Str::random(6),
            'product_type' => 'virtual',
            'status' => true,
            'meta' => ['custom_product_type_label' => 'Warranty'],
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/catalog/products')
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.product_type', 'virtual')
            ->assertJsonPath('data.0.variants', [])
            ->assertJsonPath('meta.store.id', $store->id)
            ->assertJsonPath('meta.store.name', 'Virtual Catalog Store')
            ->assertJsonPath('meta.store.currency', 'USD')
            ->assertJsonPath('meta.store.checkout_mode', 'platform_checkout');

        $this->withToken($token)
            ->getJson('/api/v1/catalog/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.tags', []);
    }

    public function test_stale_client_price_is_ignored_and_unpublished_variant_is_rejected(): void
    {
        [$store, $token] = $this->tokenedStore('Stale Price Store');
        [$product, $variant] = $this->product($store, ['price' => 12, 'stock' => 5]);

        $payload = $this->payload($variant, ['items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 999,
            'price' => 999,
        ]]]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $payload)
            ->assertCreated()
            ->assertJsonPath('checkout.items.0.unit_price', '12.00');

        $product->forceFill(['status' => false])->save();

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, ['items' => [[
                'variant_id' => $variant->id,
                'quantity' => 1,
            ]]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.variant_id']);
    }

    public function test_insufficient_stock_and_last_unit_cannot_be_sold_twice(): void
    {
        [$store, $token] = $this->tokenedStore('Last Unit Store');
        [, $variant] = $this->product($store, ['price' => 12, 'stock' => 1]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, ['items' => [[
                'variant_id' => $variant->id,
                'quantity' => 2,
            ]]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, ['items' => [[
                'variant_id' => $variant->id,
                'quantity' => 1,
            ]]]))
            ->assertCreated();

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, ['items' => [[
                'variant_id' => $variant->id,
                'quantity' => 1,
            ]]]))
            ->assertUnprocessable();
    }

    public function test_currency_mismatch_and_cross_store_variant_are_rejected(): void
    {
        [$store, $token] = $this->tokenedStore('Currency Store');
        [$otherStore] = $this->tokenedStore('Foreign Variant Store');
        [, $variant] = $this->product($store);
        [, $foreign] = $this->product($otherStore);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, ['currency_code' => 'EUR']))
            ->assertUnprocessable();

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($foreign, ['items' => [[
                'variant_id' => $foreign->id,
                'quantity' => 1,
            ]]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.variant_id']);
    }

    public function test_duplicate_checkout_submission_reuses_the_idempotency_key(): void
    {
        [$store, $token] = $this->tokenedStore('Idempotent Store');
        [, $variant] = $this->product($store, ['stock' => 5]);
        $payload = $this->payload($variant, ['items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
        ]]]);

        $first = $this->withToken($token)
            ->withHeaders(['Idempotency-Key' => 'checkout-retry-1'])
            ->postJson('/api/v1/checkout', $payload)
            ->assertCreated();

        $checkoutId = $first->json('checkout.id');

        $this->withToken($token)
            ->withHeaders(['Idempotency-Key' => 'checkout-retry-1'])
            ->postJson('/api/v1/checkout', $payload)
            ->assertCreated()
            ->assertJsonPath('checkout.id', $checkoutId);

        $this->assertSame(1, Checkout::query()->where('store_id', $store->id)->count());

        $this->withToken($token)
            ->withHeaders(['Idempotency-Key' => 'checkout-retry-1'])
            ->postJson('/api/v1/checkout', $this->payload($variant, [
                'customer' => ['email' => 'other@example.test', 'full_name' => 'Other'],
                'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            ]))
            ->assertStatus(409);
    }

    public function test_duplicate_and_out_of_order_webhooks_finalize_stock_once(): void
    {
        [$store, $token] = $this->tokenedStore('Webhook Order Store');
        [, $variant] = $this->product($store, ['price' => 12, 'stock' => 5]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, ['items' => [[
                'variant_id' => $variant->id,
                'quantity' => 1,
            ]]]))
            ->assertCreated();

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $amount = \App\Support\Money\CurrencyPrecision::toMinorUnits((string) $checkout->grand_total, $checkout->currency_code);
        $eventId = 'evt_duplicate_success';
        $success = $this->stripeEvent('payment_intent.succeeded', 'pi_test_checkout_'.$checkout->id, 'succeeded', $amount, eventId: $eventId);

        $this->postStripeWebhook($success)->assertOk()->assertJsonMissingPath('duplicate');
        $this->postStripeWebhook($success)->assertOk()->assertJsonPath('duplicate', true);

        $this->assertSame(1, Order::query()->where('store_id', $store->id)->count());
        $this->assertSame(4, (int) $variant->fresh()->stock);

        $failed = $this->stripeEvent('payment_intent.payment_failed', 'pi_test_checkout_'.$checkout->id, 'requires_payment_method', $amount, eventId: 'evt_late_failure');
        $this->postStripeWebhook($failed)->assertOk();

        $this->assertSame(1, Order::query()->where('store_id', $store->id)->count());
        $this->assertSame(Checkout::STATUS_CONVERTED, $checkout->fresh()->status);
        $this->assertSame(4, (int) $variant->fresh()->stock);
        $this->assertDatabaseHas('provider_webhook_events', [
            'provider_event_id' => $eventId,
            'status' => ProviderWebhookEvent::STATUS_PROCESSED,
        ]);
    }

    public function test_failed_then_succeeded_webhook_still_creates_one_order(): void
    {
        [$store, $token] = $this->tokenedStore('Late Success Store');
        [, $variant] = $this->product($store, ['price' => 12, 'stock' => 3]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, ['items' => [[
                'variant_id' => $variant->id,
                'quantity' => 1,
            ]]]))
            ->assertCreated();

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $amount = \App\Support\Money\CurrencyPrecision::toMinorUnits((string) $checkout->grand_total, $checkout->currency_code);

        $this->postStripeWebhook($this->stripeEvent(
            'payment_intent.payment_failed',
            'pi_test_checkout_'.$checkout->id,
            'requires_payment_method',
            $amount,
            eventId: 'evt_first_fail'
        ))->assertOk();

        $this->assertSame(Checkout::STATUS_FAILED, $checkout->fresh()->status);
        $this->assertSame(3, (int) $variant->fresh()->stock);

        $this->postStripeWebhook($this->stripeEvent(
            'payment_intent.succeeded',
            'pi_test_checkout_'.$checkout->id,
            'succeeded',
            $amount,
            eventId: 'evt_later_success'
        ))->assertOk();

        $this->assertSame(1, Order::query()->where('store_id', $store->id)->count());
        $this->assertSame(2, (int) $variant->fresh()->stock);
    }

    public function test_expired_checkout_releases_inventory(): void
    {
        [$store, $token] = $this->tokenedStore('Expire Store');
        [, $variant] = $this->product($store, ['price' => 12, 'stock' => 2]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, ['items' => [[
                'variant_id' => $variant->id,
                'quantity' => 1,
            ]]]))
            ->assertCreated();

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $checkout->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->artisan(ExpireAbandonedCheckoutsCommand::class)->assertSuccessful();

        $this->assertSame(Checkout::STATUS_CANCELLED, $checkout->fresh()->status);
        $this->assertSame(2, (int) $variant->fresh()->stock);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, ['items' => [[
                'variant_id' => $variant->id,
                'quantity' => 1,
            ]]]))
            ->assertCreated();
    }

    public function test_unavailable_delivery_method_is_rejected(): void
    {
        [$store, $token] = $this->tokenedStore('Carrier Fail Store');
        [, $variant] = $this->product($store, ['stock' => 3]);

        $created = $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, ['items' => [[
                'variant_id' => $variant->id,
                'quantity' => 1,
            ]]]))
            ->assertCreated();

        $checkoutId = $created->json('checkout.id');

        $this->withToken($token)
            ->postJson('/api/v1/checkout/'.$checkoutId.'/shipping-method', [
                'shipping_method_id' => 999999,
            ])
            ->assertUnprocessable();
    }

    public function test_confirmation_and_tracking_are_store_scoped_and_token_gated(): void
    {
        [$store, $token] = $this->tokenedStore('Confirm Store');
        [$otherStore, $otherToken] = $this->tokenedStore('Other Confirm Store');
        [, $variant] = $this->product($store, ['price' => 12, 'stock' => 4]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, ['items' => [[
                'variant_id' => $variant->id,
                'quantity' => 1,
            ]]]))
            ->assertCreated();

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $amount = \App\Support\Money\CurrencyPrecision::toMinorUnits((string) $checkout->grand_total, $checkout->currency_code);
        $this->postStripeWebhook($this->stripeEvent(
            'payment_intent.succeeded',
            'pi_test_checkout_'.$checkout->id,
            'succeeded',
            $amount,
            eventId: 'evt_confirm'
        ))->assertOk();

        $checkout->refresh();
        $confirmation = (string) data_get($checkout->metadata, 'storefront_confirmation_token');
        $this->assertStringStartsWith('ordconf_', $confirmation);

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();
        Shipment::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'shipment_number' => 'SHP-'.$order->id,
            'status' => Shipment::STATUS_SHIPPED,
            'direction' => Shipment::DIRECTION_OUTBOUND,
            'tracking_number' => '1Z999AA10123456784',
            'tracking_url' => 'https://example.test/track/1Z999AA10123456784',
            'carrier_service' => 'Ground',
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/orders/confirmation/'.$confirmation)
            ->assertOk()
            ->assertJsonPath('order.order_number', $order->order_number)
            ->assertJsonPath('order.payment_status', OrderLifecycle::PAYMENT_PAID)
            ->assertJsonPath('order.shipments.0.tracking_number', '1Z999AA10123456784')
            ->assertJsonMissingPath('order.shipments.0.label_url');

        $this->withToken($otherToken)
            ->getJson('/api/v1/orders/confirmation/'.$confirmation)
            ->assertNotFound();

        $this->withToken($token)
            ->getJson('/api/v1/orders/confirmation/ordconf_not_a_real_token')
            ->assertNotFound();
    }

    public function test_payments_copy_says_woocommerce_stripe_cannot_be_reused(): void
    {
        [$store, , $owner] = $this->tokenedStore('Stripe Decision Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index'))
            ->assertOk()
            ->assertSeeText('A Stripe account already used in WooCommerce cannot be reused here');
    }

    public function test_wordpress_plugin_sends_idempotency_and_reads_confirmation(): void
    {
        $client = file_get_contents(base_path('dev-test-wordpress/wp-content/plugins/eco-portal-connector/includes/class-api-client.php'));
        $storefront = file_get_contents(base_path('dev-test-wordpress/wp-content/plugins/eco-portal-connector/includes/class-storefront.php'));
        $checkout = file_get_contents(base_path('dev-test-wordpress/wp-content/plugins/eco-portal-connector/templates/checkout.php'));
        $catalog = file_get_contents(base_path('dev-test-wordpress/wp-content/plugins/eco-portal-connector/templates/catalog.php'));
        $order = file_get_contents(base_path('dev-test-wordpress/wp-content/plugins/eco-portal-connector/templates/order.php'));

        $this->assertIsString($client);
        $this->assertIsString($catalog);
        $this->assertIsString($order);
        $this->assertStringContainsString('Idempotency-Key', $client);
        $this->assertStringContainsString('get_order_confirmation', $client);
        $this->assertStringContainsString('/api/v1/orders/confirmation/', $client);
        $this->assertStringContainsString('/api/v1/catalog/products', $client);
        $this->assertStringContainsString('/api/v1/catalog/categories', $client);
        $this->assertStringNotContainsString('/api/developer-storefront/catalog', $client);
        $this->assertStringContainsString('get_order_confirmation', $storefront);
        $this->assertStringContainsString('eco_category', $catalog);
        $this->assertStringContainsString('tracking_number', $order);
        $this->assertStringContainsString('fulfillment_status', $order);
        $this->assertStringNotContainsString('sk_live', $checkout);
        $this->assertStringNotContainsString('sk_test', $client);
        $this->assertStringNotContainsString('external_order_number', $checkout);
    }

    /**
     * @return array{0: Store, 1: string, 2: User}
     */
    private function tokenedStore(string $name): array
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
            'settings' => ['checkout_mode' => CheckoutMode::PLATFORM],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        $token = 'baa_dev_test_'.Str::random(32);
        $store->forceFill([
            'developer_storefront_token_hash' => hash('sha256', $token),
            'developer_storefront_token_created_at' => now(),
        ])->save();

        return [$store, $token, $owner];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: Product, 1: ProductVariant}
     */
    private function product(Store $store, array $overrides = []): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $overrides['name'] ?? 'Hardening Product',
            'slug' => Str::slug($overrides['name'] ?? 'hardening-product').'-'.Str::random(6),
            'base_price' => $overrides['price'] ?? 12,
            'sku' => 'HRD-'.Str::random(6),
            'product_type' => 'physical',
            'status' => true,
            'is_taxable' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => $overrides['variant_sku'] ?? $product->sku.'-D',
            'price' => $overrides['price'] ?? 12,
            'stock' => $overrides['stock'] ?? 5,
        ]);

        return [$product, $variant];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(ProductVariant $variant, array $overrides = []): array
    {
        return array_replace_recursive([
            'source_channel' => 'wordpress_storefront',
            'currency_code' => 'USD',
            'customer' => [
                'full_name' => 'Hardening Buyer',
                'email' => 'hardening.buyer@example.test',
                'phone' => '+15550188',
            ],
            'shipping_address' => [
                'name' => 'Hardening Buyer',
                'address_line1' => '123 Platform Way',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '73301',
                'country' => 'US',
                'phone' => '+15550188',
            ],
            'billing_address' => [
                'same_as_shipping' => true,
            ],
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 2,
                ],
            ],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function stripeEvent(string $type, string $intentId, string $status, int $amount, array $extra = [], ?string $eventId = null): string
    {
        return json_encode([
            'id' => $eventId ?: 'evt_'.Str::random(12),
            'object' => 'event',
            'type' => $type,
            'data' => [
                'object' => array_replace_recursive([
                    'id' => $intentId,
                    'object' => 'payment_intent',
                    'status' => $status,
                    'amount' => $amount,
                    'currency' => 'usd',
                ], $extra),
            ],
        ], JSON_UNESCAPED_SLASHES);
    }

    private function stripeSignature(string $payload): string
    {
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_platform_checkout');

        return 't='.$timestamp.',v1='.$signature;
    }

    private function postStripeWebhook(string $payload)
    {
        return $this->call('POST', '/api/webhooks/stripe', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $this->stripeSignature($payload),
        ], $payload);
    }
}
