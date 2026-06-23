<?php

namespace App\Services\Carriers\DTO;

final class CarrierOriginReadinessResult
{
    public const STATUS_READY = 'ready';

    public const STATUS_MISSING_FIELDS = 'missing_fields';

    public const STATUS_UNSUPPORTED_COUNTRY = 'unsupported_country';

    public const STATUS_INVALID_COUNTRY_CODE = 'invalid_country_code';

    public const STATUS_INVALID_POSTAL_CODE = 'invalid_postal_code';

    /**
     * @param  list<string>  $missingFields
     * @param  array<string, string|null>  $normalizedAddress
     */
    public function __construct(
        public readonly bool $ready,
        public readonly string $status,
        public readonly array $missingFields,
        public readonly array $normalizedAddress,
        public readonly string $displayAddress,
        public readonly ?string $originZip5,
        public readonly string $merchantMessage,
        public readonly string $badgeLabel,
    ) {}

    public function isUspsReady(): bool
    {
        return $this->ready && ($this->normalizedAddress['country_code'] ?? null) === 'US';
    }
}
