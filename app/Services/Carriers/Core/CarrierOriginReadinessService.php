<?php

namespace App\Services\Carriers\Core;

use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Store;
use App\Services\Carriers\Core\DTO\CarrierOriginReadinessResult;
use Illuminate\Support\Str;

class CarrierOriginReadinessService
{
    public const CARRIER_USPS = 'usps';

    public const CARRIER_GENERIC = 'generic';

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function assessAttributes(array $attributes, string $carrierContext = self::CARRIER_USPS): CarrierOriginReadinessResult
    {
        $countryCode = $this->normalizeCountryCode($attributes['country_code'] ?? null);
        $postalCode = filled($attributes['postal_code'] ?? null)
            ? trim((string) $attributes['postal_code'])
            : null;

        $normalizedAddress = [
            'address_line1' => filled($attributes['address_line1'] ?? null)
                ? trim((string) $attributes['address_line1'])
                : null,
            'address_line2' => filled($attributes['address_line2'] ?? null)
                ? trim((string) $attributes['address_line2'])
                : null,
            'city' => filled($attributes['city'] ?? null)
                ? trim((string) $attributes['city'])
                : null,
            'state' => filled($attributes['state'] ?? null)
                ? strtoupper(trim((string) $attributes['state']))
                : null,
            'postal_code' => $postalCode,
            'country_code' => $countryCode,
        ];

        $missingFields = $this->missingRequiredFields($normalizedAddress, $countryCode);
        $originZip5 = $this->extractOriginZip5($postalCode);

        if ($countryCode === null && filled($attributes['country_code'] ?? null)) {
            return $this->result(
                ready: false,
                status: CarrierOriginReadinessResult::STATUS_INVALID_COUNTRY_CODE,
                missingFields: $missingFields,
                normalizedAddress: $normalizedAddress,
                originZip5: null,
                merchantMessage: 'Country must be a 2-letter code like US, CA, or PK.',
                badgeLabel: 'Missing shipping address',
            );
        }

        if ($countryCode !== null && ! $this->isValidIsoCountryCode($countryCode)) {
            return $this->result(
                ready: false,
                status: CarrierOriginReadinessResult::STATUS_INVALID_COUNTRY_CODE,
                missingFields: $missingFields,
                normalizedAddress: $normalizedAddress,
                originZip5: null,
                merchantMessage: 'Country must be a 2-letter code like US, CA, or PK.',
                badgeLabel: 'Missing shipping address',
            );
        }

        if ($missingFields !== []) {
            return $this->result(
                ready: false,
                status: CarrierOriginReadinessResult::STATUS_MISSING_FIELDS,
                missingFields: $missingFields,
                normalizedAddress: $normalizedAddress,
                originZip5: null,
                merchantMessage: $this->missingFieldsMessage($missingFields),
                badgeLabel: 'Missing shipping address',
            );
        }

        if ($carrierContext === self::CARRIER_USPS && $countryCode !== 'US') {
            return $this->result(
                ready: false,
                status: CarrierOriginReadinessResult::STATUS_UNSUPPORTED_COUNTRY,
                missingFields: [],
                normalizedAddress: $normalizedAddress,
                originZip5: null,
                merchantMessage: 'USPS test quotes are available only for US origin locations in this phase.',
                badgeLabel: 'Unsupported for USPS',
            );
        }

        if ($countryCode === 'US' && $originZip5 === null) {
            return $this->result(
                ready: false,
                status: CarrierOriginReadinessResult::STATUS_INVALID_POSTAL_CODE,
                missingFields: ['ZIP code'],
                normalizedAddress: $normalizedAddress,
                originZip5: null,
                merchantMessage: 'ZIP code must include at least 5 digits for US carrier origin addresses.',
                badgeLabel: 'Missing shipping address',
            );
        }

        return $this->result(
            ready: true,
            status: CarrierOriginReadinessResult::STATUS_READY,
            missingFields: [],
            normalizedAddress: $normalizedAddress,
            originZip5: $originZip5,
            merchantMessage: 'This origin is ready for carrier testing and rate quotes.',
            badgeLabel: 'Carrier-ready',
        );
    }

    public function assess(Location $location, string $carrierContext = self::CARRIER_USPS): CarrierOriginReadinessResult
    {
        return $this->assessForFulfillmentOrigin($location, $carrierContext);
    }

    public function assessForFulfillmentOrigin(Location $location, string $carrierContext = self::CARRIER_USPS): CarrierOriginReadinessResult
    {
        if (! $location->is_active) {
            return $this->result(
                ready: false,
                status: CarrierOriginReadinessResult::STATUS_MISSING_FIELDS,
                missingFields: ['Active location'],
                normalizedAddress: $this->normalizedFromLocation($location),
                originZip5: null,
                merchantMessage: 'This location is inactive. Activate it before using it as a ship-from location.',
                badgeLabel: 'Needs attention',
            );
        }

        if (! $location->fulfills_online_orders) {
            return $this->result(
                ready: false,
                status: CarrierOriginReadinessResult::STATUS_MISSING_FIELDS,
                missingFields: ['Online fulfillment'],
                normalizedAddress: $this->normalizedFromLocation($location),
                originZip5: null,
                merchantMessage: 'This location is not enabled for online fulfillment. Turn on fulfillment for this location first.',
                badgeLabel: 'Not enabled for fulfillment',
            );
        }

        return $this->assessAttributes($location->only([
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
            'country_code',
        ]), $carrierContext);
    }

    /**
     * @return array<string, string|null>
     */
    private function normalizedFromLocation(Location $location): array
    {
        return [
            'address_line1' => $location->address_line1,
            'address_line2' => $location->address_line2,
            'city' => $location->city,
            'state' => $location->state,
            'postal_code' => $location->postal_code,
            'country_code' => $location->country_code,
        ];
    }

    public function assessForAccount(CarrierAccount $account, string $carrierContext = self::CARRIER_USPS): ?CarrierOriginReadinessResult
    {
        $locationId = $account->defaultOriginLocationId();

        if (! filled($locationId)) {
            return null;
        }

        $location = Location::query()
            ->where('store_id', $account->store_id)
            ->whereKey((int) $locationId)
            ->first();

        return $location ? $this->assessForFulfillmentOrigin($location, $carrierContext) : null;
    }

    public function locationIsCarrierDefaultOrigin(Location $location): bool
    {
        return CarrierAccount::query()
            ->where('store_id', $location->store_id)
            ->where(function ($query) use ($location): void {
                $query->where('default_origin_location_id', $location->id)
                    ->orWhere('settings->default_origin_location_id', $location->id);
            })
            ->exists();
    }

    public function formatLocationOptionLabel(Location $location, ?CarrierOriginReadinessResult $readiness = null): string
    {
        $readiness ??= $this->assess($location);
        $address = $readiness->displayAddress !== ''
            ? $readiness->displayAddress
            : 'No ship-from address saved';

        $suffix = $readiness->ready
            ? ' · Carrier-ready'
            : ' · '.$readiness->badgeLabel;

        return $location->name.' — '.$address.$suffix;
    }

    public function storeHasCarrierReadyOrigin(Store $store, string $carrierContext = self::CARRIER_USPS): bool
    {
        return $store->locations()
            ->where('is_active', true)
            ->get()
            ->contains(fn (Location $location): bool => $this->assess($location, $carrierContext)->ready);
    }

    public function normalizeCountryCode(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $raw = strtoupper(trim((string) $value));
        $raw = str_replace('.', '', $raw);

        return match ($raw) {
            'UNITED STATES', 'UNITED STATES OF AMERICA', 'USA', 'US' => 'US',
            'UNITED KINGDOM', 'UK', 'GB' => 'GB',
            'CANADA', 'CA' => 'CA',
            'PAKISTAN', 'PK' => 'PK',
            'UNITED ARAB EMIRATES', 'UAE', 'AE' => 'AE',
            default => strlen($raw) === 2 ? $raw : null,
        };
    }

    /**
     * @return list<string>
     */
    public function missingRequiredFields(array $normalizedAddress, ?string $countryCode): array
    {
        $missing = [];

        foreach ([
            'address_line1' => 'Address line 1',
            'city' => 'City',
            'country_code' => 'Country code',
        ] as $field => $label) {
            if (! filled($normalizedAddress[$field] ?? null)) {
                $missing[] = $label;
            }
        }

        if ($countryCode === 'US' && ! filled($normalizedAddress['state'] ?? null)) {
            $missing[] = 'State';
        }

        if (! filled($normalizedAddress['postal_code'] ?? null)) {
            $missing[] = 'ZIP code';
        }

        return $missing;
    }

    public function extractOriginZip5(?string $postalCode): ?string
    {
        if ($postalCode === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $postalCode);

        if ($digits === null || strlen($digits) < 5) {
            return null;
        }

        return substr($digits, 0, 5);
    }

    private function isValidIsoCountryCode(string $countryCode): bool
    {
        if (! preg_match('/^[A-Z]{2}$/', $countryCode)) {
            return false;
        }

        return ! in_array($countryCode, ['UN', 'XX', 'ZZ'], true);
    }

    /**
     * @param  list<string>  $missingFields
     * @param  array<string, string|null>  $normalizedAddress
     */
    private function result(
        bool $ready,
        string $status,
        array $missingFields,
        array $normalizedAddress,
        ?string $originZip5,
        string $merchantMessage,
        string $badgeLabel,
    ): CarrierOriginReadinessResult {
        return new CarrierOriginReadinessResult(
            ready: $ready,
            status: $status,
            missingFields: $missingFields,
            normalizedAddress: $normalizedAddress,
            displayAddress: $this->buildDisplayAddress($normalizedAddress),
            originZip5: $originZip5,
            merchantMessage: $merchantMessage,
            badgeLabel: $badgeLabel,
        );
    }

    /**
     * @param  array<string, string|null>  $normalizedAddress
     */
    private function buildDisplayAddress(array $normalizedAddress): string
    {
        $parts = collect([
            $normalizedAddress['address_line1'] ?? null,
            $normalizedAddress['city'] ?? null,
            collect([$normalizedAddress['state'] ?? null, $normalizedAddress['postal_code'] ?? null])
                ->filter()
                ->implode(' '),
            $normalizedAddress['country_code'] ?? null,
        ])->filter()->map(fn (string $part): string => Str::upper($part));

        return $parts->implode(', ');
    }

    /**
     * @param  list<string>  $missingFields
     */
    private function missingFieldsMessage(array $missingFields): string
    {
        $list = collect($missingFields)
            ->map(fn (string $field): string => $field === 'ZIP code' ? 'ZIP code' : $field)
            ->unique()
            ->values();

        if ($list->count() === 1) {
            return 'This fulfillment location is missing '.$list->first().'. Carrier rate quotes need a complete ship-from address.';
        }

        $last = $list->pop();

        return 'This fulfillment location is missing '.$list->implode(', ').' and '.$last.'. Carrier rate quotes need a complete ship-from address.';
    }
}
