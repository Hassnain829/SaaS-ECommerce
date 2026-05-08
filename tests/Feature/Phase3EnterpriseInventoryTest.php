<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryLevel;
use App\Models\InventoryReservation;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\InventoryAdjustmentService;
use App\Services\Inventory\InventoryBackfillService;
use App\Services\Inventory\InventoryReservationService;
use App\Services\Inventory\InventorySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase3EnterpriseInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_gets_default_location_and_owner_can_manage_locations(): void
    {
        $owner = $this->merchantUser();
        $store = $this->storeFor($owner, 'Inventory Location Store');

        $this->assertDatabaseHas('locations', [
            'store_id' => $store->id,
            'name' => 'Main location',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.locations.index'))
            ->assertOk()
            ->assertSee('Inventory locations', false)
            ->assertSee('Main location', false);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.locations.store'), [
                'name' => 'Back room',
                'type' => Location::TYPE_STORE,
                'address_line1' => 'Shop floor',
                'city' => 'Karachi',
                'country_code' => 'PK',
            ])
            ->assertRedirect();

        $newLocation = Location::query()->where('store_id', $store->id)->where('name', 'Back room')->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.locations.make-default', $newLocation))
            ->assertRedirect();

        $this->assertTrue((bool) $newLocation->fresh()->is_default);
        $this->assertSame(1, Location::query()->where('store_id', $store->id)->where('is_default', true)->count());

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('settings.locations.update', $newLocation), [
                'name' => 'Back room north',
                'type' => Location::TYPE_STORE,
                'address_line1' => 'Shop floor',
                'city' => 'Lahore',
                'country_code' => 'PK',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('locations', [
            'id' => $newLocation->id,
            'name' => 'Back room north',
            'city' => 'Lahore',
        ]);

        $oldDefault = $store->locations()->where('name', 'Main location')->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('settings.locations.deactivate', $oldDefault))
            ->assertRedirect();

        $this->assertFalse((bool) $oldDefault->fresh()->is_active);
    }

    public function test_staff_cannot_manage_locations_and_cross_store_location_is_hidden(): void
    {
        $owner = $this->merchantUser('owner-location@example.test');
        $staff = $this->merchantUser('staff-location@example.test');
        $store = $this->storeFor($owner, 'Staff Location Store');
        $other = $this->storeFor($owner, 'Other Location Store');
        $store->members()->syncWithoutDetaching([$staff->id => ['role' => Store::ROLE_STAFF]]);
        $otherLocation = $other->locations()->firstOrFail();

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.locations.store'), [
                'name' => 'Blocked',
                'type' => Location::TYPE_WAREHOUSE,
            ])
            ->assertForbidden();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.locations.make-default', $otherLocation))
            ->assertNotFound();
    }

    public function test_backfill_creates_inventory_items_levels_and_is_idempotent(): void
    {
        $owner = $this->merchantUser();
        $store = $this->storeFor($owner, 'Backfill Store');
        [, $variant] = $this->productFor($store, 'Backfill Product', 12);

        $first = app(InventoryBackfillService::class)->backfill();
        $second = app(InventoryBackfillService::class)->backfill();

        $this->assertSame(1, $first['items_created']);
        $this->assertSame(1, $first['levels_created']);
        $this->assertSame(0, $second['items_created']);
        $this->assertSame(0, $second['levels_created']);

        $item = InventoryItem::query()->where('variant_id', $variant->id)->firstOrFail();
        $level = InventoryLevel::query()->where('inventory_item_id', $item->id)->firstOrFail();

        $this->assertSame((int) $store->id, (int) $item->store_id);
        $this->assertSame(12, (int) $level->available);
        $this->assertSame(12, (int) $variant->fresh()->stock);
    }

    public function test_backfill_can_be_limited_to_one_store(): void
    {
        $owner = $this->merchantUser();
        $store = $this->storeFor($owner, 'Scoped Backfill Store');
        $otherStore = $this->storeFor($owner, 'Other Scoped Backfill Store');
        [, $variant] = $this->productFor($store, 'Scoped Product', 6);
        [, $otherVariant] = $this->productFor($otherStore, 'Other Scoped Product', 9);

        $result = app(InventoryBackfillService::class)->backfill($store->id);

        $this->assertSame(1, $result['items_created']);
        $this->assertDatabaseHas('inventory_items', [
            'store_id' => $store->id,
            'variant_id' => $variant->id,
        ]);
        $this->assertDatabaseMissing('inventory_items', [
            'store_id' => $otherStore->id,
            'variant_id' => $otherVariant->id,
        ]);
    }

    public function test_adjustment_updates_level_cache_and_movement_without_negative_stock(): void
    {
        $owner = $this->merchantUser();
        $store = $this->storeFor($owner, 'Adjustment Store');
        [, $variant] = $this->productFor($store, 'Adjust Product', 7);
        $item = app(InventorySyncService::class)->ensureInventoryItemForVariant($variant);
        $location = $store->defaultLocation()->firstOrFail();

        app(InventoryAdjustmentService::class)->adjustAvailable(
            $item,
            $location,
            5,
            'Cycle count correction',
            $owner,
            ['movement_type' => StockMovement::TYPE_MANUAL_ADJUSTMENT, 'source' => 'test']
        );

        $this->assertSame(12, (int) $variant->fresh()->stock);
        $this->assertDatabaseHas('inventory_levels', [
            'inventory_item_id' => $item->id,
            'location_id' => $location->id,
            'available' => 12,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'store_id' => $store->id,
            'variant_id' => $variant->id,
            'location_id' => $location->id,
            'inventory_item_id' => $item->id,
            'movement_type' => StockMovement::TYPE_MANUAL_ADJUSTMENT,
            'quantity_change' => 5,
            'new_stock' => 12,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(InventoryAdjustmentService::class)->adjustAvailable(
            $item,
            $location,
            -50,
            'Invalid count',
            $owner
        );
    }

    public function test_reservation_lifecycle_prevents_overselling_and_syncs_cache(): void
    {
        $owner = $this->merchantUser();
        $store = $this->storeFor($owner, 'Reservation Store');
        [, $variant] = $this->productFor($store, 'Reserved Product', 5);
        $item = app(InventorySyncService::class)->ensureInventoryItemForVariant($variant);

        $reservation = app(InventoryReservationService::class)->reserve($item, 3, 'checkout', 'abc-1');

        $this->assertSame(InventoryReservation::STATUS_ACTIVE, $reservation->status);
        $this->assertSame(2, (int) $variant->fresh()->stock);
        $this->assertDatabaseHas('inventory_levels', [
            'inventory_item_id' => $item->id,
            'available' => 2,
            'reserved' => 3,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(InventoryReservationService::class)->reserve($item, 3, 'checkout', 'abc-2');
    }

    public function test_storefront_order_uses_reservations_and_location_aware_movements(): void
    {
        [$store, $plain] = $this->tokenedStore('Inventory Storefront');
        [$product, $variant] = $this->productFor($store, 'API Inventory Product', 4);

        $this->withToken($plain)
            ->postJson('/api/developer-storefront/orders', [
                'customer_name' => 'Inventory Buyer',
                'customer_email' => 'inventory-buyer@example.test',
                'shipping_address' => [
                    'address_line1' => '123 Market Street',
                    'city' => 'Karachi',
                    'postal_code' => '74000',
                    'country' => 'Pakistan',
                ],
                'items' => [
                    [
                        'product_id' => $product->id,
                        'variant_id' => $variant->id,
                        'quantity' => 2,
                    ],
                ],
            ])
            ->assertCreated();

        $this->assertSame(2, (int) $variant->fresh()->stock);
        $this->assertDatabaseHas('inventory_reservations', [
            'store_id' => $store->id,
            'inventory_item_id' => InventoryItem::query()->where('variant_id', $variant->id)->value('id'),
            'quantity' => 2,
            'status' => InventoryReservation::STATUS_DEDUCTED,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'store_id' => $store->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'movement_type' => StockMovement::TYPE_ORDER_RESERVED,
            'quantity_change' => -2,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'store_id' => $store->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'movement_type' => StockMovement::TYPE_ORDER_DEDUCTED,
            'quantity_change' => -2,
        ]);
    }

    private function merchantUser(?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'role_id' => $role->id,
        ]);
    }

    private function storeFor(User $user, string $name): Store
    {
        $store = Store::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => '123 Stock Street',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($user->id, ['role' => Store::ROLE_OWNER]);

        return $store;
    }

    /**
     * @return array{0: Product, 1: ProductVariant}
     */
    private function productFor(Store $store, string $name, int $stock): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => null,
            'base_price' => 10,
            'sku' => 'P-'.Str::upper(Str::random(8)),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => $product->sku.'-D',
            'price' => 10,
            'stock' => $stock,
            'stock_alert' => 1,
        ]);

        return [$product, $variant];
    }

    /**
     * @return array{0: Store, 1: string, 2: User}
     */
    private function tokenedStore(string $name): array
    {
        $owner = $this->merchantUser(Str::slug($name).'-owner@example.test');
        $store = $this->storeFor($owner, $name);
        $plain = 'baa_dev_test_'.Str::random(32);

        $store->forceFill([
            'developer_storefront_token_hash' => hash('sha256', $plain),
            'developer_storefront_token_created_at' => now(),
        ])->save();

        return [$store, $plain, $owner];
    }
}
