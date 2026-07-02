<?php

namespace Tests\Unit;

use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\User;
use App\Services\Delivery\DeliveryAddressDiagnosticService;
use App\Services\Shipping\DeliveryOptionService;
use App\Services\Shipping\ShippingZoneMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryAddressDiagnosticServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagnose_marks_available_and_unavailable_options_with_reasons(): void
    {
        $store = $this->store();
        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'Texas',
            'countries' => ['US'],
            'regions' => ['TX'],
            'postal_patterns' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'name' => 'Standard delivery',
            'code' => 'standard',
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 5,
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 0,
        ]);

        ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'name' => 'Large orders only',
            'code' => 'large-only',
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 8,
            'min_order_amount' => 50,
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 1,
        ]);

        $service = new DeliveryAddressDiagnosticService(new ShippingZoneMatcher, new DeliveryOptionService(new ShippingZoneMatcher));

        $result = $service->diagnose($store, 'US', 'TX', '75002', 25);

        $this->assertTrue($result['has_matching_area']);
        $available = collect($result['options'])->firstWhere('name', 'Standard delivery');
        $blocked = collect($result['options'])->firstWhere('name', 'Large orders only');

        $this->assertSame('available', $available['status']);
        $this->assertSame('unavailable', $blocked['status']);
        $this->assertSame('minimum_order_not_met', $blocked['reason_code']);
    }

    private function store(): Store
    {
        $user = User::factory()->create();

        return Store::query()->create([
            'user_id' => $user->id,
            'name' => 'Diagnostic Store',
            'slug' => 'diagnostic-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
    }
}
