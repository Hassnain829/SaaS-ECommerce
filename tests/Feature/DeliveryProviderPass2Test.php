<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\USPS\Support\USPSConfig;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryProviderPass2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierSeeder::class);
        config([
            'carriers.fedex.enabled' => true,
            'carriers.fedex.integrator_model_a_enabled' => true,
            'carriers.usps.enabled' => true,
            'carriers.usps.merchant_connection_enabled' => true,
            'carriers.usps.merchant_visible' => false,
        ]);
    }

    public function test_delivery_hub_hides_usps_and_manual_provider_language(): void
    {
        [$owner, $store] = $this->ownerStore('Pass2 Hub Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('FedEx')
            ->assertDontSeeText('USPS')
            ->assertDontSeeText('DHL')
            ->assertDontSeeText('Connect delivery provider')
            ->assertDontSeeText('Manual / Local Delivery');
    }

    public function test_generic_carrier_connect_index_redirects_to_fedex(): void
    {
        [$owner, $store] = $this->ownerStore('Pass2 Connect Redirect Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shipping.carriers.connect.index'))
            ->assertRedirect(route('settings.shipping.fedex-integrator.start'));
    }

    public function test_manual_and_deferred_carrier_routes_redirect_to_delivery(): void
    {
        [$owner, $store] = $this->ownerStore('Pass2 Manual Redirect Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shipping.carriers.connect.show', 'manual'))
            ->assertRedirect(route('shippingAutomation'));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shipping.carriers.connect.show', 'dhl'))
            ->assertRedirect(route('shippingAutomation'));
    }

    public function test_usps_merchant_start_is_gated_when_connection_disabled(): void
    {
        config([
            'carriers.usps.merchant_visible' => false,
            'carriers.usps.merchant_connection_enabled' => false,
        ]);

        [$owner, $store] = $this->ownerStore('Pass2 USPS Gate Store');

        $this->assertFalse(app(USPSConfig::class)->merchantRoutesAccessible());

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.usps-merchant.start'))
            ->assertNotFound();
    }

    public function test_usps_merchant_routes_accessible_in_testing_when_connection_enabled(): void
    {
        config([
            'carriers.usps.merchant_visible' => false,
            'carriers.usps.merchant_connection_enabled' => true,
        ]);

        $this->assertFalse(app(USPSConfig::class)->merchantVisible());
        $this->assertTrue(app(USPSConfig::class)->merchantRoutesAccessible());
    }

    public function test_fedex_start_hides_environment_choice_outside_local_testing_flag_path(): void
    {
        // In this test suite environment is "testing", so choice remains available.
        $this->assertTrue(app(FedExConfig::class)->merchantMayChooseEnvironment());

        [$owner, $store] = $this->ownerStore('Pass2 FedEx Start Store');

        $html = (string) $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.fedex-integrator.start'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('FedEx environment (developer)', $html);
        $this->assertStringContainsString('Back to Delivery', $html);
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name): array
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create([
            'email' => Str::slug($name).'-owner@example.test',
            'role_id' => $role->id,
        ]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->syncWithoutDetaching([$owner->id => ['role' => Store::ROLE_OWNER]]);

        return [$owner, $store];
    }
}
