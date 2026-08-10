<?php

namespace App\Services\Carriers\FedEx\Operations;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validates international customs commodity data before Ship API create.
 */
final class FedExCustomsValidationService
{
    /**
     * @param  array<string, mixed>  $customsClearance
     * @return array{ok: bool, errors: array<string, list<string>>, normalized: array<string, mixed>|null}
     */
    public function validate(string $originCountry, string $destinationCountry, array $customsClearance): array
    {
        $origin = strtoupper(trim($originCountry));
        $destination = strtoupper(trim($destinationCountry));

        if ($origin === $destination) {
            return ['ok' => true, 'errors' => [], 'normalized' => null];
        }

        $validator = Validator::make($customsClearance, [
            'total_customs_value.amount' => ['required', 'numeric', 'min:0.01'],
            'total_customs_value.currency' => ['required', 'string', 'size:3'],
            'duties_payment_type' => ['nullable', 'string', 'max:40'],
            'commercial_invoice.shipment_purpose' => ['nullable', 'string', 'max:40'],
            'commodities' => ['required', 'array', 'min:1'],
            'commodities.*.description' => ['required', 'string', 'max:450'],
            'commodities.*.quantity' => ['required', 'numeric', 'min:1'],
            'commodities.*.weight' => ['required', 'numeric', 'min:0.01'],
            'commodities.*.weight_unit' => ['nullable', 'string', 'in:LB,KG'],
            'commodities.*.customs_value.amount' => ['required', 'numeric', 'min:0.01'],
            'commodities.*.customs_value.currency' => ['required', 'string', 'size:3'],
            'commodities.*.country_of_manufacture' => ['required', 'string', 'size:2'],
            'commodities.*.harmonized_code' => ['nullable', 'string', 'max:18'],
        ], [
            'commodities.required' => 'International shipments require at least one customs commodity line.',
            'commodities.*.description.required' => 'Each commodity needs a description.',
            'commodities.*.country_of_manufacture.required' => 'Each commodity needs a country of manufacture.',
        ]);

        if ($validator->fails()) {
            return [
                'ok' => false,
                'errors' => $validator->errors()->toArray(),
                'normalized' => null,
            ];
        }

        $data = $validator->validated();
        $commodities = [];
        foreach ($data['commodities'] as $commodity) {
            $commodities[] = [
                'description' => trim((string) $commodity['description']),
                'quantity' => (float) $commodity['quantity'],
                'weight' => [
                    'units' => strtoupper((string) ($commodity['weight_unit'] ?? 'LB')),
                    'value' => (float) $commodity['weight'],
                ],
                'customs_value' => [
                    'amount' => (float) $commodity['customs_value']['amount'],
                    'currency' => strtoupper((string) $commodity['customs_value']['currency']),
                ],
                'country_of_manufacture' => strtoupper((string) $commodity['country_of_manufacture']),
                'harmonized_code' => filled($commodity['harmonized_code'] ?? null)
                    ? (string) $commodity['harmonized_code']
                    : null,
            ];
        }

        $normalized = [
            'total_customs_value' => [
                'amount' => (float) $data['total_customs_value']['amount'],
                'currency' => strtoupper((string) $data['total_customs_value']['currency']),
            ],
            'duties_payment_type' => strtoupper((string) ($data['duties_payment_type'] ?? 'SENDER')),
            'commodities' => $commodities,
            'commercial_invoice' => [
                'shipment_purpose' => strtoupper((string) (
                    data_get($data, 'commercial_invoice.shipment_purpose')
                    ?: data_get($customsClearance, 'commercial_invoice.shipment_purpose')
                    ?: 'SOLD'
                )),
            ],
        ];

        return ['ok' => true, 'errors' => [], 'normalized' => $normalized];
    }

    /**
     * @param  array<string, mixed>  $customsClearance
     * @throws ValidationException
     * @return array<string, mixed>|null
     */
    public function assertValidOrNull(string $originCountry, string $destinationCountry, array $customsClearance): ?array
    {
        $result = $this->validate($originCountry, $destinationCountry, $customsClearance);
        if ($result['ok']) {
            return $result['normalized'];
        }

        throw ValidationException::withMessages($result['errors']);
    }
}
