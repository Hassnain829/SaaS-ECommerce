<?php

namespace Tests\Unit;

use App\Models\CarrierAccount;
use App\Services\Carriers\FedEx\Connection\FedExRegistrationPayloadBuilder;
use Tests\TestCase;

class FedExRegistrationPayloadBuilderTest extends TestCase
{
    public function test_resolve_account_number_strips_non_digits_from_multiple_sources(): void
    {
        $builder = app(FedExRegistrationPayloadBuilder::class);

        $account = new CarrierAccount(['provider_account_number' => '510-087-240']);

        $this->assertSame('510087240', $builder->resolveAccountNumber($account, []));

        $account = new CarrierAccount([
            'provider_account_number' => null,
            'settings' => ['registration' => ['provider_account_number' => '510087240']],
        ]);

        $this->assertSame('510087240', $builder->resolveAccountNumber($account, [
            'account_number' => '510 087 240',
        ]));
    }

    public function test_build_v2_payload_normalizes_address_and_omits_residential_when_mode_is_omit(): void
    {
        config(['carriers.fedex.account_registration_residential_mode' => 'omit']);

        $builder = app(FedExRegistrationPayloadBuilder::class);

        $payload = $builder->buildV2Payload('510087240', [
            'company_name' => 'Acme Widgets',
            'address_line1' => '123 Main St',
            'address_line2' => 'Suite 4',
            'city' => 'memphis',
            'state' => 'tn',
            'postal_code' => '38118-1234',
            'country_code' => 'us',
            'residential' => true,
        ]);

        $this->assertSame('Acme Widgets', $payload['customerName']);
        $this->assertSame('510087240', $payload['accountNumber']['value']);
        $this->assertSame('MEMPHIS', $payload['address']['city']);
        $this->assertSame('TN', $payload['address']['stateOrProvinceCode']);
        $this->assertSame('381181234', $payload['address']['postalCode']);
        $this->assertSame('US', $payload['address']['countryCode']);
        $this->assertArrayNotHasKey('residential', $payload['address']);
    }

    public function test_build_v2_payload_applies_boolean_residential_when_configured(): void
    {
        config(['carriers.fedex.account_registration_residential_mode' => 'boolean']);

        $builder = app(FedExRegistrationPayloadBuilder::class);

        $payload = $builder->buildV2Payload('510087240', [
            'contact_name' => 'Jane Merchant',
            'address_line1' => '123 Main St',
            'city' => 'Memphis',
            'state' => 'TN',
            'postal_code' => '38118',
            'country_code' => 'US',
            'residential' => true,
        ]);

        $this->assertTrue($payload['address']['residential']);
    }

    public function test_build_request_summary_includes_redacted_registration_diagnostics(): void
    {
        config(['carriers.fedex.account_registration_residential_mode' => 'boolean']);

        $builder = app(FedExRegistrationPayloadBuilder::class);
        $accountDetails = [
            'company_name' => 'Acme',
            'address_line1' => '123 Main',
            'city' => 'Memphis',
            'state' => 'TN',
            'postal_code' => '38118',
            'country_code' => 'US',
            'residential' => false,
        ];
        $payload = $builder->buildV2Payload('510087240', $accountDetails);

        $summary = $builder->buildRequestSummary('/registration/v2/address/keysgeneration', '510087240', $payload, $accountDetails);

        $this->assertSame('/registration/v2/address/keysgeneration', $summary['endpoint']);
        $this->assertSame(9, $summary['account_number_digits_len']);
        $this->assertSame('7240', $summary['account_number_last4']);
        $this->assertTrue($summary['customer_name_present']);
        $this->assertSame('boolean', $summary['residential_mode']);
        $this->assertTrue($summary['residential_sent']);
        $this->assertFalse($summary['residential_setting']);
    }

    public function test_registration_postal_code_raw_bypasses_digit_normalization(): void
    {
        $builder = app(FedExRegistrationPayloadBuilder::class);

        $payload = $builder->buildV2Payload('510087240', [
            'company_name' => 'Acme',
            'address_line1' => '123 Main',
            'city' => 'Memphis',
            'state' => 'TN',
            'postal_code' => '38118',
            'registration_postal_code_raw' => '38118-0001',
            'country_code' => 'US',
        ]);

        $this->assertSame('38118-0001', $payload['address']['postalCode']);
    }
}
