<?php

namespace App\Services\Carriers\FedEx;

use App\Models\CarrierAccount;
use Illuminate\Validation\ValidationException;

class FedExMerchantCredentialsInputValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{normalized: array<string, mixed>, errors: array<string, string>}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $normalized = $input;

        $displayName = trim((string) ($input['display_name'] ?? ''));
        if ($displayName === '') {
            $errors['display_name'] = 'Account nickname is required.';
        } else {
            $normalized['display_name'] = $displayName;
        }

        $accountNumber = preg_replace('/\D+/', '', (string) ($input['provider_account_number'] ?? '')) ?? '';
        if ($accountNumber === '' || strlen($accountNumber) !== 9) {
            $errors['provider_account_number'] = 'FedEx account number should be 9 digits.';
        }
        $normalized['provider_account_number'] = $accountNumber;

        $clientId = trim((string) ($input['fedex_client_id'] ?? ''));
        if ($clientId === '') {
            $errors['fedex_client_id'] = 'FedEx API key / client ID is required.';
        } else {
            $normalized['fedex_client_id'] = $clientId;
        }

        $clientSecret = trim((string) ($input['fedex_client_secret'] ?? ''));
        if ($clientSecret === '') {
            $errors['fedex_client_secret'] = 'FedEx secret key / client secret is required.';
        } else {
            $normalized['fedex_client_secret'] = $clientSecret;
        }

        $environment = strtolower(trim((string) ($input['environment'] ?? CarrierAccount::ENVIRONMENT_SANDBOX)));
        if (! in_array($environment, [CarrierAccount::ENVIRONMENT_SANDBOX, CarrierAccount::ENVIRONMENT_LIVE], true)) {
            $errors['environment'] = 'Choose Sandbox or Production.';
        } else {
            $normalized['environment'] = $environment;
        }

        return [
            'normalized' => $normalized,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function validateOrFail(array $input): array
    {
        $result = $this->validate($input);

        if ($result['errors'] !== []) {
            throw ValidationException::withMessages($result['errors']);
        }

        return $result['normalized'];
    }
}
