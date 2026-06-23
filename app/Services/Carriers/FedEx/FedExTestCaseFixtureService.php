<?php

namespace App\Services\Carriers\FedEx;

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

                if (! str_contains($name, 'americas_us')) {
                    continue;
                }

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
}
