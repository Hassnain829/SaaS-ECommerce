<?php

namespace Tests\Feature;

use App\Models\DraftOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DraftOrderBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_has_hidden_false_and_checkbox_true_contract(): void
    {
        [$owner, $store] = $this->fixture(withVariant: false);

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orders.create'))
            ->assertOk()
            ->getContent();

        $this->assertBillingCheckboxContract($html);
        $this->assertBillingFieldsAccessibleWithoutJs($html);
    }

    public function test_edit_form_has_hidden_false_and_checkbox_true_contract(): void
    {
        [$owner, $store, $variant] = $this->fixture();
        $draft = $this->createDraft($owner, $store, $variant);

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('draft-orders.show', $draft))
            ->assertOk()
            ->getContent();

        $this->assertBillingCheckboxContract($html);
        $this->assertBillingFieldsAccessibleWithoutJs($html);
    }

    public function test_unchecked_browser_style_submission_resolves_to_false(): void
    {
        [$owner, $store, $variant] = $this->fixture();

        $this->assertBillingCheckboxContract(
            $this->actingAs($owner)
                ->withSession(['current_store_id' => $store->id])
                ->get(route('orders.create'))
                ->assertOk()
                ->getContent()
        );

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('orders.create'))
            ->post(route('draft-orders.store'), $this->browserBillingPayload($variant, false))
            ->assertRedirect(route('orders.create'))
            ->assertSessionHasErrors(['billing_name', 'billing_address_line1', 'billing_city', 'billing_country']);
    }

    public function test_validation_failure_keeps_independent_billing_mode_selected(): void
    {
        [$owner, $store, $variant] = $this->fixture();

        $payload = $this->browserBillingPayload($variant, false, [
            'billing_name' => 'Partial Billing Contact',
            'billing_address_line1' => '123 Partial Street',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('orders.create'))
            ->post(route('draft-orders.store'), $payload)
            ->assertRedirect(route('orders.create'))
            ->assertSessionHasErrors(['billing_city', 'billing_country']);

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orders.create'))
            ->assertOk()
            ->getContent();

        $this->assertBillingCheckboxContract($html);
        $this->assertBillingFieldsAccessibleWithoutJs($html);
        $this->assertStringContainsString('value="Partial Billing Contact"', $html);
        $this->assertStringContainsString('value="123 Partial Street"', $html);
        $this->assertMatchesRegularExpression(
            '/<input[^>]*type="checkbox"[^>]*name="billing_same_as_shipping"[^>]*value="1"(?![^>]*\bchecked\b)[^>]*>/',
            $html
        );
        $this->assertStringContainsString('Enter a billing city when billing differs from shipping.', $html);
        $this->assertStringContainsString('Enter a billing country code when billing differs from shipping.', $html);
    }

    public function test_same_as_shipping_copies_shipping_to_billing_metadata(): void
    {
        [$owner, $store, $variant] = $this->fixture();

        $payload = $this->browserBillingPayload($variant, true, [
            'shipping_name' => 'Ship To Name',
            'shipping_address_line1' => '100 Ship Street',
            'shipping_city' => 'Austin',
            'shipping_country' => 'us',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.store'), $payload)
            ->assertRedirect();

        $draft = DraftOrder::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertTrue($draft->billingSameAsShipping());
        $this->assertSame('US', $draft->billingAddress()['country']);
        $this->assertSame('100 Ship Street', $draft->billingAddress()['address_line1']);
        $this->assertSame('Ship To Name', $draft->billingAddress()['name']);
    }

    public function test_same_as_shipping_ignores_stale_invalid_billing_values(): void
    {
        [$owner, $store, $variant] = $this->fixture();

        $payload = $this->browserBillingPayload($variant, true, [
            'shipping_name' => 'Ship To Name',
            'shipping_address_line1' => '100 Ship Street',
            'shipping_city' => 'Austin',
            'shipping_country' => 'US',
            'billing_name' => 'Stale Billing Name',
            'billing_address_line1' => '999 Stale Street',
            'billing_city' => 'Stale City',
            'billing_country' => 'United States',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.store'), $payload)
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $draft = DraftOrder::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertTrue($draft->billingSameAsShipping());
        $this->assertSame('100 Ship Street', $draft->billingAddress()['address_line1']);
        $this->assertSame('Ship To Name', $draft->billingAddress()['name']);
        $this->assertSame('US', $draft->billingAddress()['country']);
        $this->assertNotSame('999 Stale Street', $draft->billingAddress()['address_line1']);
    }

    public function test_unchecked_billing_requires_billing_fields(): void
    {
        [$owner, $store, $variant] = $this->fixture();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('orders.create'))
            ->post(route('draft-orders.store'), $this->browserBillingPayload($variant, false))
            ->assertRedirect(route('orders.create'))
            ->assertSessionHasErrors(['billing_name', 'billing_address_line1', 'billing_city', 'billing_country']);
    }

    public function test_independent_billing_values_persist_and_convert(): void
    {
        [$owner, $store, $variant] = $this->fixture();

        $payload = $this->browserBillingPayload($variant, false, [
            'billing_name' => 'Billing Contact',
            'billing_phone' => '+15550999',
            'billing_address_line1' => '500 Billing Ave',
            'billing_address_line2' => 'Suite 9',
            'billing_city' => 'Toronto',
            'billing_state' => 'ON',
            'billing_postal_code' => 'M5V2T6',
            'billing_country' => 'ca',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.store'), $payload)
            ->assertRedirect();

        $draft = DraftOrder::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertFalse($draft->billingSameAsShipping());
        $this->assertSame('CA', $draft->billingAddress()['country']);
        $this->assertSame('500 Billing Ave', $draft->billingAddress()['address_line1']);
        $this->assertSame('Toronto', $draft->billingAddress()['city']);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.convert', $draft), $payload)
            ->assertRedirect();

        $order = Order::query()->where('store_id', $store->id)->with('addresses')->firstOrFail();
        $billing = $order->addresses->firstWhere('type', 'billing');
        $this->assertNotNull($billing);
        $this->assertSame('CA', $billing->country);
        $this->assertSame('500 Billing Ave', $billing->address_line1);
        $this->assertFalse($order->billing_same_as_shipping);
    }

    public function test_invalid_billing_country_is_rejected(): void
    {
        [$owner, $store, $variant] = $this->fixture();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('orders.create'))
            ->post(route('draft-orders.store'), $this->browserBillingPayload($variant, false, [
                'billing_name' => 'Billing Contact',
                'billing_address_line1' => '500 Billing Ave',
                'billing_city' => 'Toronto',
                'billing_country' => 'United States',
            ]))
            ->assertRedirect(route('orders.create'))
            ->assertSessionHasErrors('billing_country');
    }

    public function test_legacy_draft_without_billing_metadata_renders_safely(): void
    {
        [$owner, $store, $variant] = $this->fixture();
        $draft = $this->createDraft($owner, $store, $variant);
        $metadata = is_array($draft->metadata) ? $draft->metadata : [];
        unset($metadata['billing_address'], $metadata['billing_same_as_shipping']);
        $draft->update(['metadata' => $metadata]);

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('draft-orders.show', $draft))
            ->assertOk()
            ->assertSeeText('Billing address is the same as shipping')
            ->getContent();

        $this->assertBillingCheckboxContract($html);
        $this->assertBillingFieldsAccessibleWithoutJs($html);
    }

    private function assertBillingCheckboxContract(string $html): void
    {
        $this->assertStringContainsString('type="hidden" name="billing_same_as_shipping" value="0"', $html);
        $this->assertStringContainsString('type="checkbox" name="billing_same_as_shipping" value="1"', $html);
    }

    private function assertBillingFieldsAccessibleWithoutJs(string $html): void
    {
        preg_match('/<div[^>]*data-draft-billing-fields[^>]*>/', $html, $matches);
        $this->assertNotEmpty($matches[0] ?? null, 'Expected independent billing fields container in rendered markup.');
        $this->assertStringNotContainsString(' hidden', $matches[0]);
        $this->assertDoesNotMatchRegularExpression('/\bhidden\b/', $matches[0]);
    }

    /**
     * @return array{0: User, 1: Store, 2?: ProductVariant}
     */
    private function fixture(bool $withVariant = true): array
    {
        $owner = $this->merchant('draft-billing@example.test');
        $store = $this->store($owner, 'Draft Billing Store');

        if (! $withVariant) {
            return [$owner, $store];
        }

        [, $variant] = $this->product($store);

        return [$owner, $store, $variant];
    }

    private function createDraft(User $owner, Store $store, ProductVariant $variant): DraftOrder
    {
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.store'), $this->browserBillingPayload($variant, true))
            ->assertRedirect();

        return DraftOrder::query()->where('store_id', $store->id)->latest('id')->firstOrFail();
    }

    /**
     * Build a payload that matches the real browser checkbox contract:
     * hidden billing_same_as_shipping=0 is always present; checked adds value=1.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function browserBillingPayload(ProductVariant $variant, bool $sameAsShipping, array $overrides = []): array
    {
        $payload = $this->draftPayload($variant, $overrides);
        $payload['billing_same_as_shipping'] = $sameAsShipping ? '1' : '0';

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function draftPayload(ProductVariant $variant, array $overrides = []): array
    {
        return array_replace_recursive([
            'customer_name' => 'Billing Buyer',
            'customer_email' => 'billing.buyer@example.test',
            'shipping_name' => 'Shipping Buyer',
            'shipping_address_line1' => '10 Ship Street',
            'shipping_city' => 'Austin',
            'shipping_state' => 'TX',
            'shipping_postal_code' => '73301',
            'shipping_country' => 'US',
            'billing_same_as_shipping' => '0',
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

    private function product(Store $store): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Billing Product',
            'slug' => 'billing-product-'.Str::random(6),
            'base_price' => 20,
            'sku' => 'BILL-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'is_taxable' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => $product->sku.'-D',
            'price' => 20,
            'stock' => 5,
        ]);

        return [$product, $variant];
    }
}
