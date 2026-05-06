<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'requires_shipping')) {
                $table->boolean('requires_shipping')->default(true)->after('product_type');
            }

            if (! Schema::hasColumn('products', 'track_inventory')) {
                $table->boolean('track_inventory')->default(true)->after('requires_shipping');
            }
        });

        DB::table('products')
            ->whereIn('product_type', ['digital', 'service', 'virtual'])
            ->update(['requires_shipping' => false]);

        DB::table('products')
            ->whereIn('product_type', ['digital', 'service', 'subscription', 'virtual'])
            ->update(['track_inventory' => false]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'track_inventory')) {
                $table->dropColumn('track_inventory');
            }

            if (Schema::hasColumn('products', 'requires_shipping')) {
                $table->dropColumn('requires_shipping');
            }
        });
    }
};
