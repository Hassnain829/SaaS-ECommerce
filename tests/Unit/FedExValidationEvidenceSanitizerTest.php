<?php

namespace Tests\Unit;

use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceSanitizer;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FedExValidationEvidenceSanitizerTest extends TestCase
{
    private FedExValidationEvidenceSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sanitizer = app(FedExValidationEvidenceSanitizer::class);
    }

    public function test_redacts_nested_authorization_and_secrets(): void
    {
        $payload = [
            'headers' => [
                'Authorization' => 'Bearer secret-token-value',
                'accountAuthToken' => 'auth-token-value',
            ],
            'client_secret' => 'child-secret-a',
            'pin' => '123456',
            'nested' => [
                'customerPassword' => 'super-secret',
            ],
        ];

        $sanitized = $this->sanitizer->sanitize($payload);

        $this->assertSame('[REDACTED]', data_get($sanitized, 'headers.Authorization'));
        $this->assertSame('[REDACTED]', data_get($sanitized, 'headers.accountAuthToken'));
        $this->assertSame('[REDACTED]', data_get($sanitized, 'client_secret'));
        $this->assertSame('[REDACTED]', data_get($sanitized, 'pin'));
        $this->assertSame('[REDACTED]', data_get($sanitized, 'nested.customerPassword'));
    }

    public function test_redacts_label_base64_payloads(): void
    {
        $payload = [
            'output' => [
                'encodedLabel' => base64_encode(str_repeat('%PDF-1.4 test label body ', 20)),
            ],
        ];

        $sanitized = $this->sanitizer->sanitize($payload);
        $encoded = data_get($sanitized, 'output.encodedLabel');

        $this->assertIsArray($encoded);
        $this->assertTrue((bool) ($encoded['redacted'] ?? false));
    }

    public function test_redacts_child_key_and_customer_key_variants(): void
    {
        $payload = [
            'output' => [
                'child_Key' => 'secret-child-key',
                'customerKey' => 'secret-customer-key',
                'apiKey' => 'secret-api-key',
            ],
        ];

        $sanitized = $this->sanitizer->sanitize($payload);

        $this->assertSame('[REDACTED]', data_get($sanitized, 'output.child_Key'));
        $this->assertSame('[REDACTED]', data_get($sanitized, 'output.customerKey'));
        $this->assertSame('[REDACTED]', data_get($sanitized, 'output.apiKey'));
    }

    public function test_secret_scan_detects_local_windows_paths(): void
    {
        $blockers = $this->sanitizer->scanForSecrets([
            'note' => 'Saved at D:\\Hassnain\\Project\\evidence\\request.json',
        ]);

        $this->assertNotEmpty($blockers);
        $this->assertSame('local_path_detected', $blockers[0]['reason']);
    }

    public function test_staging_directory_scan_detects_secret_in_readme(): void
    {
        $staging = storage_path('app/fedex-validation/sanitizer-test');
        File::ensureDirectoryExists($staging);
        File::put($staging.'/README.md', 'bundle contains child-secret-a');

        $blockers = $this->sanitizer->scanStagingDirectory($staging, ['child-secret-a']);
        File::deleteDirectory($staging);

        $this->assertNotEmpty($blockers);
        $this->assertSame('known_secret_detected', $blockers[0]['reason']);
    }

    public function test_redacted_placeholder_does_not_block_scan(): void
    {
        $staging = storage_path('app/fedex-validation/sanitizer-redacted-test');
        File::ensureDirectoryExists($staging);
        File::put($staging.'/request.json', json_encode(['Authorization' => '[REDACTED]']));

        $blockers = $this->sanitizer->scanStagingDirectory($staging, ['child-secret-a']);
        File::deleteDirectory($staging);

        $this->assertEmpty($blockers);
    }

    public function test_staging_directory_scan_detects_secret_even_when_redacted_placeholder_present(): void
    {
        $staging = storage_path('app/fedex-validation/sanitizer-mixed-redaction-test');
        File::ensureDirectoryExists($staging);
        File::put($staging.'/request.json', json_encode([
            'access_token' => '[REDACTED]',
            'client_secret' => 'child-secret-a',
        ]));

        $blockers = $this->sanitizer->scanStagingDirectory($staging, ['child-secret-a']);
        File::deleteDirectory($staging);

        $this->assertNotEmpty($blockers);
        $this->assertSame('known_secret_detected', $blockers[0]['reason']);
    }

    public function test_staging_directory_scan_detects_bearer_even_when_redacted_bearer_present(): void
    {
        $staging = storage_path('app/fedex-validation/sanitizer-mixed-bearer-test');
        File::ensureDirectoryExists($staging);
        File::put($staging.'/request.json', json_encode([
            'Authorization' => 'Bearer [REDACTED]',
            'note' => 'Bearer real-token-value',
        ]));

        $blockers = $this->sanitizer->scanStagingDirectory($staging, []);
        File::deleteDirectory($staging);

        $this->assertNotEmpty($blockers);
        $this->assertSame('bearer_token_in_string', $blockers[0]['reason']);
    }

    public function test_secret_scan_detects_known_secret_values(): void
    {
        $blockers = $this->sanitizer->scanForSecrets(
            ['message' => 'unexpected child-secret-a in output'],
            ['child-secret-a'],
        );

        $this->assertNotEmpty($blockers);
        $this->assertSame('known_secret_detected', $blockers[0]['reason']);
    }

    public function test_staging_directory_scan_skips_binary_label_files(): void
    {
        $staging = storage_path('app/fedex-validation/sanitizer-binary-test');
        File::ensureDirectoryExists($staging.'/generated');
        File::put($staging.'/generated/label.pdf', 'binary label body contains child-secret-a');
        File::put($staging.'/request.json', json_encode(['status' => 'ok']));

        $blockers = $this->sanitizer->scanStagingDirectory($staging, ['child-secret-a']);
        File::deleteDirectory($staging);

        $this->assertEmpty($blockers);
    }

    public function test_preserves_shipping_charges_payment_object(): void
    {
        $payload = [
            'requestedShipment' => [
                'shippingChargesPayment' => [
                    'paymentType' => 'SENDER',
                    'payor' => [
                        'responsibleParty' => [
                            'accountNumber' => ['value' => '700257037'],
                        ],
                    ],
                ],
            ],
            'accountNumber' => ['value' => '700257037'],
        ];

        $sanitized = $this->sanitizer->sanitize($payload);

        $this->assertSame('SENDER', data_get($sanitized, 'requestedShipment.shippingChargesPayment.paymentType'));
        $this->assertIsArray(data_get($sanitized, 'requestedShipment.shippingChargesPayment'));
        $this->assertNotSame('[REDACTED]', data_get($sanitized, 'requestedShipment.shippingChargesPayment'));
        $this->assertIsArray(data_get($sanitized, 'requestedShipment.shippingChargesPayment.payor.responsibleParty.accountNumber'));
        $this->assertNotSame('[REDACTED]', data_get($sanitized, 'requestedShipment.shippingChargesPayment.payor.responsibleParty.accountNumber'));
        $this->assertSame('[REDACTED]', data_get($sanitized, 'requestedShipment.shippingChargesPayment.payor.responsibleParty.accountNumber.value'));
        $this->assertIsArray(data_get($sanitized, 'accountNumber'));
        $this->assertNotSame('[REDACTED]', data_get($sanitized, 'accountNumber'));
        $this->assertSame('[REDACTED]', data_get($sanitized, 'accountNumber.value'));
    }

    public function test_still_redacts_actual_pin_fields(): void
    {
        $payload = [
            'secureCodePin' => '123456',
            'verificationPin' => '654321',
            'shippingChargesPayment' => ['paymentType' => 'RECIPIENT'],
        ];

        $sanitized = $this->sanitizer->sanitize($payload);

        $this->assertSame('[REDACTED]', data_get($sanitized, 'secureCodePin'));
        $this->assertSame('[REDACTED]', data_get($sanitized, 'verificationPin'));
        $this->assertSame('RECIPIENT', data_get($sanitized, 'shippingChargesPayment.paymentType'));
    }
}
