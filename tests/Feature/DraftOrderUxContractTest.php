<?php

namespace Tests\Feature;

use App\Models\DraftOrder;
use App\Models\DraftTaxLine;
use App\Models\Order;
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

class DraftOrderUxContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_submit_button_in_draft_form_is_save_not_convert(): void
    {
        [$owner, $store, $draft] = $this->editableDraftFixture();

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('draft-orders.show', $draft))
            ->assertOk()
            ->getContent();

        preg_match('/<form id="draftOrderForm"[\s\S]*?<\/form>/', $html, $matches);
        $formHtml = $matches[0] ?? '';
        $this->assertNotSame('', $formHtml);

        preg_match_all('/<button[^>]*type="submit"[^>]*>/', $formHtml, $submitButtons);
        $this->assertNotEmpty($submitButtons[0]);
        $this->assertStringContainsString('data-primary-save-button', $submitButtons[0][0]);
        $this->assertStringNotContainsString('data-convert-draft-button', $submitButtons[0][0]);
        $this->assertStringContainsString('value="PATCH"', $submitButtons[0][0]);
    }

    public function test_automatic_mode_ignores_stale_manual_tax_input(): void
    {
        $owner = $this->merchant('ux-auto-ignore-manual@example.test');
        $store = $this->store($owner, 'UX Auto Ignore Manual Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $this->enableTax($store);

        $draft = $this->createDraft($owner, $store, $variant);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('draft-orders.update', $draft), $this->draftPayload($variant, [
                'tax_mode' => DraftOrder::TAX_SOURCE_CALCULATED,
                'manual_tax_total' => '99.99',
            ]))
            ->assertRedirect(route('draft-orders.show', $draft));

        $draft = $draft->fresh();
        $this->assertSame(DraftOrder::TAX_SOURCE_CALCULATED, $draft->taxSource());
        $this->assertNotSame(99.99, (float) $draft->tax_total);
    }

    public function test_calculated_to_manual_without_confirmation_fails_and_leaves_draft_unchanged(): void
    {
        $owner = $this->merchant('ux-manual-confirm@example.test');
        $store = $this->store($owner, 'UX Manual Confirm Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $this->enableTax($store, ['shipping_taxable' => true]);

        $draft = $this->createDraft($owner, $store, $variant);
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.calculate-tax', $draft), $this->draftPayload($variant))
            ->assertRedirect();

        $before = $draft->fresh(['taxLines'])->getAttributes();
        $lineCountBefore = $draft->fresh()->taxLines()->count();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('draft-orders.show', $draft))
            ->patch(route('draft-orders.update', $draft), $this->draftPayload($variant, [
                'tax_mode' => DraftOrder::TAX_SOURCE_MANUAL,
                'manual_tax_total' => '9.99',
            ]))
            ->assertRedirect(route('draft-orders.show', $draft))
            ->assertSessionHasErrors('confirm_manual_tax_switch');

        $draft = $draft->fresh(['taxLines']);
        $this->assertSame(number_format((float) $before['tax_total'], 2, '.', ''), number_format((float) $draft->tax_total, 2, '.', ''));
        $this->assertSame($lineCountBefore, $draft->taxLines->count());
        $this->assertSame(DraftOrder::TAX_SOURCE_CALCULATED, $draft->taxSource());
    }

    public function test_rendered_markup_excludes_obsolete_manual_switch_fields(): void
    {
        [$owner, $store, $draft] = $this->editableDraftFixture();
        $this->enableTax($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.calculate-tax', $draft), $this->draftPayload($this->variantForDraft($draft)))
            ->assertRedirect();

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('draft-orders.show', $draft->fresh()))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('name="switch_to_manual"', $html);
        $this->assertStringNotContainsString('confirm_switch_to_manual', $html);
        $this->assertStringContainsString('name="confirm_manual_tax_switch"', $html);
        $this->assertStringContainsString('name="manual_tax_total"', $html);
        $this->assertStringNotContainsString('shipping country, region, items, discount', $html);
        $this->assertStringContainsString('Tax is calculated from taxable items, the shipping destination, and taxable shipping.', $html);
    }

    public function test_inclusive_saved_draft_renders_persisted_total_without_double_count(): void
    {
        $owner = $this->merchant('ux-inclusive-total@example.test');
        $store = $this->store($owner, 'UX Inclusive Total Store');
        [, $variant] = $this->product($store, ['price' => 22, 'stock' => 5]);
        $this->enableTax($store, [
            'prices_include_tax' => true,
            'shipping_taxable' => true,
        ]);

        $draft = $this->createDraft($owner, $store, $variant, $this->draftPayload($variant, [
            'tax_mode' => DraftOrder::TAX_SOURCE_CALCULATED,
            'shipping_total' => '10.00',
            'items' => [[
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'unit_price' => '22.00',
            ]],
        ]));

        $draft = $draft->fresh();
        $this->assertSame(33.00, (float) $draft->total);

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('draft-orders.show', $draft))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-persisted-total="33.00"', $html);
        $this->assertStringContainsString('USD 33.00', $html);
        $this->assertStringContainsString('Tax is included in item prices', $html);
    }

    public function test_create_form_has_mobile_bottom_padding_and_estimate_contract(): void
    {
        $owner = $this->merchant('ux-create-padding@example.test');
        $store = $this->store($owner, 'UX Create Padding Store');

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orders.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('pb-24 xl:pb-4', $html);
        $this->assertStringContainsString('data-is-estimate="1"', $html);
        $this->assertStringContainsString('Billing recipient name', $html);
    }

    public function test_historical_order_currency_survives_store_currency_change(): void
    {
        $owner = $this->merchant('ux-order-currency@example.test');
        $store = $this->store($owner, 'UX Order Currency Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);

        $draft = $this->createDraft($owner, $store, $variant);
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.convert', $draft), $this->draftPayload($variant))
            ->assertRedirect();

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame('USD', strtoupper((string) $order->currency_code));

        $store->update(['currency' => 'EUR']);

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('USD ', $html);
        $this->assertStringNotContainsString('USD10.00', $html);
        $this->assertStringNotContainsString('EUR ', $html);
    }

    public function test_calculated_draft_with_tax_driving_old_input_renders_dirty_pending_state(): void
    {
        $owner = $this->merchant('ux-dirty-hydration@example.test');
        $store = $this->store($owner, 'UX Dirty Hydration Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $this->enableTax($store);

        $draft = $this->createDraft($owner, $store, $variant);
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.calculate-tax', $draft), $this->draftPayload($variant, [
                'tax_mode' => DraftOrder::TAX_SOURCE_CALCULATED,
            ]))
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('draft-orders.show', $draft))
            ->patch(route('draft-orders.update', $draft), $this->draftPayload($variant, [
                'tax_mode' => DraftOrder::TAX_SOURCE_CALCULATED,
                'items' => [[
                    'product_variant_id' => $variant->id,
                    'quantity' => 3,
                    'unit_price' => '20.00',
                ]],
                'billing_same_as_shipping' => '0',
            ]))
            ->assertRedirect(route('draft-orders.show', $draft))
            ->assertSessionHasErrors();

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('draft-orders.show', $draft))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-tax-driving-dirty="1"', $html);
        $this->assertStringContainsString('Recalculates on save', $html);
        $this->assertStringContainsString('Pending tax recalculation', $html);
        $this->assertStringContainsString('USD 60.00', $html);
    }

    public function test_persisted_saved_rows_render_real_line_totals_without_javascript(): void
    {
        $owner = $this->merchant('ux-line-total@example.test');
        $store = $this->store($owner, 'UX Line Total Store');
        [, $variant] = $this->product($store, ['price' => 25.5, 'stock' => 5]);

        $draft = $this->createDraft($owner, $store, $variant, $this->draftPayload($variant, [
            'items' => [[
                'product_variant_id' => $variant->id,
                'quantity' => 2,
                'unit_price' => '25.50',
            ]],
        ]));

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('draft-orders.show', $draft))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-line-total', $html);
        $this->assertStringContainsString('USD 51.00', $html);
        $this->assertStringNotContainsString('data-line-total">USD 0.00', $html);
    }

    public function test_automatic_create_renders_calculation_pending_copy_server_side(): void
    {
        $owner = $this->merchant('ux-create-pending@example.test');
        $store = $this->store($owner, 'UX Create Pending Store');
        $this->enableTax($store);

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orders.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Calculated when saved', $html);
        $this->assertStringContainsString('Confirmed after tax calculation', $html);
        $this->assertStringNotContainsString('Estimated before recalculated tax', $html);
    }

    public function test_automatic_mode_ignores_invalid_stale_manual_tax_total(): void
    {
        $owner = $this->merchant('ux-invalid-manual@example.test');
        $store = $this->store($owner, 'UX Invalid Manual Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $this->enableTax($store);

        $draft = $this->createDraft($owner, $store, $variant);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('draft-orders.update', $draft), $this->draftPayload($variant, [
                'tax_mode' => DraftOrder::TAX_SOURCE_CALCULATED,
                'manual_tax_total' => 'not-a-number',
            ]))
            ->assertRedirect(route('draft-orders.show', $draft));

        $draft = $draft->fresh();
        $this->assertSame(DraftOrder::TAX_SOURCE_CALCULATED, $draft->taxSource());
    }

    public function test_manual_mode_validates_and_persists_manual_tax_total(): void
    {
        $owner = $this->merchant('ux-manual-persist@example.test');
        $store = $this->store($owner, 'UX Manual Persist Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);

        $draft = $this->createDraft($owner, $store, $variant);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('draft-orders.update', $draft), $this->draftPayload($variant, [
                'tax_mode' => DraftOrder::TAX_SOURCE_MANUAL,
                'manual_tax_total' => '7.50',
            ]))
            ->assertRedirect(route('draft-orders.show', $draft));

        $this->assertSame(7.50, (float) $draft->fresh()->tax_total);
    }

    public function test_manual_mode_tax_driving_changes_hydrate_pending_when_switching_back_to_automatic(): void
    {
        $owner = $this->merchant('ux-manual-to-auto@example.test');
        $store = $this->store($owner, 'UX Manual To Auto Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $this->enableTax($store);

        $draft = $this->createDraft($owner, $store, $variant);
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.calculate-tax', $draft), $this->draftPayload($variant, [
                'tax_mode' => DraftOrder::TAX_SOURCE_CALCULATED,
            ]))
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('draft-orders.update', $draft), $this->draftPayload($variant, [
                'tax_mode' => DraftOrder::TAX_SOURCE_MANUAL,
                'manual_tax_total' => '4.00',
                'confirm_manual_tax_switch' => '1',
                'items' => [[
                    'product_variant_id' => $variant->id,
                    'quantity' => 2,
                    'unit_price' => '20.00',
                ]],
            ]))
            ->assertRedirect(route('draft-orders.show', $draft));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('draft-orders.show', $draft))
            ->patch(route('draft-orders.update', $draft), $this->draftPayload($variant, [
                'tax_mode' => DraftOrder::TAX_SOURCE_CALCULATED,
                'items' => [[
                    'product_variant_id' => $variant->id,
                    'quantity' => 3,
                    'unit_price' => '20.00',
                ]],
                'billing_same_as_shipping' => '0',
            ]))
            ->assertRedirect(route('draft-orders.show', $draft))
            ->assertSessionHasErrors();

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('draft-orders.show', $draft))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-tax-driving-dirty="1"', $html);
        $this->assertStringContainsString('Recalculates on save', $html);
        $this->assertStringContainsString('Pending tax recalculation', $html);
        $this->assertStringContainsString('manualTaxLockedByForm', $html);
        $this->assertStringContainsString('automaticTaxPending', $html);
    }

    public function test_manual_draft_switching_to_automatic_renders_pending_not_stale_manual_total(): void
    {
        $owner = $this->merchant('ux-manual-auto-pending@example.test');
        $store = $this->store($owner, 'UX Manual Auto Pending Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $this->enableTax($store);

        $draft = $this->createDraft($owner, $store, $variant, $this->draftPayload($variant, [
            'tax_mode' => DraftOrder::TAX_SOURCE_MANUAL,
            'manual_tax_total' => '12.34',
        ]));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('draft-orders.show', $draft))
            ->patch(route('draft-orders.update', $draft), $this->draftPayload($variant, [
                'tax_mode' => DraftOrder::TAX_SOURCE_CALCULATED,
                'billing_same_as_shipping' => '0',
            ]))
            ->assertRedirect(route('draft-orders.show', $draft))
            ->assertSessionHasErrors();

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('draft-orders.show', $draft))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Pending tax recalculation', $html);
        $this->assertStringContainsString('Recalculates on save', $html);
        $this->assertStringNotContainsString('data-total-display">USD 37.34', $html);
    }

    public function test_manual_mode_validation_redirect_preserves_tax_driving_dirty_flag(): void
    {
        $owner = $this->merchant('ux-manual-dirty-redirect@example.test');
        $store = $this->store($owner, 'UX Manual Dirty Redirect Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);

        $draft = $this->createDraft($owner, $store, $variant);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('draft-orders.show', $draft))
            ->patch(route('draft-orders.update', $draft), $this->draftPayload($variant, [
                'tax_mode' => DraftOrder::TAX_SOURCE_MANUAL,
                'manual_tax_total' => '5.00',
                'items' => [[
                    'product_variant_id' => $variant->id,
                    'quantity' => 4,
                    'unit_price' => '20.00',
                ]],
                'billing_same_as_shipping' => '0',
            ]))
            ->assertRedirect(route('draft-orders.show', $draft))
            ->assertSessionHasErrors();

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('draft-orders.show', $draft))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-tax-driving-dirty="1"', $html);
        $this->assertStringContainsString('value="manual"', $html);
        $this->assertStringContainsString('name="tax_mode"', $html);
    }

    /**
     * @return array{0: User, 1: Store, 2: DraftOrder}
     */
    private function editableDraftFixture(): array
    {
        $owner = $this->merchant('ux-contract@example.test');
        $store = $this->store($owner, 'UX Contract Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $draft = $this->createDraft($owner, $store, $variant);

        return [$owner, $store, $draft];
    }

    private function variantForDraft(DraftOrder $draft): ProductVariant
    {
        return $draft->items()->firstOrFail()->variant;
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
            'customer_name' => 'UX Contract Buyer',
            'customer_email' => 'ux.contract@example.test',
            'shipping_name' => 'UX Contract Buyer',
            'shipping_address_line1' => '10 UX Road',
            'shipping_city' => 'Austin',
            'shipping_state' => 'TX',
            'shipping_postal_code' => '73301',
            'shipping_country' => 'US',
            'billing_same_as_shipping' => '1',
            'shipping_total' => '5.00',
            'manual_tax_total' => '0.00',
            'discount_total' => '0.00',
            'items' => [[
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'unit_price' => (string) $variant->price,
            ]],
        ], $overrides);
    }

    private function enableTax(Store $store, array $settingsOverrides = []): TaxSetting
    {
        $settings = $store->taxSetting;
        $settings->update(array_merge([
            'enabled' => true,
            'prices_include_tax' => false,
            'default_product_taxable' => true,
            'shipping_taxable' => false,
            'calculation_address' => TaxSetting::CALCULATION_ADDRESS_SHIPPING,
        ], $settingsOverrides));

        TaxRate::query()->create([
            'store_id' => $store->id,
            'country_code' => 'US',
            'region_code' => 'TX',
            'name' => 'TX Sales Tax',
            'rate_percent' => '10.0000',
            'priority' => 100,
            'is_active' => true,
        ]);

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
            'name' => $overrides['name'] ?? 'UX Contract Product',
            'slug' => 'ux-contract-product-'.Str::random(6),
            'base_price' => $overrides['price'] ?? 20,
            'sku' => 'UX-CONTRACT-'.Str::random(4),
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
}
