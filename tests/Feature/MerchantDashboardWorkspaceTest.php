<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use App\Models\User;
use App\Services\ConnectedSiteService;
use App\Support\OrderLifecycle;
use App\Support\ReturnLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MerchantDashboardWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-11 15:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_user_without_a_store_sees_create_store_state(): void
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Create a store to see your dashboard')
            ->assertSeeText('Go to store management')
            ->assertDontSeeText('Performance')
            ->assertDontSeeText('Northstar Goods');
    }

    public function test_incomplete_setup_shows_readiness_card_with_real_next_step(): void
    {
        [$owner, $store] = $this->ownerStore('Incomplete Dash Store');

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Store readiness')
            ->assertSeeText('4 steps left to start selling')
            ->assertSeeText('Create a delivery area and enable an option customers can select.')
            ->assertSeeText('Delivery setup')
            ->assertSeeText('Set up delivery')
            ->assertSeeText('Payment setup')
            ->assertSeeText('Connect payments')
            ->assertSeeText('Checkout tax')
            ->assertSeeText('Configure tax')
            ->assertSeeText('Website connection')
            ->assertSeeText('Connect website')
            ->assertSeeText('View full checklist')
            ->assertSeeText('Location ready')
            ->assertDontSeeText('Store operational')
            ->assertDontSeeText('Finish setup to start selling')
            ->assertDontSeeText('Your store is ready to operate')
            ->assertDontSeeText('Mark as complete')
            ->assertDontSeeText('Reset preview')
            ->getContent();

        $this->assertSame(1, substr_count($html, '4 steps left to start selling'));
        $this->assertStringContainsString('data-setup-card', $html);
        $this->assertStringContainsString('id="setupChecklist" data-setup-checklist hidden', $html);
        $this->assertMatchesRegularExpression(
            '/class="mdash-setup-list".*Store location.*Delivery setup.*Payment setup.*Checkout tax.*Website connection/s',
            $html
        );
        $this->assertStringContainsString(route('settings.taxes.index', [], false), $html);
        $this->assertStringContainsString(route('settings.locations.index', [], false), $html);
        $this->assertStringContainsString(route('shippingAutomation', [], false), $html);
        $this->assertStringContainsString(route('settings.payments.index', [], false), $html);
        $this->assertStringContainsString(route('developer-storefront.settings', [], false), $html);
        $this->assertStringNotContainsString('settings-checklist', $html);
        $this->assertStringNotContainsString('VERDANT', $html);
        $this->assertStringNotContainsString('/settings/shipping-automation', $html);
    }

    public function test_one_remaining_setup_step_uses_singular_headline(): void
    {
        [$owner, $store] = $this->ownerStore('Almost Ready Dash Store');
        $this->completeTax($store);
        $this->completeDelivery($store);
        $this->connectReadyStripeForCheckout($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Store readiness')
            ->assertSeeText('One step left to start selling')
            ->assertSeeText('Website connection')
            ->assertSeeText('Connect website')
            ->assertSeeText('Location ready')
            ->assertSeeText('Tax ready')
            ->assertSeeText('Delivery ready')
            ->assertSeeText('Payments ready')
            ->assertDontSeeText('4 steps left to start selling')
            ->assertDontSeeText('Store operational');
    }

    public function test_completed_setup_shows_operational_status_without_checklist(): void
    {
        [$owner, $store] = $this->ownerStore('Ready Dash Store');
        $this->completeSetup($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Store operational')
            ->assertSeeText('Performance')
            ->assertSeeText('Store systems')
            ->assertSeeText('Customize dashboard')
            ->assertSeeText('Choose which operational panels are visible.')
            ->assertDontSeeText('Finish setup to start selling')
            ->assertDontSeeText('Store readiness')
            ->assertDontSeeText('Your store is ready to operate');
    }

    public function test_default_range_is_thirty_days_and_invalid_range_falls_back(): void
    {
        [$owner, $store] = $this->ownerStore('Range Dash Store');
        $this->completeSetup($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('dashboard', ['range' => '30']).'"', false)
            ->assertSeeText('Compared with the previous 30 days');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard', ['range' => '99']))
            ->assertOk()
            ->assertSeeText('Compared with the previous 30 days')
            ->assertSeeText('30 days');
    }

    public function test_date_ranges_include_current_window_and_exclude_older_orders(): void
    {
        [$owner, $store] = $this->ownerStore('Window Dash Store');
        $this->completeSetup($store);

        $this->order($store, [
            'order_number' => '#TODAY-1',
            'grand_total' => 40,
            'placed_at' => now(),
        ]);
        $this->order($store, [
            'order_number' => '#WEEK-1',
            'grand_total' => 25,
            'placed_at' => now()->subDays(3),
        ]);
        $this->order($store, [
            'order_number' => '#OLD-1',
            'grand_total' => 999,
            'placed_at' => now()->subDays(40),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard', ['range' => 'today']))
            ->assertOk()
            ->assertSee('data-metric="revenue" data-metric-display="$40.00"', false)
            ->assertDontSee('data-metric="revenue" data-metric-display="$999.00"', false)
            ->assertSeeText('Yesterday at this time had no sales');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard', ['range' => '7']))
            ->assertOk()
            ->assertSee('data-metric="revenue" data-metric-display="$65.00"', false)
            ->assertDontSee('data-metric="revenue" data-metric-display="$999.00"', false)
            ->assertSeeText('to compare');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard', ['range' => '30']))
            ->assertOk()
            ->assertSee('data-metric="revenue" data-metric-display="$65.00"', false)
            ->assertDontSee('data-metric="revenue" data-metric-display="$999.00"', false);
    }

    public function test_revenue_excludes_cancelled_orders_and_uses_store_currency(): void
    {
        [$owner, $store] = $this->ownerStore('Revenue Dash Store');
        $this->completeSetup($store);

        $this->order($store, [
            'order_number' => '#REV-OK',
            'grand_total' => 80,
            'placed_at' => now()->subDays(2),
        ]);
        $this->order($store, [
            'order_number' => '#REV-CANCEL',
            'status' => OrderLifecycle::ORDER_CANCELLED,
            'grand_total' => 500,
            'placed_at' => now()->subDays(2),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-metric="revenue" data-metric-display="$80.00"', false)
            ->assertDontSee('data-metric="revenue" data-metric-display="$500.00"', false)
            ->assertDontSee('data-metric="revenue" data-metric-display="$580.00"', false)
            ->assertDontSeeText('Profit')
            ->assertDontSeeText('Net sales');
    }

    public function test_previous_period_percentage_is_calculated_from_equal_windows(): void
    {
        [$owner, $store] = $this->ownerStore('Change Dash Store');
        $this->completeSetup($store);

        $this->order($store, [
            'order_number' => '#CUR-100',
            'grand_total' => 100,
            'placed_at' => now()->subDays(2),
        ]);
        $this->order($store, [
            'order_number' => '#PREV-50',
            'grand_total' => 50,
            'placed_at' => now()->subDays(32),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard', ['range' => '30']))
            ->assertOk()
            ->assertSeeText('$100.00')
            ->assertSeeText('↑ 100.0%')
            ->assertSeeText('Compared with the previous 30 days');
    }

    public function test_seven_day_percentage_uses_the_previous_seven_days(): void
    {
        [$owner, $store] = $this->ownerStore('Week Change Dash Store');
        $this->completeSetup($store);

        $this->order($store, [
            'order_number' => '#WEEK-CUR',
            'grand_total' => 80,
            'placed_at' => now()->subDays(2),
        ]);
        $this->order($store, [
            'order_number' => '#WEEK-PREV',
            'grand_total' => 40,
            'placed_at' => now()->subDays(10),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard', ['range' => '7']))
            ->assertOk()
            ->assertSee('data-metric="revenue" data-metric-display="$80.00"', false)
            ->assertSeeText('↑ 100.0%')
            ->assertSeeText('Compared with the previous 7 days');
    }

    public function test_zero_previous_period_does_not_invent_a_percentage(): void
    {
        [$owner, $store] = $this->ownerStore('Percent Dash Store');
        $this->completeSetup($store);
        $this->order($store, [
            'order_number' => '#PCT-1',
            'grand_total' => 14.50,
            'placed_at' => now()->subDays(2),
        ]);

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard', ['range' => '30']))
            ->assertOk()
            ->assertSeeText('$14.50')
            ->assertSeeText('to compare')
            ->assertDontSeeText('vs $0.00')
            ->assertDontSeeText('vs 0')
            ->assertDontSeeText('No prior period')
            ->assertDontSeeText('↑ 100.0%')
            ->getContent();

        $this->assertStringNotContainsString('>New</em>', $html);
        $this->assertStringContainsString('id="revenueChart"', $html);
        $this->assertStringContainsString('merchant-dashboard-chart-data', $html);
        $this->assertStringNotContainsString('resources/js/dashboard-workspace.js', $html);
        $this->assertMatchesRegularExpression('/No sales in [A-Z][a-z]{2} \d{1,2} – [A-Z][a-z]{2} \d{1,2} to compare/', $html);
    }

    public function test_turbo_navigation_keeps_dashboard_chart_and_clears_list_loading_locks(): void
    {
        $appJs = (string) file_get_contents(base_path('resources/js/app.js'));
        $dashboardJs = (string) file_get_contents(base_path('resources/js/dashboard-workspace.js'));

        $this->assertStringContainsString("import './dashboard-workspace.js'", $appJs);
        $this->assertStringContainsString('clearMerchantTurboLoading', $appJs);
        $this->assertStringContainsString("frame !== '_top'", $appJs);
        $this->assertStringContainsString('turbo:load', $appJs);
        $this->assertStringContainsString('turbo:frame-load', $appJs);
        $this->assertStringContainsString('#customers-panel, #orders-panel', $appJs);
        $this->assertStringNotContainsString("getElementById('revenueChart')", $dashboardJs);
        $this->assertStringContainsString("document.addEventListener('turbo:load', boot)", $dashboardJs);
        $this->assertStringContainsString("document.addEventListener('turbo:render', boot)", $dashboardJs);
    }

    public function test_attention_counts_use_real_store_records(): void
    {
        [$owner, $store] = $this->ownerStore('Attention Dash Store');
        $this->completeSetup($store);

        $this->order($store, [
            'order_number' => '#FUL-1',
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
        ]);
        $this->order($store, [
            'order_number' => '#FUL-SKIP',
            'payment_status' => OrderLifecycle::PAYMENT_PENDING,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
        ]);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Low Stock Mug',
            'slug' => 'low-stock-mug-'.fake()->unique()->numberBetween(1000, 9999),
            'base_price' => 12,
            'sku' => 'LSM-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => 'LSM-1-V',
            'price' => 12,
            'stock' => 2,
            'stock_alert' => 5,
        ]);

        $customer = Customer::query()->create([
            'store_id' => $store->id,
            'email' => 'returner@example.test',
            'full_name' => 'Return Customer',
            'status' => 'active',
        ]);
        $returnOrder = $this->order($store, [
            'order_number' => '#RMA-1',
            'customer_id' => $customer->id,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_FULFILLED,
        ]);
        OrderReturn::query()->create([
            'store_id' => $store->id,
            'order_id' => $returnOrder->id,
            'customer_id' => $customer->id,
            'return_number' => 'RMA-DASH-1',
            'status' => ReturnLifecycle::STATUS_REQUESTED,
            'source' => ReturnLifecycle::SOURCE_MERCHANT,
            'requested_at' => now(),
        ]);

        $store->locations()->where('is_active', true)->update([
            'address_line1' => null,
            'city' => null,
            'country_code' => null,
        ]);

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Ready to fulfill')
            ->assertSeeText('Low stock')
            ->assertSeeText('Awaiting review')
            ->assertSeeText('Address needs attention')
            ->assertSeeText('Low Stock Mug')
            ->assertSeeText('2 left')
            ->getContent();

        $this->assertStringContainsString('mdash-stock-track', $html);
        $this->assertStringContainsString('mdash-stock-bar', $html);
        $this->assertStringContainsString('width: 40%', $html);
        $this->assertMatchesRegularExpression('/<b[^>]*>1<\/b>\s+order/', $html);
        $this->assertMatchesRegularExpression('/<b[^>]*>1<\/b>\s+product/', $html);
        $this->assertMatchesRegularExpression('/<b[^>]*>1<\/b>\s+return/', $html);
        $this->assertStringContainsString(route('products', ['stock' => 'low']), $html);
        $this->assertStringContainsString(route('orders.create'), $html);
        $this->assertStringContainsString(route('products.create'), $html);
    }

    public function test_store_isolation_hides_other_store_orders_and_metrics(): void
    {
        [$owner, $store] = $this->ownerStore('Isolation A Store');
        $this->completeSetup($store);
        $this->order($store, [
            'order_number' => '#OWN-100',
            'grand_total' => 33,
        ]);

        $otherOwner = $this->merchant('isolation-b@example.test');
        $otherStore = $this->storeFor($otherOwner, 'Isolation B Store');
        $this->attach($otherStore, $otherOwner, Store::ROLE_OWNER);
        $this->completeSetup($otherStore);
        $this->order($otherStore, [
            'order_number' => '#FOREIGN-9999',
            'grand_total' => 880,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('#OWN-100')
            ->assertSeeText('$33.00')
            ->assertDontSeeText('#FOREIGN-9999')
            ->assertDontSeeText('$880.00')
            ->assertDontSeeText('Isolation B Store');
    }

    public function test_staff_cannot_see_create_order_or_add_product_actions(): void
    {
        [$owner, $store] = $this->ownerStore('Staff Dash Store');
        $this->completeSetup($store);
        $staff = $this->merchant('staff-dash@example.test');
        $this->attach($store, $staff, Store::ROLE_STAFF);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSeeText('Create order')
            ->assertDontSeeText('Add product')
            ->assertSeeText('Performance');
    }

    public function test_empty_states_render_for_orders_revenue_stock_returns_and_integrations(): void
    {
        [$owner, $store] = $this->ownerStore('Empty Dash Store');
        $this->completeSetup($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('No orders yet. When sales come in, they will show up here.')
            ->assertSeeText('No revenue in this period')
            ->assertSeeText('None waiting to fulfill')
            ->assertSeeText('No low-stock items')
            ->assertSeeText('No returns waiting')
            ->assertSeeText('No delivery issues')
            ->assertSeeText('No items are at their low-stock alert.')
            ->assertSeeText('Website connected')
            ->assertSeeText('FedEx not connected');
    }

    public function test_store_timezone_drives_today_range_and_greeting(): void
    {
        [$owner, $store] = $this->ownerStore('Timezone Dash Store');
        $store->update(['timezone' => 'Asia/Karachi']);
        $this->completeSetup($store);

        $this->order($store, [
            'order_number' => '#TZ-TODAY',
            'grand_total' => 18,
            'placed_at' => Carbon::parse('2026-09-11 08:00:00', 'UTC'),
        ]);
        $this->order($store, [
            'order_number' => '#TZ-YDAY',
            'grand_total' => 71,
            'placed_at' => Carbon::parse('2026-09-10 10:00:00', 'UTC'),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard', ['range' => 'today']))
            ->assertOk()
            ->assertSeeText('Good evening')
            ->assertSeeText('Here’s what needs your attention!')
            ->assertSee('data-metric="revenue" data-metric-display="$18.00"', false)
            ->assertDontSee('data-metric="revenue" data-metric-display="$71.00"', false)
            ->assertDontSee('data-metric="revenue" data-metric-display="$89.00"', false)
            ->assertDontSeeText('Good morning');
    }

    public function test_greeting_matches_store_local_time_of_day(): void
    {
        [$owner, $store] = $this->ownerStore('Greeting Dash Store');
        $store->update(['timezone' => 'Asia/Karachi']);
        $this->completeSetup($store);

        $cases = [
            ['2026-09-12 04:00:00', 'Good morning', 'Here’s what needs your attention Today!'],
            ['2026-09-11 08:00:00', 'Good afternoon', 'Here’s what needs your attention !'],
            ['2026-09-11 15:00:00', 'Good evening', 'Here’s what needs your attention!'],
            ['2026-09-11 21:30:00', 'Hi, '.$owner->name.'!', 'Here’s what needs your attention right now!'],
        ];

        foreach ($cases as [$utc, $heading, $lead]) {
            Carbon::setTestNow(Carbon::parse($utc, 'UTC'));

            $this->actingAs($owner)
                ->withSession(['current_store_id' => $store->id])
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSeeText($heading)
                ->assertSeeText($lead);
        }
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name): array
    {
        $owner = $this->merchant(str($name)->slug().'@example.test');
        $store = $this->storeFor($owner, $name);
        $this->attach($store, $owner, Store::ROLE_OWNER);

        return [$owner, $store];
    }

    private function merchant(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);
    }

    private function storeFor(User $owner, string $name): Store
    {
        return Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => '100 Main St',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
    }

    private function attach(Store $store, User $user, string $role): void
    {
        $store->members()->syncWithoutDetaching([
            $user->id => ['role' => $role],
        ]);
    }

    private function completeSetup(Store $store): void
    {
        $this->completeTax($store);
        $this->completeDelivery($store);
        $this->connectReadyStripeForCheckout($store);
        $this->completeWebsite($store);
    }

    private function completeWebsite(Store $store): void
    {
        app(ConnectedSiteService::class)->issuePrimaryCredential($store);
        $store->forceFill([
            'developer_storefront_last_seen_at' => now(),
        ])->save();
    }

    private function completeTax(Store $store): void
    {
        $store->locations()->where('is_active', true)->update([
            'address_line1' => '100 Main St',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'country_code' => 'US',
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
    }

    private function completeDelivery(Store $store): void
    {
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
    }

    private function order(Store $store, array $overrides = []): Order
    {
        $customerId = $overrides['customer_id'] ?? null;
        unset($overrides['customer_id']);

        if ($customerId === null) {
            $customer = Customer::query()->create([
                'store_id' => $store->id,
                'email' => fake()->unique()->safeEmail(),
                'full_name' => 'Dash Customer',
                'status' => 'guest',
            ]);
            $customerId = $customer->id;
            $email = $customer->email;
        } else {
            $email = Customer::query()->whereKey($customerId)->value('email');
        }

        return Order::query()->create(array_merge([
            'store_id' => $store->id,
            'customer_id' => $customerId,
            'order_number' => '#'.fake()->unique()->numberBetween(3000, 9999),
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'customer_email' => $email,
            'billing_same_as_shipping' => true,
            'subtotal' => 25,
            'total' => 25,
            'grand_total' => 25,
            'currency_code' => $store->currency ?: 'USD',
            'order_source' => 'manual',
            'channel' => 'dashboard',
            'item_count' => 1,
            'total_quantity' => 1,
            'placed_at' => now(),
        ], $overrides));
    }
}
