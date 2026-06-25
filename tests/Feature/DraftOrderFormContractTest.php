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

class DraftOrderFormContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_rendered_draft_form_has_no_global_method_override(): void
    {
        [$owner, $store, $draft] = $this->editableDraftFixture();

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('draft-orders.show', $draft))
            ->assertOk()
            ->getContent();

        preg_match('/<form id="draftOrderForm"[\s\S]*?<\/form>/', $html, $matches);
        $this->assertNotEmpty($matches[0] ?? null);
        $formHtml = $matches[0];

        $this->assertStringNotContainsString('<input type="hidden" name="_method" value="PATCH">', $formHtml);
        $this->assertStringContainsString('name="_method"', $formHtml);
        $this->assertStringContainsString('value="PATCH"', $formHtml);
        $this->assertStringContainsString('formaction="'.route('draft-orders.convert', $draft).'"', $formHtml);
        $this->assertStringContainsString('formmethod="POST"', $formHtml);
        $this->assertStringContainsString('data-convert-draft-button', $formHtml);
        $this->assertStringContainsString('data-primary-save-button', $formHtml);
    }

    public function test_save_button_submits_patch_to_update_route(): void
    {
        [$owner, $store, $draft, $variant] = $this->editableDraftFixture(returnVariant: true);

        $payload = $this->draftPayload($variant, [
            'notes' => 'Saved through PATCH contract.',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('draft-orders.update', $draft), $payload)
            ->assertRedirect(route('draft-orders.show', $draft));

        $this->assertSame('Saved through PATCH contract.', $draft->fresh()->notes);
    }

    public function test_convert_submits_post_without_method_override_and_succeeds(): void
    {
        [$owner, $store, $draft, $variant] = $this->editableDraftFixture(returnVariant: true);

        $payload = $this->draftPayload($variant, [
            'tax_total' => '2.00',
            'notes' => 'Converted through POST contract.',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.convert', $draft), $payload)
            ->assertRedirect();

        $this->assertSame(DraftOrder::STATUS_CONVERTED, $draft->fresh()->status);
        $this->assertSame(1, Order::query()->where('store_id', $store->id)->count());
    }

    public function test_convert_route_rejects_patch_method_override(): void
    {
        [$owner, $store, $draft, $variant] = $this->editableDraftFixture(returnVariant: true);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('draft-orders.convert', $draft), $this->draftPayload($variant))
            ->assertStatus(405);

        $this->assertSame(DraftOrder::STATUS_DRAFT, $draft->fresh()->status);
    }

    public function test_calculated_draft_conversion_recopies_tax_details(): void
    {
        [$owner, $store, $draft, $variant] = $this->editableDraftFixture(returnVariant: true);
        $this->enableTax($store, ['shipping_taxable' => true]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.calculate-tax', $draft), $this->draftPayload($variant))
            ->assertRedirect();

        $draft = $draft->fresh(['taxLines']);
        $this->assertSame(DraftOrder::TAX_SOURCE_CALCULATED, $draft->taxSource());
        $this->assertGreaterThan(0, $draft->taxLines->count());

        $payload = $this->draftPayload($variant, [
            'tax_mode' => DraftOrder::TAX_SOURCE_CALCULATED,
            'items' => [[
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'unit_price' => (string) $variant->price,
            ]],
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.convert', $draft), $payload)
            ->assertRedirect();

        $order = Order::query()->where('store_id', $store->id)->with('taxLines')->firstOrFail();
        $this->assertGreaterThan(0, $order->taxLines->count());
        $this->assertNotNull(data_get($order->meta, 'tax_snapshot'));
    }

    public function test_foreign_store_cannot_convert_draft(): void
    {
        [$owner, $store, $draft, $variant] = $this->editableDraftFixture(returnVariant: true);
        $otherOwner = $this->merchant('foreign-convert@example.test');
        $otherStore = $this->store($otherOwner, 'Foreign Convert Store');

        $this->actingAs($otherOwner)
            ->withSession(['current_store_id' => $otherStore->id])
            ->post(route('draft-orders.convert', $draft), $this->draftPayload($variant))
            ->assertNotFound();

        $this->assertSame(DraftOrder::STATUS_DRAFT, $draft->fresh()->status);
        $this->assertSame(0, OrderTaxLine::query()->count());
    }

    /**
     * @return array{0: User, 1: Store, 2: DraftOrder, 3?: ProductVariant}
     */
    private function editableDraftFixture(bool $returnVariant = false): array
    {
        $owner = $this->merchant('draft-form-contract@example.test');
        $store = $this->store($owner, 'Draft Form Contract Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);

        $draft = $this->createDraft($owner, $store, $variant);

        return $returnVariant
            ? [$owner, $store, $draft, $variant]
            : [$owner, $store, $draft];
    }

    private function createDraft(User $owner, Store $store, ProductVariant $variant): DraftOrder
    {
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.store'), $this->draftPayload($variant))
            ->assertRedirect();

        return DraftOrder::query()->where('store_id', $store->id)->latest('id')->firstOrFail();
    }

    private function draftPayload(ProductVariant $variant, array $overrides = []): array
    {
        return array_replace_recursive([
            'customer_name' => 'Form Contract Buyer',
            'customer_email' => 'form.contract@example.test',
            'shipping_name' => 'Form Contract Buyer',
            'shipping_address_line1' => '10 Contract Road',
            'shipping_city' => 'Austin',
            'shipping_state' => 'TX',
            'shipping_postal_code' => '73301',
            'shipping_country' => 'US',
            'billing_same_as_shipping' => '1',
            'shipping_total' => '5.00',
            'tax_total' => '0.00',
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
            'name' => 'Form Contract Product',
            'slug' => 'form-contract-product-'.Str::random(6),
            'base_price' => $overrides['price'] ?? 20,
            'sku' => 'FORM-CONTRACT-'.Str::random(4),
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
