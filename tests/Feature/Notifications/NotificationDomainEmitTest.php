<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendNotificationEmailJob;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\StoreNotification;
use App\Models\User;
use App\Services\Notifications\CommerceNotificationEmitter;
use App\Services\ReturnService;
use App\Support\NotificationEvent;
use App\Support\OrderLifecycle;
use App\Support\ReturnLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationDomainEmitTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_created_emits_merchant_and_customer_notifications(): void
    {
        Queue::fake();
        [$owner, $store] = $this->ownerWithStore('Order Notify');
        $customer = $this->customer($store);
        $order = $this->order($store, $customer);

        app(CommerceNotificationEmitter::class)->orderCreated($order, $owner);

        $this->assertDatabaseHas('notifications', [
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'type' => NotificationEvent::ORDER_CREATED,
            'channel' => NotificationEvent::CHANNEL_IN_APP,
        ]);

        $this->assertDatabaseHas('notifications', [
            'store_id' => $store->id,
            'type' => NotificationEvent::ORDER_CREATED,
            'channel' => NotificationEvent::CHANNEL_EMAIL,
            'recipient_email' => $customer->email,
        ]);

        Queue::assertPushed(SendNotificationEmailJob::class);
    }

    public function test_payment_failed_and_import_and_low_stock_emit(): void
    {
        Queue::fake();
        [$owner, $store] = $this->ownerWithStore('Ops Notify');

        app(CommerceNotificationEmitter::class)->paymentFailed(
            $store,
            'pi_test',
            'Card was declined.',
            42
        );

        $this->assertDatabaseHas('notifications', [
            'store_id' => $store->id,
            'type' => NotificationEvent::PAYMENT_FAILED,
            'user_id' => $owner->id,
        ]);

        $import = ProductImport::query()->create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'original_filename' => 'products.csv',
            'stored_path' => 'imports/test.csv',
            'file_extension' => 'csv',
            'status' => ProductImport::STATUS_COMPLETED,
            'result_summary' => ['processed_rows' => 3],
        ]);

        app(CommerceNotificationEmitter::class)->importFinished($import, false);

        $this->assertDatabaseHas('notifications', [
            'store_id' => $store->id,
            'type' => NotificationEvent::IMPORT_COMPLETED,
        ]);

        [$product, $variant] = $this->product($store);
        $variant->update(['stock' => 2, 'stock_alert' => 5]);

        app(\App\Services\Notifications\LowStockNotifier::class)->checkVariant($variant->fresh());

        $this->assertDatabaseHas('notifications', [
            'store_id' => $store->id,
            'type' => NotificationEvent::INVENTORY_LOW,
        ]);
    }

    public function test_return_and_shipment_emit_merchant_and_customer_mail(): void
    {
        Queue::fake();
        [$owner, $store] = $this->ownerWithStore('Return Ship Notify');
        $customer = $this->customer($store);
        $order = $this->order($store, $customer);
        [$product, $variant] = $this->product($store);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_label' => 'Size: M',
            'sku_snapshot' => $variant->sku,
            'product_type_snapshot' => 'physical',
            'quantity' => 2,
            'unit_price' => 25,
            'subtotal' => 50,
            'total' => 50,
        ]);

        $return = app(ReturnService::class)->requestReturn($order, [
            'items' => [$item->id => 1],
        ], $owner);

        $this->assertDatabaseHas('notifications', [
            'store_id' => $store->id,
            'type' => NotificationEvent::RETURN_REQUESTED,
            'channel' => NotificationEvent::CHANNEL_IN_APP,
        ]);
        $this->assertTrue(
            StoreNotification::query()
                ->where('store_id', $store->id)
                ->where('type', NotificationEvent::RETURN_REQUESTED)
                ->where('channel', NotificationEvent::CHANNEL_EMAIL)
                ->where('recipient_email', $customer->email)
                ->exists()
        );

        $shipment = Shipment::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'shipment_number' => 'SHP-1',
            'status' => Shipment::STATUS_SHIPPED,
            'tracking_number' => 'TRACK-1',
        ]);

        app(CommerceNotificationEmitter::class)->shipmentEvent(
            $shipment,
            NotificationEvent::SHIPMENT_SHIPPED,
            'Shipment shipped',
            $owner
        );

        $this->assertDatabaseHas('notifications', [
            'store_id' => $store->id,
            'type' => NotificationEvent::SHIPMENT_SHIPPED,
        ]);

        $this->assertTrue(
            StoreNotification::query()
                ->where('type', NotificationEvent::SHIPMENT_SHIPPED)
                ->where('recipient_email', $customer->email)
                ->exists()
        );

        $this->assertSame(ReturnLifecycle::STATUS_REQUESTED, $return->status);
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerWithStore(string $name): array
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['role_id' => $role->id]);
        $store = Store::create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => 'dom-'.Str::lower(Str::random(8)),
            'logo' => null,
            'address' => 'Addr',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        return [$owner, $store];
    }

    private function customer(Store $store): Customer
    {
        return Customer::query()->create([
            'store_id' => $store->id,
            'email' => fake()->unique()->safeEmail(),
            'full_name' => 'Notify Buyer',
            'status' => 'active',
        ]);
    }

    private function order(Store $store, Customer $customer): Order
    {
        return Order::query()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => '#N-1001',
            'status' => OrderLifecycle::ORDER_COMPLETED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'customer_email' => $customer->email,
            'subtotal' => 50,
            'total' => 50,
            'grand_total' => 50,
            'currency_code' => 'USD',
            'order_source' => 'manual',
            'channel' => 'dashboard',
            'item_count' => 1,
            'total_quantity' => 2,
            'placed_at' => now(),
        ]);
    }

    /**
     * @return array{0: Product, 1: ProductVariant}
     */
    private function product(Store $store): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Notify Tee',
            'slug' => 'notify-tee-'.Str::random(6),
            'base_price' => 25,
            'sku' => 'NTF',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'NTF-M',
            'price' => 25,
            'stock' => 10,
            'stock_alert' => 5,
        ]);

        return [$product, $variant];
    }
}
