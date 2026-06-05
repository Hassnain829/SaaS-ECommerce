<?php

namespace App\Services\Carriers;

use App\Models\CarrierAccount;
use App\Services\Carriers\FedEx\FedExCarrierProvider;
use InvalidArgumentException;

class CarrierProviderManager
{
    public function __construct(
        private readonly FedExCarrierProvider $fedExCarrierProvider,
    ) {
    }

    public function provider(string $code): CarrierProviderInterface
    {
        return match (strtolower($code)) {
            CarrierAccount::PROVIDER_FEDEX, 'fedex' => $this->fedExCarrierProvider,
            default => throw new InvalidArgumentException("Carrier provider [{$code}] is not supported."),
        };
    }

    public function supports(string $code): bool
    {
        return in_array(strtolower($code), [CarrierAccount::PROVIDER_FEDEX], true);
    }
}
