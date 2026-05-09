<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DraftOrder;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\SecurityLog;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Support\OrderLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Phase4DraftOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_draft_without_deducting_stock_then_convert_to_order(): void
    {
        $owner = $this->merchant('owner@example.com');
        $store = $this->store($owner, 'Manual Order Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        [, $variant] = $this->product($store, ['stock' => 5]);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.store'), $this->draftPayload($variant, 2));

        $draft = DraftOrder::query()->where('store_id', $store->id)->firstOrFail();
        $response->assertRedirect(route('draft-orders.show', $draft));

        $this->assertSame(5, (int) $variant->fresh()->stock);
        $this->assertDatabaseHas('security_logs', [
            'store_id' => $store->id,
            'event_type' => 'manual_draft_created',
            'severity' => SecurityLog::SEVERITY_INFO,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.convert', $draft))
            ->assertRedirect();

        $order = Order::query()->where('store_id', $store->id)->where('order_source', 'manual')->firstOrFail();
        $this->assertSame(DraftOrder::STATUS_CONVERTED, $draft->fresh()->status);
        $this->assertSame($order->id, $draft->fresh()->converted_order_id);
        $this->assertSame(3, (int) $variant->fresh()->stock);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PENDING,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'grand_total' => 31.00,
        ]);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_ORDER_CREATED,
        ]);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_INVENTORY_DEDUCTED,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'store_id' => $store->id,
            'variant_id' => $variant->id,
            'movement_type' => StockMovement::TYPE_ORDER_DEDUCTED,
            'source' => 'manual_order',
        ]);
        $this->assertDatabaseHas('inventory_reservations', [
            'store_id' => $store->id,
            'order_id' => $order->id,
            'status' => InventoryReservation::STATUS_DEDUCTED,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('security_logs', [
            'store_id' => $store->id,
            'event_type' => 'manual_draft_converted',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('draft-orders.show', $draft))
            ->post(route('draft-orders.convert', $draft))
            ->assertRedirect(route('draft-orders.show', $draft))
            ->assertSessionHasErrors('draft_order');
    }

    public function test_manager_can_create_draft_but_staff_cannot(): void
    {
        $owner = $this->merchant('owner@example.com');
        $manager = $this->merchant('manager@example.com');
        $staff = $this->merchant('staff@example.com');
        $store = $this->store($owner, 'Draft Permission Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($store, $manager, Store::ROLE_MANAGER);
        $this->attach($store, $staff, Store::ROLE_STAFF);
        [, $variant] = $this->product($store, ['stock' => 5]);

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.store'), $this->draftPayload($variant, 1))
            ->assertRedirect();

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.store'), $this->draftPayload($variant, 1))
            ->assertForbidden();
    }

    public function test_insufficient_stock_blocks_draft_conversion_cleanly(): void
    {
        $owner = $this->merchant('owner@example.com');
        $store = $this->store($owner, 'Low Manual Stock Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        [, $variant] = $this->product($store, ['stock' => 1]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.store'), $this->draftPayload($variant, 2))
            ->assertRedirect();

        $draft = DraftOrder::query()->where('store_id', $store->id)->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('draft-orders.show', $draft))
            ->post(route('draft-orders.convert', $draft))
            ->assertRedirect(route('draft-orders.show', $draft))
            ->assertSessionHasErrors('items');

        $this->assertSame(DraftOrder::STATUS_DRAFT, $draft->fresh()->status);
        $this->assertSame(1, (int) $variant->fresh()->stock);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_create_order_saves_current_shipping_form_fields_before_conversion(): void
    {
        $owner = $this->merchant('owner@example.com');
        $store = $this->store($owner, 'Unsaved Address Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        [, $variant] = $this->product($store, ['stock' => 5]);

        $initialPayload = $this->draftPayload($variant, 1);
        $initialPayload['shipping_address_line1'] = '';
        $initialPayload['shipping_city'] = '';
        $initialPayload['shipping_country'] = '';

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.store'), $initialPayload)
            ->assertRedirect();

        $draft = DraftOrder::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame('', $draft->shippingAddress()['address_line1'] ?? null);

        $currentFormPayload = $this->draftPayload($variant, 2);
        $currentFormPayload['shipping_address_line1'] = '99 Saved During Convert Road';
        $currentFormPayload['shipping_city'] = 'Lahore';
        $currentFormPayload['shipping_country'] = 'Pakistan';

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('draft-orders.show', $draft))
            ->post(route('draft-orders.convert', $draft), $currentFormPayload)
            ->assertRedirect();

        $draft = $draft->fresh();
        $order = Order::query()->where('store_id', $store->id)->where('order_source', 'manual')->firstOrFail();

        $this->assertSame(DraftOrder::STATUS_CONVERTED, $draft->status);
        $this->assertSame('99 Saved During Convert Road', $draft->shippingAddress()['address_line1']);
        $this->assertDatabaseHas('order_addresses', [
            'order_id' => $order->id,
            'type' => 'shipping',
            'address_line1' => '99 Saved During Convert Road',
            'city' => 'Lahore',
            'country' => 'Pakistan',
        ]);
    }

    #[DataProvider('missingShippingFieldProvider')]
    public function test_create_order_validates_current_shipping_payload(string $field, string $errorKey): void
    {
        $owner = $this->merchant('owner@example.com');
        $store = $this->store($owner, 'Missing Shipping Store '.$field);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        [, $variant] = $this->product($store, ['stock' => 5]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.store'), $this->draftPayload($variant, 1))
            ->assertRedirect();

        $draft = DraftOrder::query()->where('store_id', $store->id)->firstOrFail();
        $payload = $this->draftPayload($variant, 1);
        $payload[$field] = '';

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('draft-orders.show', $draft))
            ->post(route('draft-orders.convert', $draft), $payload)
            ->assertRedirect(route('draft-orders.show', $draft))
            ->assertSessionHasErrors($errorKey);

        $this->assertSame(DraftOrder::STATUS_DRAFT, $draft->fresh()->status);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_blank_unit_price_uses_variant_price_and_duplicate_rows_merge(): void
    {
        $owner = $this->merchant('owner@example.com');
        $store = $this->store($owner, 'Blank Price Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        [, $variant] = $this->product($store, ['stock' => 10]);

        $payload = $this->draftPayload($variant, 1);
        $payload['items'] = [
            [
                'product_variant_id' => $variant->id,
                'quantity' => '',
                'unit_price' => '',
            ],
            [
                'product_variant_id' => $variant->id,
                'quantity' => 2,
                'unit_price' => null,
            ],
            [
                'product_variant_id' => null,
                'quantity' => '',
                'unit_price' => '',
            ],
        ];

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.store'), $payload)
            ->assertRedirect();

        $draft = DraftOrder::query()->where('store_id', $store->id)->with('items')->firstOrFail();
        $this->assertCount(1, $draft->items);
        $this->assertSame(3, $draft->items->first()->quantity);
        $this->assertSame('12.00', $draft->items->first()->unit_price);
        $this->assertSame('36.00', $draft->subtotal);
        $this->assertSame('43.00', $draft->total);
    }

    public function test_draft_product_select_renders_variant_data_for_price_autofill(): void
    {
        $owner = $this->merchant('owner@example.com');
        $store = $this->store($owner, 'Variant Data Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        [, $variant] = $this->product($store, ['stock' => 7]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orders.create'))
            ->assertOk()
            ->assertSee('data-price="12.00"', false)
            ->assertSee('data-stock="7"', false)
            ->assertSee('data-sku="'.$variant->sku.'"', false)
            ->assertSee('data-label="Manual Product - Default variant"', false);
    }

    public function test_orders_page_shows_and_searches_draft_orders_separately(): void
    {
        $owner = $this->merchant('owner@example.com');
        $otherOwner = $this->merchant('other@example.com');
        $store = $this->store($owner, 'Draft Orders Visible Store');
        $otherStore = $this->store($otherOwner, 'Draft Orders Hidden Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($otherStore, $otherOwner, Store::ROLE_OWNER);
        [, $variant] = $this->product($store, ['stock' => 5]);
        [, $otherVariant] = $this->product($otherStore, ['stock' => 5]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.store'), $this->draftPayload($variant, 1))
            ->assertRedirect();

        $otherDraft = DraftOrder::query()->create([
            'store_id' => $otherStore->id,
            'customer_id' => $this->customer($otherStore)->id,
            'draft_number' => 'DRAFT-HIDDEN',
            'status' => DraftOrder::STATUS_DRAFT,
            'currency' => 'USD',
            'metadata' => [],
        ]);
        $otherDraft->items()->create([
            'store_id' => $otherStore->id,
            'product_id' => $otherVariant->product_id,
            'product_variant_id' => $otherVariant->id,
            'product_name' => 'Other Product',
            'quantity' => 1,
            'unit_price' => 10,
            'line_total' => 10,
        ]);

        $draft = DraftOrder::query()->where('store_id', $store->id)->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orders'))
            ->assertOk()
            ->assertSeeText('Draft orders')
            ->assertSeeText('Final orders')
            ->assertSeeText($draft->draft_number)
            ->assertDontSeeText('DRAFT-HIDDEN');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orders', ['q' => $draft->draft_number]))
            ->assertOk()
            ->assertSeeText($draft->draft_number)
            ->assertDontSeeText('DRAFT-HIDDEN');
    }

    public function test_draft_delete_archive_rules_are_store_scoped_permissioned_and_audited(): void
    {
        $owner = $this->merchant('owner@example.com');
        $staff = $this->merchant('staff@example.com');
        $otherOwner = $this->merchant('other@example.com');
        $store = $this->store($owner, 'Delete Draft Store');
        $otherStore = $this->store($otherOwner, 'Other Delete Draft Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($store, $staff, Store::ROLE_STAFF);
        $this->attach($otherStore, $otherOwner, Store::ROLE_OWNER);
        [, $variant] = $this->product($store, ['stock' => 10]);
        [, $otherVariant] = $this->product($otherStore, ['stock' => 10]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.store'), $this->draftPayload($variant, 1))
            ->assertRedirect();

        $draft = DraftOrder::query()->where('store_id', $store->id)->firstOrFail();

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('draft-orders.destroy', $draft))
            ->assertForbidden();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('draft-orders.destroy', $draft))
            ->assertRedirect(route('orders'));

        $this->assertSoftDeleted('draft_orders', ['id' => $draft->id]);
        $this->assertDatabaseHas('security_logs', [
            'store_id' => $store->id,
            'event_type' => 'manual_draft_deleted',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orders'))
            ->assertOk()
            ->assertDontSeeText($draft->draft_number);

        $cancelledDraft = DraftOrder::query()->create([
            'store_id' => $store->id,
            'customer_id' => $this->customer($store)->id,
            'draft_number' => 'DRAFT-CANCELLED',
            'status' => DraftOrder::STATUS_CANCELLED,
            'currency' => 'USD',
            'metadata' => [],
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('draft-orders.destroy', $cancelledDraft))
            ->assertRedirect(route('orders'));

        $this->assertSoftDeleted('draft_orders', ['id' => $cancelledDraft->id]);

        $convertedPayload = $this->draftPayload($variant, 1);
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.store'), $convertedPayload)
            ->assertRedirect();
        $convertedDraft = DraftOrder::query()->where('store_id', $store->id)->whereNull('deleted_at')->latest('id')->firstOrFail();
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.convert', $convertedDraft))
            ->assertRedirect();
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('draft-orders.show', $convertedDraft))
            ->delete(route('draft-orders.destroy', $convertedDraft))
            ->assertRedirect(route('draft-orders.show', $convertedDraft))
            ->assertSessionHasErrors('draft_order');

        $this->assertDatabaseHas('draft_orders', [
            'id' => $convertedDraft->id,
            'deleted_at' => null,
        ]);

        $otherDraft = DraftOrder::query()->create([
            'store_id' => $otherStore->id,
            'customer_id' => $this->customer($otherStore)->id,
            'draft_number' => 'DRAFT-OTHER',
            'status' => DraftOrder::STATUS_DRAFT,
            'currency' => 'USD',
            'metadata' => [],
        ]);
        $otherDraft->items()->create([
            'store_id' => $otherStore->id,
            'product_id' => $otherVariant->product_id,
            'product_variant_id' => $otherVariant->id,
            'product_name' => 'Other Product',
            'quantity' => 1,
            'unit_price' => 10,
            'line_total' => 10,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('draft-orders.destroy', $otherDraft))
            ->assertNotFound();
    }

    public function test_blocked_customer_cannot_be_converted_to_manual_order(): void
    {
        $owner = $this->merchant('owner@example.com');
        $store = $this->store($owner, 'Blocked Customer Draft Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        [, $variant] = $this->product($store, ['stock' => 5]);
        $customer = $this->customer($store);
        $customer->update(['status' => 'blocked', 'blocked_at' => now()]);

        $payload = $this->draftPayload($variant, 1);
        $payload['customer_id'] = $customer->id;
        unset($payload['customer_name'], $payload['customer_email']);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.store'), $payload)
            ->assertRedirect();

        $draft = DraftOrder::query()->where('store_id', $store->id)->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('draft-orders.show', $draft))
            ->post(route('draft-orders.convert', $draft))
            ->assertRedirect(route('draft-orders.show', $draft))
            ->assertSessionHasErrors('customer_id');

        $this->assertSame(DraftOrder::STATUS_DRAFT, $draft->fresh()->status);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_store_cannot_access_another_store_draft(): void
    {
        $owner = $this->merchant('owner@example.com');
        $otherOwner = $this->merchant('other@example.com');
        $store = $this->store($owner, 'Draft Store A');
        $otherStore = $this->store($otherOwner, 'Draft Store B');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($otherStore, $otherOwner, Store::ROLE_OWNER);
        [, $variant] = $this->product($otherStore, ['stock' => 5]);

        $draft = DraftOrder::query()->create([
            'store_id' => $otherStore->id,
            'customer_id' => $this->customer($otherStore)->id,
            'draft_number' => 'DRAFT-X',
            'status' => DraftOrder::STATUS_DRAFT,
            'currency' => 'USD',
            'metadata' => [],
        ]);
        $draft->items()->create([
            'store_id' => $otherStore->id,
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Other Product',
            'quantity' => 1,
            'unit_price' => 10,
            'line_total' => 10,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('draft-orders.show', $draft))
            ->assertNotFound();
    }

    public static function missingShippingFieldProvider(): array
    {
        return [
            'address line' => ['shipping_address_line1', 'shipping_address_line1'],
            'city' => ['shipping_city', 'shipping_city'],
            'country' => ['shipping_country', 'shipping_country'],
        ];
    }

    private function draftPayload(ProductVariant $variant, int $quantity): array
    {
        return [
            'customer_name' => 'Manual Buyer',
            'customer_email' => 'manual@example.test',
            'customer_phone' => '+923001234567',
            'shipping_name' => 'Manual Buyer',
            'shipping_phone' => '+923001234567',
            'shipping_address_line1' => '10 Manual Road',
            'shipping_city' => 'Karachi',
            'shipping_state' => 'Sindh',
            'shipping_postal_code' => '74000',
            'shipping_country' => 'Pakistan',
            'billing_same_as_shipping' => '1',
            'shipping_total' => '5.00',
            'tax_total' => '2.00',
            'discount_total' => '0.00',
            'notes' => 'Phone order.',
            'items' => [
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => $quantity,
                    'unit_price' => '12.00',
                ],
            ],
        ];
    }

    private function merchant(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create(['email' => $email, 'role_id' => $role->id]);
    }

    private function store(User $owner, string $name): Store
    {
        return Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
    }

    private function attach(Store $store, User $user, string $role): void
    {
        $store->members()->syncWithoutDetaching([$user->id => ['role' => $role]]);
    }

    private function customer(Store $store): Customer
    {
        return Customer::query()->create([
            'store_id' => $store->id,
            'email' => fake()->unique()->safeEmail(),
            'full_name' => 'Draft Customer',
            'status' => 'active',
        ]);
    }

    private function product(Store $store, array $overrides = []): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Manual Product',
            'slug' => 'manual-product-'.Str::random(6),
            'base_price' => 12,
            'sku' => 'MANUAL-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => $product->sku.'-D',
            'price' => 12,
            'stock' => $overrides['stock'] ?? 5,
        ]);

        return [$product, $variant];
    }
}
