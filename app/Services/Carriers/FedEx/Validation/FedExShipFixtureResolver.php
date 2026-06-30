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
    ) {}

    /**
     * @return list<string>
     */
    public function usTestCaseKeys(): array
    {
        return $this->usFixtures->testCaseKeys();
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

    public function regionForCase(string $testCaseKey): ?string
    {
        if (in_array($testCaseKey, $this->usFixtures->testCaseKeys(), true)) {
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
        return match ($this->regionForCase($testCaseKey)) {
            'US' => $this->usFixtures->fixture($testCaseKey),
            FedExGlobalShipCaseCatalog::REGION_CA => $this->canadaFixtures->fixture($testCaseKey),
            default => abort(404, 'Unknown FedEx ship test case.'),
        };
    }

    public function lockedLabelFormat(string $testCaseKey): string
    {
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
