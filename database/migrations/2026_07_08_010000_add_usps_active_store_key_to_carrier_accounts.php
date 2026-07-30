<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('carrier_accounts')) {
            return;
        }

        if (! Schema::hasColumn('carrier_accounts', 'usps_active_store_key')) {
            Schema::table('carrier_accounts', function (Blueprint $table): void {
                $table->unsignedBigInteger('usps_active_store_key')->nullable()->unique();
            });
        }

        if (! Schema::hasColumn('carrier_accounts', 'usps_authorization_status')) {
            return;
        }

        DB::table('carrier_accounts')
            ->where('connection_mode', 'usps_merchant_label_provider')
            ->where('usps_authorization_status', '!=', 'disabled')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'store_id'])
            ->groupBy('store_id')
            ->each(function ($accounts, $storeId): void {
                $keeper = $accounts->first();

                if ($keeper === null) {
                    return;
                }

                DB::table('carrier_accounts')
                    ->where('id', $keeper->id)
                    ->update(['usps_active_store_key' => (int) $storeId]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('carrier_accounts') || ! Schema::hasColumn('carrier_accounts', 'usps_active_store_key')) {
            return;
        }

        Schema::table('carrier_accounts', function (Blueprint $table): void {
            $table->dropUnique(['usps_active_store_key']);
            $table->dropColumn('usps_active_store_key');
        });
    }
};
