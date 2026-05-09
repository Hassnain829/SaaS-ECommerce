<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerTag;
use App\Models\Order;
use App\Models\Role;
use App\Models\SecurityLog;
use App\Models\Store;
use App\Models\User;
use App\Support\OrderLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase4CustomerCrmTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_profile_crm_actions_are_dynamic_and_audited(): void
    {
        $owner = $this->merchant('owner@example.com');
        $store = $this->store($owner, 'CRM Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $customer = $this->customer($store);
        $order = $this->order($store, $customer, 75);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('customersProfile', $customer))
            ->assertOk()
            ->assertSeeText('Customer profile')
            ->assertSeeText($customer->email)
            ->assertSeeText(strtoupper($order->order_number))
            ->assertDontSeeText('Preferred Categories')
            ->assertDontSeeText('AUTUMN23')
            ->assertDontSeeText('Support Ticket');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('customers.notes.store', $customer), ['body' => 'Prefers delivery after noon.'])
            ->assertRedirect();
        $this->assertDatabaseHas('customer_notes', [
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'body' => 'Prefers delivery after noon.',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('customers.tags.store', $customer), ['name' => 'VIP'])
            ->assertRedirect();
        $tag = CustomerTag::query()->where('store_id', $store->id)->where('slug', 'vip')->firstOrFail();
        $this->assertTrue($customer->fresh()->tags()->whereKey($tag->id)->exists());

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('customers.addresses.store', $customer), [
                'type' => 'shipping',
                'name' => 'CRM Buyer',
                'address_line1' => '99 CRM Street',
                'city' => 'Lahore',
                'state' => 'Punjab',
                'postal_code' => '54000',
                'country' => 'Pakistan',
                'is_default' => '1',
            ])
            ->assertRedirect();

        $address = CustomerAddress::query()->where('customer_id', $customer->id)->where('address_line1', '99 CRM Street')->firstOrFail();
        $this->assertTrue((bool) $address->is_default);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('customers.status.update', $customer), [
                'status' => 'blocked',
                'blocked_reason' => 'Fraud review.',
            ])
            ->assertRedirect();
        $this->assertSame('blocked', $customer->fresh()->status);
        $this->assertNotNull($customer->fresh()->blocked_at);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('customers.status.update', $customer), ['status' => 'active'])
            ->assertRedirect();
        $this->assertSame('active', $customer->fresh()->status);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('customers.marketing.update', $customer), [
                'marketing_consent' => '1',
                'marketing_consent_source' => 'phone call',
            ])
            ->assertRedirect();
        $this->assertTrue((bool) $customer->fresh()->marketing_consent);
        $this->assertSame('phone call', $customer->fresh()->marketing_consent_source);

        foreach ([
            'customer_note_added',
            'customer_tags_updated',
            'customer_address_changed',
            'customer_blocked',
            'customer_unblocked',
            'marketing_consent_updated',
        ] as $eventType) {
            $this->assertDatabaseHas('security_logs', [
                'store_id' => $store->id,
                'user_id' => $owner->id,
                'event_type' => $eventType,
                'severity' => SecurityLog::SEVERITY_INFO,
            ]);
        }
    }

    public function test_customer_metrics_recalculate_from_real_orders(): void
    {
        $owner = $this->merchant('owner@example.com');
        $store = $this->store($owner, 'Metric Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $customer = $this->customer($store, [
            'total_orders' => 0,
            'total_spent' => 0,
            'average_order_value' => 0,
        ]);
        $this->order($store, $customer, 40);
        $this->order($store, $customer, 60);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('customersProfile', $customer))
            ->assertOk()
            ->assertSeeText('USD 100.00');

        $customer->refresh();
        $this->assertSame(2, $customer->total_orders);
        $this->assertSame('100.00', (string) $customer->total_spent);
        $this->assertSame('50.00', (string) $customer->average_order_value);
    }

    public function test_store_and_staff_customer_permissions_are_enforced(): void
    {
        $owner = $this->merchant('owner@example.com');
        $staff = $this->merchant('staff@example.com');
        $otherOwner = $this->merchant('other@example.com');
        $store = $this->store($owner, 'Customer Store A');
        $otherStore = $this->store($otherOwner, 'Customer Store B');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($store, $staff, Store::ROLE_STAFF);
        $this->attach($otherStore, $otherOwner, Store::ROLE_OWNER);
        $customer = $this->customer($store);
        $otherCustomer = $this->customer($otherStore);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('customersProfile', $otherCustomer))
            ->assertNotFound();

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('customersProfile', $customer))
            ->assertOk();

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('customers.notes.store', $customer), ['body' => 'Staff note'])
            ->assertForbidden();
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

    private function customer(Store $store, array $overrides = []): Customer
    {
        return Customer::query()->create(array_merge([
            'store_id' => $store->id,
            'email' => fake()->unique()->safeEmail(),
            'full_name' => 'CRM Buyer',
            'phone' => '+923001234567',
            'status' => 'active',
        ], $overrides));
    }

    private function order(Store $store, Customer $customer, int $total): Order
    {
        return Order::query()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => '#'.fake()->unique()->numberBetween(5000, 9999),
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'customer_email' => $customer->email,
            'subtotal' => $total,
            'total' => $total,
            'grand_total' => $total,
            'currency_code' => 'USD',
            'order_source' => 'developer_storefront',
            'channel' => 'developer_test_react',
            'item_count' => 1,
            'total_quantity' => 1,
            'placed_at' => now(),
        ]);
    }
}
