<?php

namespace Tests\Unit;

use App\Models\CarrierAccount;
use App\Models\CarrierAccountRegistrationSession;
use App\Services\Carriers\FedEx\Connection\FedExConnectionFailurePresenter;
use Tests\TestCase;

class FedExConnectionFailurePresenterTest extends TestCase
{
    public function test_it_maps_connection_failures_to_merchant_safe_messages(): void
    {
        $presenter = app(FedExConnectionFailurePresenter::class);
        $session = $this->registrationSession(CarrierAccount::ENVIRONMENT_LIVE, 'US');
        $cases = [
            ['INVALID.INPUT.EXCEPTION', 'account address mismatch', 'account name or address'],
            ['registration_mfa_required', null, 'additional verification'],
            ['account_auth_token_expired', null, 'verification expired'],
            ['invalid_pin', null, 'PIN or invoice'],
            ['child_oauth_failed', null, 'could not be verified'],
            ['service_unavailable', null, 'temporarily unavailable'],
            ['fedex_authorization_blocked', null, 'account access is not available'],
            ['configuration_error', null, 'account access is not available'],
        ];

        foreach ($cases as [$code, $technicalMessage, $expected]) {
            $message = $presenter->message($session, $code, $technicalMessage);

            $this->assertStringContainsString($expected, $message, (string) $code);
        }
    }

    private function registrationSession(
        string $environment,
        string $countryCode,
    ): CarrierAccountRegistrationSession
    {
        return new CarrierAccountRegistrationSession([
            'environment' => $environment,
            'registration_address_json' => ['country_code' => $countryCode],
        ]);
    }
}
