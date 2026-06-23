<?php

namespace App\Services\Carriers\FedEx\Validation;

final class FedExValidationScenarioCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function registrationScenarios(): array
    {
        return [
            'registration_address_validation' => ['label' => 'Address/account validation', 'mfa_method' => null],
            'registration_invoice_validation' => ['label' => 'Invoice validation', 'mfa_method' => 'invoice'],
            'registration_pin_generation_sms' => ['label' => 'SMS PIN generation', 'mfa_method' => 'sms'],
            'registration_pin_validation_sms' => ['label' => 'SMS PIN validation', 'mfa_method' => 'sms'],
            'registration_pin_generation_email' => ['label' => 'Email PIN generation', 'mfa_method' => 'email'],
            'registration_pin_validation_email' => ['label' => 'Email PIN validation', 'mfa_method' => 'email'],
            'registration_pin_generation_call' => ['label' => 'Phone-call PIN generation', 'mfa_method' => 'call'],
            'registration_pin_validation_call' => ['label' => 'Phone-call PIN validation', 'mfa_method' => 'call'],
            'registration_child_credentials_generated' => ['label' => 'Child credential generation', 'mfa_method' => null],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function lockedShipScenarios(): array
    {
        return [
            'IntegratorUS02' => [
                'scenario_key' => 'ship_us02_zplii',
                'label_format' => 'ZPLII',
                'label_stock_type' => 'STOCK_4X6',
                'expected_packages' => 1,
            ],
            'IntegratorUS04' => [
                'scenario_key' => 'ship_us04_png',
                'label_format' => 'PNG',
                'label_stock_type' => 'PAPER_4X6',
                'expected_packages' => 1,
            ],
            'IntegratorUS05' => [
                'scenario_key' => 'ship_us05_pdf_mps',
                'label_format' => 'PDF',
                'label_stock_type' => 'PAPER_85X11_TOP_HALF_LABEL',
                'expected_packages' => 2,
            ],
        ];
    }

    public static function lockedLabelFormat(string $testCaseKey): ?string
    {
        return self::lockedShipScenarios()[$testCaseKey]['label_format'] ?? null;
    }

    public static function scenarioKeyForTestCase(string $testCaseKey): ?string
    {
        return self::lockedShipScenarios()[$testCaseKey]['scenario_key'] ?? null;
    }
}
