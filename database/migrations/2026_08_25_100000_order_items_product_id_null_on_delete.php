<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historical order lines must survive permanent catalog product deletion.
 * order_items.product_id: NOT NULL CASCADE → NULLABLE SET NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_items') || ! Schema::hasColumn('order_items', 'product_id')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table): void {
            $this->dropForeignCompat($table, 'order_items_product_id_foreign', ['product_id']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id')->nullable()->change();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_items') || ! Schema::hasColumn('order_items', 'product_id')) {
            return;
        }

        // Cannot safely restore NOT NULL + CASCADE if any product_id is already NULL.
        if (DB::table('order_items')->whereNull('product_id')->exists()) {
            throw new RuntimeException(
                'Cannot safely roll back order_items.product_id nullOnDelete: historical rows contain NULL product references.'
            );
        }

        Schema::table('order_items', function (Blueprint $table): void {
            $this->dropForeignCompat($table, 'order_items_product_id_foreign', ['product_id']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropForeignCompat(Blueprint $table, string $fkName, array $columns): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $table->dropForeign($columns);

            return;
        }

        $table->dropForeign($fkName);
    }
};
