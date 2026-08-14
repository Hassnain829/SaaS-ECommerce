<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Validation\ValidationException;

class ExternalOrderSyncService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{order: never, created: never}
     */
    public function sync(Store $store, array $payload, string $requestHash): array
    {
        throw ValidationException::withMessages([
            'checkout' => 'External checkout is no longer available. Create orders through platform checkout.',
        ]);
    }
}
