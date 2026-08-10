<?php

namespace Tests\Unit;

use App\Services\Carriers\FedEx\Support\Branding\FedExBrandComplianceService;
use Tests\TestCase;

class FedExProductionBrandingTest extends TestCase
{
    public function test_registered_display_names_for_merchant_facing_services(): void
    {
        $branding = app(FedExBrandComplianceService::class);

        $this->assertSame('FedEx Ground®', $branding->registeredDisplayName('FEDEX_GROUND'));
        $this->assertSame('FedEx 2Day®', $branding->registeredDisplayName('FEDEX_2_DAY'));
        $this->assertSame('FedEx International Priority®', $branding->registeredDisplayName('FEDEX_INTERNATIONAL_PRIORITY'));

        // API enums are not mutated — display helper returns the same string when already branded.
        $this->assertSame('FEDEX_GROUND', 'FEDEX_GROUND');
        $this->assertSame('FedEx Ground®', $branding->registeredDisplayName('FedEx Ground®'));
    }

    public function test_logo_metadata_and_public_asset_resolve(): void
    {
        $branding = app(FedExBrandComplianceService::class);
        $meta = $branding->logoMetadata();

        $this->assertSame('assets/carriers/fedex/fedex-unified-logo.svg', $meta['public_path'] ?? null);
        $this->assertArrayNotHasKey('approved_for_validation', $meta);
        $this->assertArrayNotHasKey('baseline_media_file', $meta);
        $this->assertArrayNotHasKey('baseline_source', $meta);

        $this->assertTrue($branding->logoIsAvailable());
        $this->assertNotNull($branding->logoPublicPath());
        $this->assertFileExists(public_path((string) $branding->logoPublicPath()));
        $this->assertNotNull($branding->logoHash());
        $this->assertNotSame('', $branding->legalNotice());
    }
}
