<?php

namespace Tests\Unit;

use App\Services\Carriers\FedEx\Validation\FedExValidationQuickTestPresets;
use Tests\TestCase;

class FedExValidationQuickTestPresetsTest extends TestCase
{
    public function test_address_check_uses_baseline_us_validation_account(): void
    {
        $preset = app(FedExValidationQuickTestPresets::class)->addressCheck();

        $this->assertSame('15 W 18TH ST FL 7', $preset['address_line1']);
        $this->assertSame('NEW YORK', $preset['city']);
        $this->assertSame('NY', $preset['state']);
        $this->assertSame('100114624', $preset['postal_code']);
        $this->assertSame('US', $preset['country_code']);
    }

    public function test_ship_validate_preset_uses_locked_test_case(): void
    {
        $preset = app(FedExValidationQuickTestPresets::class)->shipValidate('IntegratorUS02');

        $this->assertSame('IntegratorUS02', $preset['test_case']);
    }

    public function test_ship_label_preset_uses_locked_format_from_fixture(): void
    {
        $preset = app(FedExValidationQuickTestPresets::class)->shipLabel('IntegratorUS04');

        $this->assertSame('IntegratorUS04', $preset['test_case']);
        $this->assertSame('PNG', $preset['label_format']);
    }
}
