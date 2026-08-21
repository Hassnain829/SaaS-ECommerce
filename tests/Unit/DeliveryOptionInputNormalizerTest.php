<?php

namespace Tests\Unit;

use App\Models\ShippingMethod;
use App\Services\Delivery\DeliveryOptionInputNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class DeliveryOptionInputNormalizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_pricing_mode_handles_fixed_free_and_free_over(): void
    {
        $normalizer = new DeliveryOptionInputNormalizer;

        $free = $normalizer->applyPricingMode('free', ['flat_rate' => 9]);
        $this->assertSame(ShippingMethod::RATE_FREE, $free['rate_type']);
        $this->assertSame(0, $free['flat_rate']);

        $fixed = $normalizer->applyPricingMode('fixed', ['flat_rate' => 7.5]);
        $this->assertSame(ShippingMethod::RATE_FLAT, $fixed['rate_type']);
        $this->assertNull($fixed['free_over_amount']);

        $freeOver = $normalizer->applyPricingMode('free_over', ['flat_rate' => 4, 'free_over_amount' => 50]);
        $this->assertSame(ShippingMethod::RATE_FLAT, $freeOver['rate_type']);
        $this->assertSame(4, $freeOver['flat_rate']);
    }

    public function test_apply_simple_availability_preserves_mismatched_flags_until_resolved(): void
    {
        $normalizer = new DeliveryOptionInputNormalizer;
        $method = new ShippingMethod([
            'is_active' => true,
            'enabled_for_checkout' => false,
        ]);

        $request = Request::create('/', 'POST', ['resolve_flag_mismatch' => 'keep']);
        $validated = $normalizer->applySimpleAvailability($request, [], $method);

        $this->assertTrue($validated['is_active']);
        $this->assertFalse($validated['enabled_for_checkout']);
    }

    public function test_apply_simple_availability_syncs_both_flags_from_single_control(): void
    {
        $normalizer = new DeliveryOptionInputNormalizer;
        $method = new ShippingMethod([
            'is_active' => true,
            'enabled_for_checkout' => true,
        ]);

        $on = $normalizer->applySimpleAvailability(
            Request::create('/', 'POST', ['available_to_customers' => '1']),
            [],
            $method
        );
        $this->assertTrue($on['is_active']);
        $this->assertTrue($on['enabled_for_checkout']);

        $off = $normalizer->applySimpleAvailability(
            Request::create('/', 'POST', ['available_to_customers' => '0']),
            [],
            $method
        );
        $this->assertFalse($off['is_active']);
        $this->assertFalse($off['enabled_for_checkout']);
    }

    public function test_apply_simple_availability_resolves_mismatch_via_single_control(): void
    {
        $normalizer = new DeliveryOptionInputNormalizer;
        $method = new ShippingMethod([
            'is_active' => true,
            'enabled_for_checkout' => false,
        ]);

        $validated = $normalizer->applySimpleAvailability(
            Request::create('/', 'POST', ['available_to_customers' => '1']),
            [],
            $method
        );

        $this->assertTrue($validated['is_active']);
        $this->assertTrue($validated['enabled_for_checkout']);
    }
}
