<?php

namespace Tests\Unit;

use App\Services\Carriers\FedEx\Validation\FedExLabelArtifactValidator;
use Tests\Support\FedExShipTestEvidenceFactory;
use Tests\TestCase;

class FedExLabelArtifactValidatorTest extends TestCase
{
    public function test_valid_zpl_png_and_pdf_are_accepted(): void
    {
        $zpl = tempnam(sys_get_temp_dir(), 'zpl');
        $png = tempnam(sys_get_temp_dir(), 'png');
        $pdf = tempnam(sys_get_temp_dir(), 'pdf');

        file_put_contents($zpl, FedExShipTestEvidenceFactory::validZplBinary());
        file_put_contents($png, FedExShipTestEvidenceFactory::validPngBinary());
        file_put_contents($pdf, FedExShipTestEvidenceFactory::validPdfBinary());

        $this->assertTrue(FedExLabelArtifactValidator::isValid($zpl, 'ZPLII'));
        $this->assertTrue(FedExLabelArtifactValidator::isValid($png, 'PNG'));
        $this->assertTrue(FedExLabelArtifactValidator::isValid($pdf, 'PDF'));
    }

    public function test_invalid_binaries_are_rejected(): void
    {
        $bad = tempnam(sys_get_temp_dir(), 'bad');
        file_put_contents($bad, 'not-a-label');

        $this->assertFalse(FedExLabelArtifactValidator::isValid($bad, 'PDF'));
        $this->assertFalse(FedExLabelArtifactValidator::isValid($bad, 'PNG'));
        $this->assertFalse(FedExLabelArtifactValidator::isValid($bad, 'ZPLII'));
    }

    public function test_jpeg_printed_scans_are_accepted_by_validator(): void
    {
        $jpeg = tempnam(sys_get_temp_dir(), 'scan').'.jpg';
        // Minimal valid JPEG binary (1x1 pixel).
        file_put_contents($jpeg, hex2bin(
            'ffd8ffe000104a46494600010100000100010000ffdb004300080606070605080707070909080a0c140d0c0b0b0c1912130f141d1a1f1e1d1a1c1c20242e2720222c231c1c2837292c30313434341f27393d38323c2e333432ffdb0043010909090c0b0c180d0d1832211c213232323232323232323232323232323232323232323232323232323232323232323232323232323232323232323232323232ffc00011080001000103011100021100031101ffc40014000100000000000000000000000000000008ffc40014100100000000000000000000000000000000ffda000c0301000210031000003f00bf80ffd9'
        ));

        $result = FedExLabelArtifactValidator::validateScan($jpeg, 600);
        $this->assertTrue($result['valid'], (string) ($result['reason'] ?? 'unknown'));

        @unlink($jpeg);
    }
}
