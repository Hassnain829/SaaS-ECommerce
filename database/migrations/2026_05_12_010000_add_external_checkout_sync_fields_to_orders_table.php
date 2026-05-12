<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'external_checkout_reference')) {
                $table->string('external_checkout_reference')->nullable()->after('external_order_number');
            }

            $table->unique(
                ['store_id', 'order_source', 'channel', 'external_order_number'],
                'orders_external_checkout_order_unique'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_external_checkout_order_unique');

            if (Schema::hasColumn('orders', 'external_checkout_reference')) {
                $table->dropColumn('external_checkout_reference');
            }
        });
    }
};
