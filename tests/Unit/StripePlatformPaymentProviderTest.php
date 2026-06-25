<?php

namespace Tests\Unit;

use App\Services\Payments\StripePlatformPaymentProvider;
use Tests\TestCase;

class StripePlatformPaymentProviderTest extends TestCase
{
    public function test_payment_intent_update_result_reads_actual_stripe_object_fields(): void
    {
        $provider = app(StripePlatformPaymentProvider::class);

        $result = $provider->paymentIntentUpdateResultFromStripeObject([
            'id' => 'pi_test_123',
            'amount' => 2750,
            'currency' => 'usd',
            'status' => 'requires_payment_method',
            'client_secret' => 'pi_test_123_secret',
        ], 'test');

        $this->assertSame('pi_test_123', $result->providerIntentId);
        $this->assertSame(2750, $result->amountMinor);
        $this->assertSame('USD', $result->currencyCode);
        $this->assertSame('requires_payment_method', $result->status);
        $this->assertSame('pi_test_123_secret', $result->clientSecret);
        $this->assertSame('test', $result->mode);
    }

    public function test_payment_intent_update_result_supports_jpy_zero_decimal_amounts(): void
    {
        $provider = app(StripePlatformPaymentProvider::class);

        $result = $provider->paymentIntentUpdateResultFromStripeObject([
            'id' => 'pi_jpy_1',
            'amount' => 1430,
            'currency' => 'jpy',
            'status' => 'requires_confirmation',
        ]);

        $this->assertSame(1430, $result->amountMinor);
        $this->assertSame('JPY', $result->currencyCode);
        $this->assertSame('requires_confirmation', $result->status);
    }
}
