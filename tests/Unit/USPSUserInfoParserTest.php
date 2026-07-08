<?php

namespace Tests\Unit;

use App\Services\Carriers\USPS\Support\USPSUserInfoParser;
use PHPUnit\Framework\TestCase;

class USPSUserInfoParserTest extends TestCase
{
    public function test_extracts_identifiers_from_mail_owners_and_payment_accounts(): void
    {
        $parser = new USPSUserInfoParser;

        $extracted = $parser->extractIdentifiers([
            'mail_owners' => [
                [
                    'crid' => '49188300',
                    'mids' => ['903800001', '903800002'],
                ],
            ],
            'payment_accounts' => [
                'accounts' => [
                    ['account_number' => '1000445839'],
                ],
            ],
        ]);

        $this->assertSame(['49188300'], $extracted['crids']);
        $this->assertSame(['903800001', '903800002'], $extracted['mids']);
        $this->assertSame(['1000445839'], $extracted['epas']);
    }

    public function test_missing_mail_owners_is_inconclusive_not_success(): void
    {
        $parser = new USPSUserInfoParser;

        $result = $parser->validateAgainstMerchantAccount(
            extracted: ['crids' => [], 'mids' => [], 'epas' => []],
            expectedCrid: '49188300',
            expectedMid: '903800001',
            expectedEpa: '1000445839',
        );

        $this->assertFalse($result['matched']);
        $this->assertTrue($result['inconclusive']);
        $this->assertSame('verification_inconclusive', $result['code']);
    }

    public function test_mismatching_crid_fails_verification(): void
    {
        $parser = new USPSUserInfoParser;

        $result = $parser->validateAgainstMerchantAccount(
            extracted: ['crids' => ['99999999'], 'mids' => ['903800001'], 'epas' => ['1000445839']],
            expectedCrid: '49188300',
            expectedMid: '903800001',
            expectedEpa: '1000445839',
        );

        $this->assertFalse($result['matched']);
        $this->assertSame('identifier_mismatch', $result['code']);
    }
}
