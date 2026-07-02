<?php

namespace Tests\Unit;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Store;
use App\Models\User;
use App\Services\Delivery\ManualDeliveryProviderResolver;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManualDeliveryProviderResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
    }

    public function test_reuses_enabled_manual_account_for_store(): void
    {
        [$store, $user] = $this->storeWithOwner();
        $manualCarrier = Carrier::query()->where('code', 'manual-delivery')->firstOrFail();

        $existing = CarrierAccount::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $manualCarrier->id,
            'provider' => CarrierAccount::PROVIDER_MANUAL,
            'display_name' => 'Manual delivery',
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_type' => CarrierAccount::CONNECTION_MANUAL,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_MANUAL,
            'status' => CarrierAccount::STATUS_ENABLED,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'enabled_for_checkout' => false,
            ...CarrierAccount::ownershipAttributesForManual(),
        ]);

        $resolved = app(ManualDeliveryProviderResolver::class)->resolveForStore($store, $user);

        $this->assertSame($existing->id, $resolved->id);
        $this->assertSame(1, CarrierAccount::query()->where('store_id', $store->id)->where('provider', CarrierAccount::PROVIDER_MANUAL)->count());
    }

    public function test_creates_manual_account_when_missing(): void
    {
        [$store, $user] = $this->storeWithOwner();

        $resolved = app(ManualDeliveryProviderResolver::class)->resolveForStore($store, $user);

        $this->assertSame(CarrierAccount::PROVIDER_MANUAL, $resolved->provider);
        $this->assertSame(CarrierAccount::STATUS_ENABLED, $resolved->status);
        $this->assertDatabaseHas('carrier_accounts', [
            'id' => $resolved->id,
            'store_id' => $store->id,
            'provider' => CarrierAccount::PROVIDER_MANUAL,
        ]);
    }

    public function test_does_not_reuse_manual_account_from_other_store(): void
    {
        [$storeA, $userA] = $this->storeWithOwner('Store A');
        [$storeB] = $this->storeWithOwner('Store B');
        $manualCarrier = Carrier::query()->where('code', 'manual-delivery')->firstOrFail();

        CarrierAccount::query()->create([
            'store_id' => $storeA->id,
            'carrier_id' => $manualCarrier->id,
            'provider' => CarrierAccount::PROVIDER_MANUAL,
            'display_name' => 'Store A manual',
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_type' => CarrierAccount::CONNECTION_MANUAL,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_MANUAL,
            'status' => CarrierAccount::STATUS_ENABLED,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'enabled_for_checkout' => false,
            ...CarrierAccount::ownershipAttributesForManual(),
        ]);

        $resolvedB = app(ManualDeliveryProviderResolver::class)->resolveForStore($storeB, $userA);

        $this->assertSame($storeB->id, $resolvedB->store_id);
        $this->assertNotSame($storeA->id, $resolvedB->store_id);
    }

    /**
     * @return array{0: Store, 1: User}
     */
    private function storeWithOwner(string $name = 'Manual Resolver Store'): array
    {
        $user = User::factory()->create();
        $store = Store::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);

        return [$store, $user];
    }
}
