<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MerchantReadinessBatch1Test extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_sidebar_hides_analytics_payments_and_billing(): void
    {
        [$owner, $store] = $this->ownerStore();

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringNotContainsString('route(\'analytics\')', $html);
        $this->assertStringNotContainsString(route('analytics'), $html);
        $this->assertStringNotContainsString(route('billingSubscription'), $html);
        $this->assertStringNotContainsString('>Analytics</', $html);
        $this->assertStringNotContainsString('>Payments</', $html);
        $this->assertStringNotContainsString('>Billing</', $html);
        $this->assertStringNotContainsString('Connect payments', $html);
    }

    public function test_general_settings_links_to_real_payments_workspace(): void
    {
        [$owner, $store] = $this->ownerStore();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('generalSettings'))
            ->assertOk()
            ->assertSeeText('Open payments settings')
            ->assertSeeText('Checkout payments')
            ->assertSee(route('settings.payments.index'), false);
    }

    public function test_analytics_and_billing_routes_redirect_to_dashboard_for_owner(): void
    {
        [$owner, $store] = $this->ownerStore();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('analytics'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('billingSubscription'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_admin_dashboard_shows_unavailable_placeholder_removal(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($admin)
            ->get(route('admin-dashboard'))
            ->assertOk()
            ->assertSeeText('Platform overview is not available yet')
            ->assertSeeText('Invented platform data has been removed');
    }

    public function test_signin_and_register_remove_baas_and_sla_claims(): void
    {
        $this->get(route('signin'))
            ->assertOk()
            ->assertDontSeeText('BaaS Core')
            ->assertDontSeeText('99.9%')
            ->assertSeeText('Merchant workspace');

        $this->get(route('register'))
            ->assertOk()
            ->assertDontSeeText('BaaS Core')
            ->assertDontSeeText('production-ready')
            ->assertSeeText('Merchant workspace');
    }

    public function test_products_list_edit_routes_to_workspace_not_modal_opener(): void
    {
        [$owner, $store] = $this->ownerStore();
        $product = $this->makeProduct($store, 'Workspace Edit Target');

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products'))
            ->assertOk()
            ->getContent() ?: '';

        $editUrl = route('products.edit', $product);
        $this->assertMatchesRegularExpression(
            '/<a[^>]+href="'.preg_quote($editUrl, '/').'"[^>]*class="[^"]*js-product-edit-payload[^"]*"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<button[^>]+class="[^"]*js-open-edit-product-modal[^"]*"[^>]*>\s*Edit\s*<\/button>/',
            $html
        );
    }

    public function test_empty_catalog_shows_add_and_import_ctas(): void
    {
        [$owner, $store] = $this->ownerStore();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products'))
            ->assertOk()
            ->assertSeeText('Add your first product')
            ->assertSeeText('Add product')
            ->assertSeeText('Import products');
    }

    public function test_deleted_products_copy_avoids_soft_delete_jargon(): void
    {
        [$owner, $store] = $this->ownerStore();
        $product = $this->makeProduct($store, 'Soft Language Check');
        $product->delete();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products', ['view' => 'deleted']))
            ->assertOk()
            ->assertSeeText('Deleted products')
            ->assertSeeText('Undo delete')
            ->assertDontSeeText('soft-delete')
            ->assertDontSeeText('Soft delete');
    }

    public function test_delivery_overview_gates_usps_and_dhl_truthfully(): void
    {
        [$owner, $store] = $this->ownerStore();

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk();

        $response->assertSeeText('Coming later');
        $response->assertSeeText('FedEx');
        $html = $response->getContent() ?: '';
        $this->assertStringContainsString('DHL', $html);
        $this->assertStringContainsString('USPS', $html);
    }

    public function test_onboarding_step2_import_links_to_real_import_flow(): void
    {
        [$owner, $store] = $this->ownerStore();

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('onboarding-Step2-AddProductVariations'))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString(route('products.import.create'), $html);
        $this->assertStringNotContainsString('Upload CSV', $html);
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name = 'Batch1 Store'): array
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['role_id' => $role->id]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Addr',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        return [$owner, $store];
    }

    private function makeProduct(Store $store, string $name): Product
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => null,
            'base_price' => 10,
            'sku' => 'SKU-'.strtoupper(Str::random(6)),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $product->variants()->create([
            'sku' => $product->sku.'-V',
            'price' => 10,
            'stock' => 5,
            'is_default' => true,
        ]);

        return $product;
    }
}
