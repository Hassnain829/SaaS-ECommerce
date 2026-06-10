<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carrier_accounts', function (Blueprint $table): void {
            $table->string('ownership_mode', 40)->default('manual')->after('billing_owner');
            $table->string('connection_owner', 32)->default('merchant')->after('ownership_mode');
            $table->string('credentials_source', 40)->default('none')->after('connection_owner');
            $table->foreignId('default_origin_location_id')->nullable()->after('credentials_source')->constrained('locations')->nullOnDelete();
            $table->string('origin_validation_status', 40)->nullable()->after('default_origin_location_id');
            $table->string('origin_validation_summary', 500)->nullable()->after('origin_validation_status');
        });

        DB::table('carrier_accounts')->orderBy('id')->chunkById(100, function ($accounts): void {
            foreach ($accounts as $account) {
                $settings = json_decode((string) ($account->settings ?? 'null'), true);
                $settings = is_array($settings) ? $settings : [];
                $capabilities = json_decode((string) ($account->capabilities ?? 'null'), true);
                $capabilities = is_array($capabilities) ? $capabilities : [];

                $provider = (string) ($account->provider ?? 'manual');
                $connectionMode = (string) ($account->connection_mode ?? 'manual');
                $connectionStatus = (string) ($account->connection_status ?? 'not_connected');
                $billingOwner = (string) ($account->billing_owner ?? 'merchant');

                $ownership = match (true) {
                    $provider === 'manual' || $connectionMode === 'manual' => [
                        'ownership_mode' => 'manual',
                        'connection_owner' => 'merchant',
                        'credentials_source' => 'manual_entry',
                    ],
                    $connectionMode === 'usps_platform_api' => [
                        'ownership_mode' => 'platform_testing',
                        'connection_owner' => 'platform',
                        'credentials_source' => 'platform_env',
                    ],
                    $connectionStatus === 'sandbox_platform_fallback' => [
                        'ownership_mode' => 'platform_testing',
                        'connection_owner' => 'platform',
                        'credentials_source' => 'platform_env',
                    ],
                    $provider === 'fedex' => [
                        'ownership_mode' => 'merchant_owned',
                        'connection_owner' => 'merchant',
                        'credentials_source' => 'merchant_encrypted',
                    ],
                    default => [
                        'ownership_mode' => $billingOwner === 'platform' ? 'platform_testing' : 'merchant_owned',
                        'connection_owner' => $billingOwner === 'platform' ? 'platform' : 'merchant',
                        'credentials_source' => $billingOwner === 'platform' ? 'platform_env' : 'merchant_encrypted',
                    ],
                };

                $originLocationId = data_get($settings, 'default_origin_location_id');
                $originLocationId = filled($originLocationId) ? (int) $originLocationId : null;

                $normalizedCapabilities = array_merge([
                    'rates' => (bool) ($capabilities['rates'] ?? false),
                    'labels' => (bool) ($capabilities['labels'] ?? false),
                    'tracking' => (bool) ($capabilities['tracking'] ?? false),
                    'pickup' => (bool) ($capabilities['pickup'] ?? false),
                ], $capabilities);

                DB::table('carrier_accounts')->where('id', $account->id)->update([
                    'ownership_mode' => $ownership['ownership_mode'],
                    'connection_owner' => $ownership['connection_owner'],
                    'credentials_source' => $ownership['credentials_source'],
                    'default_origin_location_id' => $originLocationId,
                    'capabilities' => json_encode($normalizedCapabilities),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('carrier_accounts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('default_origin_location_id');
            $table->dropColumn([
                'ownership_mode',
                'connection_owner',
                'credentials_source',
                'origin_validation_status',
                'origin_validation_summary',
            ]);
        });
    }
};
