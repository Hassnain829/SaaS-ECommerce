<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Support\StockMovementRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_simple_product_records_initial_stock_movement(): void
    {
        $merchant = $this->createMerchantUser();
        $store = $this->createMemberStore($merchant, 'Stock Store Alpha');

        $this->actingAs($merchant)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                'name' => 'Simple Widget',
                'description' => 'A widget.',
                'bulk_price' => 29.99,
                'sku' => 'SW-001',
                'product_type' => 'physical',
                'bulk_stock' => 14,
                'stock_alert' => 2,
            ])
            ->assertRedirect(route('products'));

        $product = Product::query()->where('name', 'Simple Widget')->firstOrFail();
        $variant = $product->variants()->firstOrFail();

        $this->assertSame(14, (int) $variant->stock);

        $this->assertDatabaseHas('stock_movements', [
            'store_id' => $store->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'movement_type' => StockMovement::TYPE_INITIAL,
            'quantity_change' => 14,
            'new_stock' => 14,
            'performed_by' => $merchant->id,
            'source' => 'catalog',
        ]);
    }

    public function test_creating_a_product_with_two_variant_rows_records_two_initial_movements(): void
    {
        $merchant = $this->createMerchantUser();
        $store = $this->createMemberStore($merchant, 'Variant Stock Store');

        $this->actingAs($merchant)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), $this->twoVariantProductPayload('Dual Tee', 5, 7))
            ->assertRedirect(route('products'));

        $product = Product::query()->where('name', 'Dual Tee')->firstOrFail();

        $this->assertSame(2, $product->variants()->count());
        $this->assertSame(2, StockMovement::query()->where('product_id', $product->id)->count());

        foreach ($product->variants as $variant) {
            $this->assertDatabaseHas('stock_movements', [
                'store_id' => $store->id,
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'movement_type' => StockMovement::TYPE_INITIAL,
                'performed_by' => $merchant->id,
            ]);
        }

        $stocks = $product->variants()->pluck('stock')->map(fn ($s) => (int) $s)->sort()->values()->all();
        $this->assertSame([5, 7], $stocks);
    }

    public function test_updating_stock_records_movement_only_when_quantity_changes(): void
    {
        $merchant = $this->createMerchantUser();
        $store = $this->createMemberStore($merchant, 'Edit Stock Store');

        $this->actingAs($merchant)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), $this->twoVariantProductPayload('Edit Tee', 10, 20));

        $product = Product::query()->where('name', 'Edit Tee')->firstOrFail();
        $initialCount = StockMovement::query()->where('product_id', $product->id)->count();
        $this->assertSame(2, $initialCount);

        $this->actingAs($merchant)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), $this->twoVariantProductUpdatePayload(
                $product,
                'Edit Tee',
                10,
                20,
            ));

        $this->assertSame($initialCount, StockMovement::query()->where('product_id', $product->id)->count());

        $this->actingAs($merchant)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), $this->twoVariantProductUpdatePayload(
                $product,
                'Edit Tee Renamed',
                13,
                20,
            ));

        $product->refresh();

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'movement_type' => StockMovement::TYPE_EDIT_UPDATE,
            'previous_stock' => 10,
            'quantity_change' => 3,
            'new_stock' => 13,
            'performed_by' => $merchant->id,
        ]);

        $this->actingAs($merchant)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), $this->twoVariantProductUpdatePayload(
                $product,
                'Edit Tee Renamed',
                13,
                16,
            ));

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'movement_type' => StockMovement::TYPE_EDIT_UPDATE,
            'previous_stock' => 20,
            'quantity_change' => -4,
            'new_stock' => 16,
        ]);

        $product->refresh();
        $stocks = $product->variants()->orderBy('id')->pluck('stock')->map(fn ($s) => (int) $s)->all();
        $this->assertContains(13, $stocks);
        $this->assertContains(16, $stocks);
    }

    public function test_stock_movements_are_store_scoped_and_cross_store_update_does_not_apply(): void
    {
        $merchant = $this->createMerchantUser();
        $alpha = $this->createMemberStore($merchant, 'Alpha Stock');
        $beta = $this->createMemberStore($merchant, 'Beta Stock');

        $this->actingAs($merchant)
            ->withSession(['current_store_id' => $beta->id])
            ->post(route('product.store'), [
                'name' => 'Beta Only',
                'description' => 'Beta product.',
                'bulk_price' => 9.99,
                'sku' => 'BETA-S',
                'product_type' => 'physical',
                'bulk_stock' => 3,
                'stock_alert' => 1,
            ]);

        $betaProduct = Product::query()->where('name', 'Beta Only')->firstOrFail();
        $betaMovementsBefore = StockMovement::query()->where('store_id', $beta->id)->count();

        $this->actingAs($merchant)
            ->withSession(['current_store_id' => $alpha->id])
            ->put(route('product.update', ['productId' => $betaProduct->id]), $this->twoVariantProductUpdatePayload(
                $betaProduct,
                'Hijacked',
                99,
                99,
            ))
            ->assertNotFound();

        $this->assertSame($betaMovementsBefore, StockMovement::query()->where('store_id', $beta->id)->count());
        $this->assertDatabaseHas('products', [
            'id' => $betaProduct->id,
            'name' => 'Beta Only',
            'store_id' => $beta->id,
        ]);

        foreach (StockMovement::query()->where('product_id', $betaProduct->id)->get() as $row) {
            $this->assertSame((int) $beta->id, (int) $row->store_id);
        }
    }

    public function test_recorder_rejects_store_mismatch(): void
    {
        $merchant = $this->createMerchantUser();
        $alpha = $this->createMemberStore($merchant, 'Alpha R');
        $beta = $this->createMemberStore($merchant, 'Beta R');
        $product = Product::query()->create([
            'store_id' => $alpha->id,
            'name' => 'Lone',
            'slug' => 'lone-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => null,
            'base_price' => 1,
            'sku' => 'L1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'L1-V',
            'price' => 1,
            'stock' => 1,
            'stock_alert' => 0,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        StockMovementRecorder::recordInitial($beta, $product, $variant, 1, $merchant->id, 'test');
    }

    /**
     * @return array<string, mixed>
     */
    private function twoVariantProductPayload(string $name, int $stockS, int $stockM): array
    {
        return [
            'name' => $name,
            'description' => 'Two sizes.',
            'bulk_price' => 24.00,
            'sku' => 'TT-'.strtoupper(substr(sha1($name), 0, 6)),
            'product_type' => 'physical',
            'bulk_stock' => 1,
            'stock_alert' => 1,
            'variation_types' => [
                [
                    'name' => 'Size',
                    'type' => 'select',
                    'options' => ['S', 'M'],
                ],
            ],
            'variants' => [
                [
                    'option_map' => ['0' => 0],
                    'stock' => $stockS,
                    'price' => 24.00,
                    'stock_alert' => 1,
                ],
                [
                    'option_map' => ['0' => 1],
                    'stock' => $stockM,
                    'price' => 24.00,
                    'stock_alert' => 1,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function twoVariantProductUpdatePayload(Product $product, string $name, int $stockS, int $stockM): array
    {
        return [
            '_open_edit_product_modal' => '1',
            '_edit_product_id' => (string) $product->id,
            'name' => $name,
            'description' => $product->description ?? 'Two sizes.',
            'sku' => $product->sku ?? 'TT-UPD',
            'base_price' => (string) $product->base_price,
            'product_type' => $product->product_type,
            'stock_alert' => (int) (($product->meta['stock_alert'] ?? 1)),
            'variation_types' => [
                [
                    'name' => 'Size',
                    'type' => 'select',
                    'options' => ['S', 'M'],
                ],
            ],
            'variants' => [
                [
                    'option_map' => ['0' => 0],
                    'stock' => $stockS,
                    'price' => (float) $product->base_price,
                    'stock_alert' => (int) (($product->meta['stock_alert'] ?? 1)),
                ],
                [
                    'option_map' => ['0' => 1],
                    'stock' => $stockM,
                    'price' => (float) $product->base_price,
                    'stock_alert' => (int) (($product->meta['stock_alert'] ?? 1)),
                ],
            ],
        ];
    }

    protected function createMerchantUser(?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'role_id' => $role->id,
        ]);
    }

    protected function createMemberStore(User $user, string $name): Store
    {
        $store = Store::create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Test Address',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => false,
        ]);

        $store->members()->attach($user->id, ['role' => 'owner']);

        return $store;
    }
}
