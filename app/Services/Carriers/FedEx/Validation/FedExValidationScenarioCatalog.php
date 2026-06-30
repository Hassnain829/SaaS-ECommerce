<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierApiEvent;
use App\Services\Carriers\FedEx\Support\FedExConfig;

final class FedExValidationScenarioCatalog
{
    public static function authorizationScenarios(): array
    {
        return [
            CarrierApiEvent::SCENARIO_AUTHORIZATION_PARENT => [
                'label' => 'Parent authorization',
                'action' => CarrierApiEvent::ACTION_PLATFORM_OAUTH_TOKEN,
                'grant_type' => 'client_credentials',
                'export_folder' => '01_parent_authorization',
            ],
            CarrierApiEvent::SCENARIO_AUTHORIZATION_CHILD => [
                'label' => 'Child authorization',
                'action' => CarrierApiEvent::ACTION_MERCHANT_OAUTH_TOKEN,
                'grant_type' => 'csp_credentials',
                'export_folder' => '02_child_authorization',
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function swedenPassthroughScenarios(): array
    {
        return [
            CarrierApiEvent::SCENARIO_REGISTRATION_SWEDEN_PASSTHROUGH_ADDRESS => [
                'label' => 'Sweden passthrough address validation',
                'action' => CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
                'case_key' => FedExValidationSwedenPassthroughSupport::CASE_KEY,
                'export_subfolder' => '01_address_validation',
            ],
            CarrierApiEvent::SCENARIO_AUTHORIZATION_SWEDEN_PASSTHROUGH_CHILD => [
                'label' => 'Sweden passthrough child authorization',
                'action' => CarrierApiEvent::ACTION_MERCHANT_OAUTH_TOKEN,
                'grant_type' => 'csp_credentials',
                'case_key' => FedExValidationSwedenPassthroughSupport::CASE_KEY,
                'export_subfolder' => '02_child_authorization',
            ],
        ];
    }

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
        return self::lockedShipScenarios()[$testCaseKey]['scenario_key']
            ?? self::globalShipScenarios()[$testCaseKey]['scenario_key']
            ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function globalShipScenarios(): array
    {
        $scenarios = [];

        foreach (FedExGlobalShipCaseCatalog::casesByRegion()[FedExGlobalShipCaseCatalog::REGION_CA] ?? [] as $case) {
            $key = (string) ($case['case_key'] ?? '');
            if ($key === '' || str_starts_with((string) ($case['service_type'] ?? ''), 'WORKBOOK_')) {
                continue;
            }

            $scenarios[$key] = [
                'scenario_key' => self::globalScenarioKey($key),
                'label_format' => (string) ($case['label_format'] ?? ''),
                'label_stock_type' => self::globalLabelStockType($key),
                'expected_packages' => (int) ($case['expected_packages'] ?? 1),
                'validation_region' => FedExGlobalShipCaseCatalog::REGION_CA,
                'transaction_representative' => (bool) ($case['transaction_representative'] ?? false),
            ];
        }

        return $scenarios;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function globalShipScenariosForRegion(string $region): array
    {
        return array_filter(
            self::globalShipScenarios(),
            static fn (array $meta): bool => strtoupper((string) ($meta['validation_region'] ?? '')) === strtoupper($region),
        );
    }

    public static function globalScenarioKey(string $testCaseKey): string
    {
        return match ($testCaseKey) {
            'IntegratorCA01' => 'ship_ca01_pdf',
            'IntegratorCA02' => 'ship_ca02_png',
            'IntegratorCA03' => 'ship_ca03_pdf',
            'IntegratorCA04' => 'ship_ca04_pdf',
            'IntegratorCA05' => 'ship_ca05_zplii',
            default => 'ship_'.strtolower(str_replace(['Integrator', '-'], ['', '_'], $testCaseKey)),
        };
    }

    private static function globalLabelStockType(string $testCaseKey): string
    {
        return match ($testCaseKey) {
            'IntegratorCA01', 'IntegratorCA03', 'IntegratorCA04' => 'PAPER_85X11_TOP_HALF_LABEL',
            'IntegratorCA02' => 'PAPER_4X6',
            'IntegratorCA05' => 'STOCK_4X6',
            default => 'PAPER_4X6',
        };
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function comprehensiveRateScenario(): array
    {
        return [
            CarrierApiEvent::SCENARIO_RATE_COMPREHENSIVE_QUOTE => [
                'scenario_key' => CarrierApiEvent::SCENARIO_RATE_COMPREHENSIVE_QUOTE,
                'label' => 'Comprehensive Rates & Transit Times',
                'method' => 'POST',
                'endpoint' => FedExConfig::COMPREHENSIVE_RATE_QUOTE_PATH,
                'export_folder' => FedExComprehensiveRateEvidenceService::EXPORT_FOLDER,
            ],
        ];
    }
}
