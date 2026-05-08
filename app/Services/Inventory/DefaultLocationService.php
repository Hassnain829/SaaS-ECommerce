<?php

namespace App\Services\Inventory;

use App\Models\Location;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DefaultLocationService
{
    public function ensureForStore(Store $store, ?User $actor = null): Location
    {
        return DB::transaction(function () use ($store, $actor): Location {
            return $this->ensureDefaultLocation($store, $actor);
        });
    }

    public function ensureFromStoreDefaults(Store $store, ?User $actor = null): Location
    {
        return DB::transaction(function () use ($store, $actor): Location {
            $location = $this->ensureDefaultLocation($store, $actor);
            $updates = $this->blankAddressUpdates($location, $store);

            if ($updates !== []) {
                $location->update([
                    ...$updates,
                    'updated_by' => $actor?->id,
                ]);
            }

            return $location->refresh();
        });
    }

    public function ensureForAllStores(?int $storeId = null): int
    {
        $count = 0;

        $query = Store::query()->orderBy('id');

        if ($storeId !== null) {
            $query->whereKey($storeId);
        }

        $query->chunkById(100, function ($stores) use (&$count): void {
            foreach ($stores as $store) {
                $before = $store->locations()->count();
                $this->ensureFromStoreDefaults($store);
                if ($before === 0) {
                    $count++;
                }
            }
        });

        return $count;
    }

    public function makeDefault(Location $location, ?User $actor = null): Location
    {
        return DB::transaction(function () use ($location, $actor): Location {
            $location->store->locations()
                ->where('id', '!=', $location->id)
                ->where('is_default', true)
                ->update(['is_default' => false, 'updated_by' => $actor?->id]);

            $location->update([
                'is_default' => true,
                'is_active' => true,
                'updated_by' => $actor?->id,
            ]);

            return $location->refresh();
        });
    }

    private function ensureDefaultLocation(Store $store, ?User $actor = null): Location
    {
        $default = $store->locations()
            ->where('is_default', true)
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($default) {
            $store->locations()
                ->where('id', '!=', $default->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            if (! $default->is_active) {
                $default->update(['is_active' => true, 'updated_by' => $actor?->id]);
            }

            return $default->refresh();
        }

        $existingActive = $store->locations()
            ->where('is_active', true)
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($existingActive) {
            $existingActive->update([
                'is_default' => true,
                'updated_by' => $actor?->id,
            ]);

            return $existingActive->refresh();
        }

        return $store->locations()->create([
            'name' => 'Main location',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => filled($store->address) ? $store->address : null,
            'country_code' => $this->storeCountryCode($store),
            'is_default' => true,
            'is_active' => true,
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function blankAddressUpdates(Location $location, Store $store): array
    {
        $updates = [];
        $defaults = [
            'address_line1' => filled($store->address) ? (string) $store->address : null,
            'city' => $this->storeSetting($store, ['city', 'business_city', 'store_city']),
            'state' => $this->storeSetting($store, ['state', 'business_state', 'store_state']),
            'postal_code' => $this->storeSetting($store, ['postal_code', 'zip', 'store_postal_code']),
            'country_code' => $this->storeCountryCode($store),
        ];

        foreach ($defaults as $field => $value) {
            if (filled($value) && blank($location->{$field})) {
                $updates[$field] = $value;
            }
        }

        return $updates;
    }

    /**
     * @param  list<string>  $keys
     */
    private function storeSetting(Store $store, array $keys): ?string
    {
        $settings = is_array($store->settings) ? $store->settings : [];

        foreach ($keys as $key) {
            $value = $settings[$key] ?? null;
            if (filled($value)) {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function storeCountryCode(Store $store): ?string
    {
        $value = $this->storeSetting($store, ['country_code', 'business_country_code', 'store_country_code']);

        if (! $value) {
            return null;
        }

        $normalized = strtoupper(trim($value));

        return strlen($normalized) === 2 ? $normalized : null;
    }
}
