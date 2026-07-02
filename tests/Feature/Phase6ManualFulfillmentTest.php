<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\SecurityLog;
use App\Models\Shipment;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\User;
use App\Services\Fulfillment\FulfillmentStatusService;
use App\Services\ShipmentNumberGenerator;
use App\Support\OrderLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6ManualFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipping_page_is_real_and_owner_can_manage_setup_while_staff_cannot(): void
    {
        $owner = $this->merchant('owner@example.test');
        $staff = $this->merchant('staff@example.test');
        $store = $this->store($owner, 'Shipping Setup Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($store, $staff, Store::ROLE_STAFF);
        $carrier = $this->carrier('manual-delivery', 'Manual delivery');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('Delivery')
            ->assertSeeText('Delivery areas')
            ->assertSeeText('Delivery options')
            ->assertSeeText('FedEx Merchant Account')
            ->assertSeeText('Connect FedEx account')
            ->assertSeeText('Fulfillment locations')
            ->assertDontSeeText('Add carrier account')
            ->assertDontSeeText('Save unavailable')
            ->assertDontSeeText('Export preview')
            ->assertDontSeeText('Global Automation Preview')
            ->assertDontSeeText('Active Label Gen');

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.store'), [
                'carrier_id' => $carrier->id,
                'display_name' => 'Blocked account',
                'connection_type' => CarrierAccount::CONNECTION_MANUAL,
                'status' => CarrierAccount::STATUS_ENABLED,
            ])
            ->assertForbidden();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.store'), [
                'carrier_id' => $carrier->id,
                'display_name' => 'Main manual delivery',
                'connection_type' => CarrierAccount::CONNECTION_MANUAL,
                'status' => CarrierAccount::STATUS_ENABLED,
            ])
            ->assertRedirect(route('shipping.carriers.connect.index'))
            ->assertSessionHas('success');

        $location = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Main warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '100 Warehouse Rd',
            'city' => 'Memphis',
            'state' => 'TN',
            'postal_code' => '38118',
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.origin', 'manual'), [
                'origin_location_id' => $location->id,
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.ownership', 'manual'), [
                'origin_location_id' => $location->id,
                'ownership_mode' => CarrierAccount::OWNERSHIP_MANUAL,
                'carrier_id' => $carrier->id,
                'display_name' => 'Main manual delivery',
                'supported_countries' => 'US, CA',
                'enabled_for_checkout' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('carrier_accounts', [
            'store_id' => $store->id,
            'carrier_id' => $carrier->id,
            'display_name' => 'Main manual delivery',
            'status' => CarrierAccount::STATUS_ENABLED,
        ]);
        $this->assertDatabaseHas('security_logs', [
            'store_id' => $store->id,
            'event_type' => 'shipping.carrier_wizard_ownership_saved',
            'severity' => SecurityLog::SEVERITY_INFO,
        ]);
    }

    public function test_zones_methods_and_carrier_accounts_are_store_scoped(): void
    {
        $owner = $this->merchant('owner@example.test');
        $otherOwner = $this->merchant('other@example.test');
        $store = $this->store($owner, 'Delivery Store A');
        $otherStore = $this->store($otherOwner, 'Delivery Store B');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($otherStore, $otherOwner, Store::ROLE_OWNER);
        $carrier = $this->carrier('local-courier', 'Local courier');
        $otherAccount = $this->carrierAccount($otherStore, $carrier, 'Other carrier');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.zones.store'), [
                'name' => 'United States',
                'countries' => 'US',
                'regions' => 'California',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $zone = ShippingZone::query()->where('store_id', $store->id)->firstOrFail();
        $account = $this->carrierAccount($store, $carrier, 'Local delivery');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.methods.store'), [
                'shipping_zone_id' => $zone->id,
                'carrier_account_id' => $account->id,
                'name' => 'Standard delivery',
                'rate_type' => ShippingMethod::RATE_FLAT,
                'flat_rate' => '8.00',
                'enabled_for_checkout' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('shipping_methods', [
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'carrier_account_id' => $account->id,
            'name' => 'Standard delivery',
            'flat_rate' => '8.00',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.methods.store'), [
                'shipping_zone_id' => $zone->id,
                'carrier_account_id' => $otherAccount->id,
                'name' => 'Cross-store carrier',
                'rate_type' => ShippingMethod::RATE_FLAT,
                'flat_rate' => '5.00',
                'enabled_for_checkout' => '1',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('carrier_account_id');

        $this->assertDatabaseMissing('shipping_methods', [
            'store_id' => $store->id,
            'name' => 'Cross-store carrier',
        ]);
    }

    public function test_order_detail_creates_shipments_and_recalculates_fulfillment_status(): void
    {
        [$owner, $store, $order, $item] = $this->orderFixture('Shipment Flow Store', 2);
        [$account, $method] = $this->shippingSetup($store);
        $location = $store->locations()->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('Fulfillment')
            ->assertSeeText('Create shipment')
            ->assertDontSeeText('Print Label')
            ->assertDontSeeText('Schedule pickup');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.shipments.store', $order), [
                'origin_location_id' => $location->id,
                'carrier_account_id' => $account->id,
                'shipping_method_id' => $method->id,
                'tracking_number' => 'TRACK-1',
                'items' => [$item->id => 1],
            ])
            ->assertRedirect();

        $shipment = Shipment::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(Shipment::STATUS_PENDING, $shipment->status);
        $this->assertSame(OrderLifecycle::FULFILLMENT_UNFULFILLED, $order->fresh()->fulfillment_status);
        $this->assertSame(OrderLifecycle::FULFILLMENT_UNFULFILLED, $item->fresh()->fulfillment_status);
        $this->assertDatabaseHas('shipment_items', [
            'store_id' => $store->id,
            'shipment_id' => $shipment->id,
            'order_item_id' => $item->id,
            'quantity' => 1,
        ]);
        $this->assertDatabaseHas('order_events', [
            'store_id' => $store->id,
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_SHIPMENT_CREATED,
        ]);
        $this->assertDatabaseMissing('order_events', [
            'store_id' => $store->id,
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_FULFILLMENT_STATUS_CHANGED,
        ]);

        $shipment->forceFill(['status' => Shipment::STATUS_LABEL_CREATED])->save();
        app(FulfillmentStatusService::class)->recalculateAndPersist($order->fresh(), $owner, 'label_created_check');
        $this->assertSame(OrderLifecycle::FULFILLMENT_UNFULFILLED, $order->fresh()->fulfillment_status);
        $this->assertSame(OrderLifecycle::FULFILLMENT_UNFULFILLED, $item->fresh()->fulfillment_status);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipments.mark-shipped', $shipment))
            ->assertRedirect();

        $this->assertSame(Shipment::STATUS_SHIPPED, $shipment->fresh()->status);
        $this->assertSame(OrderLifecycle::FULFILLMENT_PARTIAL, $order->fresh()->fulfillment_status);
        $this->assertSame(OrderLifecycle::FULFILLMENT_PARTIAL, $item->fresh()->fulfillment_status);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.shipments.store', $order), [
                'origin_location_id' => $location->id,
                'carrier_account_id' => $account->id,
                'shipping_method_id' => $method->id,
                'items' => [$item->id => 1],
            ])
            ->assertRedirect();

        $secondShipment = Shipment::query()
            ->where('order_id', $order->id)
            ->whereKeyNot($shipment->id)
            ->firstOrFail();

        $this->assertSame(OrderLifecycle::FULFILLMENT_PARTIAL, $order->fresh()->fulfillment_status);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipments.mark-shipped', $secondShipment))
            ->assertRedirect();

        $this->assertSame(OrderLifecycle::FULFILLMENT_FULFILLED, $order->fresh()->fulfillment_status);
        $this->assertSame(OrderLifecycle::FULFILLMENT_FULFILLED, $item->fresh()->fulfillment_status);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipments.mark-delivered', $secondShipment))
            ->assertRedirect();

        $this->assertSame(OrderLifecycle::FULFILLMENT_FULFILLED, $order->fresh()->fulfillment_status);
        $this->assertSame(OrderLifecycle::FULFILLMENT_FULFILLED, $item->fresh()->fulfillment_status);
    }

    public function test_shipment_cannot_exceed_unfulfilled_quantity_or_use_other_store_account(): void
    {
        [$owner, $store, $order, $item] = $this->orderFixture('Shipment Safety Store', 1);
        $otherOwner = $this->merchant('other-owner@example.test');
        $otherStore = $this->store($otherOwner, 'Other Shipment Store');
        $this->attach($otherStore, $otherOwner, Store::ROLE_OWNER);
        $carrier = $this->carrier('ups', 'UPS');
        $otherAccount = $this->carrierAccount($otherStore, $carrier, 'Other UPS');
        $location = $store->locations()->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.shipments.store', $order), [
                'origin_location_id' => $location->id,
                'carrier_account_id' => $otherAccount->id,
                'items' => [$item->id => 1],
            ])
            ->assertSessionHasErrors('carrier_account_id');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.shipments.store', $order), [
                'origin_location_id' => $location->id,
                'items' => [$item->id => 2],
            ])
            ->assertSessionHasErrors("items.{$item->id}");

        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_tracking_status_updates_cancel_and_shipment_numbers_are_audited(): void
    {
        [$owner, $store, $order, $item] = $this->orderFixture('Shipment Status Store', 1);
        [$account, $method] = $this->shippingSetup($store);
        $location = $store->locations()->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.shipments.store', $order), [
                'origin_location_id' => $location->id,
                'carrier_account_id' => $account->id,
                'shipping_method_id' => $method->id,
                'items' => [$item->id => 1],
            ])
            ->assertRedirect();

        $shipment = Shipment::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(OrderLifecycle::FULFILLMENT_UNFULFILLED, $order->fresh()->fulfillment_status);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('shipments.tracking.update', $shipment), [
                'tracking_number' => 'TRACK-UPDATED',
                'tracking_url' => 'https://example.test/track/TRACK-UPDATED',
            ])
            ->assertRedirect();

        $this->assertSame('TRACK-UPDATED', $shipment->fresh()->tracking_number);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_SHIPMENT_TRACKING_ADDED,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipments.mark-shipped', $shipment))
            ->assertRedirect();

        $this->assertSame(Shipment::STATUS_SHIPPED, $shipment->fresh()->status);
        $this->assertSame(OrderLifecycle::FULFILLMENT_FULFILLED, $order->fresh()->fulfillment_status);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipments.mark-delivered', $shipment))
            ->assertRedirect();

        $this->assertSame(Shipment::STATUS_DELIVERED, $shipment->fresh()->status);
        $this->assertNotNull($shipment->fresh()->delivered_at);
        $this->assertSame(OrderLifecycle::FULFILLMENT_FULFILLED, $order->fresh()->fulfillment_status);
        $this->assertDatabaseHas('security_logs', [
            'store_id' => $store->id,
            'event_type' => 'shipment_status_changed',
        ]);

        [$cancelOwner, $cancelStore, $cancelOrder, $cancelItem] = $this->orderFixture('Shipment Cancel Store', 1);
        [$cancelAccount, $cancelMethod] = $this->shippingSetup($cancelStore);

        $this->actingAs($cancelOwner)
            ->withSession(['current_store_id' => $cancelStore->id])
            ->post(route('orders.shipments.store', $cancelOrder), [
                'carrier_account_id' => $cancelAccount->id,
                'shipping_method_id' => $cancelMethod->id,
                'items' => [$cancelItem->id => 1],
            ])
            ->assertRedirect();

        $cancelShipment = Shipment::query()->where('order_id', $cancelOrder->id)->firstOrFail();
        $this->assertSame(OrderLifecycle::FULFILLMENT_UNFULFILLED, $cancelOrder->fresh()->fulfillment_status);

        $this->actingAs($cancelOwner)
            ->withSession(['current_store_id' => $cancelStore->id])
            ->post(route('shipments.cancel', $cancelShipment))
            ->assertRedirect();

        $this->assertSame(Shipment::STATUS_CANCELLED, $cancelShipment->fresh()->status);
        $this->assertSame(OrderLifecycle::FULFILLMENT_UNFULFILLED, $cancelOrder->fresh()->fulfillment_status);

        $this->actingAs($cancelOwner)
            ->withSession(['current_store_id' => $cancelStore->id])
            ->post(route('orders.shipments.store', $cancelOrder), [
                'carrier_account_id' => $cancelAccount->id,
                'shipping_method_id' => $cancelMethod->id,
                'items' => [$cancelItem->id => 1],
            ])
            ->assertRedirect();

        $retryShipment = Shipment::query()
            ->where('order_id', $cancelOrder->id)
            ->where('id', '!=', $cancelShipment->id)
            ->firstOrFail();

        $this->assertSame(Shipment::STATUS_PENDING, $retryShipment->status);
        $this->assertSame(OrderLifecycle::FULFILLMENT_UNFULFILLED, $cancelOrder->fresh()->fulfillment_status);
    }

    public function test_failed_shipments_do_not_count_and_duplicate_item_lines_are_grouped_before_validation(): void
    {
        [$owner, $store, $order, $item] = $this->orderFixture('Shipment Duplicate Safety Store', 2);
        [$account, $method] = $this->shippingSetup($store);
        $location = $store->locations()->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.shipments.store', $order), [
                'origin_location_id' => $location->id,
                'carrier_account_id' => $account->id,
                'shipping_method_id' => $method->id,
                'items' => [
                    ['order_item_id' => $item->id, 'quantity' => 1],
                    ['order_item_id' => $item->id, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $shipment = Shipment::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertSame(OrderLifecycle::FULFILLMENT_UNFULFILLED, $order->fresh()->fulfillment_status);
        $this->assertDatabaseHas('shipment_items', [
            'shipment_id' => $shipment->id,
            'order_item_id' => $item->id,
            'quantity' => 2,
        ]);
        $this->assertSame(1, $shipment->items()->count());

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipments.mark-failed', $shipment))
            ->assertRedirect();

        $this->assertSame(Shipment::STATUS_FAILED, $shipment->fresh()->status);
        $this->assertSame(OrderLifecycle::FULFILLMENT_UNFULFILLED, $order->fresh()->fulfillment_status);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.shipments.store', $order), [
                'origin_location_id' => $location->id,
                'carrier_account_id' => $account->id,
                'shipping_method_id' => $method->id,
                'items' => [$item->id => 2],
            ])
            ->assertRedirect();

        $replacementShipment = Shipment::query()
            ->where('order_id', $order->id)
            ->where('id', '!=', $shipment->id)
            ->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipments.mark-shipped', $replacementShipment))
            ->assertRedirect();

        $this->assertSame(OrderLifecycle::FULFILLMENT_FULFILLED, $order->fresh()->fulfillment_status);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipments.mark-failed', $replacementShipment))
            ->assertRedirect();

        $this->assertSame(Shipment::STATUS_FAILED, $replacementShipment->fresh()->status);
        $this->assertSame(OrderLifecycle::FULFILLMENT_UNFULFILLED, $order->fresh()->fulfillment_status);

        [$blockedOwner, $blockedStore, $blockedOrder, $blockedItem] = $this->orderFixture('Shipment Duplicate Block Store', 1);
        [$blockedAccount, $blockedMethod] = $this->shippingSetup($blockedStore);

        $this->actingAs($blockedOwner)
            ->withSession(['current_store_id' => $blockedStore->id])
            ->post(route('orders.shipments.store', $blockedOrder), [
                'carrier_account_id' => $blockedAccount->id,
                'shipping_method_id' => $blockedMethod->id,
                'items' => [
                    ['order_item_id' => $blockedItem->id, 'quantity' => 1],
                    ['order_item_id' => $blockedItem->id, 'quantity' => 1],
                ],
            ])
            ->assertSessionHasErrors([
                "items.{$blockedItem->id}" => 'Shipment quantity exceeds the remaining quantity for this item.',
            ]);

        $this->assertDatabaseMissing('shipments', [
            'order_id' => $blockedOrder->id,
        ]);
    }

    public function test_pending_duplicate_shipments_cannot_later_over_fulfill_when_marked_shipped(): void
    {
        [$owner, $store, $order, $item] = $this->orderFixture('Shipment Pending Safety Store', 1);
        [$account, $method] = $this->shippingSetup($store);

        foreach (range(1, 2) as $index) {
            $this->actingAs($owner)
                ->withSession(['current_store_id' => $store->id])
                ->post(route('orders.shipments.store', $order), [
                    'carrier_account_id' => $account->id,
                    'shipping_method_id' => $method->id,
                    'items' => [$item->id => 1],
                ])
                ->assertRedirect();
        }

        $shipments = Shipment::query()->where('order_id', $order->id)->orderBy('id')->get();
        $this->assertCount(2, $shipments);
        $this->assertSame(OrderLifecycle::FULFILLMENT_UNFULFILLED, $order->fresh()->fulfillment_status);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipments.mark-shipped', $shipments[0]))
            ->assertRedirect();

        $this->assertSame(OrderLifecycle::FULFILLMENT_FULFILLED, $order->fresh()->fulfillment_status);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipments.mark-shipped', $shipments[1]))
            ->assertSessionHasErrors([
                'shipment_status' => 'Shipment quantity exceeds the remaining quantity for this item.',
            ]);

        $this->assertSame(Shipment::STATUS_PENDING, $shipments[1]->fresh()->status);
        $this->assertSame(OrderLifecycle::FULFILLMENT_FULFILLED, $order->fresh()->fulfillment_status);
    }

    public function test_shipment_numbers_are_store_scoped_sequences(): void
    {
        $owner = $this->merchant('sequence-owner@example.test');
        $storeA = $this->store($owner, 'Sequence Store A');
        $storeB = $this->store($owner, 'Sequence Store B');
        $this->attach($storeA, $owner, Store::ROLE_OWNER);
        $this->attach($storeB, $owner, Store::ROLE_OWNER);

        $generator = app(ShipmentNumberGenerator::class);

        $this->assertSame('SHP-1001', $generator->generate($storeA));
        $this->assertSame('SHP-1002', $generator->generate($storeA));
        $this->assertSame('SHP-1001', $generator->generate($storeB));
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

    private function carrier(string $code, string $name): Carrier
    {
        return Carrier::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'type' => Carrier::TYPE_COURIER,
                'is_system' => true,
                'is_active' => true,
            ]
        );
    }

    private function carrierAccount(Store $store, Carrier $carrier, string $name): CarrierAccount
    {
        return CarrierAccount::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $carrier->id,
            'display_name' => $name,
            'connection_type' => CarrierAccount::CONNECTION_MANUAL,
            'status' => CarrierAccount::STATUS_ENABLED,
            'supported_countries' => ['US'],
            'enabled_for_checkout' => true,
        ]);
    }

    /**
     * @return array{0: CarrierAccount, 1: ShippingMethod}
     */
    private function shippingSetup(Store $store): array
    {
        $carrier = $this->carrier('manual-delivery', 'Manual delivery');
        $account = $this->carrierAccount($store, $carrier, 'Manual delivery');
        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'Local delivery area',
            'countries' => ['US'],
            'is_active' => true,
        ]);
        $method = ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'carrier_account_id' => $account->id,
            'name' => 'Standard delivery',
            'code' => 'standard-delivery-'.Str::random(5),
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 5,
            'enabled_for_checkout' => true,
            'is_active' => true,
        ]);

        return [$account, $method];
    }

    /**
     * @return array{0: User, 1: Store, 2: Order, 3: OrderItem}
     */
    private function orderFixture(string $storeName, int $quantity): array
    {
        $owner = $this->merchant(Str::slug($storeName).'-owner@example.test');
        $store = $this->store($owner, $storeName);
        $this->attach($store, $owner, Store::ROLE_OWNER);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Fulfillment Product',
            'slug' => 'fulfillment-product-'.Str::random(6),
            'base_price' => 20,
            'sku' => 'FUL-'.$quantity.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => $product->sku.'-D',
            'price' => 20,
            'stock' => 10,
        ]);
        $order = Order::query()->create([
            'store_id' => $store->id,
            'order_number' => '#F'.fake()->unique()->numberBetween(1000, 9999),
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'customer_email' => fake()->unique()->safeEmail(),
            'subtotal' => 20 * $quantity,
            'total' => 20 * $quantity,
            'grand_total' => 20 * $quantity,
            'currency_code' => 'USD',
            'order_source' => 'manual',
            'channel' => 'dashboard',
            'item_count' => 1,
            'total_quantity' => $quantity,
            'placed_at' => now(),
        ]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Fulfillment Product',
            'variant_label' => 'Default option',
            'sku_snapshot' => $variant->sku,
            'product_type_snapshot' => 'physical',
            'quantity' => $quantity,
            'unit_price' => 20,
            'subtotal' => 20 * $quantity,
            'total' => 20 * $quantity,
        ]);

        return [$owner, $store, $order, $item];
    }
}
