<?php

namespace Tests\Unit;

use App\Models\CarrierAccount;
use App\Models\FedExValidationArtifact;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Validation\FedExValidationPreflightService;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

class FedExPrintedScanJpegAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierSeeder::class);
    }

    public function test_preflight_accepts_jpeg_printed_scans(): void
    {
        $owner = User::factory()->create();
        $store = Store::query()->create([
            'name' => 'JPEG Scan Store',
            'slug' => 'jpeg-scan-'.Str::random(6),
            'user_id' => $owner->id,
        ]);
        $fedEx = \App\Models\Carrier::query()->where('code', 'fedex')->firstOrFail();
        $account = CarrierAccount::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_model' => CarrierAccount::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
            'connection_type' => 'manual',
            'status' => 'enabled',
            'provider_account_number' => '700257037',
            'display_name' => 'JPEG Scan',
            'created_by' => $owner->id,
        ]);

        $relative = "fedex-validation/{$store->id}/uploads/scan-jpeg.jpg";
        $absolute = storage_path('app/'.$relative);
        File::ensureDirectoryExists(dirname($absolute));
        File::put($absolute, hex2bin(
            'ffd8ffe000104a46494600010100000100010000ffdb004300080606070605080707070909080a0c140d0c0b0b0c1912130f141d1a1f1e1d1a1c1c20242e2720222c231c1c2837292c30313434341f27393d38323c2e333432ffdb0043010909090c0b0c180d0d1832211c213232323232323232323232323232323232323232323232323232323232323232323232323232323232323232323232323232ffc00011080001000103011100021100031101ffc40014000100000000000000000000000000000008ffc40014100100000000000000000000000000000000ffda000c0301000210031000003f00bf80ffd9'
        ));

        $artifact = FedExValidationArtifact::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'scenario_key' => 'ship_us09_image_pdf',
            'test_case_key' => 'IntegratorUS09_IMAGE',
            'label_format' => 'PDF',
            'package_sequence' => 1,
            'artifact_type' => 'printed_scan_IntegratorUS09_IMAGE_1',
            'artifact_role' => FedExValidationArtifact::ROLE_PRINTED_SCAN,
            'label' => 'Scan JPEG',
            'file_path' => $relative,
            'mime_type' => 'image/jpeg',
            'file_size' => filesize($absolute),
            'sha256' => hash_file('sha256', $absolute),
            'scan_dpi' => 600,
            'metadata_json' => ['printed_scan_attestation' => true],
        ]);

        $method = new ReflectionMethod(FedExValidationPreflightService::class, 'scanArtifactStatus');
        $method->setAccessible(true);
        $status = $method->invoke(app(FedExValidationPreflightService::class), $artifact);

        $this->assertSame('passed', $status);
    }
}
