<?php

namespace App\Services\Carriers\FedEx\Connection;

use App\Models\CarrierAccount;
use App\Services\Carriers\Core\CarrierOriginReadinessService;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Support\CarrierCountryOptions;
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
        'CANADA',
        'SWEDEN',
    ];

    public function __construct(
        private readonly CarrierOriginReadinessService $originReadiness,
        private readonly FedExConfig $fedExConfig,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @param  array{environment?: string, validation_mode?: bool}  $context
     * @return array{normalized: array<string, mixed>, errors: array<string, string>}
     */
    public function validate(array $input, array $context = []): array
    {
        $errors = [];
        $normalized = $input;
        $environment = strtolower((string) ($context['environment'] ?? CarrierAccount::ENVIRONMENT_SANDBOX));
        $validationMode = array_key_exists('validation_mode', $context)
            ? (bool) $context['validation_mode']
            : $this->fedExConfig->validationModeEnabled();

        $accountNumber = preg_replace('/\D+/', '', (string) ($input['provider_account_number'] ?? '')) ?? '';
        if ($accountNumber === '' || strlen($accountNumber) !== 9) {
            $errors['provider_account_number'] = 'FedEx account number should be 9 digits.';
        }
        $normalized['provider_account_number'] = $accountNumber;

        $country = $this->resolveCountryCode(
            $input['country_code'] ?? null,
            $environment,
            $validationMode,
            $errors
        );
        if ($country !== null) {
            $normalized['country_code'] = $country;
        }

        $state = strtoupper(trim((string) ($input['state'] ?? '')));
        if ($country === 'US') {
            if (! in_array($state, CarrierCountryOptions::unitedStatesStateCodes(), true)) {
                $errors['state'] = 'Choose a valid US state code such as TX.';
            } else {
                $normalized['state'] = $state;
            }
        } elseif ($country === 'CA') {
            if ($state === '' || ! in_array($state, CarrierCountryOptions::canadianProvinceCodes(), true)) {
                $errors['state'] = 'Use a 2-letter Canadian province code such as ON.';
            } else {
                $normalized['state'] = $state;
            }
        } elseif ($state !== '') {
            $normalized['state'] = $state;
        }

        $postalRaw = (string) ($input['postal_code'] ?? '');
        if ($country === 'US') {
            $postalCode = $this->normalizeUsPostalCode($postalRaw);
            if ($postalCode === null) {
                $errors['postal_code'] = 'Enter a valid US ZIP code.';
            } else {
                $normalized['postal_code'] = $postalCode;
            }
            $digits = preg_replace('/\D+/', '', $postalRaw) ?? '';
            if ($digits !== '') {
                $normalized['registration_postal_code_raw'] = $this->registrationPostalCodeRaw($digits, $country);
            }
        } elseif ($country === 'CA') {
            $postalCode = $this->normalizeCanadianPostalCode($postalRaw);
            if ($postalCode === null) {
                $errors['postal_code'] = 'Enter a valid Canadian postal code such as A1A 1A1.';
            } else {
                $normalized['postal_code'] = $postalCode;
                $normalized['registration_postal_code_raw'] = str_replace(' ', '', $postalCode);
            }
        } elseif (trim($postalRaw) !== '') {
            $normalized['postal_code'] = trim($postalRaw);
            $digits = preg_replace('/\D+/', '', $postalRaw) ?? '';
            if ($digits !== '') {
                $normalized['registration_postal_code_raw'] = $digits;
            }
        }

        $normalized['city'] = trim((string) ($input['city'] ?? ''));
        if ($normalized['city'] === '') {
            $errors['city'] = 'City is required.';
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
     * @param  array{environment?: string, validation_mode?: bool}  $context
     * @return array<string, mixed>
     */
    public function validateOrFail(array $input, array $context = []): array
    {
        $result = $this->validate($input, $context);

        if ($result['errors'] !== []) {
            throw ValidationException::withMessages($result['errors']);
        }

        return $result['normalized'];
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    private function resolveCountryCode(
        mixed $value,
        string $environment,
        bool $validationMode,
        array &$errors,
    ): ?string {
        $raw = strtoupper(trim(str_replace('.', '', (string) ($value ?? ''))));

        if ($raw === '') {
            $errors['country_code'] = 'Choose a supported FedEx account country.';

            return null;
        }

        if (in_array($raw, self::REJECTED_COUNTRY_INPUTS, true)) {
            $errors['country_code'] = 'Choose a supported FedEx account country from the list.';

            return null;
        }

        $normalized = $this->originReadiness->normalizeCountryCode($raw);

        if ($normalized === null
            || ! CarrierCountryOptions::isAllowedFedExRegistrationCountry($normalized, $environment, $validationMode)) {
            if ($normalized === 'SE' && ($environment === CarrierAccount::ENVIRONMENT_LIVE || ! $validationMode)) {
                $errors['country_code'] = 'Sweden is supported for FedEx sandbox validation only.';
            } else {
                $errors['country_code'] = 'Choose United States or Canada as the FedEx account country.';
            }

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

    private function normalizeCanadianPostalCode(string $value): ?string
    {
        $compact = strtoupper(preg_replace('/\s+/', '', trim($value)) ?? '');

        if (! preg_match(
            '/^[ABCEGHJ-NPRSTVXY]\d[ABCEGHJ-NPRSTVWXYZ]\d[ABCEGHJ-NPRSTVWXYZ]\d$/',
            $compact
        )) {
            return null;
        }

        return substr($compact, 0, 3).' '.substr($compact, 3);
    }

    private function registrationPostalCodeRaw(string $digits, ?string $country): string
    {
        if ($country === 'US' && in_array(strlen($digits), [5, 9], true)) {
            return $digits;
        }

        return $digits;
    }

    private function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return $digits !== '' ? $digits : trim($value);
    }
}
