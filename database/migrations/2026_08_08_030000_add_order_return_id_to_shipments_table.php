<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            if (! Schema::hasColumn('shipments', 'order_return_id')) {
                $table->foreignId('order_return_id')
                    ->nullable()
                    ->after('order_id')
                    ->constrained('returns')
                    ->nullOnDelete();
                $table->index(['store_id', 'order_return_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            if (Schema::hasColumn('shipments', 'order_return_id')) {
                $table->dropIndex(['store_id', 'order_return_id']);
                $table->dropConstrainedForeignId('order_return_id');
            }
        });
    }
};
