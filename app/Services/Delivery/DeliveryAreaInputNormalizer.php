<?php

namespace App\Services\Delivery;

use App\Models\ShippingZone;
use App\Support\Tax\TaxCountryCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DeliveryAreaInputNormalizer
{
    /** @var list<string> */
    private array $countryWarnings = [];

    /**
     * @return list<string>
     */
    public function consumeCountryWarnings(): array
    {
        $warnings = $this->countryWarnings;
        $this->countryWarnings = [];

        return $warnings;
    }

    /**
     * @return array{countries: ?list<string>, regions: ?list<string>, postal_patterns: ?list<string>}
     */
    public function normalizeFromRequest(Request $request): array
    {
        if ($request->input('zone_editor_mode') === 'legacy') {
            return [
                'countries' => $this->normalizeCountryList($request->input('legacy_countries'), preserveUnknown: true),
                'regions' => $this->normalizeRegionTokens($request->input('legacy_regions'), null),
                'postal_patterns' => $this->normalizeLegacyPostalInput($request->input('legacy_postal_patterns')),
            ];
        }

        if ($request->filled('countries') || $request->filled('regions') || $request->filled('postal_patterns')) {
            $countries = $this->normalizeCountryList($request->input('countries'), preserveUnknown: true);
            $primaryCountry = $countries[0] ?? null;

            return [
                'countries' => $countries,
                'regions' => $this->normalizeRegionTokens($request->input('regions'), $primaryCountry),
                'postal_patterns' => $this->normalizeLegacyPostalInput($request->input('postal_patterns')),
            ];
        }

        $countryCode = strtoupper(trim((string) $request->input('country_code', '')));
        if ($countryCode === '' || ! array_key_exists($countryCode, TaxCountryCatalog::all())) {
            throw ValidationException::withMessages([
                'country_code' => 'Choose a valid delivery country.',
            ]);
        }

        $regionCodes = $request->input('region_codes', []);
        if (! is_array($regionCodes)) {
            $regionCodes = [];
        }

        $postalRules = json_decode((string) $request->input('postal_rules_json', '[]'), true);
        if (! is_array($postalRules)) {
            $postalRules = [];
        }

        return [
            'countries' => [$countryCode],
            'regions' => $this->normalizeRegionTokens($regionCodes, $countryCode, strict: true),
            'postal_patterns' => $this->rulesToPostalPatterns($postalRules),
        ];
    }

    /**
     * @return array{
     *     editor_mode: string,
     *     name: string,
     *     country_code: string,
     *     legacy_countries: string,
     *     legacy_regions: string,
     *     legacy_postal_patterns: string,
     *     region_codes: list<string>,
     *     postal_rules: list<array{type: string, value: string}>,
     *     sort_order: int,
     *     is_active: bool
     * }
     */
    public function presentationFromZone(ShippingZone $zone): array
    {
        $countries = collect($zone->countries)->filter()->map(fn ($country): string => strtoupper(trim((string) $country)))->filter()->values()->all();
        $isLegacy = count($countries) > 1;

        $primaryCountry = $countries[0] ?? '';
        $regionCodes = collect($zone->regions)
            ->filter()
            ->map(fn ($region): string => $this->normalizeRegionToken((string) $region, $primaryCountry))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'editor_mode' => $isLegacy ? 'legacy' : 'simple',
            'name' => (string) $zone->name,
            'country_code' => $primaryCountry,
            'legacy_countries' => implode(', ', $countries),
            'legacy_regions' => collect($zone->regions)->filter()->implode(', '),
            'legacy_postal_patterns' => collect($zone->postal_patterns)->filter()->implode(', '),
            'region_codes' => $regionCodes,
            'postal_rules' => $this->postalPatternsToRules($zone->postal_patterns),
            'sort_order' => (int) $zone->sort_order,
            'is_active' => (bool) $zone->is_active,
        ];
    }

    /**
     * @param  list<array{type?: string, value?: string}>|null  $rules
     * @return list<string>|null
     */
    public function rulesToPostalPatterns(?array $rules): ?array
    {
        if ($rules === null || $rules === []) {
            return null;
        }

        $patterns = collect($rules)
            ->map(function (array $rule): ?string {
                $type = strtolower(trim((string) ($rule['type'] ?? 'exact')));
                $value = strtoupper(str_replace(' ', '', trim((string) ($rule['value'] ?? ''))));

                if ($value === '') {
                    return null;
                }

                if (str_contains($value, '*')) {
                    throw ValidationException::withMessages([
                        'postal_rules_json' => 'Enter postal codes without wildcard characters. Use Starts with for prefixes.',
                    ]);
                }

                if ($type === 'prefix' || $type === 'starts_with') {
                    return rtrim($value, '*').'*';
                }

                return $value;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $patterns === [] ? null : $patterns;
    }

    /**
     * @return list<array{type: string, value: string}>
     */
    public function postalPatternsToRules(mixed $patterns): array
    {
        return collect(is_array($patterns) ? $patterns : [])
            ->filter()
            ->map(function ($pattern): array {
                $pattern = strtoupper(str_replace(' ', '', trim((string) $pattern)));

                if ($pattern === '') {
                    return ['type' => 'exact', 'value' => ''];
                }

                if (str_ends_with($pattern, '*')) {
                    return [
                        'type' => 'prefix',
                        'value' => rtrim($pattern, '*'),
                    ];
                }

                return [
                    'type' => 'exact',
                    'value' => $pattern,
                ];
            })
            ->filter(fn (array $rule): bool => $rule['value'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<string>|null
     */
    private function normalizeCountryList(mixed $value, bool $preserveUnknown = false): ?array
    {
        $parts = $this->tokenize($value);
        $countries = collect($parts)
            ->map(fn (string $part): string => strtoupper(trim($part)))
            ->filter(fn (string $code): bool => $code !== '')
            ->unique()
            ->values();

        if ($preserveUnknown) {
            $catalog = TaxCountryCatalog::all();
            foreach ($countries as $code) {
                if (! array_key_exists($code, $catalog)) {
                    $this->countryWarnings[] = 'Country code "'.$code.'" is not in the standard catalog and was kept as imported.';
                }
            }

            return $countries->isEmpty() ? null : $countries->all();
        }

        $valid = $countries
            ->filter(fn (string $code): bool => array_key_exists($code, TaxCountryCatalog::all()))
            ->values()
            ->all();

        return $valid === [] ? null : $valid;
    }

    /**
     * @return list<string>|null
     */
    private function normalizeLegacyPostalInput(mixed $value): ?array
    {
        $parts = $this->tokenize($value);

        return $this->rulesToPostalPatterns(
            collect($parts)->map(function (string $part): array {
                if (str_ends_with($part, '*')) {
                    return ['type' => 'prefix', 'value' => rtrim($part, '*')];
                }

                return ['type' => 'exact', 'value' => $part];
            })->all()
        );
    }

    /**
     * @return list<string>|null
     */
    private function normalizeRegionTokens(mixed $value, ?string $countryCode, bool $strict = false): ?array
    {
        $tokens = is_array($value) ? $value : $this->tokenize($value);
        $catalog = ($countryCode !== null && $countryCode !== '') ? TaxCountryCatalog::regionsFor($countryCode) : [];
        $hasCatalog = $catalog !== [];

        $regions = collect($tokens)
            ->map(fn ($token): string => $this->normalizeRegionToken((string) $token, $countryCode))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($strict && $hasCatalog && $regions !== []) {
            $invalid = collect($regions)->reject(fn (string $code): bool => isset($catalog[$code]))->values();
            if ($invalid->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'region_codes' => 'Choose states or provinces that belong to the selected country.',
                ]);
            }
        }

        return $regions === [] ? null : $regions;
    }

    private function normalizeRegionToken(string $token, ?string $countryCode): string
    {
        $token = strtoupper(trim($token));
        if ($token === '') {
            return '';
        }

        if ($countryCode !== null && $countryCode !== '') {
            $catalog = TaxCountryCatalog::regionsFor($countryCode);
            if (isset($catalog[$token])) {
                return $token;
            }

            foreach ($catalog as $code => $label) {
                if (strtoupper($label) === $token) {
                    return $code;
                }
            }
        }

        return $token;
    }

    /**
     * @return list<string>
     */
    private function tokenize(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item): string => trim((string) $item))
                ->filter()
                ->values()
                ->all();
        }

        if (trim((string) $value) === '') {
            return [];
        }

        return collect(preg_split('/[\r\n,]+/', (string) $value) ?: [])
            ->map(fn ($item): string => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }
}
