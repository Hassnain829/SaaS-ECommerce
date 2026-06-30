<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Models\Store;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class FedExValidationLegacyBundleImporter
{
    /**
     * @var array<string, array{test_case_key: string, scenario_key: string, label_format: string, expected_packages: int}>
     */
    private const US_SHIP_FOLDERS = [
        '05_ship_us02_zplii' => [
            'test_case_key' => 'IntegratorUS02',
            'scenario_key' => 'ship_us02_zplii',
            'label_format' => 'ZPLII',
            'expected_packages' => 1,
        ],
        '06_ship_us04_png' => [
            'test_case_key' => 'IntegratorUS04',
            'scenario_key' => 'ship_us04_png',
            'label_format' => 'PNG',
            'expected_packages' => 1,
        ],
        '07_ship_us05_pdf_mps' => [
            'test_case_key' => 'IntegratorUS05',
            'scenario_key' => 'ship_us05_pdf_mps',
            'label_format' => 'PDF',
            'expected_packages' => 2,
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public function importUsShipEvidence(Store $store, CarrierAccount $account, string $bundleRoot): array
    {
        $bundleRoot = $this->resolveBundleRoot($bundleRoot);
        $results = [];

        foreach (self::US_SHIP_FOLDERS as $folder => $meta) {
            $scenarioDir = $bundleRoot.DIRECTORY_SEPARATOR.$folder;
            if (! is_dir($scenarioDir)) {
                $results[$meta['test_case_key']] = [
                    'imported' => false,
                    'reason' => 'folder_missing',
                ];

                continue;
            }

            $results[$meta['test_case_key']] = $this->importScenario(
                store: $store,
                account: $account,
                scenarioDir: $scenarioDir,
                meta: $meta,
            );
        }

        return [
            'bundle_root' => $bundleRoot,
            'validation_region' => 'US',
            'legacy_grandfathered' => true,
            'scenarios' => $results,
        ];
    }

    private function resolveBundleRoot(string $path): string
    {
        if (is_file($path) && str_ends_with(strtolower($path), '.zip')) {
            $extractTo = storage_path('app/fedex-validation/tmp-import-'.Str::uuid());
            File::ensureDirectoryExists($extractTo);
            $zip = new \ZipArchive();
            if ($zip->open($path) !== true) {
                throw new RuntimeException('Unable to open validation bundle ZIP.');
            }
            $zip->extractTo($extractTo);
            $zip->close();

            $entries = File::directories($extractTo);
            if (count($entries) === 1 && is_dir($entries[0].DIRECTORY_SEPARATOR.'05_ship_us02_zplii')) {
                return $entries[0];
            }

            if (is_dir($extractTo.DIRECTORY_SEPARATOR.'05_ship_us02_zplii')) {
                return $extractTo;
            }

            throw new RuntimeException('ZIP does not contain a recognized FedEx validation bundle layout.');
        }

        $normalized = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        if (is_dir($normalized.DIRECTORY_SEPARATOR.'05_ship_us02_zplii')) {
            return $normalized;
        }

        $childDirs = File::directories($normalized);
        foreach ($childDirs as $childDir) {
            if (is_dir($childDir.DIRECTORY_SEPARATOR.'05_ship_us02_zplii')) {
                return $childDir;
            }
        }

        throw new RuntimeException('Bundle path does not contain US ship validation folders.');
    }

    /**
     * @param  array{test_case_key: string, scenario_key: string, label_format: string, expected_packages: int}  $meta
     * @return array<string, mixed>
     */
    private function importScenario(Store $store, CarrierAccount $account, string $scenarioDir, array $meta): array
    {
        $requestPath = $scenarioDir.DIRECTORY_SEPARATOR.'request.json';
        $responsePath = $scenarioDir.DIRECTORY_SEPARATOR.'response.json';
        abort_unless(is_file($requestPath) && is_file($responsePath), 422, 'Missing request/response JSON for '.$meta['test_case_key'].'.');

        $requestEnvelope = json_decode((string) file_get_contents($requestPath), true);
        $responseEnvelope = json_decode((string) file_get_contents($responsePath), true);
        $requestMeta = is_array($requestEnvelope['meta'] ?? null) ? $requestEnvelope['meta'] : [];
        $responseMeta = is_array($responseEnvelope['meta'] ?? null) ? $responseEnvelope['meta'] : [];

        $httpStatus = (int) ($responseMeta['http_status'] ?? 0);
        if ($httpStatus !== 200) {
            return [
                'imported' => false,
                'reason' => 'bundle_http_not_successful',
                'http_status' => $httpStatus,
            ];
        }

        $requestBody = data_get($requestEnvelope, 'request.body', data_get($requestEnvelope, 'body'));
        $responseBody = data_get($responseEnvelope, 'response.body', data_get($responseEnvelope, 'body'));

        $event = CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'registration_session_id' => $account->registration_session_id,
            'provider' => 'fedex',
            'environment' => $account->environment,
            'action' => CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL,
            'scenario_key' => $meta['scenario_key'],
            'test_case_key' => $meta['test_case_key'],
            'label_format' => $meta['label_format'],
            'package_count' => $meta['expected_packages'],
            'validation_region' => 'US',
            'endpoint' => (string) ($requestMeta['endpoint'] ?? '/ship/v1/shipments'),
            'http_method' => strtoupper((string) ($requestMeta['method'] ?? 'POST')),
            'http_status' => $httpStatus,
            'fedex_transaction_id' => (string) ($responseMeta['fedex_transaction_id'] ?? data_get($responseBody, 'transactionId', '')),
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
            'request_summary' => [
                'import_source' => 'legacy_validation_bundle',
                'test_case' => $meta['test_case_key'],
                'validation_region' => 'US',
            ],
            'response_summary' => [
                'import_source' => 'legacy_validation_bundle',
                'legacy_grandfathered' => true,
                'original_event_id' => $requestMeta['event_id'] ?? null,
                'imported_at' => now()->toIso8601String(),
                'http_status' => $httpStatus,
                'fedex_transaction_id' => $responseMeta['fedex_transaction_id'] ?? null,
                'canonical_ready' => true,
            ],
            'request_body_encrypted' => is_array($requestBody) ? $requestBody : [],
            'response_body_encrypted' => is_array($responseBody) ? $responseBody : [],
            'evidence_recorded_at' => now(),
        ]);

        $labelArtifacts = $this->importArtifacts(
            store: $store,
            account: $account,
            event: $event,
            meta: $meta,
            sourceDir: $scenarioDir.DIRECTORY_SEPARATOR.'generated',
            role: FedExValidationArtifact::ROLE_GENERATED_LABEL,
        );

        $scanArtifacts = $this->importArtifacts(
            store: $store,
            account: $account,
            event: $event,
            meta: $meta,
            sourceDir: $scenarioDir.DIRECTORY_SEPARATOR.'printed_scans',
            role: FedExValidationArtifact::ROLE_PRINTED_SCAN,
            scanDefaults: ['scan_dpi' => 600, 'printed_scan_attestation' => true],
        );

        return [
            'imported' => true,
            'event_id' => $event->id,
            'generated_labels' => count($labelArtifacts),
            'printed_scans' => count($scanArtifacts),
        ];
    }

    /**
     * @param  array{test_case_key: string, scenario_key: string, label_format: string, expected_packages: int}  $meta
     * @param  array<string, mixed>  $scanDefaults
     * @return list<FedExValidationArtifact>
     */
    private function importArtifacts(
        Store $store,
        CarrierAccount $account,
        CarrierApiEvent $event,
        array $meta,
        string $sourceDir,
        string $role,
        array $scanDefaults = [],
    ): array {
        if (! is_dir($sourceDir)) {
            return [];
        }

        $artifacts = [];
        $files = collect(File::files($sourceDir))
            ->sortBy(fn (\SplFileInfo $file): string => $file->getFilename())
            ->values();

        foreach ($files as $index => $file) {
            $sequence = $this->resolvePackageSequence($file->getFilename(), $index + 1);
            $binary = file_get_contents($file->getPathname()) ?: '';
            if ($binary === '') {
                continue;
            }

            $extension = strtolower($file->getExtension());
            $relativeDir = "fedex-validation/{$store->id}/legacy-import";
            $filename = Str::slug($meta['test_case_key']).'-'.$role.'-pkg'.$sequence.'-'.now()->format('YmdHis').'.'.$extension;
            $relativePath = $relativeDir.'/'.$filename;
            $absolutePath = storage_path('app/'.$relativePath);
            File::ensureDirectoryExists(dirname($absolutePath));
            File::put($absolutePath, $binary);

            $artifacts[] = FedExValidationArtifact::query()->create([
                'store_id' => $store->id,
                'carrier_account_id' => $account->id,
                'registration_session_id' => $account->registration_session_id,
                'carrier_api_event_id' => $event->id,
                'environment' => $account->environment,
                'artifact_type' => $role.'_'.$meta['test_case_key'].'_'.$sequence,
                'scenario_key' => $meta['scenario_key'],
                'test_case_key' => $meta['test_case_key'],
                'label_format' => $meta['label_format'],
                'package_sequence' => $sequence,
                'artifact_role' => $role,
                'label' => $meta['test_case_key'].' · '.$role.' · package '.$sequence,
                'original_filename' => $file->getFilename(),
                'mime_type' => mime_content_type($absolutePath) ?: null,
                'file_size' => strlen($binary),
                'sha256' => hash('sha256', $binary),
                'scan_dpi' => $role === FedExValidationArtifact::ROLE_PRINTED_SCAN
                    ? (int) ($scanDefaults['scan_dpi'] ?? 600)
                    : null,
                'file_path' => $relativePath,
                'metadata_json' => $role === FedExValidationArtifact::ROLE_PRINTED_SCAN
                    ? [
                        'legacy_import' => true,
                        'printed_scan_attestation' => (bool) ($scanDefaults['printed_scan_attestation'] ?? true),
                        'scan_dpi_claimed' => (int) ($scanDefaults['scan_dpi'] ?? 600),
                    ]
                    : ['legacy_import' => true],
            ]);
        }

        return $artifacts;
    }

    private function resolvePackageSequence(string $filename, int $fallback): int
    {
        if (preg_match('/package-(\d+)/i', $filename, $matches) === 1) {
            return max(1, (int) $matches[1]);
        }

        return $fallback;
    }
}
