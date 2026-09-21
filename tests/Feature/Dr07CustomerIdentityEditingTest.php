<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Settings\StoreMemberPermissionSync;
use App\Support\OrderLifecycle;
use App\Support\StoreMemberAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Dr07CustomerIdentityEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_customer_from_customers_list(): void
    {
        [$owner, $store] = $this->ownerStore();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('customers'))
            ->assertOk()
            ->assertSeeText('Add customer')
            ->assertSeeText('create a manual order');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('customers.store'), [
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'email' => 'Ada.Lovelace@Example.Test',
                'phone' => '+1 555 0100',
            ])
            ->assertRedirect();

        $customer = Customer::query()->where('store_id', $store->id)->where('email', 'ada.lovelace@example.test')->firstOrFail();
        $this->assertSame('Ada', $customer->first_name);
        $this->assertSame('Lovelace', $customer->last_name);
        $this->assertSame('Ada Lovelace', $customer->full_name);
        $this->assertSame('+1 555 0100', $customer->phone);
        $this->assertSame('active', $customer->status);
        $this->assertSame('dashboard', $customer->source);

        $this->assertDatabaseHas('security_logs', [
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'event_type' => 'customer_created',
        ]);
    }

    public function test_owner_can_export_customers_csv_from_the_list(): void
    {
        [$owner, $store] = $this->ownerStore('Export Store');
        $this->customer($store, [
            'email' => 'export.me@example.test',
            'first_name' => 'Export',
            'last_name' => 'Me',
            'full_name' => 'Export Me',
            'phone' => '+1 555 0199',
            'status' => 'active',
        ]);
        $this->customer($store, [
            'email' => 'blocked@example.test',
            'full_name' => 'Blocked Buyer',
            'status' => 'blocked',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('customers'))
            ->assertOk()
            ->assertSeeText('Export CSV');

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('customers.export', ['status' => 'active']))
            ->assertOk();

        $response->assertHeader('content-disposition');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('export.me@example.test', $csv);
        $this->assertStringContainsString('Export', $csv);
        $this->assertStringNotContainsString('blocked@example.test', $csv);

        $this->assertDatabaseHas('security_logs', [
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'event_type' => 'customers_exported',
        ]);
    }

    public function test_member_without_export_permission_cannot_export_customers(): void
    {
        [$owner, $store] = $this->ownerStore('No Export Store');
        $member = $this->merchant('viewer-no-export@example.test');
        $store->members()->attach($member->id, ['role' => Store::ROLE_MEMBER]);
        app(StoreMemberPermissionSync::class)->sync(
            $store,
            $member,
            ['customers.view'],
            StoreMemberAccess::PRESET_CUSTOM,
            null,
            null,
            StoreMemberAccess::STATUS_ACTIVE,
        );

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('customers'))
            ->assertOk()
            ->assertDontSeeText('Export CSV');

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('customers.export'))
            ->assertForbidden();
    }

    public function test_owner_can_delete_and_anonymize_a_customer(): void
    {
        [$owner, $store] = $this->ownerStore('Delete Store');
        $customer = $this->customer($store, [
            'email' => 'remove.me@example.test',
            'first_name' => 'Remove',
            'last_name' => 'Me',
            'full_name' => 'Remove Me',
            'phone' => '+1 555 0111',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('customersProfile', $customer))
            ->assertOk()
            ->assertSeeText('Delete and anonymize');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers'));

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $deleted = Customer::withTrashed()->findOrFail($customer->id);
        $this->assertSame('Deleted customer', $deleted->full_name);
        $this->assertNull($deleted->phone);
        $this->assertStringStartsWith('deleted-'.$customer->id.'@', $deleted->email);

        $this->assertDatabaseHas('security_logs', [
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'event_type' => 'customer_deleted',
        ]);
    }

    public function test_member_without_delete_permission_cannot_delete_customers(): void
    {
        [$owner, $store] = $this->ownerStore('No Delete Store');
        $member = $this->merchant('editor-no-delete@example.test');
        $customer = $this->customer($store, ['email' => 'keep.me@example.test']);
        $store->members()->attach($member->id, ['role' => Store::ROLE_MEMBER]);
        app(StoreMemberPermissionSync::class)->sync(
            $store,
            $member,
            ['customers.view', 'customers.edit'],
            StoreMemberAccess::PRESET_CUSTOM,
            null,
            null,
            StoreMemberAccess::STATUS_ACTIVE,
        );

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('customersProfile', $customer))
            ->assertOk()
            ->assertDontSeeText('Delete and anonymize');

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('customers.destroy', $customer))
            ->assertForbidden();
    }

    public function test_owner_can_edit_customer_identity_on_profile(): void
    {
        [$owner, $store] = $this->ownerStore();
        $customer = $this->customer($store, [
            'first_name' => 'Old',
            'last_name' => 'Name',
            'full_name' => 'Old Name',
            'email' => 'old@example.test',
            'phone' => '111',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('customersProfile', $customer))
            ->assertOk()
            ->assertSeeText('Edit contact details')
            ->assertSeeText($customer->email)
            ->assertDontSeeText('Save contact details');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('customersProfile', ['customer' => $customer, 'edit' => 'contact']))
            ->assertOk()
            ->assertSeeText('Save contact details')
            ->assertSee('name="first_name"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="phone"', false);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('customers.identity.update', $customer), [
                'first_name' => 'New',
                'last_name' => 'Identity',
                'email' => 'new.identity@example.test',
                'phone' => '+1 555 9999',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $customer->refresh();
        $this->assertSame('New', $customer->first_name);
        $this->assertSame('Identity', $customer->last_name);
        $this->assertSame('New Identity', $customer->full_name);
        $this->assertSame('new.identity@example.test', $customer->email);
        $this->assertSame('+1 555 9999', $customer->phone);

        $this->assertDatabaseHas('security_logs', [
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'event_type' => 'customer_identity_updated',
        ]);
    }

    public function test_duplicate_email_is_rejected_per_store_but_allowed_across_stores(): void
    {
        [$ownerA, $storeA] = $this->ownerStore('Store A');
        [$ownerB, $storeB] = $this->ownerStore('Store B');
        $existing = $this->customer($storeA, ['email' => 'shared@example.test']);
        $otherStoreCustomer = $this->customer($storeB, ['email' => 'shared@example.test']);

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('customers.store'), [
                'first_name' => 'Dup',
                'email' => 'shared@example.test',
            ])
            ->assertSessionHasErrors('email');

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->patch(route('customers.identity.update', $existing), [
                'first_name' => 'Still',
                'last_name' => 'Unique',
                'email' => 'shared@example.test',
                'phone' => null,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $foreign = $this->customer($storeA, ['email' => 'other@example.test']);
        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->patch(route('customers.identity.update', $foreign), [
                'first_name' => 'Taken',
                'email' => 'shared@example.test',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame('shared@example.test', $otherStoreCustomer->fresh()->email);
        $this->assertSame(1, Customer::query()->where('store_id', $storeA->id)->where('email', 'shared@example.test')->count());
    }

    public function test_identity_edit_does_not_rewrite_historical_order_customer_email_snapshot_fields(): void
    {
        [$owner, $store] = $this->ownerStore();
        $customer = $this->customer($store, [
            'email' => 'before@example.test',
            'full_name' => 'Before Name',
            'phone' => '111',
        ]);

        $order = Order::query()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => '#DR07-1001',
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PENDING,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'customer_email' => 'before@example.test',
            'customer_phone' => '111',
            'subtotal' => 20,
            'total' => 20,
            'grand_total' => 20,
            'currency_code' => 'USD',
            'order_source' => 'manual',
            'channel' => 'dashboard',
            'item_count' => 0,
            'total_quantity' => 0,
            'placed_at' => now()->subDay(),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('customers.identity.update', $customer), [
                'first_name' => 'After',
                'last_name' => 'Name',
                'email' => 'after@example.test',
                'phone' => '222',
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('before@example.test', $order->customer_email);
        $this->assertSame('111', $order->customer_phone);
        $this->assertSame($customer->id, $order->customer_id);
    }

    public function test_staff_cannot_create_or_edit_customer_identity_and_cross_store_is_404(): void
    {
        [$owner, $store] = $this->ownerStore('Home Store');
        $staff = $this->merchant('staff-dr07@example.test');
        $store->members()->attach($staff->id, ['role' => Store::ROLE_STAFF]);
        $customer = $this->customer($store);

        [$otherOwner, $otherStore] = $this->ownerStore('Other Store');
        $foreign = $this->customer($otherStore, ['email' => 'foreign@example.test']);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('customers'))
            ->assertOk()
            ->assertDontSeeText('Add customer')
            ->assertDontSeeText('Save customer');

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('customers.store'), [
                'first_name' => 'Nope',
                'email' => 'staff-create@example.test',
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('customers.identity.update', $customer), [
                'first_name' => 'Nope',
                'email' => 'staff-edit@example.test',
            ])
            ->assertForbidden();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('customers.identity.update', $foreign), [
                'first_name' => 'Hijack',
                'email' => 'hijack@example.test',
            ])
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name = 'DR07 Store'): array
    {
        $owner = $this->merchant();
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        return [$owner, $store];
    }

    private function merchant(?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->unverified()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'role_id' => $role->id,
            'password' => Hash::make('password'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function customer(Store $store, array $overrides = []): Customer
    {
        return Customer::query()->create(array_merge([
            'store_id' => $store->id,
            'email' => fake()->unique()->safeEmail(),
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'full_name' => 'Test Customer',
            'phone' => null,
            'status' => 'active',
            'source' => 'test',
        ], $overrides));
    }
}
