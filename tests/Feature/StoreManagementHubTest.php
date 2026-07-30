<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderEventRecorder;
use App\Support\OrderLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreManagementHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_sees_only_their_stores_on_management_hub(): void
    {
        $owner = $this->merchant('owner-hub@example.com');
        $otherOwner = $this->merchant('other-hub@example.com');

        $ownStore = $this->store($owner, 'Own Hub Store', onboardingCompleted: true);
        $otherStore = $this->store($otherOwner, 'Foreign Hub Store', onboardingCompleted: true);
        $this->attach($ownStore, $owner, Store::ROLE_OWNER);
        $this->attach($otherStore, $otherOwner, Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $ownStore->id])
            ->get(route('store-management'))
            ->assertOk()
            ->assertSeeText('Own Hub Store')
            ->assertDontSeeText('Foreign Hub Store')
            ->assertSeeText('Working here')
            ->assertSeeText('Your stores are ready')
            ->assertDontSeeText('Finish store setup')
            ->assertDontSeeText('New Order: #8942')
            ->assertDontSeeText('Theme Updated: V2.4')
            ->assertDontSeeText('View Upgrade Options');
    }

    public function test_owner_can_mark_draft_store_live_and_move_back_to_draft(): void
    {
        $owner = $this->merchant('lifecycle-hub@example.com');
        $store = $this->store($owner, 'Lifecycle Hub Store', onboardingCompleted: false);
        $this->attach($store, $owner, Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('store.lifecycle', ['storeId' => $store->id]), ['status' => 'live'])
            ->assertRedirect(route('store-management'));

        $this->assertTrue((bool) $store->fresh()->onboarding_completed);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('store-management'))
            ->assertOk()
            ->assertSeeText('Live')
            ->assertSeeText('Setup needed')
            ->assertDontSeeText('Quiet')
            ->assertDontSeeText('Ready to sell');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('store.lifecycle', ['storeId' => $store->id]), ['status' => 'draft'])
            ->assertRedirect(route('store-management'));

        $this->assertFalse((bool) $store->fresh()->onboarding_completed);
    }

    public function test_setup_needed_clears_only_after_major_operational_steps_are_ready(): void
    {
        $owner = $this->merchant('ready-hub@example.com');
        $store = $this->store($owner, 'Ready Hub Store', onboardingCompleted: true);
        $this->attach($store, $owner, Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('store-management'))
            ->assertOk()
            ->assertSeeText('Setup needed')
            ->assertDontSeeText('Quiet');

        $this->seedOperationalSetup($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('store-management'))
            ->assertOk()
            ->assertSeeText('Ready to sell')
            ->assertDontSeeText('Setup needed')
            ->assertDontSeeText('Quiet');

        $this->order($store, [
            'grand_total' => 40,
            'placed_at' => now()->subDay(),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('store-management'))
            ->assertOk()
            ->assertSeeText('Selling')
            ->assertDontSeeText('Setup needed')
            ->assertDontSeeText('Ready to sell');
    }

    public function test_external_checkout_counts_as_payments_ready_without_stripe(): void
    {
        $owner = $this->merchant('external-ready-hub@example.com');
        $store = $this->store($owner, 'External Ready Store', onboardingCompleted: true);
        $this->attach($store, $owner, Store::ROLE_OWNER);

        $this->seedOperationalSetup($store);

        $this->assertSame(\App\Support\CheckoutMode::EXTERNAL, \App\Support\CheckoutMode::forStore($store->fresh()));
        $this->assertFalse(
            \App\Models\PaymentProviderAccount::query()->where('store_id', $store->id)->exists()
        );

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('store-management'))
            ->assertOk()
            ->assertSeeText('Ready to sell')
            ->assertDontSeeText('Setup needed');
    }

    public function test_platform_checkout_without_stripe_keeps_setup_needed_for_payments(): void
    {
        $owner = $this->merchant('platform-setup-hub@example.com');
        $store = $this->store($owner, 'Platform Setup Store', onboardingCompleted: true);
        $this->attach($store, $owner, Store::ROLE_OWNER);

        $this->seedOperationalSetup($store);
        $store->forceFill([
            'settings' => array_merge($store->fresh()->settings ?? [], [
                'checkout_mode' => \App\Support\CheckoutMode::PLATFORM,
            ]),
        ])->save();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('store-management'))
            ->assertOk()
            ->assertSeeText('Setup needed')
            ->assertDontSeeText('Ready to sell');
    }

    public function test_recent_activity_shows_member_store_events_and_hides_cross_store(): void
    {
        $owner = $this->merchant('activity-owner@example.com');
        $otherOwner = $this->merchant('activity-other@example.com');

        $ownStore = $this->store($owner, 'Activity Own Store');
        $otherStore = $this->store($otherOwner, 'Activity Foreign Store');
        $this->attach($ownStore, $owner, Store::ROLE_OWNER);
        $this->attach($otherStore, $otherOwner, Store::ROLE_OWNER);

        $ownOrder = $this->order($ownStore);
        $otherOrder = $this->order($otherStore);

        app(OrderEventRecorder::class)->record(
            $ownOrder,
            OrderLifecycle::EVENT_ORDER_CREATED,
            'Order placed from own storefront',
            'Visible on hub.',
            ['order_number' => $ownOrder->order_number],
            $owner,
        );

        app(OrderEventRecorder::class)->record(
            $otherOrder,
            OrderLifecycle::EVENT_ORDER_CREATED,
            'Secret foreign order event',
            'Must stay hidden.',
            ['order_number' => $otherOrder->order_number],
            $otherOwner,
        );

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $ownStore->id])
            ->get(route('store-management'))
            ->assertOk()
            ->assertSeeText('Order placed from own storefront')
            ->assertSeeText('Activity Own Store')
            ->assertDontSeeText('Secret foreign order event')
            ->assertDontSeeText('Activity Foreign Store')
            ->assertDontSeeText('New Order: #8942')
            ->assertDontSeeText('Theme Updated: V2.4')
            ->assertDontSeeText('New Domain Linked');
    }

    public function test_draft_next_steps_and_empty_activity_state_render(): void
    {
        $owner = $this->merchant('draft-hub@example.com');
        $draft = $this->store($owner, 'Draft Setup Store', onboardingCompleted: false);
        $this->attach($draft, $owner, Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $draft->id])
            ->get(route('store-management'))
            ->assertOk()
            ->assertSeeText('Draft Setup Store')
            ->assertSeeText('Finish store setup')
            ->assertSeeText('Continue setup')
            ->assertSeeText('No recent activity yet')
            ->assertSee('data-store-status="draft"', false)
            ->assertDontSeeText('View Upgrade Options');
    }

    public function test_switch_store_open_action_redirects_to_dashboard(): void
    {
        $owner = $this->merchant('switch-hub@example.com');
        $store = $this->store($owner, 'Switch Hub Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('current-store.update'), [
                'store_id' => $store->id,
                'redirect_to' => 'dashboard',
            ])
            ->assertRedirect(route('dashboard'));
    }

    private function merchant(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);
    }

    private function store(User $owner, string $name, bool $onboardingCompleted = true): Store
    {
        return Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Test Address',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => $onboardingCompleted,
        ]);
    }

    private function attach(Store $store, User $user, string $role): void
    {
        $store->members()->syncWithoutDetaching([
            $user->id => ['role' => $role],
        ]);
    }

    public function test_store_card_shows_real_seven_day_revenue_and_orders(): void
    {
        $owner = $this->merchant('metrics-hub@example.com');
        $store = $this->store($owner, 'Metrics Hub Store', onboardingCompleted: true);
        $this->attach($store, $owner, Store::ROLE_OWNER);

        \App\Models\Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Hub Product',
            'slug' => 'hub-product-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => 'Test',
            'base_price' => 10,
            'sku' => 'HUB-'.fake()->unique()->numberBetween(1000, 9999),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $this->order($store, [
            'grand_total' => 125.50,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'placed_at' => now()->subDays(2),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('store-management'))
            ->assertOk()
            ->assertSeeText('7D Revenue')
            ->assertSeeText('Orders (7D)')
            ->assertSeeText('$125.50')
            ->assertSee('js-store-sparkline', false)
            ->assertSeeText('Setup needed')
            ->assertDontSeeText('Healthy')
            ->assertDontSeeText('Quiet')
            ->assertDontSeeText('Conv. Rate')
            ->assertDontSeeText('High Health')
            ->assertDontSeeText('Critical Alert')
            ->assertDontSeeText('Download Report');
    }

    private function seedOperationalSetup(Store $store): void
    {
        \App\Models\Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Ready Product',
            'slug' => 'ready-product-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => 'Ready catalog item',
            'base_price' => 20,
            'sku' => 'READY-'.fake()->unique()->numberBetween(1000, 9999),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        \App\Models\Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Main warehouse',
            'type' => \App\Models\Location::TYPE_WAREHOUSE,
            'address_line1' => '100 Main St',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);

        \App\Models\TaxSetting::query()->updateOrCreate(
            ['store_id' => $store->id],
            [
                'enabled' => true,
                'prices_include_tax' => false,
                'default_product_taxable' => true,
                'shipping_taxable' => false,
                'calculation_address' => \App\Models\TaxSetting::CALCULATION_ADDRESS_SHIPPING,
            ]
        );

        \App\Models\TaxRate::query()->create([
            'store_id' => $store->id,
            'country_code' => 'US',
            'region_code' => 'TX',
            'name' => 'TX Sales Tax',
            'rate_percent' => 8.25,
            'priority' => 1,
            'is_active' => true,
        ]);

        $zone = \App\Models\ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'Domestic',
            'countries' => ['US'],
            'is_active' => true,
        ]);

        \App\Models\ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'carrier_account_id' => null,
            'name' => 'Standard',
            'code' => 'standard-'.fake()->unique()->numberBetween(1000, 9999),
            'rate_type' => \App\Models\ShippingMethod::RATE_FLAT,
            'flat_rate' => 5,
            'enabled_for_checkout' => true,
            'is_active' => true,
        ]);

        // External checkout (default) counts as payments-ready without Stripe.
        $store->forceFill([
            'settings' => array_merge($store->settings ?? [], [
                'checkout_mode' => \App\Support\CheckoutMode::EXTERNAL,
            ]),
        ])->save();
    }

    private function order(Store $store, array $overrides = []): Order
    {
        $customer = Customer::query()->create([
            'store_id' => $store->id,
            'email' => fake()->unique()->safeEmail(),
            'full_name' => 'Hub Customer',
            'status' => 'guest',
        ]);

        return Order::query()->create(array_merge([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => '#'.fake()->unique()->numberBetween(2000, 9999),
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PENDING,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'customer_email' => $customer->email,
            'customer_phone' => '+1234567890',
            'billing_same_as_shipping' => true,
            'subtotal' => 25,
            'total' => 25,
            'grand_total' => 25,
            'currency_code' => $store->currency,
            'order_source' => 'manual',
            'channel' => 'dashboard',
            'item_count' => 0,
            'total_quantity' => 0,
            'placed_at' => now(),
        ], $overrides));
    }
}
