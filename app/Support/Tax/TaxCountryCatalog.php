<?php

namespace App\Support\Tax;

final class TaxCountryCatalog
{
    /** @var array<string, string>|null */
    private static ?array $countries = null;

    /**
     * @return array<string, string> ISO-2 code => English country name, sorted by name
     */
    public static function all(): array
    {
        $countries = self::countries();
        asort($countries, SORT_STRING | SORT_FLAG_CASE);

        return $countries;
    }

    public static function name(string $code): string
    {
        $normalized = strtoupper(trim($code));
        $countries = self::countries();

        return $countries[$normalized] ?? $normalized;
    }

    /**
     * @return array<string, string> region code => label
     */
    public static function regionsFor(string $countryCode): array
    {
        $normalizedCountry = strtoupper(trim($countryCode));
        $regions = self::regionCatalog()[$normalizedCountry] ?? [];

        asort($regions, SORT_STRING | SORT_FLAG_CASE);

        return $regions;
    }

    public static function regionLabel(string $countryCode, string $regionCode): ?string
    {
        $normalizedCountry = strtoupper(trim($countryCode));
        $normalizedRegion = strtoupper(trim($regionCode));

        if ($normalizedRegion === '') {
            return null;
        }

        return self::regionCatalog()[$normalizedCountry][$normalizedRegion] ?? null;
    }

    /**
     * @return array{
     *     country_name: string,
     *     scope: 'country-wide'|'region-specific',
     *     region_label: ?string,
     *     display: string,
     * }
     */
    public static function jurisdictionSummary(string $countryCode, ?string $regionCode = null): array
    {
        $normalizedCountry = strtoupper(trim($countryCode));
        $normalizedRegion = $regionCode === null ? '' : strtoupper(trim($regionCode));
        $countryName = self::name($normalizedCountry);

        if ($normalizedRegion === '') {
            return [
                'country_name' => $countryName,
                'scope' => 'country-wide',
                'region_label' => null,
                'display' => $countryName,
            ];
        }

        $regionLabel = self::regionLabel($normalizedCountry, $normalizedRegion);

        return [
            'country_name' => $countryName,
            'scope' => 'region-specific',
            'region_label' => $regionLabel,
            'display' => $regionLabel !== null
                ? "{$regionLabel}, {$countryName}"
                : "{$normalizedRegion}, {$countryName}",
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function countries(): array
    {
        if (self::$countries === null) {
            /** @var array<string, string> $countries */
            $countries = require __DIR__.'/data/countries.php';
            self::$countries = $countries;
        }

        return self::$countries;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function regionCatalog(): array
    {
        return [
            'US' => [
                'AL' => 'Alabama',
                'AK' => 'Alaska',
                'AZ' => 'Arizona',
                'AR' => 'Arkansas',
                'CA' => 'California',
                'CO' => 'Colorado',
                'CT' => 'Connecticut',
                'DE' => 'Delaware',
                'DC' => 'District of Columbia',
                'FL' => 'Florida',
                'GA' => 'Georgia',
                'HI' => 'Hawaii',
                'ID' => 'Idaho',
                'IL' => 'Illinois',
                'IN' => 'Indiana',
                'IA' => 'Iowa',
                'KS' => 'Kansas',
                'KY' => 'Kentucky',
                'LA' => 'Louisiana',
                'ME' => 'Maine',
                'MD' => 'Maryland',
                'MA' => 'Massachusetts',
                'MI' => 'Michigan',
                'MN' => 'Minnesota',
                'MS' => 'Mississippi',
                'MO' => 'Missouri',
                'MT' => 'Montana',
                'NE' => 'Nebraska',
                'NV' => 'Nevada',
                'NH' => 'New Hampshire',
                'NJ' => 'New Jersey',
                'NM' => 'New Mexico',
                'NY' => 'New York',
                'NC' => 'North Carolina',
                'ND' => 'North Dakota',
                'OH' => 'Ohio',
                'OK' => 'Oklahoma',
                'OR' => 'Oregon',
                'PA' => 'Pennsylvania',
                'RI' => 'Rhode Island',
                'SC' => 'South Carolina',
                'SD' => 'South Dakota',
                'TN' => 'Tennessee',
                'TX' => 'Texas',
                'UT' => 'Utah',
                'VT' => 'Vermont',
                'VA' => 'Virginia',
                'WA' => 'Washington',
                'WV' => 'West Virginia',
                'WI' => 'Wisconsin',
                'WY' => 'Wyoming',
            ],
            'CA' => [
                'AB' => 'Alberta',
                'BC' => 'British Columbia',
                'MB' => 'Manitoba',
                'NB' => 'New Brunswick',
                'NL' => 'Newfoundland and Labrador',
                'NS' => 'Nova Scotia',
                'NT' => 'Northwest Territories',
                'NU' => 'Nunavut',
                'ON' => 'Ontario',
                'PE' => 'Prince Edward Island',
                'QC' => 'Quebec',
                'SK' => 'Saskatchewan',
                'YT' => 'Yukon',
            ],
            'AU' => [
                'ACT' => 'Australian Capital Territory',
                'NSW' => 'New South Wales',
                'NT' => 'Northern Territory',
                'QLD' => 'Queensland',
                'SA' => 'South Australia',
                'TAS' => 'Tasmania',
                'VIC' => 'Victoria',
                'WA' => 'Western Australia',
            ],
            'MX' => [
                'AGU' => 'Aguascalientes',
                'BCN' => 'Baja California',
                'BCS' => 'Baja California Sur',
                'CAM' => 'Campeche',
                'CHP' => 'Chiapas',
                'CHH' => 'Chihuahua',
                'CMX' => 'Ciudad de México',
                'COA' => 'Coahuila',
                'COL' => 'Colima',
                'DUR' => 'Durango',
                'GUA' => 'Guanajuato',
                'GRO' => 'Guerrero',
                'HID' => 'Hidalgo',
                'JAL' => 'Jalisco',
                'MEX' => 'México',
                'MIC' => 'Michoacán',
                'MOR' => 'Morelos',
                'NAY' => 'Nayarit',
                'NLE' => 'Nuevo León',
                'OAX' => 'Oaxaca',
                'PUE' => 'Puebla',
                'QUE' => 'Querétaro',
                'ROO' => 'Quintana Roo',
                'SLP' => 'San Luis Potosí',
                'SIN' => 'Sinaloa',
                'SON' => 'Sonora',
                'TAB' => 'Tabasco',
                'TAM' => 'Tamaulipas',
                'TLA' => 'Tlaxcala',
                'VER' => 'Veracruz',
                'YUC' => 'Yucatán',
                'ZAC' => 'Zacatecas',
            ],
            'IN' => [
                'AN' => 'Andaman and Nicobar Islands',
                'AP' => 'Andhra Pradesh',
                'AR' => 'Arunachal Pradesh',
                'AS' => 'Assam',
                'BR' => 'Bihar',
                'CH' => 'Chandigarh',
                'CG' => 'Chhattisgarh',
                'DH' => 'Dadra and Nagar Haveli and Daman and Diu',
                'DL' => 'Delhi',
                'GA' => 'Goa',
                'GJ' => 'Gujarat',
                'HR' => 'Haryana',
                'HP' => 'Himachal Pradesh',
                'JK' => 'Jammu and Kashmir',
                'JH' => 'Jharkhand',
                'KA' => 'Karnataka',
                'KL' => 'Kerala',
                'LA' => 'Ladakh',
                'LD' => 'Lakshadweep',
                'MP' => 'Madhya Pradesh',
                'MH' => 'Maharashtra',
                'MN' => 'Manipur',
                'ML' => 'Meghalaya',
                'MZ' => 'Mizoram',
                'NL' => 'Nagaland',
                'OR' => 'Odisha',
                'PY' => 'Puducherry',
                'PB' => 'Punjab',
                'RJ' => 'Rajasthan',
                'SK' => 'Sikkim',
                'TN' => 'Tamil Nadu',
                'TS' => 'Telangana',
                'TR' => 'Tripura',
                'UP' => 'Uttar Pradesh',
                'UK' => 'Uttarakhand',
                'WB' => 'West Bengal',
            ],
            'DE' => [
                'BW' => 'Baden-Württemberg',
                'BY' => 'Bavaria',
                'BE' => 'Berlin',
                'BB' => 'Brandenburg',
                'HB' => 'Bremen',
                'HH' => 'Hamburg',
                'HE' => 'Hesse',
                'MV' => 'Mecklenburg-Vorpommern',
                'NI' => 'Lower Saxony',
                'NW' => 'North Rhine-Westphalia',
                'RP' => 'Rhineland-Palatinate',
                'SL' => 'Saarland',
                'SN' => 'Saxony',
                'ST' => 'Saxony-Anhalt',
                'SH' => 'Schleswig-Holstein',
                'TH' => 'Thuringia',
            ],
            'GB' => [
                'ENG' => 'England',
                'NIR' => 'Northern Ireland',
                'SCT' => 'Scotland',
                'WLS' => 'Wales',
            ],
        ];
    }
}
