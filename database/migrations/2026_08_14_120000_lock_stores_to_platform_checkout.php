<?php

use App\Models\Store;
use App\Support\CheckoutMode;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! class_exists(Store::class)) {
            return;
        }

        Store::query()->orderBy('id')->chunkById(100, function ($stores): void {
            foreach ($stores as $store) {
                $settings = is_array($store->settings) ? $store->settings : [];
                $channels = is_array($settings['channels'] ?? null) ? $settings['channels'] : [];
                $external = is_array($channels['external_checkout'] ?? null) ? $channels['external_checkout'] : [];
                $external['enabled'] = false;
                $external['inventory_owner'] = 'platform';
                $channels['external_checkout'] = $external;
                $settings['channels'] = $channels;
                $settings['checkout_mode'] = CheckoutMode::PLATFORM;
                $store->forceFill(['settings' => $settings])->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        // Historical checkout_mode values are not restored. Runtime remains platform-only.
    }
};
