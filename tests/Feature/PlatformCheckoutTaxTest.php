<?php

namespace Tests\Feature;

use App\Data\Payments\PaymentIntentResult;
use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Checkout;
use App\Models\CheckoutItem;
use App\Models\CheckoutTaxLine;
use App\Models\PaymentIntent;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\SecurityLog;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use App\Models\User;
use App\Services\Checkout\CheckoutTotalsService;
use App\Services\Payments\StripePlatformPaymentProvider;
use App\Support\CheckoutMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformCheckoutTaxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.default_provider' => 'stripe',
            'payments.stripe.mode' => 'test',
            'payments.stripe.key' => 'pk_test_platform_tax',
            'payments.stripe.secret' => 'sk_test_platform_tax',
            'payments.stripe.webhook_secret' => 'whsec_platform_tax',
            'payments.stripe.modes' => [
                'test' => [
                    'key' => 'pk_test_platform_tax',
                    'secret' => 'sk_test_platform_tax',
                    'webhook_secret' => 'whsec_platform_tax',
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
                    providerIntentId: 'pi_test_tax_'.$checkout->id,
                    clientSecret: 'pi_test_tax_'.$checkout->id.'_secret_test',
                    status: 'requires_payment_method',
                    amount: (float) $checkout->grand_total,
                    currencyCode: $checkout->currency_code,
                    raw: [
                        'id' => 'pi_test_tax_'.$checkout->id,
                        'status' => 'requires_payment_method',
                    ],
                );
            }
        });
    }

    public function test_tax_disabled_checkout_leaves_grand_total_unchanged_with_zero_tax(): void
    {
        [$store, $token] = $this->tokenedStore('Tax Disabled Store');
        [, $variant] = $this->product($store, ['price' => 12, 'stock' => 5]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant))
            ->assertCreated()
            ->assertJsonPath('checkout.subtotal', '24.00')
            ->assertJsonPath('checkout.tax_total', '0.00')
            ->assertJsonPath('checkout.grand_total', '24.00');

        $this->assertSame(0, CheckoutTaxLine::query()->where('store_id', $store->id)->count());
        $this->assertDatabaseHas('checkouts', [
            'store_id' => $store->id,
            'subtotal' => 24.00,
            'tax_total' => 0.00,
            'grand_total' => 24.00,
        ]);
    }

    public function test_client_supplied_tax_totals_are_ignored_when_tax_is_enabled(): void
    {
        [$store, $token] = $this->tokenedStore('Client Tax Override Store');
        [, $variant] = $this->product($store, ['price' => 12, 'stock' => 5]);
        $this->enableTax($store);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, [
                'tax_total' => 50,
                'grand_total' => 999,
                'discount_total' => 10,
            ]))
            ->assertCreated()
            ->assertJsonPath('checkout.subtotal', '24.00')
            ->assertJsonPath('checkout.tax_total', '2.40')
            ->assertJsonPath('checkout.grand_total', '26.40')
            ->assertJsonPath('checkout.discount_total', '0.00');
    }

    public function test_missing_tax_setting_blocks_checkout_without_side_effects(): void
    {
        [$store, $token] = $this->tokenedStore('Missing Tax Setting Store');
        [, $variant] = $this->product($store, ['price' => 12, 'stock' => 5]);

        TaxSetting::query()->where('store_id', $store->id)->delete();

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['checkout']);

        $this->assertSame(0, Checkout::query()->where('store_id', $store->id)->count());
        $this->assertSame(0, CheckoutTaxLine::query()->where('store_id', $store->id)->count());
        $this->assertSame(0, \App\Models\InventoryReservation::query()->where('store_id', $store->id)->count());
    }

    public function test_cross_store_tax_rates_never_apply(): void
    {
        [$store, $token] = $this->tokenedStore('Cross Store Tax Store');
        [$otherStore] = $this->tokenedStore('Other Store Tax Rates');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);

        $store->taxSetting->update([
            'enabled' => true,
            'prices_include_tax' => false,
            'default_product_taxable' => true,
            'shipping_taxable' => false,
            'calculation_address' => TaxSetting::CALCULATION_ADDRESS_SHIPPING,
        ]);
        $this->enableTax($otherStore, rates: [[
            'country_code' => 'US',
            'region_code' => 'TX',
            'name' => 'Other Store TX',
            'rate_percent' => '20.0000',
        ]]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, [
                'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            ]))
            ->assertCreated()
            ->assertJsonPath('checkout.tax_total', '0.00')
            ->assertJsonPath('checkout.grand_total', '20.00');

        $this->assertSame(0, CheckoutTaxLine::query()->where('store_id', $store->id)->count());
    }

    public function test_exclusive_taxable_item_at_ten_percent(): void
    {
        [$store, $token] = $this->tokenedStore('Exclusive Tax Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $this->enableTax($store);

        $response = $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, [
                'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            ]))
            ->assertCreated();

        $response
            ->assertJsonPath('checkout.subtotal', '20.00')
            ->assertJsonPath('checkout.tax_total', '2.00')
            ->assertJsonPath('checkout.grand_total', '22.00')
            ->assertJsonPath('checkout.items.0.subtotal', '20.00')
            ->assertJsonPath('checkout.items.0.tax_amount', '2.00')
            ->assertJsonPath('checkout.items.0.total', '22.00');
    }

    public function test_mixed_taxable_and_non_taxable_cart_tax_allocation(): void
    {
        [$store, $token] = $this->tokenedStore('Mixed Taxable Store');
        [$taxableProduct, $taxableVariant] = $this->product($store, [
            'name' => 'Taxable Product',
            'price' => 20,
            'stock' => 5,
            'is_taxable' => true,
        ]);
        [$exemptProduct, $exemptVariant] = $this->product($store, [
            'name' => 'Exempt Product',
            'price' => 15,
            'stock' => 5,
            'is_taxable' => false,
        ]);
        $this->enableTax($store);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($taxableVariant, [
                'items' => [
                    ['variant_id' => $taxableVariant->id, 'quantity' => 1],
                    ['variant_id' => $exemptVariant->id, 'quantity' => 1],
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('checkout.subtotal', '35.00')
            ->assertJsonPath('checkout.tax_total', '2.00')
            ->assertJsonPath('checkout.grand_total', '37.00');

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $taxableItem = $checkout->items->firstWhere('product_variant_id', $taxableVariant->id);
        $exemptItem = $checkout->items->firstWhere('product_variant_id', $exemptVariant->id);

        $this->assertSame(2.0, (float) $taxableItem->tax_amount);
        $this->assertSame(0.0, (float) $exemptItem->tax_amount);
        $this->assertFalse((bool) data_get($exemptItem->metadata, 'tax.is_taxable'));
        $this->assertTrue((bool) data_get($taxableItem->metadata, 'tax.is_taxable'));
        unset($taxableProduct, $exemptProduct);
    }

    public function test_regional_tax_rate_wins_over_country_wide_rate(): void
    {
        [$store, $token] = $this->tokenedStore('Regional Tax Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $this->enableTax($store, rates: [
            [
                'country_code' => 'US',
                'region_code' => '',
                'name' => 'US Country',
                'rate_percent' => '5.0000',
            ],
            [
                'country_code' => 'US',
                'region_code' => 'TX',
                'name' => 'TX Regional',
                'rate_percent' => '9.5000',
            ],
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, [
                'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            ]))
            ->assertCreated()
            ->assertJsonPath('checkout.tax_total', '1.90')
            ->assertJsonPath('checkout.grand_total', '21.90');

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame('9.5000', (string) data_get($checkout->metadata, 'tax_snapshot.matched_rate.rate_percent'));
        $this->assertSame('TX', data_get($checkout->metadata, 'tax_snapshot.matched_rate.region_code'));
    }

    public function test_no_matching_tax_rate_produces_zero_tax_and_no_tax_lines(): void
    {
        [$store, $token] = $this->tokenedStore('No Match Tax Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $this->enableTax($store, rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Only',
            'rate_percent' => '10.0000',
        ]]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, [
                'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
                'shipping_address' => [
                    'name' => 'Platform Buyer',
                    'address_line1' => '123 Platform Way',
                    'city' => 'Austin',
                    'state' => 'TX',
                    'postal_code' => '73301',
                    'country' => 'US',
                    'phone' => '+15550188',
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('checkout.tax_total', '0.00')
            ->assertJsonPath('checkout.grand_total', '20.00');

        $this->assertSame(0, CheckoutTaxLine::query()->where('store_id', $store->id)->count());
        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertNull(data_get($checkout->metadata, 'tax_snapshot.matched_rate'));
    }

    public function test_invalid_country_code_usa_rejects_checkout_without_side_effects(): void
    {
        [$store, $token] = $this->tokenedStore('Invalid Country Code Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $this->enableTax($store);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, [
                'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
                'shipping_address' => [
                    'name' => 'Platform Buyer',
                    'address_line1' => '123 Platform Way',
                    'city' => 'Austin',
                    'state' => 'TX',
                    'postal_code' => '73301',
                    'country' => 'United States',
                    'country_code' => 'USA',
                    'phone' => '+15550188',
                ],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shipping_address.country_code']);

        $this->assertSame(0, Checkout::query()->where('store_id', $store->id)->count());
        $this->assertSame(0, CheckoutItem::query()->where('store_id', $store->id)->count());
        $this->assertSame(0, CheckoutTaxLine::query()->where('store_id', $store->id)->count());
        $this->assertSame(0, PaymentIntent::query()->where('store_id', $store->id)->count());
        $this->assertSame(0, \App\Models\InventoryReservation::query()->where('store_id', $store->id)->count());
    }

    public function test_two_letter_country_field_applies_tax_when_country_code_missing(): void
    {
        [$store, $token] = $this->tokenedStore('Two Letter Country Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $this->enableTax($store);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, [
                'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
                'shipping_address' => [
                    'name' => 'Platform Buyer',
                    'address_line1' => '123 Platform Way',
                    'city' => 'Austin',
                    'state' => 'TX',
                    'postal_code' => '73301',
                    'country' => 'US',
                    'phone' => '+15550188',
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('checkout.tax_total', '2.00')
            ->assertJsonPath('checkout.grand_total', '22.00');

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame('US', data_get($checkout->metadata, 'tax_snapshot.destination.country_code'));
        $this->assertFalse((bool) data_get($checkout->metadata, 'tax_snapshot.tax_calculation_skipped'));
    }

    public function test_lowercase_country_code_is_accepted_and_normalized(): void
    {
        [$store, $token] = $this->tokenedStore('Lowercase Country Code Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $this->enableTax($store);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, [
                'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
                'shipping_address' => [
                    'name' => 'Platform Buyer',
                    'address_line1' => '123 Platform Way',
                    'city' => 'Austin',
                    'state' => 'TX',
                    'postal_code' => '73301',
                    'country' => 'United States',
                    'country_code' => 'us',
                    'phone' => '+15550188',
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('checkout.tax_total', '2.00')
            ->assertJsonPath('checkout.grand_total', '22.00');

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame('US', data_get($checkout->metadata, 'tax_snapshot.destination.country_code'));
    }

    public function test_missing_country_code_skips_tax_calculation(): void
    {
        [$store, $token] = $this->tokenedStore('Missing Country Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $this->enableTax($store);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, [
                'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
                'shipping_address' => [
                    'name' => 'Platform Buyer',
                    'address_line1' => '123 Platform Way',
                    'city' => 'Austin',
                    'state' => 'TX',
                    'postal_code' => '73301',
                    'country' => 'United States',
                    'phone' => '+15550188',
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('checkout.tax_total', '0.00')
            ->assertJsonPath('checkout.grand_total', '20.00');

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertTrue((bool) data_get($checkout->metadata, 'tax_snapshot.tax_calculation_skipped'));
        $this->assertSame('missing_country', data_get($checkout->metadata, 'tax_snapshot.skip_reason'));
        $this->assertSame('', data_get($checkout->metadata, 'tax_snapshot.destination.country_code'));
        $this->assertSame(0, CheckoutTaxLine::query()->where('store_id', $store->id)->count());
    }

    public function test_inclusive_pricing_extracts_tax_without_inflating_grand_total(): void
    {
        [$store, $token] = $this->tokenedStore('Inclusive Tax Store');
        [, $variant] = $this->product($store, ['price' => 22, 'stock' => 5]);
        $this->enableTax($store, ['prices_include_tax' => true]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, [
                'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            ]))
            ->assertCreated()
            ->assertJsonPath('checkout.subtotal', '22.00')
            ->assertJsonPath('checkout.tax_total', '2.00')
            ->assertJsonPath('checkout.grand_total', '22.00')
            ->assertJsonPath('checkout.items.0.subtotal', '22.00')
            ->assertJsonPath('checkout.items.0.tax_amount', '2.00')
            ->assertJsonPath('checkout.items.0.total', '22.00');
    }

    public function test_inclusive_mixed_cart_totals(): void
    {
        [$store, $token] = $this->tokenedStore('Inclusive Mixed Store');
        [, $taxableVariant] = $this->product($store, [
            'name' => 'Inclusive Taxable',
            'price' => 22,
            'stock' => 5,
            'is_taxable' => true,
        ]);
        [, $exemptVariant] = $this->product($store, [
            'name' => 'Inclusive Exempt',
            'price' => 11,
            'stock' => 5,
            'is_taxable' => false,
        ]);
        $this->enableTax($store, ['prices_include_tax' => true]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($taxableVariant, [
                'items' => [
                    ['variant_id' => $taxableVariant->id, 'quantity' => 1],
                    ['variant_id' => $exemptVariant->id, 'quantity' => 1],
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('checkout.subtotal', '33.00')
            ->assertJsonPath('checkout.tax_total', '2.00')
            ->assertJsonPath('checkout.grand_total', '33.00');
    }

    public function test_taxable_shipping_on_checkout_create(): void
    {
        [$store, $token] = $this->tokenedStore('Taxable Shipping Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $methods = $this->shippingFixtures($store);
        $this->enableTax($store, ['shipping_taxable' => true]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, [
                'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
                'shipping_method_id' => $methods['flat']->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('checkout.shipping_total', '5.00')
            ->assertJsonPath('checkout.tax_total', '2.50')
            ->assertJsonPath('checkout.grand_total', '27.50');

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $shippingLine = CheckoutTaxLine::query()
            ->where('checkout_id', $checkout->id)
            ->where('applies_to', CheckoutTaxLine::APPLIES_TO_SHIPPING)
            ->first();

        $this->assertNotNull($shippingLine);
        $this->assertSame(5.0, (float) $shippingLine->taxable_amount);
        $this->assertSame(0.5, (float) $shippingLine->tax_amount);
    }

    public function test_non_taxable_shipping_omits_shipping_tax_line(): void
    {
        [$store, $token] = $this->tokenedStore('Non Taxable Shipping Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $methods = $this->shippingFixtures($store);
        $this->enableTax($store, ['shipping_taxable' => false]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, [
                'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
                'shipping_method_id' => $methods['flat']->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('checkout.shipping_total', '5.00')
            ->assertJsonPath('checkout.tax_total', '2.00')
            ->assertJsonPath('checkout.grand_total', '27.00');

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame(
            0,
            CheckoutTaxLine::query()
                ->where('checkout_id', $checkout->id)
                ->where('applies_to', CheckoutTaxLine::APPLIES_TO_SHIPPING)
                ->count()
        );
    }

    public function test_zero_percent_taxable_shipping_persists_shipping_line(): void
    {
        [$store, $token] = $this->tokenedStore('Zero Shipping Tax Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $methods = $this->shippingFixtures($store);
        $this->enableTax($store, ['shipping_taxable' => true], rates: [[
            'country_code' => 'US',
            'region_code' => 'TX',
            'name' => 'TX Zero',
            'rate_percent' => '0.0000',
        ]]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, [
                'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
                'shipping_method_id' => $methods['flat']->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('checkout.tax_total', '0.00')
            ->assertJsonPath('checkout.grand_total', '25.00');

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $shippingLine = CheckoutTaxLine::query()
            ->where('checkout_id', $checkout->id)
            ->where('applies_to', CheckoutTaxLine::APPLIES_TO_SHIPPING)
            ->first();

        $this->assertNotNull($shippingLine);
        $this->assertSame(5.0, (float) $shippingLine->taxable_amount);
        $this->assertSame(0.0, (float) $shippingLine->tax_amount);
    }

    public function test_inclusive_products_with_taxable_shipping(): void
    {
        [$store, $token] = $this->tokenedStore('Inclusive Shipping Tax Store');
        [, $variant] = $this->product($store, ['price' => 22, 'stock' => 5]);
        $methods = $this->shippingFixtures($store, flatRate: 10.00);
        $this->enableTax($store, [
            'prices_include_tax' => true,
            'shipping_taxable' => true,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, [
                'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
                'shipping_method_id' => $methods['flat']->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('checkout.subtotal', '22.00')
            ->assertJsonPath('checkout.shipping_total', '10.00')
            ->assertJsonPath('checkout.tax_total', '3.00')
            ->assertJsonPath('checkout.grand_total', '33.00');
    }

    public function test_tax_lines_metadata_and_settings_version_are_persisted_on_create(): void
    {
        [$store, $token] = $this->tokenedStore('Tax Persistence Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $settings = $this->enableTax($store);
        $settings->update(['settings_version' => 4]);
        $settingsVersionBefore = 4;
        $securityLogsBefore = SecurityLog::query()->where('store_id', $store->id)->count();

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, [
                'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            ]))
            ->assertCreated();

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $taxLine = CheckoutTaxLine::query()->where('checkout_id', $checkout->id)->firstOrFail();
        $item = CheckoutItem::query()->where('checkout_id', $checkout->id)->firstOrFail();
        $snapshot = data_get($checkout->metadata, 'tax_snapshot');

        $this->assertSame($store->id, $taxLine->store_id);
        $this->assertSame($checkout->id, $taxLine->checkout_id);
        $this->assertNotNull($taxLine->tax_rate_id);
        $this->assertSame('US', $taxLine->jurisdiction_country_code);
        $this->assertSame('TX', $taxLine->jurisdiction_region_code);
        $this->assertSame('10.0000', (string) $taxLine->rate_percent);
        $this->assertSame(20.0, (float) $taxLine->taxable_amount);
        $this->assertSame(2.0, (float) $taxLine->tax_amount);
        $this->assertSame(CheckoutTaxLine::APPLIES_TO_ITEMS, $taxLine->applies_to);
        $this->assertSame($settingsVersionBefore, $taxLine->settings_version);
        $this->assertNotNull($taxLine->calculated_at);

        $this->assertSame($variant->id, $item->product_variant_id);
        $this->assertSame(2.0, (float) $item->tax_amount);
        $this->assertTrue((bool) data_get($item->metadata, 'tax.is_taxable'));
        $this->assertFalse((bool) data_get($item->metadata, 'tax.prices_include_tax'));
        $this->assertSame($settingsVersionBefore, data_get($item->metadata, 'tax.settings_version'));

        $this->assertTrue((bool) $snapshot['enabled']);
        $this->assertFalse((bool) $snapshot['prices_include_tax']);
        $this->assertFalse((bool) $snapshot['shipping_taxable']);
        $this->assertSame($settingsVersionBefore, $snapshot['settings_version']);
        $this->assertSame('US', $snapshot['destination']['country_code']);
        $this->assertSame('TX', $snapshot['destination']['region_code']);
        $this->assertNotNull($snapshot['matched_rate']);
        $this->assertFalse((bool) $snapshot['tax_calculation_skipped']);
        $this->assertNull($snapshot['skip_reason']);
        $this->assertNotEmpty($snapshot['calculated_at']);

        $this->assertSame($settingsVersionBefore, $settings->fresh()->settings_version);
        $this->assertSame(
            $securityLogsBefore,
            SecurityLog::query()->where('store_id', $store->id)->where('event_type', 'tax.settings.updated')->count()
        );
    }

    public function test_payment_intent_amount_matches_taxed_grand_total(): void
    {
        [$store, $token] = $this->tokenedStore('Payment Intent Tax Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $this->enableTax($store);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, [
                'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            ]))
            ->assertCreated()
            ->assertJsonPath('checkout.grand_total', '22.00');

        $this->assertDatabaseHas('payment_intents', [
            'store_id' => $store->id,
            'amount' => 22.00,
            'amount_minor' => 2200,
        ]);
    }

    public function test_checkout_creation_rolls_back_when_tax_line_persistence_fails(): void
    {
        [$store, $token] = $this->tokenedStore('Tax Rollback Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $this->enableTax($store);

        $real = app(CheckoutTotalsService::class);
        $this->mock(CheckoutTotalsService::class, function ($mock) use ($real): void {
            $mock->shouldReceive('itemsSubtotal')->andReturnUsing(fn (...$a) => $real->itemsSubtotal(...$a));
            $mock->shouldReceive('calculate')->andReturnUsing(fn (...$a) => $real->calculate(...$a));
            $mock->shouldReceive('replaceTaxLines')->andThrow(new \RuntimeException('Simulated tax line persistence failure'));
        });

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, [
                'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            ]))
            ->assertStatus(500);

        $this->assertSame(0, Checkout::query()->where('store_id', $store->id)->count());
        $this->assertSame(0, CheckoutItem::query()->count());
        $this->assertSame(0, CheckoutTaxLine::query()->where('store_id', $store->id)->count());
        $this->assertSame(0, \App\Models\InventoryReservation::query()->where('store_id', $store->id)->count());
    }

    public function test_duplicate_variant_rows_merge_with_correct_tax(): void
    {
        [$store, $token] = $this->tokenedStore('Merged Variant Tax Store');
        [, $variant] = $this->product($store, ['price' => 10, 'stock' => 10]);
        $this->enableTax($store);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, [
                'items' => [
                    ['variant_id' => $variant->id, 'quantity' => 1],
                    ['variant_id' => $variant->id, 'quantity' => 1],
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('checkout.subtotal', '20.00')
            ->assertJsonPath('checkout.tax_total', '2.00')
            ->assertJsonPath('checkout.grand_total', '22.00');

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame(1, $checkout->items()->count());
        $item = $checkout->items->first();
        $this->assertSame(2, (int) $item->quantity);
        $this->assertSame(20.0, (float) $item->subtotal);
        $this->assertSame(2.0, (float) $item->tax_amount);
        $this->assertSame(22.0, (float) $item->total);
    }

    public function test_checkout_show_is_immutable_after_tax_rate_change(): void
    {
        [$store, $token] = $this->tokenedStore('Immutable Show Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $settings = $this->enableTax($store);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, [
                'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            ]))
            ->assertCreated();

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $checkoutBefore = $checkout->fresh()->getAttributes();
        $taxLinesBefore = CheckoutTaxLine::query()->where('checkout_id', $checkout->id)->count();
        $paymentIntentsBefore = PaymentIntent::query()->where('checkout_id', $checkout->id)->count();
        $itemsBefore = CheckoutItem::query()->where('checkout_id', $checkout->id)->count();

        TaxRate::query()->forStore($store->id)->update(['rate_percent' => '25.0000']);
        $settings->update(['settings_version' => 99]);

        $this->withToken($token)
            ->getJson('/api/v1/checkout/'.$checkout->id)
            ->assertOk()
            ->assertJsonPath('checkout.grand_total', '22.00')
            ->assertJsonPath('checkout.tax_total', '2.00')
            ->assertJsonPath('payment.provider_intent_id', 'pi_test_tax_'.$checkout->id);

        $checkoutAfter = $checkout->fresh()->getAttributes();
        $this->assertSame($checkoutBefore['grand_total'], $checkoutAfter['grand_total']);
        $this->assertSame($checkoutBefore['tax_total'], $checkoutAfter['tax_total']);
        $this->assertSame($checkoutBefore['subtotal'], $checkoutAfter['subtotal']);
        $this->assertSame($checkoutBefore['updated_at'], $checkoutAfter['updated_at']);
        $this->assertSame($taxLinesBefore, CheckoutTaxLine::query()->where('checkout_id', $checkout->id)->count());
        $this->assertSame($paymentIntentsBefore, PaymentIntent::query()->where('checkout_id', $checkout->id)->count());
        $this->assertSame($itemsBefore, CheckoutItem::query()->where('checkout_id', $checkout->id)->count());
    }

    public function test_checkout_show_returns_persisted_totals(): void
    {
        [$store, $token] = $this->tokenedStore('Show Totals Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $this->enableTax($store);

        $create = $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant, [
                'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            ]))
            ->assertCreated();

        $checkoutId = (int) $create->json('checkout.id');

        $this->withToken($token)
            ->getJson('/api/v1/checkout/'.$checkoutId)
            ->assertOk()
            ->assertJsonPath('checkout.subtotal', '20.00')
            ->assertJsonPath('checkout.tax_total', '2.00')
            ->assertJsonPath('checkout.grand_total', '22.00')
            ->assertJsonPath('checkout.items.0.tax_amount', '2.00')
            ->assertJsonPath('checkout.items.0.total', '22.00');
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
     * @return array{0: Product, 1: ProductVariant}
     */
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
            'is_taxable' => $overrides['is_taxable'] ?? true,
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
     * @param  list<array<string, mixed>>  $rates
     */
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
                'name' => 'TX Sales Tax',
                'rate_percent' => '10.0000',
            ]];
        }

        foreach ($rates as $rate) {
            TaxRate::query()->create(array_merge([
                'store_id' => $store->id,
                'country_code' => 'US',
                'region_code' => 'TX',
                'name' => 'TX Sales Tax',
                'rate_percent' => '10.0000',
                'priority' => 100,
                'is_active' => true,
            ], $rate));
        }

        return $settings->fresh();
    }

    /**
     * @return array{flat: ShippingMethod}
     */
    private function shippingFixtures(Store $store, float $flatRate = 5.00): array
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
            'name' => 'Texas',
            'countries' => ['US'],
            'regions' => ['TX'],
            'postal_patterns' => ['733*'],
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $flat = ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'carrier_account_id' => $account->id,
            'name' => 'Flat delivery',
            'code' => 'flat-delivery',
            'description' => 'Flat rate delivery',
            'delivery_speed_label' => '3-5 business days',
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => $flatRate,
            'enabled_for_checkout' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return ['flat' => $flat];
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
}
