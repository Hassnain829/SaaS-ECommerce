<?php

namespace Tests\Unit;

use App\Services\Carriers\USPS\Support\USPSOAuthSubjectExtractor;
use PHPUnit\Framework\TestCase;

class USPSOAuthSubjectExtractorTest extends TestCase
{
    public function test_extracts_subject_from_token_response_sub_field(): void
    {
        $extractor = new USPSOAuthSubjectExtractor;

        $this->assertSame(
            'merchant-subject-123',
            $extractor->extractFromTokenResponse(['sub' => 'merchant-subject-123']),
        );
    }

    public function test_extracts_subject_from_id_token_payload(): void
    {
        $extractor = new USPSOAuthSubjectExtractor;
        $payload = base64_encode(json_encode(['sub' => 'jwt-subject-456'], JSON_THROW_ON_ERROR));
        $idToken = 'header.'.$payload.'.signature';

        $this->assertSame(
            'jwt-subject-456',
            $extractor->extractFromTokenResponse(['id_token' => $idToken]),
        );
    }

    public function test_does_not_treat_crid_as_subject(): void
    {
        $extractor = new USPSOAuthSubjectExtractor;

        $this->assertNull($extractor->extractFromUserInfo([
            'crid' => '49188300',
            'mail_owners' => [
                ['crid' => '49188300', 'mids' => ['903800001']],
            ],
        ]));
    }
}
