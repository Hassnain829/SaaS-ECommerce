<?php

namespace Tests\Unit;

use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Support\FedExShipperPhoneResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FedExShipperPhoneResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_prefers_location_phone_over_fedex_connect_phone(): void
    {
        $location = new Location(['phone' => '2145550100']);
        $account = new CarrierAccount([
            'settings' => ['registration' => ['phone' => '4695550199']],
        ]);

        $resolved = app(FedExShipperPhoneResolver::class)->resolve($location, $account);

        $this->assertSame('2145550100', $resolved);
    }

    public function test_falls_back_to_fedex_registration_phone_when_location_empty(): void
    {
        $location = new Location(['phone' => null]);
        $account = new CarrierAccount([
            'settings' => ['registration' => ['phone' => '4695550199']],
        ]);

        $resolved = app(FedExShipperPhoneResolver::class)->resolve($location, $account);

        $this->assertSame('4695550199', $resolved);
    }

    public function test_backfill_copies_connect_phone_onto_empty_location(): void
    {
        $user = User::factory()->create();
        $store = Store::query()->create([
            'user_id' => $user->id,
            'name' => 'Phone Resolver Store',
            'slug' => 'phone-resolver-'.Str::lower(Str::random(8)),
            'currency' => 'USD',
        ]);

        $location = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Main location',
            'address_line1' => '1 Main St',
            'city' => 'Dallas',
            'state' => 'TX',
            'postal_code' => '75201',
            'country_code' => 'US',
            'phone' => null,
            'is_default' => true,
            'is_active' => true,
        ]);

        $account = new CarrierAccount([
            'store_id' => $store->id,
            'settings' => ['registration' => ['phone' => '4695550199']],
        ]);

        $resolved = app(FedExShipperPhoneResolver::class)->resolveAndBackfill($location, $account);

        $this->assertSame('4695550199', $resolved);
        $this->assertSame('4695550199', $location->fresh()->phone);
    }
}
