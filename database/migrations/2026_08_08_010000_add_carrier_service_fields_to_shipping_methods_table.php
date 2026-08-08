<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table): void {
            if (! Schema::hasColumn('shipping_methods', 'carrier_service_code')) {
                $table->string('carrier_service_code', 64)->nullable()->after('carrier_account_id');
            }
            if (! Schema::hasColumn('shipping_methods', 'carrier_service_name')) {
                $table->string('carrier_service_name', 120)->nullable()->after('carrier_service_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table): void {
            if (Schema::hasColumn('shipping_methods', 'carrier_service_name')) {
                $table->dropColumn('carrier_service_name');
            }
            if (Schema::hasColumn('shipping_methods', 'carrier_service_code')) {
                $table->dropColumn('carrier_service_code');
            }
        });
    }
};
