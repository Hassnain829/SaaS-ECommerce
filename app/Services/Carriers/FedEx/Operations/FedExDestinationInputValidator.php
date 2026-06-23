<?php

namespace App\Services\Carriers\FedEx\Operations;

class FedExDestinationInputValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{normalized: array<string, mixed>, errors: array<string, string>}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $country = strtoupper(trim((string) ($input['country_code'] ?? '')));

        if ($country === '' || strlen($country) !== 2) {
            $errors['destination_country'] = 'Enter a 2-letter destination country code such as US or CA.';
        }

        $postal = trim((string) ($input['postal_code'] ?? ''));
        if ($postal === '') {
            $errors['destination_postal_code'] = 'Destination postal code is required.';
        }

        $state = strtoupper(trim((string) ($input['state'] ?? '')));
        $city = trim((string) ($input['city'] ?? ''));

        if (in_array($country, ['US', 'CA'], true)) {
            if ($state === '') {
                $errors['destination_state'] = 'Destination state or province is required for US and Canada.';
            } elseif ($country === 'US' && ! preg_match('/^[A-Z]{2}$/', $state)) {
                $errors['destination_state'] = 'Use a 2-letter US state code such as TX.';
            }

            if ($city === '') {
                $errors['destination_city'] = 'Destination city is required for US and Canada.';
            }
        }

        if ($country === 'US' && $postal !== '') {
            $normalizedPostal = $this->normalizeUsPostalCode($postal);
            if ($normalizedPostal === null) {
                $errors['destination_postal_code'] = 'Enter a valid US ZIP code.';
            } else {
                $postal = $normalizedPostal;
            }
        }

        if ($country === 'CA' && $postal !== '') {
            $normalizedPostal = $this->normalizeCanadianPostalCode($postal);
            if ($normalizedPostal === null) {
                $errors['destination_postal_code'] = 'Enter a valid Canadian postal code such as K1A 0B1.';
            } else {
                $postal = $normalizedPostal;
            }
        }

        return [
            'normalized' => array_filter([
                'country_code' => $country !== '' ? $country : null,
                'postal_code' => $postal !== '' ? $postal : null,
                'state' => $state !== '' ? $state : null,
                'city' => $city !== '' ? $city : null,
            ]),
            'errors' => $errors,
        ];
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

        if (! preg_match('/^[A-Z]\d[A-Z]\d[A-Z]\d$/', $compact)) {
            return null;
        }

        return substr($compact, 0, 3).' '.substr($compact, 3);
    }
}
