<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carrier_api_events', function (Blueprint $table): void {
            $table->string('validation_region', 16)->nullable()->after('label_format');
            $table->index(
                ['store_id', 'carrier_account_id', 'validation_region', 'scenario_key'],
                'carrier_api_events_store_account_region_scenario_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('carrier_api_events', function (Blueprint $table): void {
            $table->dropIndex('carrier_api_events_store_account_region_scenario_idx');
            $table->dropColumn('validation_region');
        });
    }
};
