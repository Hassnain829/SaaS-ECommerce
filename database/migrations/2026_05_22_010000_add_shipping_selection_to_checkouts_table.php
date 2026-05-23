<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('checkouts')) {
            return;
        }

        Schema::table('checkouts', function (Blueprint $table): void {
            if (! Schema::hasColumn('checkouts', 'shipping_method_id')) {
                $table->foreignId('shipping_method_id')
                    ->nullable()
                    ->after('shipping_total')
                    ->constrained('shipping_methods')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('checkouts', 'shipping_snapshot')) {
                $table->json('shipping_snapshot')->nullable()->after('shipping_method_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('checkouts')) {
            return;
        }

        Schema::table('checkouts', function (Blueprint $table): void {
            if (Schema::hasColumn('checkouts', 'shipping_method_id')) {
                $table->dropConstrainedForeignId('shipping_method_id');
            }

            if (Schema::hasColumn('checkouts', 'shipping_snapshot')) {
                $table->dropColumn('shipping_snapshot');
            }
        });
    }
};
