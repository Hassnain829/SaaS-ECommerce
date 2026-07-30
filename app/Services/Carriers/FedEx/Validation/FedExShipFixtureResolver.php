<?php

namespace App\Services\Carriers\FedEx\Validation;

/**
 * Resolves US Package 6 and global regional ship fixtures by test case key.
 */
class FedExShipFixtureResolver
{
    public function __construct(
        private readonly FedExShipTestCaseFixtureService $usFixtures,
        private readonly FedExCanadaShipTestCaseFixtureService $canadaFixtures,
        private readonly FedExFreightLtlFixtureService $freightFixtures,
        private readonly FedExUs09EtdFixtureService $us09Fixtures,
        private readonly FedExConsolidationFixtureService $consolidationFixtures,
    ) {}

    /**
     * @return list<string>
     */
    public function usTestCaseKeys(): array
    {
        return array_values(array_unique(array_merge(
            $this->usFixtures->testCaseKeys(),
            $this->freightFixtures->testCaseKeys(),
            $this->us09Fixtures->testCaseKeys(),
            $this->consolidationFixtures->testCaseKeys(),
        )));
    }

    /**
     * @return list<string>
     */
    public function testCaseKeysForRegion(string $region): array
    {
        return match (strtoupper($region)) {
            FedExGlobalShipCaseCatalog::REGION_CA => $this->canadaFixtures->testCaseKeys(),
            default => [],
        };
    }

    public function supports(string $testCaseKey): bool
    {
        return $this->regionForCase($testCaseKey) !== null;
    }

    public function isFreightLtlCase(string $testCaseKey): bool
    {
        return in_array($testCaseKey, $this->freightFixtures->testCaseKeys(), true);
    }

    public function isUs09EtdCase(string $testCaseKey): bool
    {
        return in_array($testCaseKey, $this->us09Fixtures->testCaseKeys(), true);
    }

    public function isConsolidationCase(string $testCaseKey): bool
    {
        return in_array($testCaseKey, $this->consolidationFixtures->testCaseKeys(), true);
    }

    public function regionForCase(string $testCaseKey): ?string
    {
        if (in_array($testCaseKey, $this->usFixtures->testCaseKeys(), true)
            || in_array($testCaseKey, $this->freightFixtures->testCaseKeys(), true)
            || in_array($testCaseKey, $this->us09Fixtures->testCaseKeys(), true)
            || in_array($testCaseKey, $this->consolidationFixtures->testCaseKeys(), true)) {
            return 'US';
        }

        if (in_array($testCaseKey, $this->canadaFixtures->testCaseKeys(), true)) {
            return FedExGlobalShipCaseCatalog::REGION_CA;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function fixture(string $testCaseKey): array
    {
        if ($this->isFreightLtlCase($testCaseKey)) {
            return $this->freightFixtures->fixture($testCaseKey);
        }

        if ($this->isUs09EtdCase($testCaseKey)) {
            return $this->us09Fixtures->fixture($testCaseKey);
        }

        if ($this->isConsolidationCase($testCaseKey)) {
            return $this->consolidationFixtures->fixture($testCaseKey);
        }

        return match ($this->regionForCase($testCaseKey)) {
            'US' => $this->usFixtures->fixture($testCaseKey),
            FedExGlobalShipCaseCatalog::REGION_CA => $this->canadaFixtures->fixture($testCaseKey),
            default => abort(404, 'Unknown FedEx ship test case.'),
        };
    }

    public function lockedLabelFormat(string $testCaseKey): string
    {
        if ($this->isFreightLtlCase($testCaseKey)) {
            return $this->freightFixtures->lockedLabelFormat($testCaseKey);
        }

        if ($this->isUs09EtdCase($testCaseKey)) {
            return $this->us09Fixtures->lockedLabelFormat($testCaseKey);
        }

        if ($this->isConsolidationCase($testCaseKey)) {
            return 'PNG';
        }

        $region = $this->regionForCase($testCaseKey);

        if ($region === 'US') {
            return $this->usFixtures->lockedLabelFormat($testCaseKey);
        }

        $fixture = $this->fixture($testCaseKey);

        return strtoupper((string) ($fixture['label_format'] ?? abort(422, 'Unknown locked ship test case.')));
    }

    public function nextValidFriday(?\Carbon\Carbon $now = null): string
    {
        return $this->usFixtures->nextValidFriday($now);
    }

    public function nextSaturdayDeliveryFriday(?\Carbon\Carbon $now = null): string
    {
        return $this->usFixtures->nextSaturdayDeliveryFriday($now);
    }

    public function homeDeliveryPremiumDeliveryDate(string $shipDate): string
    {
        return $this->usFixtures->homeDeliveryPremiumDeliveryDate($shipDate);
    }
}
