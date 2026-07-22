<?php

namespace Tests\Feature;

use App\Data\Payments\PaymentIntentResult;
use App\Data\Payments\PaymentWebhookResult;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Payments\StripePlatformPaymentProvider;
use App\Support\CheckoutMode;
use App\Support\OrderLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase5PlatformCheckoutStripeTest extends TestCase
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
                    ],
                );
            }

            public function retrievePaymentIntent(string $providerIntentId, ?string $mode = null): PaymentWebhookResult
            {
                return new PaymentWebhookResult(
                    eventType: 'payment_intent.succeeded',
                    providerIntentId: $providerIntentId,
                    status: 'succeeded',
                    amount: '24.00',
                    currencyCode: 'USD',
                    raw: [
                        'id' => 'client_confirm_'.$providerIntentId,
                        'type' => 'payment_intent.succeeded',
                        'object' => [
                            'id' => $providerIntentId,
                            'status' => 'succeeded',
                            'amount' => 2400,
                            'currency' => 'usd',
                        ],
                    ],
                );
            }
        });
    }

    public function test_platform_checkout_requires_storefront_token(): void
    {
        $this->postJson('/api/v1/checkout', [])
            ->assertUnauthorized();
    }

    public function test_platform_checkout_rejects_raw_card_data(): void
    {
        [$store, $token] = $this->tokenedStore('Platform Card Store');
        [, $variant] = $this->product($store);
        $payload = $this->payload($variant);
        $payload['payment'] = ['card_number' => '4242424242424242'];

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment'])
            ->assertJsonPath('errors.payment.0', 'Raw payment card data must not be sent to this API. Use Stripe.js in the browser instead.');
    }

    public function test_platform_checkout_creates_checkout_payment_intent_and_reserves_inventory_with_server_totals(): void
    {
        [$store, $token] = $this->tokenedStore('Platform Checkout Store');
        [, $variant] = $this->product($store, ['price' => 12, 'stock' => 5]);
        $payload = $this->payload($variant, [
            'grand_total' => 999,
            'tax_total' => 50,
            'discount_total' => 10,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $payload)
            ->assertCreated()
            ->assertJsonPath('checkout.checkout_number', 'CHK-1001')
            ->assertJsonPath('checkout.status', Checkout::STATUS_PAYMENT_PENDING)
            ->assertJsonPath('checkout.subtotal', '24.00')
            ->assertJsonPath('checkout.grand_total', '24.00')
            ->assertJsonPath('payment.provider', 'stripe')
            ->assertJsonPath('payment.provider_intent_id', 'pi_test_checkout_1')
            ->assertJsonPath('payment.client_secret', 'pi_test_checkout_1_secret_test')
            ->assertJsonPath('payment.publishable_key', 'pk_test_platform_checkout');

        $this->assertSame(0, Order::query()->where('store_id', $store->id)->count());
        $this->assertSame(3, (int) $variant->fresh()->stock);

        $this->assertDatabaseHas('checkouts', [
            'store_id' => $store->id,
            'checkout_number' => 'CHK-1001',
            'status' => Checkout::STATUS_PAYMENT_PENDING,
            'payment_provider' => 'stripe',
            'stripe_payment_intent_id' => 'pi_test_checkout_1',
            'subtotal' => 24.00,
            'grand_total' => 24.00,
        ]);
        $this->assertDatabaseHas('payment_intents', [
            'store_id' => $store->id,
            'provider' => 'stripe',
            'provider_intent_id' => 'pi_test_checkout_1',
            'amount' => 24.00,
            'amount_minor' => 2400,
        ]);
        $this->assertDatabaseHas('checkout_events', [
            'store_id' => $store->id,
            'event_type' => 'payment.intent_created',
        ]);
        $this->assertDatabaseHas('inventory_reservations', [
            'store_id' => $store->id,
            'reference_type' => 'checkout',
            'reference_id' => '1',
            'quantity' => 2,
            'status' => 'active',
        ]);
    }

    public function test_platform_checkout_rejects_cross_store_variant(): void
    {
        [$store, $token] = $this->tokenedStore('Platform Safe Store');
        [$otherStore] = $this->tokenedStore('Platform Other Store');
        [, $otherVariant] = $this->product($otherStore);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($otherVariant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.variant_id']);

        $this->assertSame(0, Checkout::query()->where('store_id', $store->id)->count());
    }

    public function test_stripe_success_webhook_converts_checkout_to_order_once(): void
    {
        [$store, $token] = $this->tokenedStore('Platform Webhook Store');
        [, $variant] = $this->product($store, ['price' => 12, 'stock' => 5]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant))
            ->assertCreated();

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame(3, (int) $variant->fresh()->stock);

        $body = $this->stripeEvent('payment_intent.succeeded', 'pi_test_checkout_'.$checkout->id, 'succeeded', 2400);
        $this->postStripeWebhook($body)->assertOk();

        $this->assertSame(1, Order::query()->where('store_id', $store->id)->count());
        $order = Order::query()->where('store_id', $store->id)->firstOrFail();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'store_id' => $store->id,
            'order_source' => 'platform_checkout',
            'channel' => 'dev_storefront',
            'payment_gateway' => 'stripe',
            'payment_reference' => 'pi_test_checkout_'.$checkout->id,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'grand_total' => 24.00,
        ]);
        $this->assertDatabaseHas('order_addresses', [
            'order_id' => $order->id,
            'type' => 'shipping',
            'address_line1' => '123 Platform Way',
            'city' => 'Austin',
            'country' => 'US',
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'total' => 24.00,
        ]);
        $this->assertDatabaseHas('payment_intents', [
            'provider_intent_id' => 'pi_test_checkout_'.$checkout->id,
            'order_id' => $order->id,
            'status' => 'succeeded',
        ]);
        $this->assertDatabaseHas('checkouts', [
            'id' => $checkout->id,
            'status' => Checkout::STATUS_CONVERTED,
            'converted_order_id' => $order->id,
        ]);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_PAYMENT_SUCCEEDED,
        ]);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_INVENTORY_DEDUCTED,
        ]);
        $this->assertDatabaseHas('inventory_reservations', [
            'reference_type' => 'checkout',
            'reference_id' => (string) $checkout->id,
            'status' => 'deducted',
            'order_id' => $order->id,
        ]);
        $this->assertSame(3, (int) $variant->fresh()->stock);

        $body = $this->stripeEvent('payment_intent.succeeded', 'pi_test_checkout_'.$checkout->id, 'succeeded', 2400);
        $this->postStripeWebhook($body)->assertOk();

        $this->assertSame(1, Order::query()->where('store_id', $store->id)->count());
    }

    public function test_client_confirmation_endpoint_verifies_stripe_and_converts_checkout_without_webhook(): void
    {
        [$store, $token] = $this->tokenedStore('Platform Client Confirm Store');
        [, $variant] = $this->product($store, ['price' => 12, 'stock' => 5]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant))
            ->assertCreated();

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();

        $this->withToken($token)
            ->postJson('/api/v1/checkout/'.$checkout->id.'/confirm')
            ->assertOk()
            ->assertJsonPath('message', 'Platform checkout converted to an order.')
            ->assertJsonPath('order.order_number', '#1002')
            ->assertJsonPath('order.payment_status', OrderLifecycle::PAYMENT_PAID);

        $this->assertSame(1, Order::query()->where('store_id', $store->id)->count());
        $order = Order::query()->where('store_id', $store->id)->firstOrFail();

        $this->assertDatabaseHas('checkouts', [
            'id' => $checkout->id,
            'status' => Checkout::STATUS_CONVERTED,
            'converted_order_id' => $order->id,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_source' => 'platform_checkout',
            'payment_reference' => 'pi_test_checkout_'.$checkout->id,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
        ]);
        $this->assertDatabaseHas('inventory_reservations', [
            'reference_type' => 'checkout',
            'reference_id' => (string) $checkout->id,
            'status' => 'deducted',
            'order_id' => $order->id,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout/'.$checkout->id.'/confirm')
            ->assertOk()
            ->assertJsonPath('order.order_number', '#1002');

        $this->assertSame(1, Order::query()->where('store_id', $store->id)->count());
    }

    public function test_stripe_failed_webhook_marks_checkout_failed_and_releases_inventory(): void
    {
        [$store, $token] = $this->tokenedStore('Platform Failed Store');
        [, $variant] = $this->product($store, ['price' => 12, 'stock' => 5]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant))
            ->assertCreated();

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame(3, (int) $variant->fresh()->stock);

        $body = $this->stripeEvent('payment_intent.payment_failed', 'pi_test_checkout_'.$checkout->id, 'requires_payment_method', 2400, [
            'last_payment_error' => [
                'code' => 'card_declined',
                'message' => 'The card was declined.',
            ],
        ]);

        $this->postStripeWebhook($body)->assertOk();

        $this->assertSame(0, Order::query()->where('store_id', $store->id)->count());
        $this->assertSame(5, (int) $variant->fresh()->stock);
        $this->assertDatabaseHas('checkouts', [
            'id' => $checkout->id,
            'status' => Checkout::STATUS_FAILED,
        ]);
        $this->assertDatabaseHas('payment_intents', [
            'provider_intent_id' => 'pi_test_checkout_'.$checkout->id,
            'status' => 'failed',
        ]);
        $this->assertDatabaseHas('inventory_reservations', [
            'reference_type' => 'checkout',
            'reference_id' => (string) $checkout->id,
            'status' => 'released',
        ]);
    }

    public function test_stripe_webhook_rejects_invalid_signature(): void
    {
        $body = $this->stripeEvent('payment_intent.succeeded', 'pi_missing', 'succeeded', 2400);

        $this->call('POST', '/api/webhooks/stripe', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't=1,v1=invalid',
        ], $body)->assertStatus(400);
    }

    public function test_platform_checkout_order_is_visible_in_dashboard(): void
    {
        [$store, $token, $owner] = $this->tokenedStore('Platform Dashboard Store');
        [, $variant] = $this->product($store, ['price' => 12, 'stock' => 5]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant))
            ->assertCreated();

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $body = $this->stripeEvent('payment_intent.succeeded', 'pi_test_checkout_'.$checkout->id, 'succeeded', 2400);
        $this->postStripeWebhook($body)->assertOk();

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orders'))
            ->assertOk()
            ->assertSeeText('Platform checkout')
            ->assertSeeText($checkout->checkout_number)
            ->assertSeeText('Stripe')
            ->assertSeeText('Paid');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('Payment was confirmed through platform checkout.')
            ->assertSeeText($checkout->checkout_number)
            ->assertSeeText('Stripe')
            ->assertSeeText('pi_test_checkout_'.$checkout->id);
    }

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

    private function product(Store $store, array $overrides = []): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $overrides['name'] ?? 'Platform Product',
            'slug' => 'platform-product-'.Str::random(6),
            'base_price' => $overrides['price'] ?? 12,
            'sku' => $overrides['sku'] ?? 'PLAT-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
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

    private function payload(ProductVariant $variant, array $overrides = []): array
    {
        return array_replace_recursive([
            'source_channel' => 'dev_storefront',
            'currency_code' => 'USD',
            'shipping_total' => 0,
            'customer' => [
                'full_name' => 'Platform Buyer',
                'email' => 'platform.buyer@example.test',
                'phone' => '+15550188',
            ],
            'shipping_address' => [
                'name' => 'Platform Buyer',
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
    private function stripeEvent(string $type, string $intentId, string $status, int $amount, array $extra = []): string
    {
        return json_encode([
            'id' => 'evt_'.Str::random(12),
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
