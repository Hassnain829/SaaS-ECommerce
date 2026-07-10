<?php

namespace Tests\Unit;

use App\Models\CarrierAccount;
use App\Services\Carriers\USPS\Support\USPSConfig;
use App\Services\Carriers\USPS\Support\USPSPaymentRolePayloadBuilder;
use Tests\TestCase;

class USPSPaymentRolePayloadBuilderTest extends TestCase
{
    public function test_builds_merchant_payer_rate_holder_and_label_owner_roles(): void
    {
        config([
            'carriers.usps.platform_crid' => '49188300',
            'carriers.usps.platform_epa' => '1000445839',
            'carriers.usps.platform_master_mid' => '903800001',
            'carriers.usps.payment_platform_role_name' => 'PLATFORM',
            'carriers.usps.payment_include_label_provider_role' => false,
            'carriers.usps.payment_account_type' => 'EPS',
        ]);

        $account = new CarrierAccount;
        $account->setUspsMerchantIdentifiers('12345678', '903800001', '1000999888', '903800002');

        $payload = (new USPSPaymentRolePayloadBuilder(new USPSConfig))->build($account);

        $this->assertSame('PAYER', $payload['roles'][0]['roleName']);
        $this->assertSame('12345678', $payload['roles'][0]['CRID']);
        $this->assertSame('EPS', $payload['roles'][0]['accountType']);
        $this->assertSame('1000999888', $payload['roles'][0]['accountNumber']);

        $this->assertSame('RATE_HOLDER', $payload['roles'][1]['roleName']);
        $this->assertArrayNotHasKey('manifestMID', $payload['roles'][1]);

        $this->assertSame('LABEL_OWNER', $payload['roles'][2]['roleName']);
        $this->assertSame('903800002', $payload['roles'][2]['manifestMID']);

        $this->assertSame('PLATFORM', $payload['roles'][3]['roleName']);
        $this->assertSame('1000445839', $payload['roles'][3]['accountNumber']);
    }

    public function test_includes_configurable_label_provider_role_when_enabled(): void
    {
        config([
            'carriers.usps.platform_crid' => '49188300',
            'carriers.usps.platform_epa' => '1000445839',
            'carriers.usps.platform_master_mid' => '903800001',
            'carriers.usps.payment_platform_role_name' => 'PLATFORM',
            'carriers.usps.payment_label_provider_role_name' => 'LABEL_PROVIDER',
            'carriers.usps.payment_include_label_provider_role' => true,
        ]);

        $account = new CarrierAccount;
        $account->setUspsMerchantIdentifiers('12345678', '903800001', '1000999888');

        $payload = (new USPSPaymentRolePayloadBuilder(new USPSConfig))->build($account);

        $roleNames = array_column($payload['roles'], 'roleName');
        $this->assertContains('LABEL_PROVIDER', $roleNames);
    }
}
