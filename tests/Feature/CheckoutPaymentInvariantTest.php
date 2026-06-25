<?php

namespace Tests\Feature;

use App\Data\Payments\PaymentIntentResult;
use App\Data\Payments\PaymentWebhookResult;
use App\Exceptions\CheckoutPaymentAmountMismatchException;
use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Checkout;
use App\Models\CheckoutEvent;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderTaxLine;
use App\Models\PaymentCapture;
use App\Models\PaymentIntent;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use App\Models\User;
use App\Services\CheckoutConversionService;
use App\Services\Payments\StripePlatformPaymentProvider;
use App\Support\CheckoutMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutPaymentInvariantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.default_provider' => 'stripe',
            'payments.stripe.mode' => 'test',
            'payments.stripe.key' => 'pk_test_invariant',
            'payments.stripe.secret' => 'sk_test_invariant',
            'payments.stripe.webhook_secret' => 'whsec_invariant',
        ]);

        $this->app->instance(StripePlatformPaymentProvider::class, new class(app(\App\Services\Payments\StripeConfig::class)) extends StripePlatformPaymentProvider
        {
            public function createPaymentIntent(Checkout $checkout, array $options = []): PaymentIntentResult
            {
                return new PaymentIntentResult(
                    provider: 'stripe',
                    providerIntentId: 'pi_invariant_'.$checkout->id,
                    clientSecret: 'pi_invariant_'.$checkout->id.'_secret_test',
                    status: 'requires_payment_method',
                    amount: (float) $checkout->grand_total,
                    currencyCode: $checkout->currency_code,
                    raw: ['id' => 'pi_invariant_'.$checkout->id, 'status' => 'requires_payment_method'],
                );
            }
        });
    }

    public function test_provider_amount_mismatch_throws_and_does_not_create_order_or_deduct_inventory(): void
    {
        [$checkout, $variant] = $this->taxedCheckout();

        try {
            app(CheckoutConversionService::class)->handleSucceededPayment($this->paymentResult($checkout, amountMinor: 2100));
            $this->fail('Expected payment amount mismatch exception.');
        } catch (CheckoutPaymentAmountMismatchException $exception) {
            $this->assertSame($checkout->id, $exception->checkoutId);
            $this->assertSame(2750, $exception->expectedMinor);
            $this->assertSame(2100, $exception->providerActualMinor);
            $this->assertSame(2750, $exception->localPaymentIntentMinor);
            $this->assertSame('USD', $exception->expectedCurrency);
            $this->assertSame('USD', $exception->providerCurrency);
        }

        $this->assertSame(0, Order::query()->where('store_id', $checkout->store_id)->count());
        $this->assertSame(Checkout::STATUS_PAYMENT_PENDING, $checkout->fresh()->status);
        $this->assertSame(1, InventoryReservation::query()->where('reference_id', (string) $checkout->id)->where('status', InventoryReservation::STATUS_ACTIVE)->count());
        $this->assertSame(4, (int) $variant->fresh()->stock);
        $this->assertDatabaseHas('checkout_events', [
            'checkout_id' => $checkout->id,
            'event_type' => 'payment.amount_mismatch',
        ]);
    }

    public function test_local_payment_intent_amount_mismatch_throws_before_conversion(): void
    {
        [$checkout] = $this->taxedCheckout();
        PaymentIntent::query()->where('checkout_id', $checkout->id)->update(['amount_minor' => 999]);

        $this->expectException(CheckoutPaymentAmountMismatchException::class);

        app(CheckoutConversionService::class)->handleSucceededPayment($this->paymentResult($checkout, amountMinor: 2750));
    }

    public function test_local_payment_intent_currency_mismatch_blocks_conversion_without_side_effects(): void
    {
        [$checkout, $variant] = $this->taxedCheckout();
        PaymentIntent::query()->where('checkout_id', $checkout->id)->update(['currency_code' => 'EUR']);

        try {
            app(CheckoutConversionService::class)->handleSucceededPayment($this->paymentResult($checkout, amountMinor: 2750, currency: 'USD'));
            $this->fail('Expected payment currency mismatch exception.');
        } catch (CheckoutPaymentAmountMismatchException $exception) {
            $this->assertSame($checkout->id, $exception->checkoutId);
            $this->assertSame('USD', $exception->expectedCurrency);
            $this->assertSame('USD', $exception->providerCurrency);
            $this->assertSame(2750, $exception->localPaymentIntentMinor);
        }

        $this->assertSame(0, Order::query()->where('store_id', $checkout->store_id)->count());
        $this->assertSame(0, PaymentCapture::query()->where('store_id', $checkout->store_id)->count());
        $this->assertSame(Checkout::STATUS_PAYMENT_PENDING, $checkout->fresh()->status);
        $this->assertSame(1, InventoryReservation::query()->where('reference_id', (string) $checkout->id)->where('status', InventoryReservation::STATUS_ACTIVE)->count());
        $this->assertSame(4, (int) $variant->fresh()->stock);
    }

    public function test_checkout_grand_total_tampering_blocks_conversion_without_capture_or_inventory_deduction(): void
    {
        [$checkout, $variant] = $this->taxedCheckout();
        $checkout->forceFill(['grand_total' => '30.00'])->save();

        try {
            app(CheckoutConversionService::class)->handleSucceededPayment($this->paymentResult($checkout->fresh(), amountMinor: 2750, currency: 'USD'));
            $this->fail('Expected checkout total tampering mismatch exception.');
        } catch (CheckoutPaymentAmountMismatchException $exception) {
            $this->assertSame($checkout->id, $exception->checkoutId);
            $this->assertSame(3000, $exception->expectedMinor);
            $this->assertSame(2750, $exception->providerActualMinor);
            $this->assertSame(2750, $exception->localPaymentIntentMinor);
        }

        $this->assertSame(0, Order::query()->where('store_id', $checkout->store_id)->count());
        $this->assertSame(0, PaymentCapture::query()->where('store_id', $checkout->store_id)->count());
        $this->assertSame(Checkout::STATUS_PAYMENT_PENDING, $checkout->fresh()->status);
        $this->assertSame(1, InventoryReservation::query()->where('reference_id', (string) $checkout->id)->where('status', InventoryReservation::STATUS_ACTIVE)->count());
        $this->assertSame(4, (int) $variant->fresh()->stock);
    }

    public function test_provider_currency_mismatch_throws_before_conversion(): void
    {
        [$checkout] = $this->taxedCheckout();

        $this->expectException(CheckoutPaymentAmountMismatchException::class);

        app(CheckoutConversionService::class)->handleSucceededPayment($this->paymentResult($checkout, amountMinor: 2750, currency: 'EUR'));
    }

    public function test_successful_conversion_copies_checkout_item_and_tax_line_snapshots(): void
    {
        [$checkout] = $this->taxedCheckout();

        $order = app(CheckoutConversionService::class)->handleSucceededPayment($this->paymentResult($checkout, amountMinor: 2750));

        $this->assertNotNull($order);
        $order = $order->fresh(['items', 'taxLines']);
        $this->assertSame('20.00', (string) $order->subtotal);
        $this->assertSame('5.00', (string) $order->shipping);
        $this->assertSame('0.50', (string) $order->shipping_tax);
        $this->assertSame('2.50', (string) $order->tax);
        $this->assertSame('27.50', (string) $order->grand_total);
        $this->assertSame('2.00', (string) $order->items->first()->tax_amount);
        $this->assertSame(2, $order->taxLines->count());
        $this->assertDatabaseHas('order_tax_lines', [
            'order_id' => $order->id,
            'applies_to' => OrderTaxLine::APPLIES_TO_ITEMS,
            'tax_amount' => 2.00,
            'taxable_amount' => 20.00,
        ]);
        $this->assertDatabaseHas('order_tax_lines', [
            'order_id' => $order->id,
            'applies_to' => OrderTaxLine::APPLIES_TO_SHIPPING,
            'tax_amount' => 0.50,
            'taxable_amount' => 5.00,
        ]);
        $this->assertSame(data_get($checkout->fresh()->metadata, 'tax_snapshot'), data_get($order->meta, 'tax_snapshot'));
    }

    public function test_order_tax_snapshot_does_not_change_after_tax_rate_changes(): void
    {
        [$checkout] = $this->taxedCheckout();
        $order = app(CheckoutConversionService::class)->handleSucceededPayment($this->paymentResult($checkout, amountMinor: 2750));

        TaxRate::query()->where('store_id', $checkout->store_id)->update(['rate_percent' => '35.0000']);

        $this->assertSame('2.50', (string) $order->fresh()->tax);
        $this->assertSame('10.0000', (string) $order->taxLines()->firstOrFail()->rate_percent);
    }

    public function test_jpy_matching_values_convert_with_zero_decimal_minor_units(): void
    {
        [$checkout] = $this->taxedCheckout(currency: 'JPY', price: 1000, shipping: 300, country: 'JP', state: '', rate: '10.0000');

        $order = app(CheckoutConversionService::class)->handleSucceededPayment($this->paymentResult($checkout, amountMinor: 1430, currency: 'JPY'));

        $this->assertNotNull($order);
        $this->assertSame('1430.00', (string) $order->fresh()->grand_total);
        $this->assertDatabaseHas('payment_captures', [
            'payment_intent_id' => PaymentIntent::query()->where('checkout_id', $checkout->id)->firstOrFail()->id,
            'amount_minor' => 1430,
            'currency_code' => 'JPY',
        ]);
    }

    public function test_repeat_mismatch_callback_remains_state_idempotent(): void
    {
        [$checkout] = $this->taxedCheckout();

        for ($i = 0; $i < 2; $i++) {
            try {
                app(CheckoutConversionService::class)->handleSucceededPayment($this->paymentResult($checkout, amountMinor: 2100));
            } catch (CheckoutPaymentAmountMismatchException) {
                // Expected.
            }
        }

        $this->assertSame(0, Order::query()->where('store_id', $checkout->store_id)->count());
        $this->assertSame(Checkout::STATUS_PAYMENT_PENDING, $checkout->fresh()->status);
        $this->assertSame(1, InventoryReservation::query()->where('reference_id', (string) $checkout->id)->where('status', InventoryReservation::STATUS_ACTIVE)->count());
        $this->assertGreaterThanOrEqual(1, CheckoutEvent::query()->where('checkout_id', $checkout->id)->where('event_type', 'payment.amount_mismatch')->count());
    }

    /**
     * @return array{0: Checkout, 1: ProductVariant}
     */
    private function taxedCheckout(string $currency = 'USD', int|float $price = 20, int|float $shipping = 5, string $country = 'US', string $state = 'TX', string $rate = '10.0000'): array
    {
        [$store, $token] = $this->tokenedStore('Invariant Store '.Str::random(4), $currency);
        [, $variant] = $this->product($store, ['price' => $price, 'stock' => 5]);
        $methods = $this->shippingSetup($store, $shipping, $country, $state);
        $this->enableTax($store, ['shipping_taxable' => true], rates: [[
            'country_code' => $country,
            'region_code' => $state,
            'name' => $country.' '.$state.' Tax',
            'rate_percent' => $rate,
        ]]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, $currency, $country, $state, [
                'shipping_method_id' => $methods['standard']->id,
            ]))
            ->assertCreated();

        return [Checkout::query()->where('store_id', $store->id)->firstOrFail(), $variant];
    }

    private function paymentResult(Checkout $checkout, int $amountMinor, string $currency = 'USD'): PaymentWebhookResult
    {
        return new PaymentWebhookResult(
            eventType: 'payment_intent.succeeded',
            providerIntentId: (string) $checkout->stripe_payment_intent_id,
            status: 'succeeded',
            amount: $currency === 'JPY' ? (float) $amountMinor : $amountMinor / 100,
            currencyCode: $currency,
            raw: [
                'id' => 'evt_'.Str::random(12),
                'type' => 'payment_intent.succeeded',
                'object' => [
                    'id' => $checkout->stripe_payment_intent_id,
                    'status' => 'succeeded',
                    'amount' => $amountMinor,
                    'currency' => strtolower($currency),
                ],
            ],
        );
    }

    private function tokenedStore(string $name, string $currency): array
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['role_id' => $role->id]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'currency' => $currency,
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
            'name' => $overrides['name'] ?? 'Invariant Product',
            'slug' => 'invariant-product-'.Str::random(6),
            'base_price' => $overrides['price'] ?? 20,
            'sku' => $overrides['sku'] ?? 'INV-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'is_taxable' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => $product->sku.'-D',
            'price' => $overrides['price'] ?? 20,
            'stock' => $overrides['stock'] ?? 5,
        ]);

        return [$product, $variant];
    }

    private function enableTax(Store $store, array $settingsOverrides = [], array $rates = []): TaxSetting
    {
        $settings = $store->taxSetting;
        $settings->update(array_merge([
            'enabled' => true,
            'prices_include_tax' => false,
            'default_product_taxable' => true,
            'shipping_taxable' => false,
            'calculation_address' => TaxSetting::CALCULATION_ADDRESS_SHIPPING,
        ], $settingsOverrides));

        foreach ($rates as $rate) {
            TaxRate::query()->create(array_merge([
                'store_id' => $store->id,
                'country_code' => 'US',
                'region_code' => 'TX',
                'name' => 'Invariant Tax',
                'rate_percent' => '10.0000',
                'priority' => 100,
                'is_active' => true,
            ], $rate));
        }

        return $settings->fresh();
    }

    private function shippingSetup(Store $store, int|float $flatRate, string $country, string $state): array
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
            'supported_countries' => [$country],
            'enabled_for_checkout' => true,
        ]);
        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => $country.' Zone',
            'countries' => [$country],
            'regions' => [$state],
            'postal_patterns' => ['*'],
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $standard = ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'carrier_account_id' => $account->id,
            'name' => 'Standard delivery',
            'code' => 'standard-delivery',
            'description' => 'Flat rate delivery',
            'delivery_speed_label' => '3-5 business days',
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => $flatRate,
            'enabled_for_checkout' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return ['standard' => $standard];
    }

    private function payload(ProductVariant $variant, string $currency, string $country, string $state, array $overrides = []): array
    {
        return array_replace_recursive([
            'source_channel' => 'dev_storefront',
            'currency_code' => $currency,
            'customer' => [
                'full_name' => 'Invariant Buyer',
                'email' => 'invariant@example.test',
                'phone' => '+15550188',
            ],
            'shipping_address' => [
                'name' => 'Invariant Buyer',
                'address_line1' => '123 Invariant Way',
                'city' => $country === 'JP' ? 'Tokyo' : 'Austin',
                'state' => $state,
                'postal_code' => $country === 'JP' ? '1000001' : '73301',
                'country' => $country,
                'country_code' => $country,
                'phone' => '+15550188',
            ],
            'billing_address' => ['same_as_shipping' => true],
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 1],
            ],
        ], $overrides);
    }
}
