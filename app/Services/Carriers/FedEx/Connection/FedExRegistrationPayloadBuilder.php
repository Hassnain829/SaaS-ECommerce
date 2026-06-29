<?php

namespace App\Services\Carriers\FedEx\Connection;

use App\Models\CarrierAccount;
use App\Services\Carriers\FedEx\Support\FedExConfig;

final class FedExRegistrationPayloadBuilder
{
    public function __construct(
        private readonly FedExConfig $config,
    ) {}

    /**
     * @param  array<string, mixed>  $accountDetails
     */
    public function resolveAccountNumber(CarrierAccount $account, array $accountDetails): string
    {
        $rawAccountNumber = $account->provider_account_number
            ?: data_get($account->settings, 'registration.provider_account_number')
            ?: data_get($accountDetails, 'provider_account_number')
            ?: data_get($accountDetails, 'account_number');

        return preg_replace('/\D+/', '', (string) $rawAccountNumber);
    }

    /**
     * @param  array<string, mixed>  $accountDetails
     * @return array<string, mixed>
     */
    public function buildV2Payload(string $accountNumber, array $accountDetails): array
    {
        $customerName = trim((string) (
            $accountDetails['company_name']
            ?? $accountDetails['contact_name']
            ?? ''
        ));

        $residentialSetting = (bool) data_get($accountDetails, 'residential', false);
        $streetLines = array_values(array_filter([
            trim((string) ($accountDetails['address_line1'] ?? '')),
            filled($accountDetails['address_line2'] ?? null) ? trim((string) $accountDetails['address_line2']) : null,
        ]));
        $state = strtoupper(trim((string) ($accountDetails['state'] ?? '')));
        $address = [
            'streetLines' => $streetLines,
            'city' => strtoupper(trim((string) ($accountDetails['city'] ?? ''))),
            'postalCode' => $this->resolveRegistrationPostalCode($accountDetails),
            'countryCode' => strtoupper(trim((string) ($accountDetails['country_code'] ?? 'US'))),
        ];

        if ($state !== '') {
            $address['stateOrProvinceCode'] = $state;
        }

        return [
            'customerName' => $customerName,
            'accountNumber' => [
                'value' => $accountNumber,
            ],
            'address' => $this->applyRegistrationResidential($address, $residentialSetting),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $accountDetails
     * @return array<string, mixed>
     */
    public function buildRequestSummary(
        string $registrationPath,
        string $accountNumber,
        array $payload,
        array $accountDetails,
    ): array {
        $residentialSetting = (bool) data_get($accountDetails, 'residential', false);
        $residentialMode = $this->config->accountRegistrationResidentialMode();
        $residentialSent = array_key_exists('residential', $payload['address'] ?? []);
        $postalSent = (string) ($payload['address']['postalCode'] ?? '');
        $postalDigits = preg_replace('/\D+/', '', $postalSent) ?? '';

        return [
            'endpoint' => $registrationPath,
            'account_number_present' => $accountNumber !== '',
            'account_number_digits_len' => strlen($accountNumber),
            'account_number_last4' => strlen($accountNumber) >= 4 ? substr($accountNumber, -4) : null,
            'account_number_shape' => 'object_value',
            'customer_name_present' => filled($payload['customerName'] ?? null),
            'customer_name_length' => strlen((string) ($payload['customerName'] ?? '')),
            'street_lines_count' => count($payload['address']['streetLines'] ?? []),
            'city' => $payload['address']['city'] ?? null,
            'city_present' => filled($payload['address']['city'] ?? null),
            'state_or_province_code' => $payload['address']['stateOrProvinceCode'] ?? null,
            'state_present' => filled($payload['address']['stateOrProvinceCode'] ?? null),
            'postal_code_input' => $accountDetails['postal_code'] ?? null,
            'postal_code_sent' => $postalSent !== '' ? $postalSent : null,
            'postal_code_digits_len' => strlen($postalDigits),
            'postal_code' => $postalSent !== '' ? $postalSent : null,
            'postal_code_present' => filled($postalSent),
            'country_code' => $payload['address']['countryCode'] ?? null,
            'residential_setting' => $residentialSetting,
            'residential_sent' => $residentialSent,
            'residential_mode' => $residentialMode,
            'payload_root_keys' => array_keys($payload),
            'address_keys' => array_keys($payload['address'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array<string, mixed>
     */
    private function applyRegistrationResidential(array $address, bool $residentialSetting): array
    {
        $mode = $this->config->accountRegistrationResidentialMode();

        if ($mode === 'boolean') {
            $address['residential'] = $residentialSetting;
        } elseif ($mode === 'string') {
            $address['residential'] = $residentialSetting ? 'true' : 'false';
        }

        return $address;
    }

    /**
     * @param  array<string, mixed>  $accountDetails
     */
    private function resolveRegistrationPostalCode(array $accountDetails): string
    {
        if (filled($accountDetails['registration_postal_code_raw'] ?? null)) {
            return (string) $accountDetails['registration_postal_code_raw'];
        }

        $postal = trim((string) ($accountDetails['postal_code'] ?? ''));
        $digits = preg_replace('/\D+/', '', $postal) ?? '';

        if (strlen($digits) === 9 || strlen($digits) === 5) {
            return $digits;
        }

        return $postal;
    }
}
