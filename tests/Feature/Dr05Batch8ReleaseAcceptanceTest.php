<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Support\CheckoutMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Dr05Batch8ReleaseAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_retired_external_checkout_write_paths_remain_unavailable(): void
    {
        $this->postJson('/api/v1/external/orders', ['external_order_number' => 'WEB-GONE'])
            ->assertNotFound();
        $this->postJson('/api/v1/external/shipments', ['tracking_number' => 'TRK-GONE'])
            ->assertNotFound();
        $this->postJson('/api/developer-storefront/orders', ['external_order_number' => 'WEB-GONE'])
            ->assertNotFound();
    }

    public function test_website_workspace_exposes_cutover_without_external_checkout_or_plugin_deletion(): void
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create([
            'email' => 'batch8-owner@example.test',
            'role_id' => $role->id,
        ]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'Batch 8 Store',
            'slug' => 'batch-8-'.Str::random(6),
            'logo' => null,
            'address' => 'Test Address',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.settings'))
            ->assertOk()
            ->assertSee('Connect your website')
            ->assertSee('Go live checklist')
            ->assertSee('never turns off WordPress plugins')
            ->assertDontSee('/api/v1/external/orders')
            ->assertDontSee('choose whether website orders reduce stock');

        $this->assertSame([CheckoutMode::PLATFORM], CheckoutMode::ALL);
        $this->assertSame(CheckoutMode::PLATFORM, CheckoutMode::forStore($store));
    }
}
