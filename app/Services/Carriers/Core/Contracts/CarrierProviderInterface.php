<?php

namespace App\Services\Carriers\Core\Contracts;

use App\Models\CarrierAccount;
use App\Services\Carriers\Core\DTO\CarrierConnectionTestResult;

interface CarrierProviderInterface
{
    public function providerCode(): string;

    public function testConnection(CarrierAccount $account): CarrierConnectionTestResult;

    public function supportsRates(?CarrierAccount $account = null): bool;

    public function supportsLabels(): bool;

    public function supportsTracking(): bool;
}
