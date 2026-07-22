<?php

namespace Tests\Feature;

use App\Data\Payments\PaymentIntentResult;
use App\Data\Payments\PaymentWebhookResult;
use App\Exceptions\CheckoutTotalsMismatchException;
use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Checkout;
use App\Models\CheckoutEvent;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\DraftOrder;
use App\Models\Order;
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
use App\Services\Draft\DraftTaxService;
use App\Services\ManualOrderConversionService;
use App\Services\Payments\StripePlatformPaymentProvider;
use App\Services\Shipping\DeliveryOptionService;
use App\Support\CheckoutMode;
use App\Support\Money\CurrencyPrecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase5R3TotalsHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.default_provider' => 'stripe',
            'payments.stripe.mode' => 'test',
            'payments.stripe.key' => 'pk_test_5r3',
            'payments.stripe.secret' => 'sk_test_5r3',
            'payments.stripe.webhook_secret' => 'whsec_5r3',
        ]);

        $this->app->instance(StripePlatformPaymentProvider::class, new class(app(\App\Services\Payments\StripeConfig::class)) extends StripePlatformPaymentProvider
        {
            public function createPaymentIntent(Checkout $checkout, array $options = []): PaymentIntentResult
            {
                return new PaymentIntentResult(
                    provider: 'stripe',
                    providerIntentId: 'pi_5r3_'.$checkout->id,
                    clientSecret: 'pi_5r3_'.$checkout->id.'_secret_test',
                    status: 'requires_payment_method',
                    amount: (string) $checkout->grand_total,
                    currencyCode: $checkout->currency_code,
                    raw: ['id' => 'pi_5r3_'.$checkout->id, 'status' => 'requires_payment_method'],
                );
            }

            public function updatePaymentIntentAmount(
                string $providerIntentId,
                int $amountMinor,
                string $currencyCode,
                array $options = [],
            ): \App\Data\Payments\PaymentIntentUpdateResult {
                return new \App\Data\Payments\PaymentIntentUpdateResult(
                    providerIntentId: $providerIntentId,
                    amountMinor: $amountMinor,
                    currencyCode: strtoupper($currencyCode),
                    status: 'requires_payment_method',
                    clientSecret: $providerIntentId.'_secret_test',
                    raw: ['id' => $providerIntentId, 'amount' => $amountMinor, 'currency' => strtolower($currencyCode)],
                    mode: 'test',
                );
            }
        });
    }

    public function test_platform_currency_different_from_store_is_rejected(): void
    {
        [$store, $token] = $this->tokenedStore('Currency Gate Store', 'USD');
        [, $variant] = $this->product($store, ['price' => 20]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($variant, [
                'currency_code' => 'EUR',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['currency_code']);
    }

    public function test_usd_and_jpy_exact_decimal_and_minor_conversions(): void
    {
        $this->assertSame(2750, CurrencyPrecision::toMinorUnits('27.50', 'USD'));
        $this->assertSame('27.50', CurrencyPrecision::fromMinorUnits(2750, 'USD'));
        $this->assertSame(1430, CurrencyPrecision::toMinorUnits('1430', 'JPY'));
        $this->assertSame('1430', CurrencyPrecision::fromMinorUnits(1430, 'JPY'));

        [$usdCheckout] = $this->platformCheckout(currency: 'USD', price: 20, shipping: 5);
        $this->assertSame(2750, (int) PaymentIntent::query()->where('checkout_id', $usdCheckout->id)->value('amount_minor'));

        [$jpyCheckout] = $this->platformCheckout(currency: 'JPY', price: 1000, shipping: 300, country: 'JP', state: '', rate: '10.0000');
        $this->assertSame(1430, (int) PaymentIntent::query()->where('checkout_id', $jpyCheckout->id)->value('amount_minor'));
    }

    public function test_shipping_threshold_comparisons_do_not_use_float_errors(): void
    {
        [$store] = $this->tokenedStore('Threshold Store', 'USD');
        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'US',
            'countries' => ['US'],
            'regions' => [],
            'postal_patterns' => ['*'],
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $method = ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'name' => 'Threshold Ship',
            'code' => 'threshold-ship',
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => '5.00',
            'min_order_amount' => '19.99',
            'max_order_amount' => '20.01',
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 1,
        ]);

        $service = app(DeliveryOptionService::class);
        $destination = ['country_code' => 'US', 'country' => 'US', 'postal_code' => '73301'];

        $this->assertNotNull($service->optionForMethodId($store, $method->id, $destination, '20.00', 'USD'));
        $this->assertNull($service->optionForMethodId($store, $method->id, $destination, '19.98', 'USD'));
        $this->assertNull($service->optionForMethodId($store, $method->id, $destination, '20.02', 'USD'));
    }

    public function test_successful_order_exactly_matches_checkout_snapshots(): void
    {
        [$checkout] = $this->platformCheckout();
        $order = app(CheckoutConversionService::class)->handleSucceededPayment(
            $this->paymentResult($checkout, CurrencyPrecision::toMinorUnits((string) $checkout->grand_total, 'USD'))
        );

        $this->assertSame((string) $checkout->subtotal, (string) $order->subtotal);
        $this->assertSame((string) $checkout->discount_total, (string) $order->discount);
        $this->assertSame((string) $checkout->shipping_total, (string) $order->shipping);
        $this->assertSame((string) $checkout->tax_total, (string) $order->tax);
        $this->assertSame((string) $checkout->grand_total, (string) $order->grand_total);
        $this->assertSame((string) $checkout->currency_code, (string) $order->currency_code);
        $this->assertSame($checkout->items->count(), $order->items->count());
        $this->assertSame(
            data_get($checkout->metadata, 'coupon_snapshot'),
            data_get($order->meta, 'coupon_snapshot'),
        );
    }

    public function test_tampered_item_subtotal_blocks_conversion_even_when_grand_total_matches_pi(): void
    {
        [$checkout] = $this->platformCheckout();
        $item = $checkout->items()->firstOrFail();
        $item->forceFill(['subtotal' => '1.00'])->save();

        $minor = CurrencyPrecision::toMinorUnits((string) $checkout->fresh()->grand_total, 'USD');

        try {
            app(CheckoutConversionService::class)->handleSucceededPayment($this->paymentResult($checkout, $minor));
            $this->fail('Expected totals mismatch exception.');
        } catch (CheckoutTotalsMismatchException $exception) {
            $this->assertSame($checkout->id, $exception->checkoutId);
        }

        $this->assertSame(0, Order::query()->where('store_id', $checkout->store_id)->count());
        $this->assertSame(0, PaymentCapture::query()->count());
        $this->assertDatabaseHas('checkout_events', [
            'checkout_id' => $checkout->id,
            'event_type' => 'checkout.totals_mismatch',
        ]);
    }

    public function test_totals_mismatch_event_persists_after_conversion_transaction_rolls_back(): void
    {
        [$checkout] = $this->platformCheckout();
        $checkout->items()->firstOrFail()->forceFill(['discount_amount' => '9.99'])->save();
        $minor = CurrencyPrecision::toMinorUnits((string) $checkout->fresh()->grand_total, 'USD');

        try {
            app(CheckoutConversionService::class)->handleSucceededPayment($this->paymentResult($checkout, $minor));
            $this->fail('Expected totals mismatch exception.');
        } catch (CheckoutTotalsMismatchException) {
            // expected
        }

        $this->assertSame(
            1,
            CheckoutEvent::query()
                ->where('checkout_id', $checkout->id)
                ->where('event_type', 'checkout.totals_mismatch')
                ->count()
        );
        $this->assertSame(0, Order::query()->where('store_id', $checkout->store_id)->count());
        $this->assertSame(0, PaymentCapture::query()->count());
        $this->assertSame(Checkout::STATUS_PAYMENT_PENDING, $checkout->fresh()->status);
    }

    public function test_local_payment_intent_decimal_mismatch_blocks_conversion(): void
    {
        [$checkout] = $this->platformCheckout();
        $intent = PaymentIntent::query()->where('checkout_id', $checkout->id)->firstOrFail();
        $correctMinor = (int) $intent->amount_minor;
        $this->assertSame(
            CurrencyPrecision::toMinorUnits((string) $checkout->grand_total, 'USD'),
            $correctMinor,
        );

        // Corrupt only the major decimal column; amount_minor and provider stay correct.
        $intent->forceFill(['amount' => '21.00'])->save();

        try {
            app(CheckoutConversionService::class)->handleSucceededPayment(
                $this->paymentResult($checkout, $correctMinor)
            );
            $this->fail('Expected payment amount mismatch.');
        } catch (\App\Exceptions\CheckoutPaymentAmountMismatchException $exception) {
            $this->assertSame($correctMinor, $exception->localPaymentIntentMinor);
            $this->assertSame(
                CurrencyPrecision::toMinorUnits('21.00', 'USD'),
                $exception->localPaymentIntentAmountAsMinor,
            );
            $this->assertSame($correctMinor, $exception->expectedMinor);
            $this->assertSame($correctMinor, $exception->providerActualMinor);
        }

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, PaymentCapture::query()->count());
        $this->assertDatabaseHas('checkout_events', [
            'checkout_id' => $checkout->id,
            'event_type' => 'payment.amount_mismatch',
        ]);
    }

    public function test_repeated_confirm_produces_one_order_and_one_capture(): void
    {
        [$checkout] = $this->platformCheckout();
        $minor = CurrencyPrecision::toMinorUnits((string) $checkout->grand_total, 'USD');
        $service = app(CheckoutConversionService::class);

        $first = $service->handleSucceededPayment($this->paymentResult($checkout, $minor));
        $second = $service->handleSucceededPayment($this->paymentResult($checkout->fresh(), $minor));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Order::query()->where('store_id', $checkout->store_id)->count());
        $this->assertSame(1, PaymentCapture::query()->count());
    }

    public function test_draft_calculated_tax_applies_coupon_before_tax(): void
    {
        [$store, , $owner] = $this->tokenedStore('Draft Tax Coupon Store');
        [, $variant] = $this->product($store, ['price' => 40, 'stock' => 10]);
        $this->enableTax($store);
        $this->coupon($store, ['code' => 'DRAFT25', 'type' => Coupon::TYPE_PERCENTAGE, 'value' => 25]);

        $this->actingAsStore($owner, $store)
            ->post(route('draft-orders.store'), [
                'customer_name' => 'Draft Tax Buyer',
                'customer_email' => 'draft.tax@example.test',
                'shipping_name' => 'Draft Tax Buyer',
                'shipping_address_line1' => '10 Draft Road',
                'shipping_city' => 'Austin',
                'shipping_state' => 'TX',
                'shipping_postal_code' => '73301',
                'shipping_country' => 'US',
                'billing_same_as_shipping' => '1',
                'coupon_code' => 'DRAFT25',
                'discount_total' => '0.00',
                'shipping_total' => '0.00',
                'tax_total' => '0.00',
                'tax_mode' => 'calculated',
                'items' => [
                    ['product_variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => '40'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $draft = DraftOrder::query()->where('store_id', $store->id)->latest('id')->firstOrFail();
        $this->assertSame('10.00', (string) $draft->discount_total);

        $draft = app(DraftTaxService::class)->calculate($draft, $store);
        // 10% of discounted 30.00 = 3.00
        $this->assertSame('3.00', (string) $draft->tax_total);
    }

    public function test_changed_draft_coupon_blocks_conversion_cleanly(): void
    {
        [$store, , $owner] = $this->tokenedStore('Draft Coupon Block Store');
        [, $variant] = $this->product($store, ['price' => 40, 'stock' => 10]);
        $coupon = $this->coupon($store, ['code' => 'DRAFT10', 'type' => Coupon::TYPE_PERCENTAGE, 'value' => 10]);

        $this->actingAsStore($owner, $store)
            ->post(route('draft-orders.store'), $this->draftPayload($variant, [
                'coupon_code' => 'DRAFT10',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $draft = DraftOrder::query()->where('store_id', $store->id)->latest('id')->firstOrFail();
        $coupon->update(['value' => 25]);

        try {
            app(ManualOrderConversionService::class)->convert($draft, $store, $owner);
            $this->fail('Expected coupon mismatch validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('coupon_code', $exception->errors());
        }

        $this->assertSame(DraftOrder::STATUS_DRAFT, $draft->fresh()->status);
        $this->assertSame(0, Order::query()->where('store_id', $store->id)->count());
        $this->assertSame(0, CouponRedemption::query()->count());
    }

    public function test_draft_manual_tax_remains_unchanged_when_coupon_applied(): void
    {
        [$store, , $owner] = $this->tokenedStore('Draft Manual Tax Store');
        [, $variant] = $this->product($store, ['price' => 40, 'stock' => 10]);
        $this->coupon($store, ['code' => 'MANUAL5', 'type' => Coupon::TYPE_FIXED, 'value' => 5]);

        $this->actingAsStore($owner, $store)
            ->post(route('draft-orders.store'), $this->draftPayload($variant, [
                'coupon_code' => 'MANUAL5',
                'tax_mode' => 'manual',
                'tax_total' => '7.77',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $draft = DraftOrder::query()->where('store_id', $store->id)->latest('id')->firstOrFail();
        $this->assertSame('7.77', (string) $draft->tax_total);
        $this->assertSame('5.00', (string) $draft->discount_total);
    }

    public function test_jpy_draft_create_update_and_conversion_use_zero_decimal_money(): void
    {
        [$store, , $owner] = $this->tokenedStore('JPY Draft Store', 'JPY');
        [, $variant] = $this->product($store, ['price' => 1000, 'stock' => 10]);

        $this->actingAsStore($owner, $store)
            ->post(route('draft-orders.store'), $this->draftPayload($variant, [
                'tax_mode' => 'manual',
                'tax_total' => '80',
                'shipping_total' => '300',
                'discount_total' => '0',
                'items' => [
                    ['product_variant_id' => $variant->id, 'quantity' => 2, 'unit_price' => '1000'],
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $draft = DraftOrder::query()->where('store_id', $store->id)->latest('id')->firstOrFail();
        $this->assertSame('JPY', (string) $draft->currency);
        $this->assertSame(2000, CurrencyPrecision::toMinorUnits((string) $draft->subtotal, 'JPY'));
        $this->assertSame(2380, CurrencyPrecision::toMinorUnits((string) $draft->total, 'JPY'));
        $this->assertSame(2000, CurrencyPrecision::toMinorUnits((string) $draft->items->first()->line_total, 'JPY'));

        $this->actingAsStore($owner, $store)
            ->patch(route('draft-orders.update', $draft), $this->draftPayload($variant, [
                'tax_mode' => 'manual',
                'tax_total' => '100',
                'shipping_total' => '250',
                'discount_total' => '50',
                'items' => [
                    ['product_variant_id' => $variant->id, 'quantity' => 3, 'unit_price' => '1000'],
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $draft = $draft->fresh(['items']);
        $this->assertSame(3000, CurrencyPrecision::toMinorUnits((string) $draft->subtotal, 'JPY'));
        $this->assertSame(50, CurrencyPrecision::toMinorUnits((string) $draft->discount_total, 'JPY'));
        $this->assertSame(3300, CurrencyPrecision::toMinorUnits((string) $draft->total, 'JPY'));

        $order = app(ManualOrderConversionService::class)->convert($draft, $store, $owner);

        $this->assertSame('JPY', (string) $order->currency_code);
        $this->assertSame(3000, CurrencyPrecision::toMinorUnits((string) $order->subtotal, 'JPY'));
        $this->assertSame(50, CurrencyPrecision::toMinorUnits((string) $order->discount, 'JPY'));
        $this->assertSame(250, CurrencyPrecision::toMinorUnits((string) $order->shipping, 'JPY'));
        $this->assertSame(100, CurrencyPrecision::toMinorUnits((string) $order->tax, 'JPY'));
        $this->assertSame(3300, CurrencyPrecision::toMinorUnits((string) $order->total, 'JPY'));
        $this->assertSame(3300, CurrencyPrecision::toMinorUnits((string) $order->grand_total, 'JPY'));
        $this->assertSame(7, (int) $variant->fresh()->stock);
    }

    public function test_tampered_draft_header_discount_blocks_conversion_without_side_effects(): void
    {
        [$store, , $owner] = $this->tokenedStore('Draft Header Tamper Store');
        [, $variant] = $this->product($store, ['price' => 40, 'stock' => 10]);
        $this->coupon($store, ['code' => 'HDR10', 'type' => Coupon::TYPE_PERCENTAGE, 'value' => 10]);

        $this->actingAsStore($owner, $store)
            ->post(route('draft-orders.store'), $this->draftPayload($variant, [
                'coupon_code' => 'HDR10',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $draft = DraftOrder::query()->where('store_id', $store->id)->latest('id')->firstOrFail();
        $this->assertSame('4.00', (string) $draft->discount_total);
        $this->assertSame('4.00', (string) data_get($draft->metadata, 'coupon_snapshot.discount_amount'));

        $draft->forceFill(['discount_total' => '9.99'])->save();

        try {
            app(ManualOrderConversionService::class)->convert($draft->fresh(['items.variant.product', 'customer']), $store, $owner);
            $this->fail('Expected coupon mismatch validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('coupon_code', $exception->errors());
        }

        $this->assertSame(DraftOrder::STATUS_DRAFT, $draft->fresh()->status);
        $this->assertSame(0, Order::query()->where('store_id', $store->id)->count());
        $this->assertSame(0, CouponRedemption::query()->where('store_id', $store->id)->count());
        $this->assertSame(10, (int) $variant->fresh()->stock);
    }

    public function test_external_explicit_totals_remain_unchanged_and_create_no_payment_intent(): void
    {
        [$store, $token] = $this->tokenedStore('External Preserve Store');
        [, $variant] = $this->product($store, ['price' => 12, 'stock' => 5]);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->externalPayload($variant, [
                'totals' => [
                    'subtotal' => 24.00,
                    'shipping' => 4.50,
                    'tax' => 1.50,
                    'discount' => 2.00,
                    'total' => 28.00,
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('order.total', '28.00');

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame('24.00', (string) $order->subtotal);
        $this->assertSame('4.50', (string) $order->shipping);
        $this->assertSame('1.50', (string) $order->tax);
        $this->assertSame('2.00', (string) $order->discount);
        $this->assertSame('28.00', (string) $order->grand_total);
        $this->assertSame(0, PaymentIntent::query()->count());
        $this->assertSame(0, Checkout::query()->count());
    }

    public function test_external_platform_coupon_line_discounts_are_decimal_exact_and_sum_to_header(): void
    {
        [$store, $token] = $this->tokenedStore('External Decimal Coupon Store');
        [, $variantA] = $this->product($store, ['price' => '10.00', 'name' => 'Line A', 'stock' => 5]);
        [, $variantB] = $this->product($store, ['price' => '10.01', 'name' => 'Line B', 'stock' => 5]);
        $this->coupon($store, [
            'code' => 'SPLIT10',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 10,
        ]);

        $response = $this->withToken($token)
            ->postJson('/api/v1/external/orders', [
                'external_order_number' => 'EXT-DEC-1',
                'external_checkout_reference' => 'checkout-dec-1',
                'payment_status' => 'paid',
                'payment_method' => 'card',
                'payment_gateway' => 'external_test',
                'payment_reference' => 'pay-dec-1',
                'currency_code' => 'USD',
                'discount_calculation' => 'platform',
                'coupon_code' => 'SPLIT10',
                'customer' => [
                    'email' => 'external.decimal@example.test',
                    'full_name' => 'Decimal Buyer',
                ],
                'shipping_address' => [
                    'name' => 'Decimal Buyer',
                    'address_line1' => '9 Decimal Way',
                    'city' => 'Austin',
                    'state' => 'TX',
                    'postal_code' => '73301',
                    'country' => 'US',
                    'country_code' => 'US',
                ],
                'items' => [
                    ['variant_id' => $variantA->id, 'quantity' => 1, 'unit_price' => '10.00'],
                    ['variant_id' => $variantB->id, 'quantity' => 1, 'unit_price' => '10.01'],
                ],
                'totals' => [
                    'subtotal' => '20.01',
                    'shipping' => '1.23',
                    'tax' => '4.56',
                    'discount' => '0.00',
                    'total' => '99.99',
                ],
            ])
            ->assertCreated();

        $order = Order::query()->findOrFail($response->json('order.id'));
        // Explicit external shipping/tax preserved; platform discount replaces discount; grand recalculated.
        $this->assertSame('20.01', (string) $order->subtotal);
        $this->assertSame('1.23', (string) $order->shipping);
        $this->assertSame('4.56', (string) $order->tax);
        $this->assertSame('2.00', (string) $order->discount);
        $this->assertSame('23.80', (string) $order->grand_total);

        $lineDiscountSum = $order->items->reduce(
            fn (string $carry, $item): string => bcadd($carry, (string) $item->discount_amount, 2),
            '0.00'
        );
        $this->assertSame('2.00', $lineDiscountSum);
        $this->assertSame(
            CurrencyPrecision::toMinorUnits('2.00', 'USD'),
            CurrencyPrecision::toMinorUnits($lineDiscountSum, 'USD')
        );
        $this->assertSame(0, PaymentIntent::query()->count());
    }

    public function test_external_missing_grand_total_uses_deterministic_fallback(): void
    {
        [$store, $token] = $this->tokenedStore('External Fallback Store');
        [, $variant] = $this->product($store, ['price' => 10, 'stock' => 5]);

        $payload = $this->externalPayload($variant);
        $payload['totals'] = [
            'subtotal' => 20.00,
            'shipping' => 3.00,
            'tax' => 2.00,
            'discount' => 1.00,
        ];

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('order.total', '24.00');
    }

    public function test_external_nested_currency_mismatch_is_rejected(): void
    {
        [$store, $token] = $this->tokenedStore('External Currency Store');
        [, $variant] = $this->product($store, ['price' => 10, 'stock' => 5]);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->externalPayload($variant, [
                'currency_code' => 'USD',
                'shipping' => [
                    'method_name' => 'Express',
                    'amount' => 5,
                    'currency' => 'EUR',
                ],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shipping.currency']);
    }

    /**
     * @return array{0: Checkout, 1: ProductVariant, 2: Store, 3: string}
     */
    private function platformCheckout(
        string $currency = 'USD',
        int|float $price = 20,
        int|float $shipping = 5,
        string $country = 'US',
        string $state = 'TX',
        string $rate = '10.0000',
    ): array {
        [$store, $token] = $this->tokenedStore('5R3 Store '.Str::random(4), $currency);
        [, $variant] = $this->product($store, ['price' => $price, 'stock' => 5]);
        $methods = $this->shippingSetup($store, $shipping, $country, $state);
        $this->enableTax($store, ['shipping_taxable' => true], [[
            'country_code' => $country,
            'region_code' => $state,
            'name' => $country.' Tax',
            'rate_percent' => $rate,
        ]]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($variant, [
                'currency_code' => $currency,
                'shipping_method_id' => $methods['standard']->id,
                'shipping_address' => [
                    'name' => 'Buyer',
                    'address_line1' => '1 Main',
                    'city' => $country === 'JP' ? 'Tokyo' : 'Austin',
                    'state' => $state,
                    'postal_code' => $country === 'JP' ? '100-0001' : '73301',
                    'country' => $country,
                    'country_code' => $country,
                ],
            ]))
            ->assertCreated();

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();

        return [$checkout, $variant, $store, $token];
    }

    private function paymentResult(Checkout $checkout, int $amountMinor, ?string $currency = null): PaymentWebhookResult
    {
        $currency = $currency ?? strtoupper((string) $checkout->currency_code);

        return new PaymentWebhookResult(
            eventType: 'payment_intent.succeeded',
            providerIntentId: (string) $checkout->stripe_payment_intent_id,
            status: 'succeeded',
            amount: CurrencyPrecision::fromMinorUnits($amountMinor, $currency),
            currencyCode: $currency,
            raw: [
                'id' => 'evt_'.Str::random(8),
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

    /**
     * @return array{0: Store, 1: string, 2: User}
     */
    private function tokenedStore(string $name, string $currency = 'USD'): array
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: Product, 1: ProductVariant}
     */
    private function product(Store $store, array|float $overrides = [], string $name = '5R3 Product', int $stock = 10): array
    {
        if (! is_array($overrides)) {
            $overrides = ['price' => $overrides, 'name' => $name, 'stock' => $stock];
        }

        $price = $overrides['price'] ?? 20;
        $productName = $overrides['name'] ?? '5R3 Product';
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $productName,
            'slug' => Str::slug($productName).'-'.Str::random(6),
            'base_price' => $price,
            'sku' => '5R3-'.Str::random(6),
            'product_type' => 'physical',
            'status' => true,
            'is_taxable' => true,
            'meta' => [],
        ]);
        $variant = ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => $product->sku.'-D',
            'price' => $price,
            'stock' => $overrides['stock'] ?? 10,
        ]);

        return [$product, $variant];
    }

    /**
     * @return array{standard: ShippingMethod}
     */
    private function shippingSetup(Store $store, int|float|string $amount, string $country, string $state): array
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
            'regions' => $state !== '' ? [$state] : [],
            'postal_patterns' => ['*'],
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $standard = ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'carrier_account_id' => $account->id,
            'name' => 'Standard delivery',
            'code' => 'standard-delivery-'.Str::random(4),
            'description' => 'Flat rate delivery',
            'delivery_speed_label' => '3-5 business days',
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => $amount,
            'enabled_for_checkout' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return ['standard' => $standard];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $rates
     */
    private function enableTax(Store $store, array $settings = [], array $rates = []): void
    {
        $tax = $store->taxSetting()->firstOrCreate([], [
            'enabled' => false,
            'prices_include_tax' => false,
            'default_product_taxable' => true,
            'shipping_taxable' => false,
            'calculation_address' => TaxSetting::CALCULATION_ADDRESS_SHIPPING,
            'settings_version' => 1,
        ]);
        $tax->update(array_merge([
            'enabled' => true,
            'prices_include_tax' => false,
            'default_product_taxable' => true,
            'shipping_taxable' => false,
            'calculation_address' => TaxSetting::CALCULATION_ADDRESS_SHIPPING,
            'settings_version' => ((int) $tax->settings_version) + 1,
        ], $settings));

        if ($rates === []) {
            $rates = [[
                'country_code' => 'US',
                'region_code' => 'TX',
                'name' => 'TX Tax',
                'rate_percent' => '10.0000',
            ]];
        }

        foreach ($rates as $rate) {
            TaxRate::query()->create([
                'store_id' => $store->id,
                'country_code' => $rate['country_code'],
                'region_code' => $rate['region_code'] ?? '',
                'name' => $rate['name'],
                'rate_percent' => $rate['rate_percent'],
                'priority' => 100,
                'is_active' => true,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function checkoutPayload(ProductVariant $variant, array $overrides = []): array
    {
        return array_replace_recursive([
            'currency_code' => 'USD',
            'customer' => [
                'email' => 'buyer5r3@example.test',
                'full_name' => 'Buyer 5R3',
            ],
            'shipping_address' => [
                'name' => 'Buyer 5R3',
                'address_line1' => '1 Main St',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '73301',
                'country' => 'US',
                'country_code' => 'US',
            ],
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 1],
            ],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function draftPayload(ProductVariant $variant, array $overrides = []): array
    {
        return array_replace_recursive([
            'customer_name' => 'Draft Buyer',
            'customer_email' => 'draft.buyer@example.test',
            'shipping_name' => 'Draft Buyer',
            'shipping_address_line1' => '10 Draft Road',
            'shipping_city' => 'Austin',
            'shipping_state' => 'TX',
            'shipping_postal_code' => '73301',
            'shipping_country' => 'US',
            'billing_same_as_shipping' => '1',
            'discount_total' => '0.00',
            'shipping_total' => '0.00',
            'tax_total' => '0.00',
            'tax_mode' => 'manual',
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => (string) $variant->price],
            ],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function coupon(Store $store, array $overrides = []): Coupon
    {
        return Coupon::query()->create(array_merge([
            'store_id' => $store->id,
            'code' => 'SAVE10',
            'name' => 'Save',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 10,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function externalPayload(ProductVariant $variant, array $overrides = []): array
    {
        return array_replace_recursive([
            'external_order_number' => 'EXT-'.Str::random(6),
            'external_checkout_reference' => 'checkout-'.Str::random(6),
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'payment_gateway' => 'external_test',
            'payment_reference' => 'pay-'.Str::random(6),
            'currency_code' => 'USD',
            'customer' => [
                'email' => 'external.5r3@example.test',
                'full_name' => 'External Buyer',
            ],
            'shipping_address' => [
                'name' => 'External Buyer',
                'address_line1' => '9 External Way',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '73301',
                'country' => 'US',
                'country_code' => 'US',
            ],
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 2,
                    'unit_price' => (string) $variant->price,
                ],
            ],
            'totals' => [
                'subtotal' => 24.00,
                'shipping' => 4.50,
                'tax' => 1.50,
                'discount' => 2.00,
                'total' => 28.00,
            ],
        ], $overrides);
    }

    private function actingAsStore(User $user, Store $store): self
    {
        return $this->actingAs($user)->withSession(['current_store_id' => $store->id]);
    }
}
