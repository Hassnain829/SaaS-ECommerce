<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Validation\ValidationException;

class ExternalShipmentSyncService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{shipment: never, created: never}
     */
    public function sync(Store $store, array $payload, string $requestHash): array
    {
        throw ValidationException::withMessages([
            'checkout' => 'External shipment sync is no longer available.',
        ]);
    }
}
