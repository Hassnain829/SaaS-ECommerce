<?php

namespace App\Services\Carriers\FedEx;

use App\Support\CarrierCountryOptions;
use App\Services\Carriers\CarrierOriginReadinessService;
use Illuminate\Validation\ValidationException;

class FedExRegistrationInputValidator
{
    private const REJECTED_COUNTRY_INPUTS = [
        'UN',
        'XX',
        'ZZ',
        'USA',
        'UNITED STATES',
        'UNITED STATES OF AMERICA',
    ];

    public function __construct(
        private readonly CarrierOriginReadinessService $originReadiness,
    ) {
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{normalized: array<string, mixed>, errors: array<string, string>}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $normalized = $input;

        $accountNumber = preg_replace('/\D+/', '', (string) ($input['provider_account_number'] ?? '')) ?? '';
        if ($accountNumber === '' || strlen($accountNumber) !== 9) {
            $errors['provider_account_number'] = 'FedEx account number should be 9 digits.';
        }
        $normalized['provider_account_number'] = $accountNumber;

        $country = $this->resolveCountryCode($input['country_code'] ?? null, $errors);
        if ($country !== null) {
            $normalized['country_code'] = $country;
        }

        $state = strtoupper(trim((string) ($input['state'] ?? '')));
        if ($country === 'US') {
            if ($state === '' || ! preg_match('/^[A-Z]{2}$/', $state)) {
                $errors['state'] = 'Use a 2-letter state code such as TX.';
            } else {
                $normalized['state'] = $state;
            }
        } elseif ($state !== '') {
            $normalized['state'] = $state;
        }

        $postalCode = $this->normalizeUsPostalCode((string) ($input['postal_code'] ?? ''));
        if ($country === 'US' && $postalCode === null) {
            $errors['postal_code'] = 'Enter a valid US ZIP code.';
        } elseif ($postalCode !== null) {
            $normalized['postal_code'] = $postalCode;
        }

        $normalized['city'] = trim((string) ($input['city'] ?? ''));
        if ($normalized['city'] === '') {
            $errors['city'] = 'City is required.';
        } else {
            $normalized['city'] = $normalized['city'];
        }

        $normalized['address_line1'] = trim((string) ($input['address_line1'] ?? ''));
        if ($normalized['address_line1'] === '') {
            $errors['address_line1'] = 'Address line 1 is required.';
        }

        $normalized['address_line2'] = filled($input['address_line2'] ?? null)
            ? trim((string) $input['address_line2'])
            : null;
        $normalized['company_name'] = trim((string) ($input['company_name'] ?? ''));
        $normalized['contact_name'] = trim((string) ($input['contact_name'] ?? ''));
        $normalized['display_name'] = filled($input['display_name'] ?? null)
            ? trim((string) $input['display_name'])
            : null;
        $normalized['phone'] = $this->normalizePhone((string) ($input['phone'] ?? ''));
        $normalized['email'] = strtolower(trim((string) ($input['email'] ?? '')));
        $normalized['residential'] = (bool) ($input['residential'] ?? false);

        if ($normalized['company_name'] === '' && $normalized['contact_name'] === '') {
            $errors['company_name'] = 'Account name and address must match your FedEx records.';
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

    /**
     * @param  array<string, mixed>  $errors
     */
    private function resolveCountryCode(mixed $value, array &$errors): ?string
    {
        $raw = strtoupper(trim(str_replace('.', '', (string) ($value ?? ''))));

        if ($raw === '') {
            $errors['country_code'] = 'Choose United States as the FedEx account country.';

            return null;
        }

        if (in_array($raw, self::REJECTED_COUNTRY_INPUTS, true)) {
            $errors['country_code'] = 'Choose United States as the FedEx account country.';

            return null;
        }

        $normalized = $this->originReadiness->normalizeCountryCode($raw);

        if ($normalized === null || ! CarrierCountryOptions::isAllowedFedExCountry($normalized)) {
            $errors['country_code'] = 'Choose United States as the FedEx account country.';

            return null;
        }

        return $normalized;
    }

    private function normalizeUsPostalCode(string $value): ?string
    {
        $trimmed = strtoupper(trim($value));

        if (preg_match('/^\d{5}$/', $trimmed)) {
            return $trimmed;
        }

        if (preg_match('/^\d{5}-\d{4}$/', $trimmed)) {
            return $trimmed;
        }

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if (strlen($digits) === 9) {
            return substr($digits, 0, 5).'-'.substr($digits, 5);
        }

        if (strlen($digits) === 5) {
            return $digits;
        }

        return null;
    }

    private function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return $digits !== '' ? $digits : trim($value);
    }
}
