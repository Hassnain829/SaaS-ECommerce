<?php

namespace App\Services\Carriers\FedEx\Validation;

final class FedExValidationSwedenPassthroughSupport
{
    public const CASE_KEY = 'SwedenMfaPassthrough';

    public const VALIDATION_CASE = 'sweden_mfa_passthrough';

    public const FAILURE_MESSAGE = 'We are unable to process this request. Please try again later or call FedEx Customer Service and ask for technical support.';

    public const EXPORT_FOLDER = '12_sweden_mfa_passthrough';

    /**
     * @return list<string>
     */
    public static function failureCodes(): array
    {
        return [
            'sweden_passthrough_parent_oauth_failed',
            'sweden_passthrough_credentials_missing',
            'sweden_passthrough_mfa_returned',
            'sweden_passthrough_inconsistent_response',
            'sweden_passthrough_auth_token_only',
            'sweden_passthrough_child_oauth_failed',
            'sweden_passthrough_transport_error',
            'sweden_passthrough_fixture_unavailable',
            'sweden_passthrough_registration_failed',
        ];
    }
}
