<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Services\Carriers\FedEx\Support\FedExConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use OpenSpout\Reader\XLSX\Reader;

class FedExTestCaseFixtureService
{
    public const CACHE_KEY = 'fedex.integrator.test_case_fixtures';

    public function __construct(
        private readonly FedExConfig $config,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function fixtures(): array
    {
        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $path = $this->resolveBaselinePath();
        $fixtures = $path !== null
            ? $this->parseBaselineXlsx($path)
            : $this->fallbackFixtures();

        $fixtures['baseline_available'] = $path !== null;
        $fixtures['baseline_path'] = $path;

        Cache::put(self::CACHE_KEY, $fixtures, now()->addDay());

        return $fixtures;
    }

    /**
     * @return array<string, mixed>
     */
    public function usValidationAccount(): array
    {
        $fixtures = $this->fixtures();

        return is_array($fixtures['us_validation_account'] ?? null)
            ? $fixtures['us_validation_account']
            : $this->fallbackFixtures()['us_validation_account'];
    }

    /**
     * @return array<string, mixed>
     */
    public function mfaInvoice(): array
    {
        $fixtures = $this->fixtures();

        return is_array($fixtures['mfa_invoice'] ?? null)
            ? $fixtures['mfa_invoice']
            : $this->fallbackFixtures()['mfa_invoice'];
    }

    public function baselineAvailable(): bool
    {
        return $this->resolveBaselinePath() !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function swedenMfaPassthroughAccount(): ?array
    {
        $fixtures = $this->fixtures();
        $fromWorkbook = is_array($fixtures['sweden_mfa_passthrough_account'] ?? null)
            ? $fixtures['sweden_mfa_passthrough_account']
            : null;

        if ($this->isCompleteSwedenFixture($fromWorkbook)) {
            return $this->normalizeSwedenFixture($fromWorkbook, (string) ($fromWorkbook['source'] ?? 'xlsx'));
        }

        $fromConfig = $this->swedenConfigFallback();
        if ($this->isCompleteSwedenFixture($fromConfig)) {
            return $this->normalizeSwedenFixture($fromConfig, 'config');
        }

        return null;
    }

    public function swedenPassthroughAvailable(): bool
    {
        return $this->swedenMfaPassthroughAccount() !== null;
    }

    private function resolveBaselinePath(): ?string
    {
        foreach ($this->config->testCaseBaselinePaths() as $path) {
            if (File::isFile($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseBaselineXlsx(string $path): array
    {
        $fixtures = $this->fallbackFixtures();
        $fixtures['source'] = 'xlsx';

        try {
            $reader = new Reader;
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                $name = strtolower((string) $sheet->getName());

                if (str_contains($name, 'americas_us')) {
                    $this->parseAmericasUsSheet($sheet, $fixtures);
                }

                if (str_contains($name, 'test account') || $name === 'mfa') {
                    $this->parseSwedenAccountSheet($sheet, $fixtures);
                }
            }

            $reader->close();
        } catch (\Throwable) {
            $fixtures['parse_warning'] = 'FedEx baseline XLSX could not be fully parsed. Using fallback US validation account.';
        }

        return $fixtures;
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackFixtures(): array
    {
        return [
            'source' => 'fallback',
            'us_validation_account' => [
                'account_number' => '700257037',
                'company_name' => 'FedEx US Validation Test Account',
                'address_line1' => '15 W 18TH ST FL 7',
                'city' => 'NEW YORK',
                'state' => 'NY',
                'postal_code' => '100114624',
                'country_code' => 'US',
            ],
            'mfa_default_pins' => ['234560', '234561', '234562', '234563', '234564'],
            'mfa_invoice' => [
                'number' => '234562278',
                'date' => '2020-09-14',
                'currency' => 'USD',
                'amount' => '125.50',
            ],
            'us_test_accounts' => ['700257037', '740561073'],
        ];
    }

    /**
     * @param  array<string, mixed>  $fixtures
     */
    private function parseAmericasUsSheet(mixed $sheet, array &$fixtures): void
    {
        $rows = [];
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = array_map(
                static fn ($cell) => trim((string) $cell->getValue()),
                iterator_to_array($row->getCells()),
            );
        }

        foreach ($rows as $row) {
            $joined = strtolower(implode(' ', $row));
            if (preg_match('/\b700257037\b/', $joined)) {
                $fixtures['us_validation_account']['account_number'] = '700257037';
            }
            if (preg_match('/\b740561073\b/', $joined)) {
                $fixtures['us_test_accounts'][] = '740561073';
            }
        }
    }

    /**
     * @param  array<string, mixed>  $fixtures
     */
    private function parseSwedenAccountSheet(mixed $sheet, array &$fixtures): void
    {
        foreach ($sheet->getRowIterator() as $row) {
            $cells = array_map(
                static fn ($cell) => trim((string) $cell->getValue()),
                iterator_to_array($row->getCells()),
            );
            $joined = strtolower(implode(' ', $cells));

            if (! str_contains($joined, 'se') && ! str_contains($joined, 'sweden') && ! preg_match('/9268\b/', $joined)) {
                continue;
            }

            if (preg_match('/\b(\d{9})\b/', implode(' ', $cells), $matches) && str_ends_with($matches[1], '9268')) {
                $parsedAddress = $this->parseSwedenParentheticalAddress($joined);
                $fixtures['sweden_mfa_passthrough_account'] = array_merge(
                    $fixtures['sweden_mfa_passthrough_account'] ?? [],
                    [
                        'source' => 'xlsx',
                        'case_key' => FedExValidationSwedenPassthroughSupport::CASE_KEY,
                        'account_number' => $matches[1],
                        'country_code' => 'SE',
                    ],
                    $parsedAddress,
                );
            }

            foreach ($cells as $index => $value) {
                $label = strtolower($value);
                $next = $cells[$index + 1] ?? null;
                if (! filled($next)) {
                    continue;
                }

                $target = &$fixtures['sweden_mfa_passthrough_account'];
                if (str_contains($label, 'customer') && str_contains($label, 'name')) {
                    $target['contact_name'] = $next;
                    $target['company_name'] = $next;
                } elseif (str_contains($label, 'address')) {
                    $target['address_line1'] = $next;
                } elseif ($label === 'city') {
                    $target['city'] = strtoupper($next);
                } elseif (str_contains($label, 'postal')) {
                    $target['postal_code'] = $next;
                }
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function swedenConfigFallback(): ?array
    {
        $config = $this->config->validationSwedenFixtureConfig();
        if ($config === []) {
            return null;
        }

        return [
            'source' => 'config',
            'case_key' => FedExValidationSwedenPassthroughSupport::CASE_KEY,
            'account_number' => $config['account_number'] ?? null,
            'company_name' => $config['customer_name'] ?? null,
            'contact_name' => $config['customer_name'] ?? null,
            'address_line1' => $config['address_line1'] ?? null,
            'address_line2' => $config['address_line2'] ?? null,
            'state' => strtoupper((string) ($config['state'] ?? '')),
            'city' => strtoupper((string) ($config['city'] ?? '')),
            'postal_code' => $config['postal_code'] ?? null,
            'country_code' => strtoupper((string) ($config['country_code'] ?? 'SE')),
            'residential' => false,
        ];
    }

    /**
     * Parse workbook format e.g. (HAGAGATAN 1, VI, STOCKHOLM, 11349, SE).
     * The second token is part of street line 1 (HAGAGATAN 1, VI), not stateOrProvinceCode.
     *
     * @return array<string, string>
     */
    private function parseSwedenParentheticalAddress(string $text): array
    {
        if (! preg_match('/\(([^)]+)\)/', $text, $matches)) {
            return [];
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $matches[1]))));

        if (count($parts) === 5 && strlen($parts[4]) === 2) {
            return [
                'address_line1' => $parts[0].', '.$parts[1],
                'city' => strtoupper($parts[2]),
                'postal_code' => $parts[3],
                'country_code' => strtoupper($parts[4]),
            ];
        }

        if (count($parts) === 4 && strlen($parts[3]) === 2) {
            return [
                'address_line1' => $parts[0],
                'city' => strtoupper($parts[1]),
                'postal_code' => $parts[2],
                'country_code' => strtoupper($parts[3]),
            ];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>|null  $fixture
     */
    private function isCompleteSwedenFixture(?array $fixture): bool
    {
        if ($fixture === null) {
            return false;
        }

        $accountNumber = preg_replace('/\D+/', '', (string) ($fixture['account_number'] ?? '')) ?? '';

        return strlen($accountNumber) === 9
            && str_ends_with($accountNumber, '9268')
            && filled($fixture['address_line1'] ?? null)
            && filled($fixture['city'] ?? null)
            && filled($fixture['postal_code'] ?? null)
            && strtoupper((string) ($fixture['country_code'] ?? '')) === 'SE';
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    private function normalizeSwedenFixture(array $fixture, string $source): array
    {
        $accountNumber = preg_replace('/\D+/', '', (string) ($fixture['account_number'] ?? '')) ?? '';
        $addressLine1 = trim((string) ($fixture['address_line1'] ?? ''));
        $stateSuffix = strtoupper(trim((string) ($fixture['state'] ?? '')));

        if ($stateSuffix !== '' && ! $this->swedenStreetLineIncludesSuffix($addressLine1, $stateSuffix)) {
            $addressLine1 = rtrim($addressLine1, ',').', '.$stateSuffix;
        }

        return [
            'source' => $source,
            'case_key' => FedExValidationSwedenPassthroughSupport::CASE_KEY,
            'account_number' => $accountNumber,
            'account_last4' => substr($accountNumber, -4),
            'company_name' => (string) ($fixture['company_name'] ?? $fixture['contact_name'] ?? 'FedEx Sweden Validation Customer'),
            'contact_name' => (string) ($fixture['contact_name'] ?? $fixture['company_name'] ?? 'FedEx Sweden Validation Customer'),
            'address_line1' => $addressLine1,
            'address_line2' => (string) ($fixture['address_line2'] ?? ''),
            'city' => strtoupper((string) ($fixture['city'] ?? 'STOCKHOLM')),
            'state' => '',
            'postal_code' => (string) ($fixture['postal_code'] ?? ''),
            'country_code' => 'SE',
            'residential' => false,
        ];
    }

    private function swedenStreetLineIncludesSuffix(string $addressLine1, string $suffix): bool
    {
        $normalizedLine = strtoupper(trim($addressLine1));
        $normalizedSuffix = strtoupper(trim($suffix));

        return str_contains($normalizedLine, ', '.$normalizedSuffix)
            || str_ends_with($normalizedLine, ','.$normalizedSuffix);
    }
}
