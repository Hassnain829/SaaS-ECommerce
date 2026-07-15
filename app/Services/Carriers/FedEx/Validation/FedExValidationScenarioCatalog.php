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
            'IntegratorUS01' => [
                'scenario_key' => 'ship_us01_pdf',
                'label_format' => 'PDF',
                'label_stock_type' => 'PAPER_85X11_TOP_HALF_LABEL',
                'expected_packages' => 1,
                'export_folder' => '05a_ship_us01_pdf',
            ],
            'IntegratorUS02' => [
                'scenario_key' => 'ship_us02_zplii',
                'label_format' => 'ZPLII',
                'label_stock_type' => 'STOCK_4X6',
                'expected_packages' => 1,
                'export_folder' => '05_ship_us02_zplii',
            ],
            'IntegratorUS03' => [
                'scenario_key' => 'ship_us03_pdf',
                'label_format' => 'PDF',
                'label_stock_type' => 'PAPER_85X11_TOP_HALF_LABEL',
                'expected_packages' => 1,
                'export_folder' => '05c_ship_us03_pdf',
            ],
            'IntegratorUS04' => [
                'scenario_key' => 'ship_us04_png',
                'label_format' => 'PNG',
                'label_stock_type' => 'PAPER_4X6',
                'expected_packages' => 1,
                'export_folder' => '06_ship_us04_png',
            ],
            'IntegratorUS05' => [
                'scenario_key' => 'ship_us05_pdf_mps',
                'label_format' => 'PDF',
                'label_stock_type' => 'PAPER_85X11_TOP_HALF_LABEL',
                'expected_packages' => 2,
                'export_folder' => '07_ship_us05_pdf_mps',
            ],
            'IntegratorUS06' => [
                'scenario_key' => 'ship_us06_pdf',
                'label_format' => 'PDF',
                'label_stock_type' => 'PAPER_85X11_TOP_HALF_LABEL',
                'expected_packages' => 1,
                'export_folder' => '07a_ship_us06_pdf',
            ],
            'IntegratorUS07' => [
                'scenario_key' => 'ship_us07_pdf',
                'label_format' => 'PDF',
                'label_stock_type' => 'PAPER_85X11_TOP_HALF_LABEL',
                'expected_packages' => 1,
                'export_folder' => '07b_ship_us07_pdf',
            ],
            'IntegratorUS08' => [
                'scenario_key' => 'ship_us08_zplii',
                'label_format' => 'ZPLII',
                'label_stock_type' => 'STOCK_4X6',
                'expected_packages' => 1,
                'export_folder' => '07c_ship_us08_zplii',
                'api_family' => 'freight_ltl',
            ],
            'IntegratorUS09_IMAGE' => [
                'scenario_key' => 'ship_us09_image_pdf',
                'label_format' => 'PDF',
                'label_stock_type' => 'PAPER_85X11_TOP_HALF_LABEL',
                'expected_packages' => 1,
                'export_folder' => '07d_ship_us09_image_pdf',
                'api_family' => 'parcel_etd',
                'etd_mode' => 'image',
                'upload_scenario_keys' => [
                    'upload_us09_image_letterhead',
                    'upload_us09_image_signature',
                ],
            ],
            'IntegratorUS09_DOCUMENT' => [
                'scenario_key' => 'ship_us09_document_pdf',
                'label_format' => 'PDF',
                'label_stock_type' => 'PAPER_85X11_TOP_HALF_LABEL',
                'expected_packages' => 1,
                'export_folder' => '07e_ship_us09_document_pdf',
                'api_family' => 'parcel_etd',
                'etd_mode' => 'document',
                'upload_scenario_keys' => [
                    'upload_us09_document',
                ],
            ],
        ];
    }

    /**
     * IntegratorUS10 Consolidation / IPD — each step is an independent evidence requirement.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function lockedConsolidationScenarios(): array
    {
        return [
            'IntegratorUS10_CREATE_CONSOLIDATION' => [
                'scenario_key' => 'consolidation_us10_create',
                'operation' => 'create',
                'export_folder' => '08_ship_us10_ipd/01_create_consolidation',
                'api_family' => 'consolidation',
            ],
            'IntegratorUS10_ADD_SHIPMENT_1' => [
                'scenario_key' => 'consolidation_us10_add_shipment_1',
                'operation' => 'add_shipment',
                'shipment_sequence' => 1,
                'export_folder' => '08_ship_us10_ipd/02_add_shipment_1',
                'api_family' => 'consolidation',
            ],
            'IntegratorUS10_ADD_SHIPMENT_2' => [
                'scenario_key' => 'consolidation_us10_add_shipment_2',
                'operation' => 'add_shipment',
                'shipment_sequence' => 2,
                'export_folder' => '08_ship_us10_ipd/03_add_shipment_2',
                'api_family' => 'consolidation',
            ],
            'IntegratorUS10_ADD_SHIPMENT_3' => [
                'scenario_key' => 'consolidation_us10_add_shipment_3',
                'operation' => 'add_shipment',
                'shipment_sequence' => 3,
                'export_folder' => '08_ship_us10_ipd/04_add_shipment_3',
                'api_family' => 'consolidation',
            ],
            'IntegratorUS10_ADD_SHIPMENT_4' => [
                'scenario_key' => 'consolidation_us10_add_shipment_4',
                'operation' => 'add_shipment',
                'shipment_sequence' => 4,
                'export_folder' => '08_ship_us10_ipd/05_add_shipment_4',
                'api_family' => 'consolidation',
            ],
            'IntegratorUS10_ADD_SHIPMENT_5' => [
                'scenario_key' => 'consolidation_us10_add_shipment_5',
                'operation' => 'add_shipment',
                'shipment_sequence' => 5,
                'export_folder' => '08_ship_us10_ipd/06_add_shipment_5',
                'api_family' => 'consolidation',
            ],
            'IntegratorUS10_ADD_SHIPMENT_6' => [
                'scenario_key' => 'consolidation_us10_add_shipment_6',
                'operation' => 'add_shipment',
                'shipment_sequence' => 6,
                'export_folder' => '08_ship_us10_ipd/07_add_shipment_6',
                'api_family' => 'consolidation',
            ],
            'IntegratorUS10_CONFIRM_CONSOLIDATION' => [
                'scenario_key' => 'consolidation_us10_confirm',
                'operation' => 'confirm',
                'export_folder' => '08_ship_us10_ipd/08_confirm',
                'api_family' => 'consolidation',
            ],
            'IntegratorUS10_CONFIRM_RESULTS' => [
                'scenario_key' => 'consolidation_us10_confirm_results',
                'operation' => 'confirm_results',
                'export_folder' => '08_ship_us10_ipd/09_confirm_results',
                'api_family' => 'consolidation',
            ],
        ];
    }

    public static function isShipScenarioEnabled(string $testCaseKey): bool
    {
        if ($testCaseKey === 'IntegratorUS08') {
            // Archived / excluded from active validation unless both archival flags are intentionally enabled
            // for a future separate FedEx Freight integration.
            return filter_var(config('carriers.fedex.validation_us08_enabled', false), FILTER_VALIDATE_BOOL)
                && filter_var(config('carriers.fedex.freight_ltl_api_enabled', false), FILTER_VALIDATE_BOOL);
        }

        return true;
    }

    public static function us08ExclusionNote(): string
    {
        return app(\App\Services\Carriers\FedEx\Support\FedExConfig::class)->us08ExclusionNote();
    }

    public static function isConsolidationEnabled(): bool
    {
        return filter_var(config('carriers.fedex.validation_us10_enabled', false), FILTER_VALIDATE_BOOL);
    }

    public static function us10ExclusionNote(): string
    {
        return app(\App\Services\Carriers\FedEx\Support\FedExConfig::class)->us10ExclusionNote();
    }

    /**
     * Consolidation scenarios that still count toward validation readiness.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function requiredConsolidationScenarios(): array
    {
        if (! self::isConsolidationEnabled()) {
            return [];
        }

        return self::lockedConsolidationScenarios();
    }

    /**
     * Locked ship scenarios shown as active run cards (excludes archived IntegratorUS08 by default).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function activeLockedShipScenarios(): array
    {
        return array_filter(
            self::lockedShipScenarios(),
            static fn (array $meta, string $testCaseKey): bool => self::isShipScenarioEnabled($testCaseKey),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * Locked ship scenarios that still count toward validation readiness.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function requiredLockedShipScenarios(): array
    {
        return array_filter(
            self::lockedShipScenarios(),
            static fn (array $meta, string $testCaseKey): bool => self::isShipScenarioEnabled($testCaseKey),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public static function lockedLabelFormat(string $testCaseKey): ?string
    {
        return self::lockedShipScenarios()[$testCaseKey]['label_format'] ?? null;
    }

    public static function scenarioKeyForTestCase(string $testCaseKey): ?string
    {
        return self::lockedShipScenarios()[$testCaseKey]['scenario_key']
            ?? self::lockedConsolidationScenarios()[$testCaseKey]['scenario_key']
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
