<?php

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\FedExValidationEvidenceExporter;
use App\Services\Carriers\FedEx\FedExValidationEvidenceSanitizer;
use Database\Seeders\CarrierSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->call('migrate:fresh', ['--force' => true]);
$kernel->call('db:seed', ['--class' => CarrierSeeder::class, '--force' => true]);

config([
    'carriers.fedex.enabled' => true,
    'carriers.fedex.integrator_model_a_enabled' => true,
    'carriers.fedex.validation_mode_enabled' => true,
]);

$owner = User::factory()->create([
    'email' => 'zip-inspect-owner@example.test',
    'role_id' => Role::firstOrCreate(['name' => 'user'])->id,
]);
$store = Store::query()->create([
    'user_id' => $owner->id,
    'name' => 'ZIP Inspect Store',
    'slug' => 'zip-inspect-'.Str::random(6),
    'currency' => 'USD',
    'timezone' => 'UTC',
    'category' => 'physical',
    'settings' => [],
    'onboarding_completed' => true,
]);
$store->members()->syncWithoutDetaching([$owner->id => ['role' => Store::ROLE_OWNER]]);

Location::query()->create([
    'store_id' => $store->id,
    'name' => 'Main warehouse',
    'type' => Location::TYPE_WAREHOUSE,
    'address_line1' => '1751 THOMPSON ST',
    'city' => 'AURORA',
    'state' => 'OH',
    'postal_code' => '44202',
    'country_code' => 'US',
    'is_default' => true,
    'is_active' => true,
    'fulfills_online_orders' => true,
]);

$fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();
$account = CarrierAccount::query()->create(array_merge([
    'store_id' => $store->id,
    'carrier_id' => $fedEx->id,
    'provider' => CarrierAccount::PROVIDER_FEDEX,
    'display_name' => 'FedEx integrator account',
    'provider_account_number' => '700257037',
    'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
    'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
    'status' => CarrierAccount::STATUS_ENABLED,
], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));
$account->setCredentials(['customer_key' => 'child-key-a', 'customer_password' => 'child-secret-a']);
$account->save();

foreach ([
    CarrierApiEvent::ACTION_FEDEX_ADDRESS_VALIDATION => 'address_validation',
    CarrierApiEvent::ACTION_FEDEX_SERVICE_AVAILABILITY => 'service_availability',
] as $action => $scenario) {
    CarrierApiEvent::query()->create([
        'store_id' => $store->id,
        'carrier_account_id' => $account->id,
        'provider' => CarrierAccount::PROVIDER_FEDEX,
        'action' => $action,
        'scenario_key' => $scenario,
        'status' => CarrierApiEvent::STATUS_SUCCEEDED,
        'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
        'http_status' => 200,
        'request_body_encrypted' => ['baseline' => true],
        'response_body_encrypted' => ['output' => []],
    ]);
}

CarrierApiEvent::query()->create([
    'store_id' => $store->id,
    'carrier_account_id' => $account->id,
    'provider' => CarrierAccount::PROVIDER_FEDEX,
    'action' => CarrierApiEvent::ACTION_FEDEX_RATE_QUOTE,
    'scenario_key' => 'rate_quote',
    'status' => CarrierApiEvent::STATUS_FAILED,
    'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
    'http_status' => 403,
    'error_code' => 'fedex_authorization_blocked',
    'request_body_encrypted' => ['rateRequestControlParameters' => ['returnTransitTimes' => true]],
    'response_body_encrypted' => ['errors' => [['code' => 'FORBIDDEN.ERROR']]],
]);

$us05Event = CarrierApiEvent::query()->create([
    'store_id' => $store->id,
    'carrier_account_id' => $account->id,
    'provider' => CarrierAccount::PROVIDER_FEDEX,
    'action' => CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL,
    'scenario_key' => 'ship_us05_pdf_mps',
    'test_case_key' => 'IntegratorUS05',
    'label_format' => 'PDF',
    'status' => CarrierApiEvent::STATUS_SUCCEEDED,
    'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
    'http_status' => 200,
    'request_body_encrypted' => ['requestedShipment' => ['totalPackageCount' => 2]],
    'response_body_encrypted' => ['output' => ['transactionShipments' => []]],
]);

foreach ([1, 2] as $packageSequence) {
    $labelRelative = "fedex-validation/{$store->id}/labels/us05-package-{$packageSequence}.pdf";
    $labelAbsolute = storage_path('app/'.$labelRelative);
    File::ensureDirectoryExists(dirname($labelAbsolute));
    File::put($labelAbsolute, '%PDF-1.4 us05 label '.$packageSequence);

    FedExValidationArtifact::query()->create([
        'store_id' => $store->id,
        'carrier_account_id' => $account->id,
        'carrier_api_event_id' => $us05Event->id,
        'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
        'scenario_key' => 'ship_us05_pdf_mps',
        'test_case_key' => 'IntegratorUS05',
        'label_format' => 'PDF',
        'package_sequence' => $packageSequence,
        'artifact_type' => 'ship_label_pdf',
        'artifact_role' => FedExValidationArtifact::ROLE_GENERATED_LABEL,
        'label' => 'IntegratorUS05 package '.$packageSequence,
        'file_path' => $labelRelative,
        'mime_type' => 'application/pdf',
        'file_size' => 24,
        'sha256' => hash('sha256', '%PDF-1.4 us05 label '.$packageSequence),
    ]);

    $scanRelative = "fedex-validation/{$store->id}/uploads/us05-scan-{$packageSequence}.pdf";
    $scanAbsolute = storage_path('app/'.$scanRelative);
    File::ensureDirectoryExists(dirname($scanAbsolute));
    File::put($scanAbsolute, '%PDF-1.4 us05 scan '.$packageSequence);

    FedExValidationArtifact::query()->create([
        'store_id' => $store->id,
        'carrier_account_id' => $account->id,
        'carrier_api_event_id' => $us05Event->id,
        'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
        'scenario_key' => 'ship_us05_pdf_mps',
        'test_case_key' => 'IntegratorUS05',
        'label_format' => 'PDF',
        'package_sequence' => $packageSequence,
        'artifact_type' => 'printed_scan_IntegratorUS05_'.$packageSequence,
        'artifact_role' => FedExValidationArtifact::ROLE_PRINTED_SCAN,
        'label' => 'Scan IntegratorUS05 package '.$packageSequence,
        'file_path' => $scanRelative,
        'mime_type' => 'application/pdf',
        'file_size' => 23,
        'sha256' => hash('sha256', '%PDF-1.4 us05 scan '.$packageSequence),
        'scan_dpi' => 600,
    ]);
}

$zipPath = app(FedExValidationEvidenceExporter::class)->exportDiagnostic($store, $account, region: 'US');
$zip = new ZipArchive;
$zip->open($zipPath);

$paths = [];
$allText = '';
for ($i = 0; $i < $zip->numFiles; $i++) {
    $path = (string) $zip->getNameIndex($i);
    $paths[] = $path;
    if (preg_match('/\.(json|md|txt)$/i', $path)) {
        $allText .= (string) $zip->getFromIndex($i)."\n";
    }
}
sort($paths);

$readme = (string) $zip->getFromName('FedEx_Integrator_Validation_BaasPlatformFedExSandbox/README.md');
$preflightReport = (string) $zip->getFromName('FedEx_Integrator_Validation_BaasPlatformFedExSandbox/preflight-report.json');
$preflightData = json_decode($preflightReport, true) ?: [];
$rateCheck = collect($preflightData['checks'] ?? [])->firstWhere('key', 'rate_quote');
$us05Generated = collect($paths)->filter(fn (string $path): bool => str_contains($path, '/07_ship_us05_pdf_mps/generated/'))->values();
$zip->close();

$sanitizer = app(FedExValidationEvidenceSanitizer::class);
$secretScan = $sanitizer->scanForSecrets($allText, ['child-secret-a', 'child-key-a']);

$expectedFolders = [
    'FedEx_Integrator_Validation_BaasPlatformFedExSandbox/02_address_validation/',
    'FedEx_Integrator_Validation_BaasPlatformFedExSandbox/03_service_availability/',
    'FedEx_Integrator_Validation_BaasPlatformFedExSandbox/04_rates/',
    'FedEx_Integrator_Validation_BaasPlatformFedExSandbox/07_ship_us05_pdf_mps/generated/',
    'FedEx_Integrator_Validation_BaasPlatformFedExSandbox/07_ship_us05_pdf_mps/printed_scans/',
];

$checks = [
    'zip_exists' => is_file($zipPath),
    'paths_count_gt_zero' => count($paths) > 0,
    'readme_incomplete' => str_contains($readme, 'INCOMPLETE') && str_contains($readme, 'NOT READY FOR FEDEX SUBMISSION'),
    'rate_403_blocks_preflight' => is_array($rateCheck)
        && ($rateCheck['status'] ?? null) === 'blocked'
        && str_contains((string) ($rateCheck['explanation'] ?? ''), 'Blocked — FedEx entitlement pending'),
    'no_event_null' => ! preg_match('/"event"\s*:\s*null/', $allText),
    'no_child_secret' => ! str_contains($allText, 'child-secret-a'),
    'no_child_key' => ! str_contains($allText, 'child-key-a'),
    'no_local_windows_path' => ! preg_match('/[A-Za-z]:\\\\[^\s"\']+/i', $allText),
    'no_storage_path' => ! preg_match('#/storage/app/[^\s"\']+#', $allText),
    'sanitizer_scan_clean' => $secretScan === [],
    'has_04_rates' => collect($paths)->contains(fn (string $path): bool => str_contains($path, '/04_rates/')),
    'has_preflight_report' => collect($paths)->contains(fn (string $path): bool => str_ends_with($path, 'preflight-report.json')),
    'us05_package_1_label' => $us05Generated->contains(fn (string $path): bool => str_contains($path, 'package-1.pdf')),
    'us05_package_2_label' => $us05Generated->contains(fn (string $path): bool => str_contains($path, 'package-2.pdf')),
    'deterministic_folders' => collect($expectedFolders)->every(
        fn (string $folder): bool => collect($paths)->contains(fn (string $path): bool => str_starts_with($path, $folder))
    ),
];

echo "Diagnostic ZIP: {$zipPath}\n";
echo 'Paths ('.count($paths)."):\n";
foreach ($paths as $path) {
    echo "  - {$path}\n";
}
echo "\nInspection checks:\n";
foreach ($checks as $name => $passed) {
    echo '  '.($passed ? 'PASS' : 'FAIL')." {$name}\n";
}

if ($secretScan !== []) {
    echo "\nSecret scan blockers:\n";
    foreach ($secretScan as $blocker) {
        echo "  - {$blocker['path']}: {$blocker['reason']}\n";
    }
}

exit(collect($checks)->contains(false) ? 1 : 0);
