<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Delivery\ShippingWeightResolver;
use App\Services\Delivery\StoreShippingPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShippingWeightResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_resolution_prefers_variant_over_product(): void
    {
        [$store, $product] = $this->storeWithProduct();
        $product->forceFill(['meta' => ['shipping_weight' => 2.5]])->save();
        $variant = $product->variants()->first();
        $variant->forceFill(['meta' => ['shipping_weight' => 4.25]])->save();

        $resolver = app(ShippingWeightResolver::class);

        $this->assertSame(4.25, $resolver->resolveExact($product->fresh(), $variant->fresh()));
        $this->assertSame(2.5, $resolver->resolveExactProductLevel($product->fresh()));
    }

    public function test_store_fallback_used_only_when_exact_missing(): void
    {
        [$store, $product] = $this->storeWithProduct();
        app(StoreShippingPreferences::class)->update($store, ['fallback_item_weight' => 1.0]);

        $resolver = app(ShippingWeightResolver::class);
        $this->assertNull($resolver->resolveExact($product));
        $this->assertSame(1.0, $resolver->resolveForStore($store->fresh(), $product));

        $product->forceFill(['meta' => ['shipping_weight' => 3.0]])->save();
        $this->assertSame(3.0, $resolver->resolveForStore($store->fresh(), $product->fresh()));
    }

    public function test_snapshot_for_store_formats_resolved_weight(): void
    {
        [$store, $product] = $this->storeWithProduct();
        app(StoreShippingPreferences::class)->update($store, ['fallback_item_weight' => 1.5]);

        $snapshot = app(ShippingWeightResolver::class)->resolveSnapshotForStore($store->fresh(), $product);
        $this->assertSame('1.500', $snapshot);
    }

    public function test_legacy_resolve_does_not_apply_store_fallback(): void
    {
        [$store, $product] = $this->storeWithProduct();
        app(StoreShippingPreferences::class)->update($store, ['fallback_item_weight' => 1.0]);

        $this->assertNull(app(ShippingWeightResolver::class)->resolve($product));
    }

    public function test_persist_variant_shipping_weight_meta_sets_and_clears(): void
    {
        $resolver = app(ShippingWeightResolver::class);
        $meta = ['weight' => 2.0];

        $resolver->persistVariantShippingWeightMeta($meta, 4.2);
        $this->assertSame(4.2, $meta['shipping_weight']);
        $this->assertArrayNotHasKey('weight', $meta);

        $resolver->persistVariantShippingWeightMeta($meta, '');
        $this->assertArrayNotHasKey('shipping_weight', $meta);
    }

    /**
     * @return array{0: Store, 1: Product}
     */
    private function storeWithProduct(): array
    {
        $owner = User::factory()->create([
            'email' => 'weight-resolver-'.Str::lower(Str::random(6)).'@example.test',
            'role_id' => Role::firstOrCreate(['name' => 'user'])->id,
        ]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'Weight Resolver Store',
            'slug' => 'weight-resolver-'.Str::lower(Str::random(6)),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->syncWithoutDetaching([$owner->id => ['role' => Store::ROLE_OWNER]]);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Shirt',
            'slug' => 'shirt-'.Str::lower(Str::random(6)),
            'base_price' => 10,
            'sku' => 'SKU-'.strtoupper(Str::random(6)),
            'product_type' => 'physical',
            'status' => true,
            'requires_shipping' => true,
            'meta' => [],
        ]);
        $product->variants()->create([
            'sku' => $product->sku,
            'price' => 10,
            'stock' => 1,
            'stock_alert' => 0,
            'meta' => [],
        ]);

        return [$store, $product];
    }
}
