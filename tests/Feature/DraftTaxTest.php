<?php

namespace Tests\Feature;

use App\Models\DraftOrder;
use App\Models\DraftTaxLine;
use App\Models\Order;
use App\Models\OrderTaxLine;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DraftTaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_show_does_not_calculate_or_mutate_tax(): void
    {
        $owner = $this->merchant('draft-show-tax@example.test');
        $store = $this->store($owner, 'Draft Show Tax Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $this->enableTax($store);

        $draft = $this->createDraft($owner, $store, $variant, $this->draftPayload($variant, [
            'tax_total' => '4.44',
        ]));
        $before = $draft->fresh()->getAttributes();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('draft-orders.show', $draft))
            ->assertOk()
            ->assertSeeText('Manual tax')
            ->assertSeeText('Save and calculate tax');

        $after = $draft->fresh()->getAttributes();
        $this->assertSame($before['tax_total'], $after['tax_total']);
        $this->assertSame($before['total'], $after['total']);
        $this->assertSame($before['updated_at'], $after['updated_at']);
        $this->assertSame(0, DraftTaxLine::query()->where('draft_order_id', $draft->id)->count());
    }

    public function test_manual_draft_tax_is_preserved_on_conversion_without_order_tax_lines(): void
    {
        $owner = $this->merchant('draft-manual-tax@example.test');
        $store = $this->store($owner, 'Draft Manual Tax Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);

        $draft = $this->createDraft($owner, $store, $variant, $this->draftPayload($variant, [
            'tax_total' => '3.33',
            'shipping_total' => '5.00',
        ]));

        $this->assertSame(DraftOrder::TAX_SOURCE_MANUAL, $draft->fresh()->taxSource());
        $this->assertSame('28.33', $draft->fresh()->total);
        $this->assertSame(0, DraftTaxLine::query()->where('draft_order_id', $draft->id)->count());
        $this->assertSame(0.0, (float) $draft->items()->firstOrFail()->tax_amount);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.convert', $draft))
            ->assertRedirect();

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame(3.33, (float) $order->tax);
        $this->assertSame(28.33, (float) $order->grand_total);
        $this->assertSame(0.0, (float) $order->items()->firstOrFail()->tax_amount);
        $this->assertSame(0, OrderTaxLine::query()->where('order_id', $order->id)->count());
    }

    public function test_calculated_draft_tax_persists_lines_and_copies_snapshot_to_order(): void
    {
        $owner = $this->merchant('draft-calculated-tax@example.test');
        $store = $this->store($owner, 'Draft Calculated Tax Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $this->enableTax($store, ['shipping_taxable' => true]);

        $draft = $this->createDraft($owner, $store, $variant, $this->draftPayload($variant, [
            'tax_total' => '0.00',
            'shipping_total' => '5.00',
        ]));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('draft-orders.show', $draft))
            ->post(route('draft-orders.calculate-tax', $draft), $this->draftPayload($variant, [
                'tax_total' => '0.00',
                'shipping_total' => '5.00',
            ]))
            ->assertRedirect(route('draft-orders.show', $draft))
            ->assertSessionHas('success', 'Draft saved and tax calculated from store settings.');

        $draft = $draft->fresh(['items', 'taxLines']);
        $this->assertSame(DraftOrder::TAX_SOURCE_CALCULATED, $draft->taxSource());
        $this->assertSame(2.50, (float) $draft->tax_total);
        $this->assertSame(27.50, (float) $draft->total);
        $this->assertSame(2.00, (float) $draft->items->first()->tax_amount);
        $this->assertSame(2, $draft->taxLines->count());
        $this->assertSame('US', data_get($draft->metadata, 'tax_snapshot.destination.country_code'));
        $this->assertDatabaseHas('draft_tax_lines', [
            'draft_order_id' => $draft->id,
            'applies_to' => DraftTaxLine::APPLIES_TO_ITEMS,
            'taxable_amount' => 20.00,
            'tax_amount' => 2.00,
        ]);
        $this->assertDatabaseHas('draft_tax_lines', [
            'draft_order_id' => $draft->id,
            'applies_to' => DraftTaxLine::APPLIES_TO_SHIPPING,
            'taxable_amount' => 5.00,
            'tax_amount' => 0.50,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.convert', $draft))
            ->assertRedirect();

        $order = Order::query()->where('store_id', $store->id)->with(['items', 'taxLines'])->firstOrFail();
        $this->assertSame(2.50, (float) $order->tax);
        $this->assertSame(0.50, (float) $order->shipping_tax);
        $this->assertSame(27.50, (float) $order->grand_total);
        $this->assertSame(2.00, (float) $order->items->first()->tax_amount);
        $this->assertSame(22.00, (float) $order->items->first()->total);
        $this->assertSame(2, $order->taxLines->count());
        $this->assertSame('US', data_get($order->meta, 'tax_snapshot.destination.country_code'));

        TaxRate::query()->where('store_id', $store->id)->update(['rate_percent' => '25.0000']);
        $this->assertSame(2.50, (float) $order->fresh()->tax);
        $this->assertSame(2, OrderTaxLine::query()->where('order_id', $order->id)->count());
    }

    public function test_calculated_inclusive_draft_tax_extracts_item_tax_and_adds_shipping_tax_once(): void
    {
        $owner = $this->merchant('draft-inclusive-tax@example.test');
        $store = $this->store($owner, 'Draft Inclusive Tax Store');
        [, $variant] = $this->product($store, ['price' => 22, 'stock' => 5]);
        $this->enableTax($store, [
            'prices_include_tax' => true,
            'shipping_taxable' => true,
        ]);

        $draft = $this->createDraft($owner, $store, $variant, $this->draftPayload($variant, [
            'shipping_total' => '10.00',
            'tax_total' => '0.00',
        ]));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.calculate-tax', $draft), $this->draftPayload($variant, [
                'shipping_total' => '10.00',
                'tax_total' => '0.00',
            ]))
            ->assertRedirect();

        $draft = $draft->fresh(['items', 'taxLines']);
        $this->assertSame(3.00, (float) $draft->tax_total);
        $this->assertSame(33.00, (float) $draft->total);
        $this->assertSame(2.00, (float) $draft->items->first()->tax_amount);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.convert', $draft))
            ->assertRedirect();

        $orderItem = Order::query()->where('store_id', $store->id)->firstOrFail()->items()->firstOrFail();
        $this->assertSame(2.00, (float) $orderItem->tax_amount);
        $this->assertSame(22.00, (float) $orderItem->total);
    }

    public function test_draft_calculate_tax_requires_country_and_enabled_tax(): void
    {
        $owner = $this->merchant('draft-tax-validation@example.test');
        $store = $this->store($owner, 'Draft Tax Validation Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);

        $draft = $this->createDraft($owner, $store, $variant, $this->draftPayload($variant, [
            'shipping_country' => '',
        ]));
        $this->enableTax($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('draft-orders.show', $draft))
            ->post(route('draft-orders.calculate-tax', $draft), $this->draftPayload($variant, [
                'shipping_country' => '',
            ]))
            ->assertRedirect(route('draft-orders.show', $draft))
            ->assertSessionHasErrors('shipping_country');

        $draftWithCountry = $this->createDraft($owner, $store, $variant, $this->draftPayload($variant, [
            'customer_email' => 'second-draft-tax@example.test',
            'shipping_country' => 'US',
        ]));
        $store->taxSetting->update(['enabled' => false]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('draft-orders.show', $draftWithCountry))
            ->post(route('draft-orders.calculate-tax', $draftWithCountry), $this->draftPayload($variant, [
                'customer_email' => 'second-draft-tax@example.test',
                'shipping_country' => 'US',
            ]))
            ->assertRedirect(route('draft-orders.show', $draftWithCountry))
            ->assertSessionHasErrors('tax');
    }

    public function test_manual_edit_after_calculation_returns_to_manual_and_clears_stale_tax_lines(): void
    {
        $owner = $this->merchant('draft-tax-reset@example.test');
        $store = $this->store($owner, 'Draft Tax Reset Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $this->enableTax($store, ['shipping_taxable' => true]);

        $draft = $this->createDraft($owner, $store, $variant);
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.calculate-tax', $draft), $this->draftPayload($variant))
            ->assertRedirect();

        $this->assertSame(2, DraftTaxLine::query()->where('draft_order_id', $draft->id)->count());

        $payload = $this->draftPayload($variant, [
            'tax_total' => '9.99',
            'shipping_total' => '5.00',
            'notes' => 'Manual tax override.',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('draft-orders.update', $draft), $payload)
            ->assertRedirect(route('draft-orders.show', $draft));

        $draft = $draft->fresh(['items']);
        $this->assertSame(DraftOrder::TAX_SOURCE_MANUAL, $draft->taxSource());
        $this->assertSame(0, DraftTaxLine::query()->where('draft_order_id', $draft->id)->count());
        $this->assertSame(0.0, (float) $draft->items->first()->tax_amount);
        $this->assertSame(34.99, (float) $draft->total);
    }

    public function test_zero_percent_calculated_draft_tax_lines_are_preserved(): void
    {
        $owner = $this->merchant('draft-zero-tax@example.test');
        $store = $this->store($owner, 'Draft Zero Tax Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $this->enableTax($store, rates: [[
            'country_code' => 'US',
            'region_code' => 'TX',
            'name' => 'Zero TX',
            'rate_percent' => '0.0000',
        ]]);

        $draft = $this->createDraft($owner, $store, $variant);
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.calculate-tax', $draft), $this->draftPayload($variant))
            ->assertRedirect();

        $this->assertDatabaseHas('draft_tax_lines', [
            'draft_order_id' => $draft->id,
            'applies_to' => DraftTaxLine::APPLIES_TO_ITEMS,
            'taxable_amount' => 20.00,
            'tax_amount' => 0.00,
        ]);
    }

    public function test_calculate_tax_saves_unsaved_form_values_before_calculating(): void
    {
        $owner = $this->merchant('draft-save-calc@example.test');
        $store = $this->store($owner, 'Draft Save Calc Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $this->enableTax($store, ['shipping_taxable' => true]);

        $draft = $this->createDraft($owner, $store, $variant, $this->draftPayload($variant, [
            'items' => [
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 1,
                    'unit_price' => '20.00',
                ],
            ],
            'shipping_total' => '5.00',
            'shipping_state' => 'TX',
            'shipping_country' => 'US',
        ]));

        $this->assertSame(1, (int) $draft->items()->firstOrFail()->quantity);
        $this->assertSame('5.00', (string) $draft->fresh()->shipping_total);

        $payload = $this->draftPayload($variant, [
            'items' => [
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 2,
                    'unit_price' => '20.00',
                ],
            ],
            'shipping_total' => '10.00',
            'shipping_state' => 'TX',
            'shipping_country' => 'US',
            'notes' => 'Updated before tax calculation.',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('draft-orders.show', $draft))
            ->post(route('draft-orders.calculate-tax', $draft), $payload)
            ->assertRedirect(route('draft-orders.show', $draft))
            ->assertSessionHas('success', 'Draft saved and tax calculated from store settings.');

        $draft = $draft->fresh(['items', 'taxLines']);
        $this->assertSame(2, (int) $draft->items->first()->quantity);
        $this->assertSame('10.00', (string) $draft->shipping_total);
        $this->assertSame('Updated before tax calculation.', $draft->notes);
        $this->assertSame(DraftOrder::TAX_SOURCE_CALCULATED, $draft->taxSource());
        $this->assertSame(5.00, (float) $draft->tax_total);
        $this->assertSame(55.00, (float) $draft->total);
        $this->assertSame(4.00, (float) $draft->items->first()->tax_amount);
        $this->assertGreaterThan(0, $draft->taxLines->count());
    }

    public function test_failed_calculate_tax_request_does_not_leave_partially_updated_draft(): void
    {
        $owner = $this->merchant('draft-calc-fail@example.test');
        $store = $this->store($owner, 'Draft Calc Fail Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $this->enableTax($store);

        $draft = $this->createDraft($owner, $store, $variant, $this->draftPayload($variant, [
            'items' => [
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 1,
                    'unit_price' => '20.00',
                ],
            ],
            'shipping_total' => '5.00',
            'notes' => 'Original note.',
        ]));

        $store->taxSetting->update(['enabled' => false]);

        $payload = $this->draftPayload($variant, [
            'items' => [
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 3,
                    'unit_price' => '20.00',
                ],
            ],
            'shipping_total' => '15.00',
            'notes' => 'Should not persist.',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('draft-orders.show', $draft))
            ->post(route('draft-orders.calculate-tax', $draft), $payload)
            ->assertRedirect(route('draft-orders.show', $draft))
            ->assertSessionHasErrors('tax');

        $draft = $draft->fresh(['items']);
        $this->assertSame(1, (int) $draft->items->first()->quantity);
        $this->assertSame('5.00', (string) $draft->shipping_total);
        $this->assertSame('Original note.', $draft->notes);
        $this->assertSame(0, DraftTaxLine::query()->where('draft_order_id', $draft->id)->count());
    }

    public function test_draft_country_and_region_codes_are_normalized_to_uppercase(): void
    {
        $owner = $this->merchant('draft-code-norm@example.test');
        $store = $this->store($owner, 'Draft Code Norm Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);

        $payload = $this->draftPayload($variant, [
            'shipping_country' => 'us',
            'shipping_state' => 'ca',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.store'), $payload)
            ->assertRedirect();

        $draft = DraftOrder::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame('US', $draft->shippingAddress()['country']);
        $this->assertSame('CA', $draft->shippingAddress()['state']);
    }

    public function test_draft_rejects_full_country_name_on_create_update_and_calculate(): void
    {
        $owner = $this->merchant('draft-country-reject@example.test');
        $store = $this->store($owner, 'Draft Country Reject Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $this->enableTax($store, rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales Tax',
            'rate_percent' => '8.2500',
        ]]);

        $invalidPayload = $this->draftPayload($variant, [
            'shipping_country' => 'United States',
            'shipping_state' => 'CA',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('orders.create'))
            ->post(route('draft-orders.store'), $invalidPayload)
            ->assertRedirect(route('orders.create'))
            ->assertSessionHasErrors('shipping_country');

        $draft = $this->createDraft($owner, $store, $variant);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('draft-orders.show', $draft))
            ->patch(route('draft-orders.update', $draft), array_merge($this->draftPayload($variant), [
                'shipping_country' => 'United States',
            ]))
            ->assertRedirect(route('draft-orders.show', $draft))
            ->assertSessionHasErrors('shipping_country');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('draft-orders.show', $draft))
            ->post(route('draft-orders.calculate-tax', $draft), array_merge($this->draftPayload($variant), [
                'shipping_country' => 'United States',
                'shipping_state' => 'CA',
            ]))
            ->assertRedirect(route('draft-orders.show', $draft))
            ->assertSessionHasErrors('shipping_country');
    }

    public function test_draft_rejects_numeric_and_non_ascii_country_codes(): void
    {
        $owner = $this->merchant('draft-country-format@example.test');
        $store = $this->store($owner, 'Draft Country Format Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);

        foreach (['U1', 'ÜS'] as $invalidCountry) {
            $this->actingAs($owner)
                ->withSession(['current_store_id' => $store->id])
                ->from(route('orders.create'))
                ->post(route('draft-orders.store'), $this->draftPayload($variant, [
                    'shipping_country' => $invalidCountry,
                ]))
                ->assertRedirect(route('orders.create'))
                ->assertSessionHasErrors('shipping_country');
        }
    }

    public function test_draft_show_renders_legacy_country_value_with_guidance(): void
    {
        $owner = $this->merchant('draft-legacy-country@example.test');
        $store = $this->store($owner, 'Draft Legacy Country Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);

        $draft = $this->createDraft($owner, $store, $variant);
        $metadata = is_array($draft->metadata) ? $draft->metadata : [];
        $metadata['shipping_address']['country'] = 'United States';
        $draft->update(['metadata' => $metadata]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('draft-orders.show', $draft))
            ->assertOk()
            ->assertSee('United States', false)
            ->assertSee('This draft contains a legacy country value', false)
            ->assertSee('Replace it with a two-letter code such as US', false);
    }

    public function test_ascii_two_letter_country_code_is_accepted(): void
    {
        $owner = $this->merchant('draft-country-ca@example.test');
        $store = $this->store($owner, 'Draft Country CA Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.store'), $this->draftPayload($variant, [
                'shipping_country' => 'CA',
            ]))
            ->assertRedirect();

        $draft = DraftOrder::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame('CA', $draft->shippingAddress()['country']);
    }

    public function test_calculated_draft_tax_matches_us_ca_configured_rate(): void
    {
        $owner = $this->merchant('draft-ca-rate@example.test');
        $store = $this->store($owner, 'Draft CA Rate Store');
        [, $variant] = $this->product($store, ['price' => 100, 'stock' => 5]);
        $this->enableTax($store, rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales Tax',
            'rate_percent' => '8.2500',
        ]]);

        $draft = $this->createDraft($owner, $store, $variant, $this->draftPayload($variant, [
            'shipping_total' => '0.00',
            'shipping_state' => 'CA',
            'shipping_country' => 'US',
            'items' => [
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 1,
                    'unit_price' => '100.00',
                ],
            ],
        ]));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.calculate-tax', $draft), $this->draftPayload($variant, [
                'shipping_total' => '0.00',
                'shipping_state' => 'CA',
                'shipping_country' => 'US',
                'items' => [
                    [
                        'product_variant_id' => $variant->id,
                        'quantity' => 1,
                        'unit_price' => '100.00',
                    ],
                ],
            ]))
            ->assertRedirect();

        $draft = $draft->fresh(['items', 'taxLines']);
        $this->assertSame(8.25, (float) $draft->tax_total);
        $this->assertSame(DraftOrder::TAX_SOURCE_CALCULATED, $draft->taxSource());
        $this->assertGreaterThan(0, $draft->taxLines->count());
    }

    private function createDraft(User $owner, Store $store, ProductVariant $variant, ?array $payload = null): DraftOrder
    {
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.store'), $payload ?? $this->draftPayload($variant))
            ->assertRedirect();

        return DraftOrder::query()->where('store_id', $store->id)->latest('id')->firstOrFail();
    }

    private function draftPayload(ProductVariant $variant, array $overrides = []): array
    {
        return array_replace_recursive([
            'customer_name' => 'Draft Tax Buyer',
            'customer_email' => 'draft.tax@example.test',
            'customer_phone' => '+15550199',
            'shipping_name' => 'Draft Tax Buyer',
            'shipping_phone' => '+15550199',
            'shipping_address_line1' => '10 Tax Road',
            'shipping_city' => 'Austin',
            'shipping_state' => 'TX',
            'shipping_postal_code' => '73301',
            'shipping_country' => 'US',
            'billing_same_as_shipping' => '1',
            'shipping_total' => '5.00',
            'tax_total' => '0.00',
            'discount_total' => '0.00',
            'notes' => 'Manual draft order.',
            'items' => [
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 1,
                    'unit_price' => (string) $variant->price,
                ],
            ],
        ], $overrides);
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

    private function merchant(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create(['email' => $email, 'role_id' => $role->id]);
    }

    private function store(User $owner, string $name): Store
    {
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
        $store->members()->syncWithoutDetaching([$owner->id => ['role' => Store::ROLE_OWNER]]);

        return $store;
    }

    private function product(Store $store, array $overrides = []): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $overrides['name'] ?? 'Draft Tax Product',
            'slug' => 'draft-tax-product-'.Str::random(6),
            'base_price' => $overrides['price'] ?? 20,
            'sku' => $overrides['sku'] ?? 'DRAFT-TAX-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'is_taxable' => $overrides['is_taxable'] ?? true,
            'meta' => ['stock_alert' => 1],
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
}
