<?php

namespace App\Services\Carriers\FedEx\Validation;

/**
 * Package 7A catalog for global territory ship validation cases.
 * Regional execution remains Package 7B–7E; US locked cases stay in Package 6.
 */
final class FedExGlobalShipCaseCatalog
{
    public const REGION_CA = 'CA';

    public const REGION_LAC = 'LAC';

    public const REGION_AMEA = 'AMEA';

    public const REGION_EU = 'EU';

    /**
     * @return list<string>
     */
    public static function regions(): array
    {
        return [
            self::REGION_CA,
            self::REGION_LAC,
            self::REGION_AMEA,
            self::REGION_EU,
        ];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function casesByRegion(): array
    {
        return [
            self::REGION_CA => [
                self::case('IntegratorCA01', 'FEDEX_EXPRESS_SAVER', 'PDF', 1, true, false),
                self::case('IntegratorCA02', 'PRIORITY_OVERNIGHT', 'PNG', 1, true, false),
                self::case('IntegratorCA03', 'FEDEX_INTERNATIONAL_PRIORITY', 'PDF', 1, false, false),
                self::case('IntegratorCA04', 'FEDEX_GROUND', 'PDF', 1, false, false),
                self::case('IntegratorCA05', 'FEDEX_GROUND', 'ZPLII', 1, true, false),
            ],
            self::REGION_LAC => [
                self::case('IntegratorLAC01', 'INTERNATIONAL_ECONOMY', 'PDF', 1, true, false),
                self::case('IntegratorLAC02', 'STANDARD_OVERNIGHT', 'ZPLII', 1, true, false),
                self::case('IntegratorLAC03', 'PRIORITY_OVERNIGHT', 'PDF', 1, false, false),
            ],
            self::REGION_AMEA => [
                self::case('AMEA-Integrator1', 'FEDEX_INTERNATIONAL_PRIORITY', 'PDF', 1, true, false),
                self::case('AMEA-Integrator2', 'INTERNATIONAL_FIRST', 'PNG', 1, true, false),
                self::case('AMEA-Integrator3', 'INTERNATIONAL_ECONOMY_FREIGHT', 'PDF', 1, false, false),
                self::case('AMEA-Integrator4', 'INTERNATIONAL_ECONOMY', 'PNG', 1, false, false),
                self::case('AMEA-Integrator5', 'FEDEX_INTERNATIONAL_PRIORITY', 'ZPLII', 1, true, false),
                self::case('AMEA-Integrator8', 'FEDEX_INTERNATIONAL_PRIORITY', 'PDF', 2, false, false),
                self::case('AMEA-Integrator10(ETD)', 'WORKBOOK_COMPOUND_ETD', 'PDF', 1, false, true),
            ],
            self::REGION_EU => [
                self::case('EU-Integrator1', 'FEDEX_REGIONAL_ECONOMY', 'PDF', 1, true, false),
                self::case('EU-Integrator2', 'FEDEX_INTERNATIONAL_PRIORITY', 'PNG', 1, true, false),
                self::case('EU-Integrator3', 'FEDEX_INTERNATIONAL_PRIORITY', 'ZPLII', 1, true, false),
            ],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public static function transactionRepresentatives(string $region): array
    {
        return match (strtoupper($region)) {
            self::REGION_CA => ['PDF' => 'IntegratorCA01', 'PNG' => 'IntegratorCA02', 'ZPLII' => 'IntegratorCA05'],
            self::REGION_LAC => ['PDF' => 'IntegratorLAC01', 'PNG' => null, 'ZPLII' => 'IntegratorLAC02'],
            self::REGION_AMEA => ['PDF' => 'AMEA-Integrator1', 'PNG' => 'AMEA-Integrator2', 'ZPLII' => 'AMEA-Integrator5'],
            self::REGION_EU => ['PDF' => 'EU-Integrator1', 'PNG' => 'EU-Integrator2', 'ZPLII' => 'EU-Integrator3'],
            default => ['PDF' => null, 'PNG' => null, 'ZPLII' => null],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function case(
        string $key,
        string $serviceType,
        string $labelFormat,
        int $packages,
        bool $transactionRepresentative,
        bool $compoundFlow,
    ): array {
        return [
            'case_key' => $key,
            'service_type' => $serviceType,
            'label_format' => $labelFormat,
            'expected_packages' => $packages,
            'transaction_representative' => $transactionRepresentative,
            'compound_flow' => $compoundFlow,
        ];
    }
}
