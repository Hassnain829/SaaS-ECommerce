<?php

namespace Tests\Unit;

use App\Services\Carriers\FedEx\Validation\FedExShipTestCaseFixtureService;
use Carbon\Carbon;
use Tests\TestCase;

class FedExSaturdayDeliveryShipDateTest extends TestCase
{
    public function test_next_saturday_delivery_friday_targets_fedex_near_future_window(): void
    {
        $service = app(FedExShipTestCaseFixtureService::class);
        $now = Carbon::parse('2026-06-30');

        $this->assertSame('2026-07-03', $service->nextValidFriday($now));
        $this->assertSame('2026-07-10', $service->nextSaturdayDeliveryFriday($now));
        $this->assertSame('Friday', Carbon::parse($service->nextSaturdayDeliveryFriday($now))->format('l'));
    }

    public function test_saturday_delivery_candidates_start_at_next_valid_friday(): void
    {
        $service = app(FedExShipTestCaseFixtureService::class);
        $now = Carbon::parse('2026-06-30');

        $this->assertSame([
            '2026-07-03',
            '2026-07-10',
            '2026-07-17',
            '2026-07-24',
        ], $service->saturdayDeliveryFridayCandidates($now));
    }
}
