<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('carrier_accounts')
            || ! Schema::hasColumn('carrier_accounts', 'usps_authorization_status')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS carrier_accounts_store_usps_merchant_active_unique '
            .'ON carrier_accounts (store_id) '
            ."WHERE connection_mode = 'usps_merchant_label_provider' "
            ."AND usps_authorization_status != 'disabled' "
            .'AND deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('carrier_accounts')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS carrier_accounts_store_usps_merchant_active_unique');
        }
    }
};
