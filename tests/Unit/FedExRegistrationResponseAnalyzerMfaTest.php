<?php

namespace Tests\Unit;

use App\Services\Carriers\FedEx\FedExRegistrationResponseAnalyzer;
use Tests\TestCase;

class FedExRegistrationResponseAnalyzerMfaTest extends TestCase
{
    public function test_sandbox_mfa_shape_parses_secure_code_and_invoice(): void
    {
        $analyzer = app(FedExRegistrationResponseAnalyzer::class);
        $data = [
            'transactionId' => 'fedex-reg-mfa-txn-1',
            'output' => [
                'mfaOptions' => [
                    [
                        'accountAuthToken' => 'fedex-account-auth-token-test',
                        'mfaRequired' => true,
                        'phoneNumber' => '***-***-3021',
                        'options' => [
                            'invoice' => 'INVOICE',
                            'secureCode' => ['SMS', 'EMAIL', 'CALL'],
                        ],
                    ],
                ],
            ],
        ];

        $options = $analyzer->parseSanitizedMfaOptions($data);
        $rawKeys = array_column($options, 'raw_key');

        $this->assertEqualsCanonicalizing(['sms', 'email', 'call', 'invoice'], $rawKeys);
        $this->assertNotNull($analyzer->extractAccountAuthToken($data));
        $this->assertSame('***-***-3021', $analyzer->extractMfaDestinationMasked($data));
    }
}
