<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Support\OrderLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CurrencyDisplayBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_list_uses_order_currency_not_store_currency(): void
    {
        [$owner, $store] = $this->ownerStore('Orders Currency Store');
        $store->update(['currency' => 'PKR']);

        Order::query()->create([
            'store_id' => $store->id,
            'order_number' => '#CUR-1049',
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'subtotal' => 43.23,
            'total' => 43.23,
            'grand_total' => 43.23,
            'currency_code' => 'USD',
            'order_source' => 'platform_checkout',
            'channel' => 'wordpress',
            'item_count' => 1,
            'total_quantity' => 1,
            'placed_at' => now(),
        ]);

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orders'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('USD 43.23', $html);
        $this->assertStringNotContainsString('PKR43.23', $html);
        $this->assertStringNotContainsString('PKR 43.23', $html);
    }

    public function test_store_management_edit_payload_requires_conversion_and_revenue_converts_for_display(): void
    {
        Http::fake([
            'open.er-api.com/*' => Http::response([
                'result' => 'success',
                'base_code' => 'USD',
                'rates' => ['PKR' => 280],
            ], 200),
        ]);

        [$owner, $store] = $this->ownerStore('Hub Convert Store');
        $store->update(['currency' => 'PKR']);

        Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Hub Product',
            'slug' => 'hub-product-'.fake()->unique()->numberBetween(1000, 9999),
            'status' => true,
            'product_type' => 'simple',
            'base_price' => 10,
        ]);

        Order::query()->create([
            'store_id' => $store->id,
            'order_number' => '#HUB-1',
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'subtotal' => 10,
            'total' => 10,
            'grand_total' => 10,
            'currency_code' => 'USD',
            'order_source' => 'manual',
            'channel' => 'dashboard',
            'item_count' => 1,
            'total_quantity' => 1,
            'placed_at' => now()->subDay(),
        ]);

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('store-management'))
            ->assertOk()
            ->assertSee('requires_catalog_conversion":true', false)
            ->assertSee('2,800.00 PKR', false)
            ->getContent();

        $this->assertStringContainsString('Shown in current store currency', $html);
    }

    public function test_store_management_currency_change_converts_catalog_with_confirmation(): void
    {
        Http::fake([
            'open.er-api.com/*' => Http::response([
                'result' => 'success',
                'base_code' => 'USD',
                'rates' => ['PKR' => 2],
            ], 200),
        ]);

        [$owner, $store] = $this->ownerStore('Store Page Convert');
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Page Convert Product',
            'slug' => 'page-convert-'.fake()->unique()->numberBetween(1000, 9999),
            'status' => true,
            'product_type' => 'simple',
            'base_price' => 50,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('store-management'))
            ->put(route('store.update', ['storeId' => $store->id]), [
                'name' => $store->name,
                'primary_market' => 'Global Market',
                'address' => $store->address,
                'currency' => 'PKR',
                'timezone' => 'UTC',
                'category' => 'physical',
                'confirm_currency_conversion' => '1',
                'redirect_to' => 'store-management',
            ])
            ->assertRedirect(route('store-management'));

        $this->assertSame('PKR', $store->fresh()->currency);
        $this->assertSame('100.00', number_format((float) $product->fresh()->base_price, 2, '.', ''));
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name = 'Currency Display Store'): array
    {
        $owner = $this->merchant();
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Addr',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => ['primary_market' => 'Global Market'],
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
}
