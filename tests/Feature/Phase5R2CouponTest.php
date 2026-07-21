<?php

namespace Tests\Feature;

use App\Data\Payments\PaymentIntentResult;
use App\Data\Payments\PaymentWebhookResult;
use App\Data\Coupons\CouponDiscountResult;
use App\Models\Category;
use App\Models\Checkout;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use App\Models\User;
use App\Services\CheckoutConversionService;
use App\Services\Checkout\CheckoutTotalsService;
use App\Services\Coupons\CouponService;
use App\Services\Payments\StripePlatformPaymentProvider;
use App\Support\CheckoutMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase5R2CouponTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.default_provider' => 'stripe',
            'payments.stripe.mode' => 'test',
            'payments.stripe.key' => 'pk_test_coupons',
            'payments.stripe.secret' => 'sk_test_coupons',
            'payments.stripe.webhook_secret' => 'whsec_coupons',
        ]);

        $this->app->instance(StripePlatformPaymentProvider::class, new class(app(\App\Services\Payments\StripeConfig::class)) extends StripePlatformPaymentProvider
        {
            public function createPaymentIntent(Checkout $checkout, array $options = []): PaymentIntentResult
            {
                return new PaymentIntentResult(
                    provider: 'stripe',
                    providerIntentId: 'pi_coupon_'.$checkout->id,
                    clientSecret: 'pi_coupon_'.$checkout->id.'_secret_test',
                    status: 'requires_payment_method',
                    amount: (float) $checkout->grand_total,
                    currencyCode: $checkout->currency_code,
                    raw: ['id' => 'pi_coupon_'.$checkout->id, 'status' => 'requires_payment_method'],
                );
            }
        });
    }

    public function test_owner_can_manage_store_scoped_coupons_with_same_code_allowed_in_another_store(): void
    {
        [$storeA, , $ownerA] = $this->tokenedStore('Coupon Store A');
        [$storeB, , $ownerB] = $this->tokenedStore('Coupon Store B');

        $this->actingAsStore($ownerA, $storeA)
            ->post(route('settings.coupons.store'), $this->couponPayload(['code' => 'welcome10']))
            ->assertRedirect()
            ->assertSessionHas('success');

        $couponA = Coupon::query()->forStore($storeA->id)->firstOrFail();
        $this->assertSame('WELCOME10', $couponA->code);

        $this->actingAsStore($ownerA, $storeA)
            ->post(route('settings.coupons.store'), $this->couponPayload(['code' => 'WELCOME10']))
            ->assertSessionHasErrors('code');

        $this->actingAsStore($ownerB, $storeB)
            ->post(route('settings.coupons.store'), $this->couponPayload(['code' => 'WELCOME10']))
            ->assertRedirect();

        $this->assertSame(1, Coupon::query()->forStore($storeA->id)->count());
        $this->assertSame(1, Coupon::query()->forStore($storeB->id)->count());

        $staff = User::factory()->create(['role_id' => Role::firstOrCreate(['name' => 'user'])->id]);
        $storeA->members()->attach($staff->id, ['role' => Store::ROLE_STAFF]);
        $this->actingAsStore($staff, $storeA)
            ->post(route('settings.coupons.store'), $this->couponPayload(['code' => 'BLOCKED']))
            ->assertForbidden();

        $this->actingAsStore($ownerA, $storeA)
            ->patch(route('settings.coupons.update', Coupon::query()->forStore($storeB->id)->firstOrFail()), $this->couponPayload())
            ->assertNotFound();
    }

    public function test_percentage_coupon_is_applied_before_tax_and_snapshotted(): void
    {
        [$store, $token] = $this->tokenedStore('Percentage Coupon Store');
        [, $variant] = $this->product($store, price: 20);
        $this->enableTenPercentTax($store);
        $coupon = $this->coupon($store, [
            'code' => 'SAVE25',
            'type' => 'percentage',
            'value' => 25,
            'maximum_discount_amount' => 8,
        ]);

        $response = $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($variant, ['coupon_code' => 'save25']))
            ->assertCreated()
            ->assertJsonPath('checkout.subtotal', '40.00')
            ->assertJsonPath('checkout.discount_total', '8.00')
            ->assertJsonPath('checkout.tax_total', '3.20')
            ->assertJsonPath('checkout.grand_total', '35.20')
            ->assertJsonPath('checkout.coupon.code', 'SAVE25')
            ->assertJsonPath('checkout.items.0.discount_amount', '8.00');

        $checkout = Checkout::query()->findOrFail($response->json('checkout.id'));
        $this->assertSame('SAVE25', data_get($checkout->metadata, 'coupon_snapshot.code'));
        $recalculated = app(CheckoutTotalsService::class)->calculateForCheckout(
            $checkout->load('items'),
            $store->taxSetting,
            '5.00',
            $this->checkoutPayload($variant)['shipping_address'],
        );
        $this->assertSame('8.00', $recalculated->discountTotal);
        $this->assertSame('40.20', $recalculated->grandTotal);
        $this->assertDatabaseHas('coupon_redemptions', [
            'coupon_id' => $coupon->id,
            'checkout_id' => $checkout->id,
            'status' => CouponRedemption::STATUS_RESERVED,
            'discount_amount' => 8.00,
        ]);
    }

    public function test_fixed_coupon_only_discounts_eligible_products_and_never_below_zero(): void
    {
        [$store, $token] = $this->tokenedStore('Eligible Coupon Store');
        [$eligibleProduct, $eligibleVariant] = $this->product($store, price: 20, name: 'Eligible');
        [, $otherVariant] = $this->product($store, price: 30, name: 'Other');
        $coupon = $this->coupon($store, ['code' => 'FIXED50', 'type' => 'fixed', 'value' => 50]);
        $coupon->products()->attach($eligibleProduct->id);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($eligibleVariant, [
                'coupon_code' => 'FIXED50',
                'items' => [
                    ['variant_id' => $eligibleVariant->id, 'quantity' => 1],
                    ['variant_id' => $otherVariant->id, 'quantity' => 1],
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('checkout.subtotal', '50.00')
            ->assertJsonPath('checkout.discount_total', '20.00')
            ->assertJsonPath('checkout.grand_total', '30.00');

        $category = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Eligible category',
            'slug' => 'eligible-category',
            'status' => true,
        ]);
        $eligibleProduct->categories()->attach($category->id);
        $categoryCoupon = $this->coupon($store, ['code' => 'CATEGORY5', 'type' => 'fixed', 'value' => 5]);
        $categoryCoupon->categories()->attach($category->id);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($eligibleVariant, [
                'coupon_code' => 'CATEGORY5',
                'items' => [
                    ['variant_id' => $eligibleVariant->id, 'quantity' => 1],
                    ['variant_id' => $otherVariant->id, 'quantity' => 1],
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('checkout.discount_total', '5.00')
            ->assertJsonPath('checkout.grand_total', '45.00');
    }

    public function test_coupon_rejects_cross_store_inactive_future_expired_and_minimum_order_cases(): void
    {
        [$store, $token] = $this->tokenedStore('Coupon Validation Store');
        [$otherStore] = $this->tokenedStore('Other Coupon Store');
        [, $variant] = $this->product($store, price: 10);

        foreach ([
            ['coupon' => $this->coupon($otherStore, ['code' => 'OTHER']), 'code' => 'OTHER'],
            ['coupon' => $this->coupon($store, ['code' => 'OFF', 'is_active' => false]), 'code' => 'OFF'],
            ['coupon' => $this->coupon($store, ['code' => 'FUTURE', 'starts_at' => now()->addDay()]), 'code' => 'FUTURE'],
            ['coupon' => $this->coupon($store, ['code' => 'EXPIRED', 'expires_at' => now()->subMinute()]), 'code' => 'EXPIRED'],
            ['coupon' => $this->coupon($store, ['code' => 'MINIMUM', 'minimum_order_amount' => 50]), 'code' => 'MINIMUM'],
        ] as $case) {
            $this->withToken($token)
                ->postJson('/api/v1/checkout', $this->checkoutPayload($variant, ['coupon_code' => $case['code']]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('coupon_code');
        }

        $this->assertSame(0, Checkout::query()->where('store_id', $store->id)->count());
    }

    public function test_usage_limit_counts_an_active_checkout_reservation_idempotently(): void
    {
        [$store, $token] = $this->tokenedStore('Coupon Limit Store');
        [, $variant] = $this->product($store, price: 10, stock: 10);
        $coupon = $this->coupon($store, [
            'code' => 'ONCE',
            'total_usage_limit' => 2,
            'per_customer_usage_limit' => 1,
        ]);

        $first = $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($variant, ['coupon_code' => 'ONCE']))
            ->assertCreated();

        $checkout = Checkout::query()->findOrFail($first->json('checkout.id'));
        $snapshot = data_get($checkout->metadata, 'coupon_snapshot');
        app(CouponService::class)->reserve(
            $checkout,
            $checkout->customer,
            new CouponDiscountResult(
                $coupon,
                (string) $checkout->discount_total,
                (array) ($snapshot['item_discounts'] ?? []),
                (array) $snapshot,
            ),
        );
        $this->assertSame(1, $checkout->couponRedemption()->count());

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($variant, ['coupon_code' => 'ONCE']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('coupon_code');

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($variant, [
                'coupon_code' => 'ONCE',
                'customer' => ['email' => 'second.buyer@example.test'],
            ]))
            ->assertCreated();

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($variant, [
                'coupon_code' => 'ONCE',
                'customer' => ['email' => 'third.buyer@example.test'],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('coupon_code');

        $this->assertSame(2, CouponRedemption::query()->where('store_id', $store->id)->count());
    }

    public function test_checkout_conversion_copies_coupon_snapshot_and_marks_redemption_redeemed(): void
    {
        [$store, $token] = $this->tokenedStore('Coupon Conversion Store');
        [, $variant] = $this->product($store, price: 20);
        $this->coupon($store, ['code' => 'ORDER5', 'type' => 'fixed', 'value' => 5]);

        $response = $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($variant, [
                'coupon_code' => 'ORDER5',
                'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            ]))
            ->assertCreated();

        $checkout = Checkout::query()->findOrFail($response->json('checkout.id'));
        $order = app(CheckoutConversionService::class)->handleSucceededPayment(new PaymentWebhookResult(
            eventType: 'payment_intent.succeeded',
            providerIntentId: 'pi_coupon_'.$checkout->id,
            status: 'succeeded',
            amount: 15.00,
            currencyCode: 'USD',
            raw: [
                'id' => 'evt_coupon_order',
                'type' => 'payment_intent.succeeded',
                'object' => ['id' => 'pi_coupon_'.$checkout->id, 'amount' => 1500, 'currency' => 'usd'],
            ],
        ));

        $this->assertNotNull($order);
        $this->assertSame('5.00', (string) $order->discount);
        $this->assertSame('ORDER5', data_get($order->meta, 'coupon_snapshot.code'));
        $this->assertSame('5.00', (string) $order->items()->firstOrFail()->discount_amount);
        $this->assertDatabaseHas('coupon_redemptions', [
            'checkout_id' => $checkout->id,
            'order_id' => $order->id,
            'status' => CouponRedemption::STATUS_REDEEMED,
        ]);

        $coupon = Coupon::query()->forStore($store->id)->firstOrFail();
        $coupon->update(['value' => 19, 'code' => 'CHANGED']);
        $this->assertSame('ORDER5', data_get($order->fresh()->meta, 'coupon_snapshot.code'));
        $this->assertSame('5.00', (string) $order->fresh()->discount);
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

    private function product(Store $store, float $price, string $name = 'Coupon Product', int $stock = 5): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'base_price' => $price,
            'sku' => 'CP-'.Str::random(6),
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
            'stock' => $stock,
        ]);

        return [$product, $variant];
    }

    private function coupon(Store $store, array $overrides = []): Coupon
    {
        return Coupon::query()->create(array_merge([
            'store_id' => $store->id,
            'code' => 'SAVE10',
            'name' => 'Test coupon',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 10,
            'minimum_order_amount' => 0,
            'is_active' => true,
        ], $overrides));
    }

    private function enableTenPercentTax(Store $store): void
    {
        $store->taxSetting->update([
            'enabled' => true,
            'prices_include_tax' => false,
            'shipping_taxable' => false,
            'calculation_address' => TaxSetting::CALCULATION_ADDRESS_SHIPPING,
        ]);
        TaxRate::query()->create([
            'store_id' => $store->id,
            'country_code' => 'US',
            'region_code' => 'TX',
            'name' => 'Texas tax',
            'rate_percent' => '10.0000',
            'priority' => 100,
            'is_active' => true,
        ]);
    }

    private function couponPayload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'SAVE10',
            'name' => 'Save ten',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 10,
            'minimum_order_amount' => 0,
            'is_active' => 1,
        ], $overrides);
    }

    private function checkoutPayload(ProductVariant $variant, array $overrides = []): array
    {
        return array_replace_recursive([
            'source_channel' => 'dev_storefront',
            'currency_code' => 'USD',
            'customer' => [
                'full_name' => 'Coupon Buyer',
                'email' => 'coupon.buyer@example.test',
                'phone' => '+15550188',
            ],
            'shipping_address' => [
                'name' => 'Coupon Buyer',
                'address_line1' => '123 Coupon Way',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '73301',
                'country' => 'US',
            ],
            'billing_address' => ['same_as_shipping' => true],
            'items' => [['variant_id' => $variant->id, 'quantity' => 2]],
        ], $overrides);
    }

    private function actingAsStore(User $user, Store $store): self
    {
        return $this->actingAs($user)->withSession(['current_store_id' => $store->id]);
    }
}
