<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FedExAdminPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierSeeder::class);
    }

    public function test_admin_can_access_consolidated_fedex_page(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create([
            'email' => 'admin-fedex-page@example.test',
            'role_id' => $adminRole->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.fedex.index'))
            ->assertOk()
            ->assertSeeText('FedEx')
            ->assertSeeText('Platform flags')
            ->assertSeeText('Overview')
            ->assertSeeText('Connections')
            ->assertSeeText('Trade documents')
            ->assertDontSeeText('Validation archive')
            ->assertDontSee('client_secret', false)
            ->assertDontSee('customer_password', false);
    }

    public function test_non_admin_cannot_access_consolidated_fedex_page(): void
    {
        $userRole = Role::firstOrCreate(['name' => 'user']);
        $merchant = User::factory()->create([
            'email' => 'merchant-fedex-page@example.test',
            'role_id' => $userRole->id,
        ]);

        $this->actingAs($merchant)
            ->get(route('admin.fedex.index'))
            ->assertForbidden();
    }

    public function test_legacy_diagnostics_route_redirects_to_admin_fedex_index(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create([
            'email' => 'admin-fedex-legacy@example.test',
            'role_id' => $adminRole->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.fedex.diagnostics', ['tab' => 'shipments']))
            ->assertRedirect(route('admin.fedex.index', ['tab' => 'shipments']));
    }

    public function test_connections_tab_masks_account_numbers(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create([
            'email' => 'admin-fedex-mask@example.test',
            'role_id' => $adminRole->id,
        ]);

        $owner = User::factory()->create(['role_id' => Role::firstOrCreate(['name' => 'user'])->id]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'FedEx Admin Test Store',
            'slug' => 'fedex-admin-test-store',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();
        $account = CarrierAccount::query()->create(array_merge(
            CarrierAccount::ownershipAttributesForFedExIntegratorProvider(),
            [
                'store_id' => $store->id,
                'carrier_id' => $fedEx->id,
                'provider' => CarrierAccount::PROVIDER_FEDEX,
                'display_name' => 'Test FedEx',
                'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
                'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
                'status' => CarrierAccount::STATUS_ENABLED,
            ],
        ));
        $account->setFedExAccountNumber('700257037');
        $account->save();

        $this->actingAs($admin)
            ->get(route('admin.fedex.index', ['tab' => 'connections']))
            ->assertOk()
            ->assertSeeText($account->maskedAccountNumber())
            ->assertDontSee('700257037', false);
    }
}
