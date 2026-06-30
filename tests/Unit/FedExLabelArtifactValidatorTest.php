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
}
