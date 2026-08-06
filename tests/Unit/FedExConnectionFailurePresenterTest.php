<?php

namespace Tests\Unit;

use App\Models\CarrierAccount;
use App\Models\CarrierAccountRegistrationSession;
use App\Services\Carriers\FedEx\Connection\FedExConnectionFailurePresenter;
use App\Services\Carriers\FedEx\Validation\FedExValidationSwedenPassthroughSupport;
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
            $this->assertNotSame(FedExValidationSwedenPassthroughSupport::FAILURE_MESSAGE, $message);
        }
    }

    public function test_sweden_passthrough_copy_is_limited_to_its_sandbox_validation_scenario(): void
    {
        config(['carriers.fedex.validation_mode_enabled' => true]);
        $presenter = app(FedExConnectionFailurePresenter::class);

        $sandboxSweden = $this->registrationSession(CarrierAccount::ENVIRONMENT_SANDBOX, 'SE');
        $this->assertSame(
            FedExValidationSwedenPassthroughSupport::FAILURE_MESSAGE,
            $presenter->message(
                $sandboxSweden,
                'sweden_passthrough_transport_error',
                'Transport error',
            )
        );

        $liveSweden = $this->registrationSession(CarrierAccount::ENVIRONMENT_LIVE, 'SE');
        $this->assertNotSame(
            FedExValidationSwedenPassthroughSupport::FAILURE_MESSAGE,
            $presenter->message(
                $liveSweden,
                'sweden_passthrough_transport_error',
                'Transport error',
            )
        );
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
