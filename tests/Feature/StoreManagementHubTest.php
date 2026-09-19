<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Location;
use App\Models\Order;
use App\Models\PaymentProviderAccount;
use App\Models\Product;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use App\Models\StoreMemberPermission;
use App\Models\User;
use App\Services\OrderEventRecorder;
use App\Services\Settings\StoreMemberPermissionSync;
use App\Support\CheckoutMode;
use App\Support\OrderLifecycle;
use App\Support\StoreMemberAccess;
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
            ->assertSeeText('Create store')
            ->assertSeeText('Current')
            ->assertSeeText('1 store still needs setup')
            ->assertSeeText('Remaining setup')
            ->assertSeeText('Add a product')
            ->assertSeeText('Configure checkout tax')
            ->assertSeeText('Prepare delivery setup')
            ->assertDontSeeText('Finish store setup')
            ->assertDontSeeText('New Order: #8942')
            ->assertDontSeeText('Theme Updated: V2.4')
            ->assertDontSeeText('View Upgrade Options');
    }

    public function test_store_search_lives_in_the_page_header(): void
    {
        $owner = $this->merchant('search-hub@example.com');
        $store = $this->store($owner, 'Search Hub Store', onboardingCompleted: true);
        $this->attach($store, $owner, Store::ROLE_OWNER);

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('store-management'))
            ->assertOk()
            ->assertSee('id="stores-directory-search"', false)
            ->assertSee('placeholder="Search stores..."', false)
            ->getContent();

        $searchPos = strpos($html, 'id="stores-directory-search"');
        $directoryHeadingPos = strpos($html, 'id="storesHeading"');

        $this->assertNotFalse($searchPos);
        $this->assertNotFalse($directoryHeadingPos);
        $this->assertLessThan($directoryHeadingPos, $searchPos);
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
            ->assertSee('data-store-status="live"', false)
            ->assertSeeText('Setup needed')
            ->assertSeeText('Close store')
            ->assertDontSeeText('Move to draft')
            ->assertDontSeeText('Mark as live')
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

    public function test_operational_setup_without_stripe_still_shows_ready_to_sell_on_the_hub(): void
    {
        $owner = $this->merchant('platform-setup-hub@example.com');
        $store = $this->store($owner, 'Platform Setup Store', onboardingCompleted: true);
        $this->attach($store, $owner, Store::ROLE_OWNER);

        $this->seedOperationalSetup($store);

        $this->assertSame(CheckoutMode::PLATFORM, CheckoutMode::forStore($store->fresh()));
        $this->assertFalse(
            PaymentProviderAccount::query()->where('store_id', $store->id)->exists()
        );

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('store-management'))
            ->assertOk()
            ->assertSeeText('Ready to sell')
            ->assertDontSeeText('Setup needed');
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
            ->assertSee(route('orderViewDetails', $ownOrder), false)
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
            ->assertSeeText('Close store')
            ->assertDontSeeText('Mark as live')
            ->assertDontSeeText('Move to draft')
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

    public function test_continue_setup_asks_to_switch_when_the_store_is_not_current(): void
    {
        $owner = $this->merchant('switch-confirm-hub@example.com');
        $current = $this->store($owner, 'Current Setup Store');
        $other = $this->store($owner, 'Other Setup Store');
        $this->attach($current, $owner, Store::ROLE_OWNER);
        $this->attach($other, $owner, Store::ROLE_OWNER);

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $current->id])
            ->get(route('store-management'))
            ->assertOk()
            ->assertSee('data-store-switch-request', false)
            ->assertSee('data-store-id="'.$other->id.'"', false)
            ->assertSee('data-redirect-to="dashboard"', false)
            ->getContent();

        $this->assertStringContainsString('id="storeSwitchConfirmModal"', $html);
        $this->assertStringContainsString('Remaining setup', $html);
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

    /**
     * @return array<string, mixed>
     */
    private function createStorePayload(string $name, bool $openModal = false): array
    {
        $payload = [
            'mode' => 'create',
            'name' => $name,
            'primary_market' => 'United States',
            'address' => '10 First Street',
            'currency' => 'USD',
            'timezone' => 'America/Chicago',
            'category' => 'physical',
            'business_models' => ['Physical Goods'],
        ];

        if ($openModal) {
            $payload['_open_create_store_modal'] = '1';
        }

        return $payload;
    }

    public function test_store_card_shows_real_seven_day_revenue_and_orders(): void
    {
        $owner = $this->merchant('metrics-hub@example.com');
        $store = $this->store($owner, 'Metrics Hub Store', onboardingCompleted: true);
        $this->attach($store, $owner, Store::ROLE_OWNER);

        Product::query()->create([
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
            ->assertSeeText('Revenue · 7d')
            ->assertSeeText('Orders · 7d')
            ->assertSeeText('$125.50')
            ->assertDontSee('js-store-sparkline', false)
            ->assertSeeText('Setup needed')
            ->assertDontSeeText('Healthy')
            ->assertDontSeeText('Quiet')
            ->assertDontSeeText('Conv. Rate')
            ->assertDontSeeText('High Health')
            ->assertDontSeeText('Critical Alert')
            ->assertDontSeeText('Download Report');
    }

    public function test_view_only_member_cannot_create_a_store(): void
    {
        $owner = $this->merchant('create-gate-owner@example.com');
        $member = $this->merchant('create-gate-member@example.com');
        $store = $this->store($owner, 'Shared View Store', onboardingCompleted: true);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($store, $member, Store::ROLE_MEMBER);
        app(StoreMemberPermissionSync::class)->sync(
            $store,
            $member,
            StoreMemberAccess::presets()[StoreMemberAccess::PRESET_VIEW],
            StoreMemberAccess::PRESET_VIEW,
            null,
            null,
            StoreMemberAccess::STATUS_ACTIVE,
        );

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('store-management'))
            ->assertOk()
            ->assertSeeText('Shared View Store')
            ->assertDontSeeText('Create store')
            ->assertDontSee('js-open-create-store-modal', false)
            ->assertDontSeeText('Create your first store');

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('onboarding-StoreDetails-1.store'), $this->createStorePayload('Leaked Member Store'))
            ->assertForbidden();

        $this->assertDatabaseMissing('stores', ['name' => 'Leaked Member Store']);
        $this->assertFalse($member->fresh()->canCreateStores($store));
        $this->assertFalse($member->fresh()->canCreateStores());
    }

    public function test_member_cannot_create_a_store_even_with_leftover_create_grant(): void
    {
        $owner = $this->merchant('create-allow-owner@example.com');
        $member = $this->merchant('create-allow-member@example.com');
        $store = $this->store($owner, 'Grant Create Store', onboardingCompleted: true);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($store, $member, Store::ROLE_MEMBER);
        app(StoreMemberPermissionSync::class)->sync(
            $store,
            $member,
            StoreMemberAccess::presets()[StoreMemberAccess::PRESET_VIEW],
            StoreMemberAccess::PRESET_VIEW,
            null,
            null,
            StoreMemberAccess::STATUS_ACTIVE,
        );

        StoreMemberPermission::query()->create([
            'store_id' => $store->id,
            'user_id' => $member->id,
            'permission' => 'stores.create',
        ]);

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('store-management'))
            ->assertOk()
            ->assertDontSeeText('Create store')
            ->assertDontSeeText('Close store');

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('onboarding-StoreDetails-1.store'), $this->createStorePayload('Member Owned Store', openModal: true))
            ->assertForbidden();

        $this->assertDatabaseMissing('stores', ['name' => 'Member Owned Store']);
        $this->assertFalse($member->fresh()->canCreateStores($store));
    }

    public function test_member_who_owns_another_store_still_cannot_create_stores(): void
    {
        $owner = $this->merchant('mixed-owner@example.com');
        $member = $this->merchant('mixed-member@example.com');
        $shared = $this->store($owner, 'Shared Member Store', onboardingCompleted: true);
        $owned = $this->store($member, 'Member Owned Workspace', onboardingCompleted: true);
        $this->attach($shared, $owner, Store::ROLE_OWNER);
        $this->attach($shared, $member, Store::ROLE_MEMBER);
        $this->attach($owned, $member, Store::ROLE_OWNER);
        app(StoreMemberPermissionSync::class)->sync(
            $shared,
            $member,
            StoreMemberAccess::presets()[StoreMemberAccess::PRESET_VIEW],
            StoreMemberAccess::PRESET_VIEW,
            null,
            null,
            StoreMemberAccess::STATUS_ACTIVE,
        );

        $this->actingAs($member)
            ->withSession(['current_store_id' => $owned->id])
            ->get(route('store-management'))
            ->assertOk()
            ->assertSeeText('Member Owned Workspace')
            ->assertSeeText('Shared Member Store')
            ->assertDontSeeText('Create store');

        $this->actingAs($member)
            ->withSession(['current_store_id' => $owned->id])
            ->post(route('onboarding-StoreDetails-1.store'), $this->createStorePayload('Another Member Store', openModal: true))
            ->assertForbidden();

        $this->assertDatabaseMissing('stores', ['name' => 'Another Member Store']);
        $this->assertFalse($member->fresh()->canCreateStores($owned));
    }

    public function test_owner_can_create_an_additional_store(): void
    {
        $owner = $this->merchant('owner-second-store@example.com');
        $store = $this->store($owner, 'First Owner Store', onboardingCompleted: true);
        $this->attach($store, $owner, Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('store-management'))
            ->assertOk()
            ->assertSeeText('Create store')
            ->assertSeeText('Close store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('onboarding-StoreDetails-1.store'), $this->createStorePayload('Second Owner Store', openModal: true))
            ->assertRedirect(route('store-management'));

        $created = Store::query()->where('name', 'Second Owner Store')->first();
        $this->assertNotNull($created);
        $this->assertSame($owner->id, (int) $created->user_id);
        $this->assertDatabaseHas('store_user', [
            'store_id' => $created->id,
            'user_id' => $owner->id,
            'role' => Store::ROLE_OWNER,
        ]);
    }

    public function test_member_cannot_close_a_store(): void
    {
        $owner = $this->merchant('close-gate-owner@example.com');
        $member = $this->merchant('close-gate-member@example.com');
        $store = $this->store($owner, 'Member Close Store', onboardingCompleted: true);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($store, $member, Store::ROLE_MEMBER);
        app(StoreMemberPermissionSync::class)->sync(
            $store,
            $member,
            StoreMemberAccess::presets()[StoreMemberAccess::PRESET_FULL_OPERATIONAL],
            StoreMemberAccess::PRESET_FULL_OPERATIONAL,
            null,
            null,
            StoreMemberAccess::STATUS_ACTIVE,
        );

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('store-management'))
            ->assertOk()
            ->assertDontSeeText('Close store');

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('store.destroy', ['storeId' => $store->id]))
            ->assertForbidden();

        $this->assertDatabaseHas('stores', [
            'id' => $store->id,
            'name' => 'Member Close Store',
            'deleted_at' => null,
        ]);
    }

    public function test_invited_membership_is_hidden_from_the_store_hub(): void
    {
        $owner = $this->merchant('invite-hub-owner@example.com');
        $member = $this->merchant('invite-hub-member@example.com');
        $store = $this->store($owner, 'Pending Invite Store', onboardingCompleted: true);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $store->members()->attach($member->id, [
            'role' => Store::ROLE_MEMBER,
            'status' => StoreMemberAccess::STATUS_INVITED,
        ]);

        $this->actingAs($member)
            ->get(route('store-management'))
            ->assertOk()
            ->assertDontSeeText('Pending Invite Store')
            ->assertDontSeeText('Create store')
            ->assertSeeText('You can work in stores an owner has invited you to');
    }

    private function seedOperationalSetup(Store $store): void
    {
        Product::query()->create([
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

        Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Main warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '100 Main St',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);

        TaxSetting::query()->updateOrCreate(
            ['store_id' => $store->id],
            [
                'enabled' => true,
                'prices_include_tax' => false,
                'default_product_taxable' => true,
                'shipping_taxable' => false,
                'calculation_address' => TaxSetting::CALCULATION_ADDRESS_SHIPPING,
            ]
        );

        TaxRate::query()->create([
            'store_id' => $store->id,
            'country_code' => 'US',
            'region_code' => 'TX',
            'name' => 'TX Sales Tax',
            'rate_percent' => 8.25,
            'priority' => 1,
            'is_active' => true,
        ]);

        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'Domestic',
            'countries' => ['US'],
            'is_active' => true,
        ]);

        ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'carrier_account_id' => null,
            'name' => 'Standard',
            'code' => 'standard-'.fake()->unique()->numberBetween(1000, 9999),
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 5,
            'enabled_for_checkout' => true,
            'is_active' => true,
        ]);

        // Hub operational checklist does not include Stripe. Checkout is still blocked until Stripe is connected.
        $store->forceFill([
            'settings' => array_merge($store->settings ?? [], [
                'checkout_mode' => CheckoutMode::PLATFORM,
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
