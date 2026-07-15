<?php

namespace Tests\Unit;

use App\Services\Carriers\Core\CarrierApiEventLogger;
use App\Services\Carriers\FedEx\Validation\FedExSensitiveFieldClassifier;
use Tests\TestCase;

class FedExSensitiveFieldClassifierTest extends TestCase
{
    public function test_shipping_charges_payment_is_not_sensitive(): void
    {
        $this->assertFalse(FedExSensitiveFieldClassifier::isSensitiveKey('shippingChargesPayment'));
        $this->assertFalse(FedExSensitiveFieldClassifier::isSensitiveKey('paymentType'));
        $this->assertFalse(FedExSensitiveFieldClassifier::isSensitiveKey('token_type'));
    }

    public function test_actual_sensitive_keys_are_classified(): void
    {
        $this->assertTrue(FedExSensitiveFieldClassifier::isSensitiveKey('pin'));
        $this->assertTrue(FedExSensitiveFieldClassifier::isSensitiveKey('access_token'));
        $this->assertTrue(FedExSensitiveFieldClassifier::isSensitiveKey('client_secret'));
        $this->assertTrue(FedExSensitiveFieldClassifier::isSensitiveKey('verificationPin'));
        $this->assertTrue(FedExSensitiveFieldClassifier::isSensitiveKey('accountNumber'));
        $this->assertTrue(FedExSensitiveFieldClassifier::isAccountNumberKey('accountNumber'));
        $this->assertTrue(FedExSensitiveFieldClassifier::isAccountNumberKey('account_number'));
    }

    public function test_event_logger_preserves_shipping_charges_payment_in_summary(): void
    {
        $logger = app(CarrierApiEventLogger::class);

        $masked = $logger->maskSummary([
            'requestedShipment' => [
                'shippingChargesPayment' => [
                    'paymentType' => 'SENDER',
                ],
            ],
            'token_type' => 'bearer',
            'access_token' => 'secret-value',
        ]);

        $this->assertSame('SENDER', data_get($masked, 'requestedShipment.shippingChargesPayment.paymentType'));
        $this->assertSame('bearer', data_get($masked, 'token_type'));
        $this->assertSame('[redacted]', data_get($masked, 'access_token'));
    }

    public function test_event_logger_preserves_fedex_error_code_but_redacts_authorization_and_secrets(): void
    {
        $logger = app(CarrierApiEventLogger::class);

        $masked = $logger->maskSummary([
            'errors' => [[
                'code' => 'INVALID.INPUT.EXCEPTION',
                'message' => 'Invalid field value in the input',
                'field' => 'address.residential',
            ]],
            'authorizationCode' => 'auth-code-secret',
            'authorization_code' => 'auth-code-secret-2',
            'access_token' => 'access-secret',
            'refresh_token' => 'refresh-secret',
            'client_secret' => 'client-secret',
            'pin' => '123456',
            'password' => 'pw-secret',
            'paymentAuthorizationToken' => 'pay-token',
        ]);

        $this->assertSame('INVALID.INPUT.EXCEPTION', data_get($masked, 'errors.0.code'));
        $this->assertSame('Invalid field value in the input', data_get($masked, 'errors.0.message'));
        $this->assertSame('[redacted]', data_get($masked, 'authorizationCode'));
        $this->assertSame('[redacted]', data_get($masked, 'authorization_code'));
        $this->assertSame('[redacted]', data_get($masked, 'access_token'));
        $this->assertSame('[redacted]', data_get($masked, 'refresh_token'));
        $this->assertSame('[redacted]', data_get($masked, 'client_secret'));
        $this->assertSame('[redacted]', data_get($masked, 'pin'));
        $this->assertSame('[redacted]', data_get($masked, 'password'));
        $this->assertSame('[redacted]', data_get($masked, 'paymentAuthorizationToken'));
    }
}
