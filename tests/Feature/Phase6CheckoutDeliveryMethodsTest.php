<?php

namespace Tests\Feature;

use App\Data\Payments\PaymentIntentResult;
use App\Data\Payments\PaymentIntentUpdateResult;
use App\Data\Payments\PaymentWebhookResult;
use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\User;
use App\Services\Payments\StripePlatformPaymentProvider;
use App\Support\CheckoutMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6CheckoutDeliveryMethodsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.default_provider' => 'stripe',
            'payments.stripe.mode' => 'test',
            'payments.stripe.key' => 'pk_test_phase6b',
            'payments.stripe.secret' => 'sk_test_phase6b',
            'payments.stripe.webhook_secret' => 'whsec_phase6b',
        ]);

        $this->app->instance(StripePlatformPaymentProvider::class, new class(app(\App\Services\Payments\StripeConfig::class)) extends StripePlatformPaymentProvider
        {
            private int $counter = 0;

            public function createPaymentIntent(Checkout $checkout, array $options = []): PaymentIntentResult
            {
                $this->counter++;
                $id = 'pi_phase6b_'.$checkout->id.'_'.$this->counter;

                return new PaymentIntentResult(
                    provider: 'stripe',
                    providerIntentId: $id,
                    clientSecret: $id.'_secret_test',
                    status: 'requires_payment_method',
                    amount: (float) $checkout->grand_total,
                    currencyCode: $checkout->currency_code,
                    raw: ['id' => $id, 'status' => 'requires_payment_method'],
                );
            }

            public function retrievePaymentIntent(string $providerIntentId, ?string $mode = null): PaymentWebhookResult
            {
                return new PaymentWebhookResult(
                    eventType: 'payment_intent.succeeded',
                    providerIntentId: $providerIntentId,
                    status: 'succeeded',
                    amount: 30.50,
                    currencyCode: 'USD',
                    raw: [
                        'id' => 'client_confirm_'.$providerIntentId,
                        'type' => 'payment_intent.succeeded',
                        'object' => [
                            'id' => $providerIntentId,
                            'status' => 'succeeded',
                            'amount' => 3050,
                            'currency' => 'usd',
                        ],
                    ],
                );
            }

            public function cancelPaymentIntent(string $providerIntentId, array $options = []): PaymentWebhookResult
            {
                return new PaymentWebhookResult(
                    eventType: 'payment_intent.canceled',
                    providerIntentId: $providerIntentId,
                    status: 'canceled',
                    amount: null,
                    currencyCode: null,
                    raw: ['id' => $providerIntentId, 'status' => 'canceled'],
                );
            }

            public function updatePaymentIntentAmount(
                string $providerIntentId,
                int $amountMinor,
                string $currencyCode,
                array $options = [],
            ): PaymentIntentUpdateResult {
                return new PaymentIntentUpdateResult(
                    providerIntentId: $providerIntentId,
                    amountMinor: $amountMinor,
                    currencyCode: strtoupper($currencyCode),
                    status: 'requires_payment_method',
                    clientSecret: $providerIntentId.'_secret_test',
                    raw: [
                        'id' => $providerIntentId,
                        'status' => 'requires_payment_method',
                        'amount' => $amountMinor,
                        'currency' => strtolower($currencyCode),
                        'client_secret' => $providerIntentId.'_secret_test',
                    ],
                );
            }
        });
    }

    public function test_checkout_delivery_options_match_zone_and_calculate_flat_and_free_shipping(): void
    {
        [$store, $token] = $this->tokenedStore('Phase 6B Delivery Store');
        [, $variant] = $this->product($store, ['price' => 12, 'stock' => 5]);
        $this->shippingSetup($store);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($variant))
            ->assertCreated();

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();

        $this->withToken($token)
            ->postJson('/api/v1/checkout/'.$checkout->id.'/delivery-options', [
                'shipping_address' => [
                    'country' => 'US',
                    'state' => 'CA',
                    'postal_code' => '94105',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('delivery_options.0.name', 'Standard delivery')
            ->assertJsonPath('delivery_options.0.amount_formatted', '6.50')
            ->assertJsonPath('delivery_options.1.name', 'Economy delivery')
            ->assertJsonPath('delivery_options.1.amount_formatted', '0.00');

    }

    public function test_selecting_shipping_method_updates_checkout_totals_snapshot_event_and_payment_intent(): void
    {
        [$store, $token] = $this->tokenedStore('Phase 6B Select Store');
        [, $variant] = $this->product($store, ['price' => 12, 'stock' => 5]);
        $methods = $this->shippingSetup($store);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($variant))
            ->assertCreated()
            ->assertJsonPath('checkout.grand_total', '24.00')
            ->assertJsonPath('payment.provider_intent_id', 'pi_phase6b_1_1');

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();

        $this->withToken($token)
            ->postJson('/api/v1/checkout/'.$checkout->id.'/shipping-method', [
                'shipping_method_id' => $methods['standard']->id,
            ])
            ->assertOk()
            ->assertJsonPath('checkout.shipping_method_id', $methods['standard']->id)
            ->assertJsonPath('checkout.shipping_total', '6.50')
            ->assertJsonPath('checkout.grand_total', '30.50')
            ->assertJsonPath('checkout.shipping_snapshot.method_name', 'Standard delivery')
            ->assertJsonPath('payment.provider_intent_id', 'pi_phase6b_1_1')
            ->assertJsonPath('payment.client_secret', 'pi_phase6b_1_1_secret_test');

        $paymentIntent = \App\Models\PaymentIntent::query()->where('checkout_id', $checkout->id)->sole();
        $this->assertDatabaseHas('checkouts', [
            'id' => $checkout->id,
            'shipping_method_id' => $methods['standard']->id,
            'shipping_total' => 6.50,
            'grand_total' => 30.50,
            'stripe_payment_intent_id' => 'pi_phase6b_1_1',
        ]);
        $this->assertDatabaseHas('payment_intents', [
            'id' => $paymentIntent->id,
            'checkout_id' => $checkout->id,
            'provider_intent_id' => 'pi_phase6b_1_1',
            'amount' => 30.50,
            'amount_minor' => 3050,
        ]);
        $this->assertDatabaseHas('checkout_events', [
            'checkout_id' => $checkout->id,
            'event_type' => 'shipping.method_selected',
        ]);
    }

    public function test_platform_checkout_can_snapshot_selected_shipping_method_on_final_order(): void
    {
        [$store, $token] = $this->tokenedStore('Phase 6B Order Snapshot Store');
        [, $variant] = $this->product($store, ['price' => 12, 'stock' => 5]);
        $methods = $this->shippingSetup($store);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($variant, [
                'shipping_method_id' => $methods['standard']->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('checkout.shipping_total', '6.50')
            ->assertJsonPath('checkout.grand_total', '30.50');

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();

        $this->withToken($token)
            ->postJson('/api/v1/checkout/'.$checkout->id.'/confirm')
            ->assertOk()
            ->assertJsonPath('order.total', '30.50');

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();

        $this->assertSame('Standard delivery', data_get($order->meta, 'shipping.method_name'));
        $this->assertSame(6.50, (float) data_get($order->meta, 'shipping.amount'));
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'shipping' => 6.50,
            'grand_total' => 30.50,
        ]);
    }

    public function test_checkout_rejects_cross_store_shipping_method(): void
    {
        [$store, $token] = $this->tokenedStore('Phase 6B Store Safe');
        [$otherStore] = $this->tokenedStore('Phase 6B Other Store');
        [, $variant] = $this->product($store, ['stock' => 5]);
        $otherMethods = $this->shippingSetup($otherStore);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($variant))
            ->assertCreated();

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();

        $this->withToken($token)
            ->postJson('/api/v1/checkout/'.$checkout->id.'/shipping-method', [
                'shipping_method_id' => $otherMethods['standard']->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shipping_method_id']);
    }

    public function test_external_order_sync_preserves_external_shipping_snapshot_without_internal_method(): void
    {
        [$store, $token] = $this->tokenedStore('Phase 6B External Store', false);
        [, $variant] = $this->product($store, ['price' => 12, 'stock' => 5]);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', [
                'external_order_number' => 'WEB-SHIP-100',
                'payment_status' => 'paid',
                'payment_gateway' => 'external_test',
                'payment_method' => 'card',
                'payment_reference' => 'pay-ship-100',
                'currency_code' => 'USD',
                'shipping_total' => 4.25,
                'shipping_method_name' => 'Website standard delivery',
                'shipping_carrier_name' => 'External courier',
                'shipping_delivery_speed_label' => '3-5 days',
                'customer' => [
                    'full_name' => 'External Buyer',
                    'email' => 'shipper@example.test',
                ],
                'shipping_address' => [
                    'address_line1' => '45 External Road',
                    'city' => 'Austin',
                    'state' => 'TX',
                    'country' => 'US',
                ],
                'billing_address' => ['same_as_shipping' => true],
                'items' => [
                    ['variant_id' => $variant->id, 'quantity' => 2, 'unit_price' => '12.00'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('order.shipping', '4.25')
            ->assertJsonPath('order.total', '28.25');

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame('Website standard delivery', data_get($order->meta, 'shipping.method_name'));
        $this->assertSame('External courier', data_get($order->meta, 'shipping.carrier_name'));
        $this->assertSame(4.25, (float) data_get($order->meta, 'shipping.amount'));
    }

    public function test_shipping_settings_page_exposes_checkout_delivery_method_fields(): void
    {
        [$store,, $owner] = $this->tokenedStore('Phase 6B Settings Store');
        $this->shippingSetup($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSee('postal_patterns', false)
            ->assertSee('delivery_speed_label', false)
            ->assertSee('free_over_amount', false)
            ->assertSee('min_order_amount', false)
            ->assertSee('max_order_amount', false)
            ->assertDontSeeText('Buy label');
    }

    private function tokenedStore(string $name, bool $platformMode = true): array
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
            'settings' => $platformMode ? ['checkout_mode' => CheckoutMode::PLATFORM] : [],
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
            'name' => $overrides['name'] ?? 'Delivery Product',
            'slug' => 'delivery-product-'.Str::random(6),
            'base_price' => $overrides['price'] ?? 12,
            'sku' => $overrides['sku'] ?? 'DEL-'.Str::random(4),
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

    private function shippingSetup(Store $store): array
    {
        $carrier = Carrier::query()->create([
            'name' => 'Manual courier '.Str::random(5),
            'code' => 'manual-'.Str::random(8),
            'type' => Carrier::TYPE_MANUAL,
            'is_system' => false,
            'is_active' => true,
        ]);
        $account = CarrierAccount::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $carrier->id,
            'display_name' => 'Main manual courier',
            'connection_type' => CarrierAccount::CONNECTION_MANUAL,
            'status' => CarrierAccount::STATUS_ENABLED,
            'supported_countries' => ['US'],
            'enabled_for_checkout' => true,
        ]);
        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'California',
            'countries' => ['US'],
            'regions' => ['CA'],
            'postal_patterns' => ['941*'],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $standard = ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'carrier_account_id' => $account->id,
            'name' => 'Standard delivery',
            'code' => 'standard-delivery',
            'description' => 'Arrives in 2-4 business days',
            'delivery_speed_label' => '2-4 business days',
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 6.50,
            'enabled_for_checkout' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $economy = ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'carrier_account_id' => $account->id,
            'name' => 'Economy delivery',
            'code' => 'economy-delivery',
            'description' => 'Free basic delivery',
            'delivery_speed_label' => '5-7 business days',
            'rate_type' => ShippingMethod::RATE_FREE,
            'flat_rate' => 0,
            'enabled_for_checkout' => true,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        return ['carrier' => $carrier, 'account' => $account, 'zone' => $zone, 'standard' => $standard, 'economy' => $economy];
    }

    private function checkoutPayload(ProductVariant $variant, array $overrides = []): array
    {
        return array_replace_recursive([
            'source_channel' => 'dev_storefront',
            'currency_code' => 'USD',
            'customer' => [
                'full_name' => 'Delivery Buyer',
                'email' => 'delivery.buyer@example.test',
                'phone' => '+15550188',
            ],
            'shipping_address' => [
                'name' => 'Delivery Buyer',
                'address_line1' => '123 Delivery Way',
                'city' => 'San Francisco',
                'state' => 'CA',
                'postal_code' => '94105',
                'country' => 'US',
                'phone' => '+15550188',
            ],
            'billing_address' => ['same_as_shipping' => true],
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 2],
            ],
        ], $overrides);
    }
}
