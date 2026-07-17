<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantTopbarTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_shared_topbar_without_decorative_search(): void
    {
        [$owner, $store] = $this->ownerWithStore('Dashboard Topbar Store');

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString('merchant-topbar', $html);
        $this->assertStringContainsString('aria-label="Notifications"', $html);
        $this->assertStringContainsString('aria-label="Help"', $html);
        $this->assertStringContainsString('aria-label="Open profile menu"', $html);
        $this->assertStringNotContainsString('Search products, orders, customers', $html);
        // Page identity lives in content, not the utility topbar.
        $this->assertMatchesRegularExpression('/<h1[^>]*>\s*(Welcome|Dashboard|Store setup)/', $html);
    }

    public function test_products_page_keeps_contextual_search_and_content_heading(): void
    {
        [$owner, $store] = $this->ownerWithStore('Products Topbar Store');

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products'))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString('merchant-topbar', $html);
        $this->assertStringContainsString('name="q"', $html);
        $this->assertStringContainsString('Search products', $html);
        $this->assertStringContainsString('aria-label="Notifications"', $html);
        $this->assertStringContainsString('data-products-more-actions', $html);
        $this->assertMatchesRegularExpression('/<h1[^>]*>\s*Products\s*<\/h1>/', $html);
    }

    public function test_orders_page_includes_shared_topbar_controls(): void
    {
        [$owner, $store] = $this->ownerWithStore('Orders Topbar Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orders'))
            ->assertOk()
            ->assertSee('merchant-topbar', false)
            ->assertSee('aria-label="Notifications"', false)
            ->assertSee('aria-label="Help"', false)
            ->assertSee('aria-label="Open profile menu"', false);
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerWithStore(string $name): array
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['role_id' => $role->id]);
        $store = Store::create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => 'topbar-'.fake()->unique()->numberBetween(1000, 9999),
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
}
