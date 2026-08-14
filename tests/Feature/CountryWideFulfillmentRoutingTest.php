<?php

namespace Tests\Feature;

use App\Models\InventoryLevel;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\User;
use App\Services\Fulfillment\FulfillmentOriginRouter;
use App\Services\Inventory\InventorySyncService;
use App\Services\Shipping\ShippingZoneMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CountryWideFulfillmentRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_united_states_country_name_matches_country_wide_us_origin(): void
    {
        [$store, $location, $variant] = $this->usStoreWithStock(5);

        $result = app(FulfillmentOriginRouter::class)->routeForCheckout(
            $store,
            [['variant' => $variant, 'quantity' => 1]],
            $this->austinAddress([
                'country' => 'United States',
                'country_code' => 'UN',
            ]),
        );

        $this->assertSame($location->id, $result->originLocation->id);
    }

    public function test_country_wide_origin_uses_catalog_stock_when_location_level_is_empty(): void
    {
        [$store, $location, $variant] = $this->usStoreWithStock(0);
        $variant->forceFill(['stock' => 8])->saveQuietly();

        $result = app(FulfillmentOriginRouter::class)->routeForCheckout(
            $store,
            [['variant' => $variant, 'quantity' => 1]],
            $this->austinAddress([
                'country' => 'United States',
            ]),
        );

        $this->assertSame($location->id, $result->originLocation->id);
        $this->assertGreaterThanOrEqual(8, (int) InventoryLevel::query()
            ->where('location_id', $location->id)
            ->value('available'));
    }

    public function test_country_wide_origin_still_fails_when_there_is_no_sellable_stock(): void
    {
        [$store, , $variant] = $this->usStoreWithStock(0);
        $variant->forceFill(['stock' => 0])->saveQuietly();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No fulfillment location has enough stock for this address.');

        app(FulfillmentOriginRouter::class)->routeForCheckout(
            $store,
            [['variant' => $variant, 'quantity' => 1]],
            $this->austinAddress(['country' => 'United States']),
        );
    }

    public function test_does_not_steal_stock_from_a_location_that_does_not_serve_the_address(): void
    {
        [$store, $usLocation, $variant] = $this->usStoreWithStock(0);

        $canada = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Canada warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'city' => 'Toronto',
            'state' => 'ON',
            'postal_code' => 'M5V 2T6',
            'country_code' => 'CA',
            'service_countries' => ['CA'],
            'is_default' => false,
            'is_active' => true,
            'fulfills_online_orders' => true,
            'routing_priority' => 10,
        ]);

        $this->stockAt($variant, [
            $usLocation->id => 0,
            $canada->id => 10,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No fulfillment location has enough stock for this address.');

        app(FulfillmentOriginRouter::class)->routeForCheckout(
            $store,
            [['variant' => $variant, 'quantity' => 1]],
            $this->austinAddress(['country' => 'United States']),
        );
    }

    public function test_country_wide_delivery_area_ignores_warehouse_zip_restriction(): void
    {
        [$store, $location, $variant] = $this->usStoreWithStock(5);
        $location->forceFill([
            'service_countries' => ['US'],
            'service_regions' => ['TX'],
            'service_postal_patterns' => ['75002'],
        ])->save();

        ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'US methods',
            'countries' => ['US'],
            'regions' => [],
            'postal_patterns' => [],
            'is_active' => true,
        ]);

        $result = app(FulfillmentOriginRouter::class)->routeForCheckout(
            $store,
            [['variant' => $variant, 'quantity' => 1]],
            $this->austinAddress([
                'country' => 'United States',
                'postal_code' => '78701',
            ]),
        );

        $this->assertSame($location->id, $result->originLocation->id);
    }

    public function test_zip_restricted_location_still_rejects_other_zips_without_country_wide_zone(): void
    {
        [$store, $location, $variant] = $this->usStoreWithStock(5);
        $location->forceFill([
            'service_countries' => ['US'],
            'service_postal_patterns' => ['75002'],
        ])->save();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('This address is outside the delivery area of your fulfillment locations.');

        app(FulfillmentOriginRouter::class)->routeForCheckout(
            $store,
            [['variant' => $variant, 'quantity' => 1]],
            $this->austinAddress(['postal_code' => '78701']),
        );
    }

    public function test_country_wide_shipping_zone_matches_united_states_address_name(): void
    {
        [$store] = $this->usStoreWithStock(5);

        ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'US methods',
            'countries' => ['US'],
            'regions' => [],
            'postal_patterns' => [],
            'is_active' => true,
        ]);

        $zones = app(ShippingZoneMatcher::class)->matchingZones($store, $this->austinAddress([
            'country' => 'United States',
            'country_code' => 'UN',
        ]));

        $this->assertTrue($zones->contains(fn (ShippingZone $zone): bool => $zone->name === 'US methods'));
    }

    /**
     * @return array{0: Store, 1: Location, 2: ProductVariant}
     */
    private function usStoreWithStock(int $available): array
    {
        Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create();
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'Country Wide Store',
            'slug' => 'country-wide-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => ['country_code' => 'US'],
            'onboarding_completed' => true,
        ]);

        $location = $store->defaultLocation()->firstOrFail();
        $location->forceFill([
            'name' => 'Main location',
            'address_line1' => '738 FAWN VALLEY DR',
            'city' => 'ALLEN',
            'state' => 'TX',
            'postal_code' => '75002',
            'country_code' => 'US',
            'service_countries' => ['US'],
            'service_regions' => [],
            'service_postal_patterns' => [],
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ])->save();

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Vienna Sausages',
            'slug' => 'vienna-'.Str::random(6),
            'base_price' => 125,
            'sku' => 'VS-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => $product->sku.'-BLU',
            'price' => 125,
            'stock' => $available,
        ]);

        $this->stockAt($variant, [$location->id => $available]);

        return [$store->fresh(), $location->fresh(), $variant->fresh()];
    }

    /**
     * @param  array<int, int>  $stockByLocation
     */
    private function stockAt(ProductVariant $variant, array $stockByLocation): void
    {
        $sync = app(InventorySyncService::class);
        $item = $sync->ensureInventoryItemForVariant($variant);
        InventoryLevel::query()
            ->where('inventory_item_id', $item->id)
            ->update(['available' => 0, 'reserved' => 0, 'committed' => 0, 'incoming' => 0]);

        foreach ($stockByLocation as $locationId => $available) {
            $level = $sync->ensureLevel($item, Location::query()->findOrFail($locationId), 0);
            $level->forceFill([
                'available' => $available,
                'reserved' => 0,
                'committed' => 0,
                'incoming' => 0,
            ])->save();
        }

        $sync->syncVariantStockCache($variant);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function austinAddress(array $overrides = []): array
    {
        return array_merge([
            'name' => 'WP Test Buyer',
            'address_line1' => '100 WordPress Avenue',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'country' => 'United States',
            'country_code' => 'US',
        ], $overrides);
    }
}
