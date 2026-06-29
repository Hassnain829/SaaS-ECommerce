<?php

namespace Tests\Unit;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Services\Carriers\FedEx\Validation\FedExValidationAuthorizationEvidenceRules;
use Tests\TestCase;

class FedExValidationAuthorizationEvidenceRulesTest extends TestCase
{
    private FedExValidationAuthorizationEvidenceRules $rules;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rules = app(FedExValidationAuthorizationEvidenceRules::class);
    }

    public function test_parent_authorization_requires_redacted_client_credentials(): void
    {
        $event = $this->oauthEvent([
            'grant_type' => 'client_credentials',
            'client_id' => '[REDACTED]',
            'client_secret' => '[REDACTED]',
        ], [
            'access_token' => '[REDACTED]',
            'token_type' => 'bearer',
            'expires_in' => 3600,
        ]);

        $this->assertTrue($this->rules->satisfiesRequirements($event, 'client_credentials'));
    }

    public function test_incomplete_parent_authorization_response_is_rejected(): void
    {
        $event = $this->oauthEvent([
            'grant_type' => 'client_credentials',
            'client_id' => '[REDACTED]',
            'client_secret' => '[REDACTED]',
        ], [
            'anything' => 'value',
        ]);

        $this->assertFalse($this->rules->satisfiesRequirements($event, 'client_credentials'));
    }

    public function test_child_authorization_requires_redacted_child_credentials(): void
    {
        $event = $this->oauthEvent([
            'grant_type' => 'csp_credentials',
            'client_id' => '[REDACTED]',
            'client_secret' => '[REDACTED]',
            'child_key' => '[REDACTED]',
            'child_secret' => '[REDACTED]',
        ], [
            'access_token' => '[REDACTED]',
            'token_type' => 'bearer',
            'expires_in' => 3600,
            'scope' => 'CXS-TP',
        ]);

        $this->assertTrue($this->rules->satisfiesRequirements($event, 'csp_credentials'));
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $response
     */
    private function oauthEvent(array $request, array $response): CarrierApiEvent
    {
        return CarrierApiEvent::make([
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'action' => CarrierApiEvent::ACTION_PLATFORM_OAUTH_TOKEN,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'http_status' => 200,
            'http_method' => 'POST',
            'endpoint' => '/oauth/token',
            'request_summary' => ['endpoint' => '/oauth/token'],
            'request_body_encrypted' => $request,
            'response_body_encrypted' => $response,
        ]);
    }
}
