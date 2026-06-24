<?php

namespace Tests\Feature;

use App\Data\Payments\PaymentIntentResult;
use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Checkout;
use App\Models\CheckoutTaxLine;
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
use App\Services\Payments\StripePlatformPaymentProvider;
use App\Support\CheckoutMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformCheckoutShippingTaxRecalculationTest extends TestCase
{
    use RefreshDatabase;

    public bool $failNextPaymentIntent = false;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.default_provider' => 'stripe',
            'payments.stripe.mode' => 'test',
            'payments.stripe.key' => 'pk_test_shipping_tax',
            'payments.stripe.secret' => 'sk_test_shipping_tax',
            'payments.stripe.webhook_secret' => 'whsec_shipping_tax',
        ]);

        $test = $this;
        $this->app->instance(StripePlatformPaymentProvider::class, new class(app(\App\Services\Payments\StripeConfig::class), $test) extends StripePlatformPaymentProvider
        {
            private int $counter = 0;

            public function __construct(\App\Services\Payments\StripeConfig $stripeConfig, private PlatformCheckoutShippingTaxRecalculationTest $test)
            {
                parent::__construct($stripeConfig);
            }

            public function createPaymentIntent(Checkout $checkout, array $options = []): PaymentIntentResult
            {
                if ($this->test->failNextPaymentIntent) {
                    $this->test->failNextPaymentIntent = false;

                    throw new \RuntimeException('Simulated provider sync failure');
                }

                $this->counter++;
                $id = 'pi_ship_tax_'.$checkout->id.'_'.$this->counter;

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
        });
    }

    public function test_selecting_taxable_shipping_recalculates_tax_lines_and_payment_intent(): void
    {
        [$store, $token] = $this->tokenedStore('Shipping Tax Recalc Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $methods = $this->shippingSetup($store);
        $this->enableTax($store, ['shipping_taxable' => true]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant))
            ->assertCreated()
            ->assertJsonPath('checkout.tax_total', '2.00')
            ->assertJsonPath('checkout.grand_total', '22.00')
            ->assertJsonPath('payment.provider_intent_id', 'pi_ship_tax_1_1');

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();

        $this->withToken($token)
            ->postJson('/api/v1/checkout/'.$checkout->id.'/shipping-method', [
                'shipping_method_id' => $methods['standard']->id,
            ])
            ->assertOk()
            ->assertJsonPath('checkout.shipping_total', '5.00')
            ->assertJsonPath('checkout.tax_total', '2.50')
            ->assertJsonPath('checkout.grand_total', '27.50')
            ->assertJsonPath('checkout.items.0.tax_amount', '2.00')
            ->assertJsonPath('payment.provider_intent_id', 'pi_ship_tax_1_2');

        $checkout = $checkout->fresh();
        $this->assertSame(2, CheckoutTaxLine::query()->where('checkout_id', $checkout->id)->count());
        $this->assertDatabaseHas('checkout_tax_lines', [
            'checkout_id' => $checkout->id,
            'applies_to' => CheckoutTaxLine::APPLIES_TO_SHIPPING,
            'taxable_amount' => 5.00,
            'tax_amount' => 0.50,
        ]);
        $this->assertDatabaseHas('payment_intents', [
            'checkout_id' => $checkout->id,
            'provider_intent_id' => 'pi_ship_tax_1_2',
            'amount' => 27.50,
            'amount_minor' => 2750,
        ]);
        $this->assertDatabaseHas('payment_intents', [
            'checkout_id' => $checkout->id,
            'provider_intent_id' => 'pi_ship_tax_1_1',
            'status' => 'superseded',
        ]);
    }

    public function test_switching_to_free_shipping_removes_stale_shipping_tax_and_noops_same_total(): void
    {
        [$store, $token] = $this->tokenedStore('Free Shipping Tax Recalc Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $methods = $this->shippingSetup($store);
        $this->enableTax($store, ['shipping_taxable' => true]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, [
                'shipping_method_id' => $methods['standard']->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('checkout.tax_total', '2.50')
            ->assertJsonPath('checkout.grand_total', '27.50');

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();

        $this->withToken($token)
            ->postJson('/api/v1/checkout/'.$checkout->id.'/shipping-method', [
                'shipping_method_id' => $methods['free']->id,
            ])
            ->assertOk()
            ->assertJsonPath('checkout.shipping_total', '0.00')
            ->assertJsonPath('checkout.tax_total', '2.00')
            ->assertJsonPath('checkout.grand_total', '22.00');

        $this->assertSame(
            0,
            CheckoutTaxLine::query()
                ->where('checkout_id', $checkout->id)
                ->where('applies_to', CheckoutTaxLine::APPLIES_TO_SHIPPING)
                ->count()
        );

        $paymentIntentCount = PaymentIntent::query()->where('checkout_id', $checkout->id)->count();

        $this->withToken($token)
            ->postJson('/api/v1/checkout/'.$checkout->id.'/shipping-method', [
                'shipping_method_id' => $methods['free']->id,
            ])
            ->assertOk()
            ->assertJsonPath('checkout.grand_total', '22.00');

        $this->assertSame($paymentIntentCount, PaymentIntent::query()->where('checkout_id', $checkout->id)->count());
    }

    public function test_shipping_mutation_uses_current_tax_rate_but_persisted_item_price_and_taxability(): void
    {
        [$store, $token] = $this->tokenedStore('Persisted Price Recalc Store');
        [$product, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $methods = $this->shippingSetup($store);
        $settings = $this->enableTax($store, ['shipping_taxable' => true]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant))
            ->assertCreated()
            ->assertJsonPath('checkout.subtotal', '20.00')
            ->assertJsonPath('checkout.tax_total', '2.00');

        $variant->update(['price' => 99.00]);
        $product->update(['is_taxable' => false]);
        TaxRate::query()->forStore($store->id)->update(['rate_percent' => '20.0000']);
        $settings->update(['settings_version' => 7]);

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();

        $this->withToken($token)
            ->postJson('/api/v1/checkout/'.$checkout->id.'/shipping-method', [
                'shipping_method_id' => $methods['standard']->id,
            ])
            ->assertOk()
            ->assertJsonPath('checkout.subtotal', '20.00')
            ->assertJsonPath('checkout.shipping_total', '5.00')
            ->assertJsonPath('checkout.tax_total', '5.00')
            ->assertJsonPath('checkout.grand_total', '30.00')
            ->assertJsonPath('checkout.items.0.unit_price', '20.00')
            ->assertJsonPath('checkout.items.0.tax_amount', '4.00');

        $checkout = $checkout->fresh(['items']);
        $this->assertSame(7, data_get($checkout->metadata, 'tax_snapshot.settings_version'));
        $this->assertTrue((bool) data_get($checkout->items->first()->metadata, 'tax.is_taxable'));
    }

    public function test_shipping_address_mutation_recalculates_jurisdiction_and_persists_address(): void
    {
        [$store, $token] = $this->tokenedStore('Address Jurisdiction Recalc Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $methods = $this->shippingSetup($store, includeCalifornia: true);
        $this->enableTax($store, rates: [
            [
                'country_code' => 'US',
                'region_code' => 'TX',
                'name' => 'TX Tax',
                'rate_percent' => '10.0000',
            ],
            [
                'country_code' => 'US',
                'region_code' => 'CA',
                'name' => 'CA Tax',
                'rate_percent' => '5.0000',
            ],
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant))
            ->assertCreated()
            ->assertJsonPath('checkout.tax_total', '2.00');

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();

        $this->withToken($token)
            ->postJson('/api/v1/checkout/'.$checkout->id.'/shipping-method', [
                'shipping_method_id' => $methods['california']->id,
                'shipping_address' => [
                    'address_line1' => '55 Market Street',
                    'city' => 'San Francisco',
                    'state' => 'CA',
                    'postal_code' => '94105',
                    'country' => 'US',
                    'country_code' => 'US',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('checkout.tax_total', '1.00')
            ->assertJsonPath('checkout.grand_total', '25.00');

        $checkout = $checkout->fresh(['addresses']);
        $shippingAddress = $checkout->addresses->firstWhere('type', 'shipping');
        $this->assertSame('55 Market Street', $shippingAddress->address_line1);
        $this->assertSame('CA', $shippingAddress->state);
        $this->assertSame('CA', data_get($checkout->metadata, 'tax_snapshot.destination.region_code'));
    }

    public function test_provider_sync_failure_rolls_back_shipping_tax_and_local_totals(): void
    {
        [$store, $token] = $this->tokenedStore('Provider Rollback Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $methods = $this->shippingSetup($store);
        $this->enableTax($store, ['shipping_taxable' => true]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant))
            ->assertCreated()
            ->assertJsonPath('checkout.grand_total', '22.00');

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $beforeUpdatedAt = (string) $checkout->updated_at;
        $this->failNextPaymentIntent = true;

        $this->withToken($token)
            ->postJson('/api/v1/checkout/'.$checkout->id.'/shipping-method', [
                'shipping_method_id' => $methods['standard']->id,
            ])
            ->assertStatus(500);

        $checkout = $checkout->fresh();
        $this->assertSame('0.00', (string) $checkout->shipping_total);
        $this->assertSame('2.00', (string) $checkout->tax_total);
        $this->assertSame('22.00', (string) $checkout->grand_total);
        $this->assertSame($beforeUpdatedAt, (string) $checkout->updated_at);
        $this->assertSame(1, PaymentIntent::query()->where('checkout_id', $checkout->id)->count());
        $this->assertSame(1, CheckoutTaxLine::query()->where('checkout_id', $checkout->id)->count());
    }

    public function test_jpy_shipping_recalculation_uses_zero_decimal_minor_units(): void
    {
        [$store, $token] = $this->tokenedStore('JPY Shipping Tax Store', currency: 'JPY');
        [, $variant] = $this->product($store, ['price' => 1000, 'stock' => 5]);
        $methods = $this->shippingSetup($store, flatRate: 300);
        $this->enableTax($store, ['shipping_taxable' => true], rates: [[
            'country_code' => 'JP',
            'region_code' => '',
            'name' => 'JP Tax',
            'rate_percent' => '10.0000',
        ]]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, [
                'currency_code' => 'jpy',
                'shipping_address' => [
                    'name' => 'Tokyo Buyer',
                    'address_line1' => '1 Chiyoda',
                    'city' => 'Tokyo',
                    'state' => '',
                    'postal_code' => '1000001',
                    'country' => 'JP',
                    'country_code' => 'JP',
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('checkout.grand_total', '1100.00');

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();

        $this->withToken($token)
            ->postJson('/api/v1/checkout/'.$checkout->id.'/shipping-method', [
                'shipping_method_id' => $methods['standard']->id,
            ])
            ->assertOk()
            ->assertJsonPath('checkout.tax_total', '130.00')
            ->assertJsonPath('checkout.grand_total', '1430.00');

        $this->assertDatabaseHas('payment_intents', [
            'checkout_id' => $checkout->id,
            'provider_intent_id' => 'pi_ship_tax_1_2',
            'amount_minor' => 1430,
            'currency_code' => 'JPY',
        ]);
    }

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

    private function product(Store $store, array $overrides = []): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $overrides['name'] ?? 'Shipping Tax Product',
            'slug' => 'shipping-tax-product-'.Str::random(6),
            'base_price' => $overrides['price'] ?? 20,
            'sku' => $overrides['sku'] ?? 'SHIP-TAX-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'is_taxable' => $overrides['is_taxable'] ?? true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => $overrides['variant_sku'] ?? $product->sku.'-D',
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

        if ($rates === []) {
            $rates = [[
                'country_code' => 'US',
                'region_code' => 'TX',
                'name' => 'TX Tax',
                'rate_percent' => '10.0000',
            ]];
        }

        foreach ($rates as $rate) {
            TaxRate::query()->create(array_merge([
                'store_id' => $store->id,
                'country_code' => 'US',
                'region_code' => 'TX',
                'name' => 'Default Tax',
                'rate_percent' => '10.0000',
                'priority' => 100,
                'is_active' => true,
            ], $rate));
        }

        return $settings->fresh();
    }

    private function shippingSetup(Store $store, float $flatRate = 5.00, bool $includeCalifornia = false): array
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
            'supported_countries' => ['US', 'JP'],
            'enabled_for_checkout' => true,
        ]);
        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'Texas',
            'countries' => ['US', 'JP'],
            'regions' => $store->currency === 'JPY' ? [] : ['TX'],
            'postal_patterns' => $store->currency === 'JPY' ? ['100*'] : ['733*'],
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
        $free = ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'carrier_account_id' => $account->id,
            'name' => 'Free delivery',
            'code' => 'free-delivery',
            'description' => 'Free delivery',
            'delivery_speed_label' => '5-7 business days',
            'rate_type' => ShippingMethod::RATE_FREE,
            'flat_rate' => 0,
            'enabled_for_checkout' => true,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $methods = ['standard' => $standard, 'free' => $free];

        if ($includeCalifornia) {
            $caZone = ShippingZone::query()->create([
                'store_id' => $store->id,
                'name' => 'California',
                'countries' => ['US'],
                'regions' => ['CA'],
                'postal_patterns' => ['941*'],
                'is_active' => true,
                'sort_order' => 1,
            ]);
            $methods['california'] = ShippingMethod::query()->create([
                'store_id' => $store->id,
                'shipping_zone_id' => $caZone->id,
                'carrier_account_id' => $account->id,
                'name' => 'California delivery',
                'code' => 'california-delivery',
                'description' => 'California delivery',
                'delivery_speed_label' => '2-4 business days',
                'rate_type' => ShippingMethod::RATE_FLAT,
                'flat_rate' => 4.00,
                'enabled_for_checkout' => true,
                'is_active' => true,
                'sort_order' => 1,
            ]);
        }

        return $methods;
    }

    private function payload(ProductVariant $variant, array $overrides = []): array
    {
        return array_replace_recursive([
            'source_channel' => 'dev_storefront',
            'currency_code' => $variant->product->store->currency ?? 'USD',
            'customer' => [
                'full_name' => 'Shipping Tax Buyer',
                'email' => 'shipping.tax@example.test',
                'phone' => '+15550188',
            ],
            'shipping_address' => [
                'name' => 'Shipping Tax Buyer',
                'address_line1' => '123 Platform Way',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '73301',
                'country' => 'US',
                'country_code' => 'US',
                'phone' => '+15550188',
            ],
            'billing_address' => ['same_as_shipping' => true],
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 1],
            ],
        ], $overrides);
    }
}
