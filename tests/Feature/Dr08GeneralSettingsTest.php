<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Support\CatalogRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Dr08GeneralSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_get_is_read_only_and_does_not_create_locations(): void
    {
        [$owner, $store] = $this->ownerStore('Read Only Settings');
        $beforeCount = $store->locations()->count();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('generalSettings'))
            ->assertOk()
            ->assertSeeText('Edit settings')
            ->assertSeeText('Default Inventory Location')
            ->assertDontSeeText('Delete Store')
            ->assertDontSeeText('Primary Market');

        $this->assertSame($beforeCount, $store->locations()->count());
    }

    public function test_default_location_can_be_selected_from_general_settings_update(): void
    {
        [$owner, $store] = $this->ownerStore('Location Switch Store');

        $main = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Main Warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
            'pickup_enabled' => false,
            'routing_priority' => 100,
        ]);

        $secondary = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Secondary Warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'is_default' => false,
            'is_active' => true,
            'fulfills_online_orders' => true,
            'pickup_enabled' => false,
            'routing_priority' => 90,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('store.update', ['storeId' => $store->id]), [
                'name' => $store->name,
                'address' => $store->address,
                'currency' => 'USD',
                'timezone' => 'UTC',
                'category' => 'physical',
                'default_location_id' => $secondary->id,
                'redirect_to' => 'generalSettings',
            ])
            ->assertRedirect(route('generalSettings'));

        $this->assertFalse((bool) $main->fresh()->is_default);
        $this->assertTrue((bool) $secondary->fresh()->is_default);
    }

    public function test_catalog_revision_changes_when_store_name_updates(): void
    {
        [$owner, $store] = $this->ownerStore('Catalog Bump Store');
        $before = CatalogRevision::forStore($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('store.update', ['storeId' => $store->id]), [
                'name' => 'Catalog Bump Store Renamed',
                'address' => $store->address,
                'currency' => 'USD',
                'timezone' => 'UTC',
                'category' => 'physical',
                'redirect_to' => 'generalSettings',
            ])
            ->assertRedirect(route('generalSettings'));

        $after = CatalogRevision::forStore($store->fresh());
        $this->assertNotSame($before, $after);
    }

    public function test_currency_change_requires_confirmation_then_converts_catalog_prices(): void
    {
        [$owner, $store] = $this->ownerStore('Currency Convert Store');

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Convert Product',
            'slug' => 'convert-product-'.fake()->unique()->numberBetween(1000, 9999),
            'status' => true,
            'product_type' => 'simple',
            'base_price' => 100,
        ]);

        $variant = $product->variants()->create([
            'store_id' => $store->id,
            'sku' => 'CNV-1',
            'price' => 100,
            'compare_at_price' => 120,
            'stock' => 5,
            'is_default' => true,
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'open.er-api.com/*' => \Illuminate\Support\Facades\Http::response([
                'result' => 'success',
                'base_code' => 'USD',
                'rates' => ['EUR' => 0.5],
            ], 200),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('generalSettings'))
            ->put(route('store.update', ['storeId' => $store->id]), [
                'name' => $store->name,
                'address' => $store->address,
                'currency' => 'EUR',
                'timezone' => 'UTC',
                'category' => 'physical',
                'redirect_to' => 'generalSettings',
            ])
            ->assertRedirect(route('generalSettings'))
            ->assertSessionHasErrors('confirm_currency_conversion');

        $this->assertSame('USD', $store->fresh()->currency);
        $this->assertSame('100.00', number_format((float) $product->fresh()->base_price, 2, '.', ''));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('store.update', ['storeId' => $store->id]), [
                'name' => $store->name,
                'address' => $store->address,
                'currency' => 'EUR',
                'timezone' => 'UTC',
                'category' => 'physical',
                'confirm_currency_conversion' => '1',
                'redirect_to' => 'generalSettings',
            ])
            ->assertRedirect(route('generalSettings'))
            ->assertSessionHasNoErrors();

        $store->refresh();
        $product->refresh();
        $variant->refresh();

        $this->assertSame('EUR', $store->currency);
        $this->assertSame('50.00', number_format((float) $product->base_price, 2, '.', ''));
        $this->assertSame('50.00', number_format((float) $variant->price, 2, '.', ''));
        $this->assertSame('60.00', number_format((float) $variant->compare_at_price, 2, '.', ''));
        $this->assertSame('0.5', (string) data_get($store->settings, 'last_currency_conversion.rate'));
    }

    public function test_store_validation_errors_do_not_force_account_tab(): void
    {
        [$owner, $store] = $this->ownerStore('Tab Guard Store');

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('generalSettings', ['tab' => 'store']))
            ->put(route('store.update', ['storeId' => $store->id]), [
                'name' => '',
                'address' => $store->address,
                'currency' => 'USD',
                'timezone' => 'UTC',
                'category' => 'physical',
                'redirect_to' => 'generalSettings',
                '_open_edit_store_modal' => '1',
                '_edit_store_id' => (string) $store->id,
            ]);

        $response->assertRedirect(route('generalSettings', ['tab' => 'store']));
        $response->assertSessionHasErrors('name');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('generalSettings', ['tab' => 'store']))
            ->assertOk()
            ->assertSeeText('Store Profile')
            ->assertDontSeeText('Personal information');
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name = 'DR08 Store'): array
    {
        $owner = $this->merchant();
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => '100 Settings Ave',
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
