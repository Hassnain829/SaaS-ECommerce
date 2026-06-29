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
}
