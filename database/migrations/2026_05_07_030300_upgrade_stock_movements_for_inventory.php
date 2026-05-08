<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            if (! Schema::hasColumn('stock_movements', 'location_id')) {
                $table->foreignId('location_id')->nullable()->after('variant_id')->constrained('locations')->nullOnDelete();
            }
            if (! Schema::hasColumn('stock_movements', 'inventory_item_id')) {
                $table->foreignId('inventory_item_id')->nullable()->after('location_id')->constrained('inventory_items')->nullOnDelete();
            }
            if (! Schema::hasColumn('stock_movements', 'inventory_level_id')) {
                $table->foreignId('inventory_level_id')->nullable()->after('inventory_item_id')->constrained('inventory_levels')->nullOnDelete();
            }
            if (! Schema::hasColumn('stock_movements', 'reservation_id')) {
                $table->foreignId('reservation_id')->nullable()->after('inventory_level_id')->constrained('inventory_reservations')->nullOnDelete();
            }
            if (! Schema::hasColumn('stock_movements', 'available_before')) {
                $table->integer('available_before')->nullable()->after('new_stock');
            }
            if (! Schema::hasColumn('stock_movements', 'available_after')) {
                $table->integer('available_after')->nullable()->after('available_before');
            }
            if (! Schema::hasColumn('stock_movements', 'reserved_before')) {
                $table->integer('reserved_before')->nullable()->after('available_after');
            }
            if (! Schema::hasColumn('stock_movements', 'reserved_after')) {
                $table->integer('reserved_after')->nullable()->after('reserved_before');
            }
            if (! Schema::hasColumn('stock_movements', 'committed_before')) {
                $table->integer('committed_before')->nullable()->after('reserved_after');
            }
            if (! Schema::hasColumn('stock_movements', 'committed_after')) {
                $table->integer('committed_after')->nullable()->after('committed_before');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            foreach ([
                'reservation_id',
                'inventory_level_id',
                'inventory_item_id',
                'location_id',
            ] as $column) {
                if (Schema::hasColumn('stock_movements', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach ([
                'committed_after',
                'committed_before',
                'reserved_after',
                'reserved_before',
                'available_after',
                'available_before',
            ] as $column) {
                if (Schema::hasColumn('stock_movements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
