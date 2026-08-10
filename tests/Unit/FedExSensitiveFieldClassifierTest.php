<?php

namespace Tests\Unit;

use App\Services\Carriers\Core\CarrierApiEventLogger;
use App\Services\Carriers\FedEx\Support\Security\FedExSensitiveFieldClassifier;
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
        $method = new \ReflectionMethod($logger, 'maskSummary');
        $method->setAccessible(true);

        $masked = $method->invoke($logger, [
            'shippingChargesPayment' => ['paymentType' => 'SENDER'],
            'authorization' => 'Bearer secret',
        ]);

        $this->assertSame(['paymentType' => 'SENDER'], $masked['shippingChargesPayment']);
        $this->assertSame('[redacted]', $masked['authorization']);
    }
}
