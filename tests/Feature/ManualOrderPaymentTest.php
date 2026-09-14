<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DraftOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\SecurityLog;
use App\Models\Store;
use App\Models\User;
use App\Services\ManualOrderPaymentService;
use App\Support\OrderLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManualOrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_convert_without_payment_keeps_payment_pending(): void
    {
        [$owner, $store, $variant] = $this->manualCatalog();
        $draft = $this->createDraft($owner, $store, $variant);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.convert', $draft), $this->draftPayload($variant))
            ->assertRedirect();

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame(OrderLifecycle::PAYMENT_PENDING, $order->payment_status);
        $this->assertSame('31.00', number_format((float) $order->outstanding_total, 2, '.', ''));
        $this->assertNull($order->payment_method);
    }

    public function test_convert_can_record_already_received_payment(): void
    {
        [$owner, $store, $variant] = $this->manualCatalog();
        $draft = $this->createDraft($owner, $store, $variant);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.convert', $draft), array_merge($this->draftPayload($variant), [
                'payment_received' => '1',
                'payment_method' => ManualOrderPaymentService::METHOD_CASH,
                'payment_reference' => 'RCPT-44',
            ]))
            ->assertRedirect();

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame(OrderLifecycle::PAYMENT_PAID, $order->payment_status);
        $this->assertSame(0.0, (float) $order->outstanding_total);
        $this->assertSame(ManualOrderPaymentService::METHOD_CASH, $order->payment_method);
        $this->assertSame('manual', $order->payment_gateway);
        $this->assertSame('RCPT-44', $order->payment_reference);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_PAYMENT_STATUS_RECORDED,
        ]);
    }

    public function test_order_detail_can_record_manual_payment(): void
    {
        $owner = $this->merchant('pay-detail@example.test');
        $store = $this->store($owner, 'Manual Payment Detail');
        $order = $this->pendingManualOrder($store, $this->customer($store));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('Payment is still outstanding')
            ->assertSeeText('Record payment')
            ->assertSee('id="record-manual-payment-form"', false)
            ->assertSee('form="record-manual-payment-form"', false)
            ->assertDontSee('data-ui-confirm-title="Record this payment?"', false);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.payments.record', $order), [
                'payment_method' => ManualOrderPaymentService::METHOD_BANK_TRANSFER,
                'payment_reference' => 'IBAN-9',
            ])
            ->assertRedirect(route('orderViewDetails', $order));

        $order->refresh();
        $this->assertSame(OrderLifecycle::PAYMENT_PAID, $order->payment_status);
        $this->assertSame(0.0, (float) $order->outstanding_total);
        $this->assertSame(ManualOrderPaymentService::METHOD_BANK_TRANSFER, $order->payment_method);
        $this->assertDatabaseHas('security_logs', [
            'store_id' => $store->id,
            'event_type' => 'manual_payment_recorded',
            'severity' => SecurityLog::SEVERITY_INFO,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('Paid')
            ->assertDontSeeText('Payment is still outstanding');
    }

    public function test_cannot_record_payment_twice_or_on_platform_checkout(): void
    {
        $owner = $this->merchant('pay-guard@example.test');
        $store = $this->store($owner, 'Manual Payment Guard');
        $manual = $this->pendingManualOrder($store, $this->customer($store));
        $platform = $this->pendingManualOrder($store, $this->customer($store), [
            'order_number' => '#8802',
            'order_source' => 'platform_checkout',
            'channel' => 'wordpress',
            'payment_gateway' => 'stripe',
            'meta' => ['platform_checkout' => ['checkout_number' => 'CHK-1']],
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.payments.record', $manual), [
                'payment_method' => ManualOrderPaymentService::METHOD_CASH,
            ])
            ->assertRedirect(route('orderViewDetails', $manual));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('orderViewDetails', $manual))
            ->post(route('orders.payments.record', $manual), [
                'payment_method' => ManualOrderPaymentService::METHOD_CASH,
            ])
            ->assertRedirect(route('orderViewDetails', $manual))
            ->assertSessionHasErrors('payment');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('orderViewDetails', $platform))
            ->post(route('orders.payments.record', $platform), [
                'payment_method' => ManualOrderPaymentService::METHOD_CASH,
            ])
            ->assertRedirect(route('orderViewDetails', $platform))
            ->assertSessionHasErrors('payment');

        $this->assertSame(OrderLifecycle::PAYMENT_PENDING, $platform->fresh()->payment_status);
    }

    public function test_staff_and_other_store_cannot_record_manual_payment(): void
    {
        $owner = $this->merchant('pay-owner@example.test');
        $staff = $this->merchant('pay-staff@example.test');
        $otherOwner = $this->merchant('pay-other@example.test');
        $store = $this->store($owner, 'Payment Owner Store');
        $otherStore = $this->store($otherOwner, 'Payment Other Store');
        $this->attach($store, $staff, Store::ROLE_STAFF);
        $order = $this->pendingManualOrder($store, $this->customer($store));
        $foreign = $this->pendingManualOrder($otherStore, $this->customer($otherStore), [
            'order_number' => '#9901',
        ]);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.payments.record', $order), [
                'payment_method' => ManualOrderPaymentService::METHOD_CASH,
            ])
            ->assertForbidden();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.payments.record', $foreign), [
                'payment_method' => ManualOrderPaymentService::METHOD_CASH,
            ])
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Store, 2: ProductVariant}
     */
    private function manualCatalog(): array
    {
        $owner = $this->merchant('pay-convert@example.test');
        $store = $this->store($owner, 'Manual Payment Convert');
        [, $variant] = $this->product($store);

        return [$owner, $store, $variant];
    }

    private function createDraft(User $owner, Store $store, ProductVariant $variant): DraftOrder
    {
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.store'), $this->draftPayload($variant))
            ->assertRedirect();

        return DraftOrder::query()->where('store_id', $store->id)->latest('id')->firstOrFail();
    }

    private function draftPayload(ProductVariant $variant): array
    {
        return [
            'customer_name' => 'Manual Buyer',
            'customer_email' => 'manual-pay@example.test',
            'shipping_name' => 'Manual Buyer',
            'shipping_address_line1' => '10 Manual Road',
            'shipping_city' => 'Karachi',
            'shipping_state' => 'SD',
            'shipping_postal_code' => '74000',
            'shipping_country' => 'PK',
            'billing_same_as_shipping' => '1',
            'shipping_total' => '5.00',
            'tax_total' => '2.00',
            'discount_total' => '0.00',
            'items' => [[
                'product_variant_id' => $variant->id,
                'quantity' => 2,
                'unit_price' => '12.00',
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function pendingManualOrder(Store $store, Customer $customer, array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => '#8801',
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PENDING,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'customer_email' => $customer->email,
            'subtotal' => 60,
            'total' => 60,
            'grand_total' => 60,
            'outstanding_total' => 60,
            'currency_code' => 'USD',
            'order_source' => 'manual',
            'channel' => 'dashboard',
            'item_count' => 1,
            'total_quantity' => 1,
            'placed_at' => now(),
        ], $overrides));
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

    private function attach(Store $store, User $user, string $role): void
    {
        $store->members()->syncWithoutDetaching([$user->id => ['role' => $role]]);
    }

    private function customer(Store $store): Customer
    {
        return Customer::query()->create([
            'store_id' => $store->id,
            'email' => fake()->unique()->safeEmail(),
            'full_name' => 'Test Buyer',
            'status' => 'active',
        ]);
    }

    private function product(Store $store): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Manual Jacket',
            'slug' => 'manual-jacket-'.Str::random(6),
            'base_price' => 12,
            'sku' => 'MANUAL-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => $product->sku.'-M',
            'price' => 12,
            'stock' => 8,
        ]);

        return [$product, $variant];
    }
}
