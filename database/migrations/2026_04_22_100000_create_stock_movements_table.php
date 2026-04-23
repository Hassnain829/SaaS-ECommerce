<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only inventory audit trail (Day 11). Live stock remains on product_variants.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnDelete();
            // nullOnDelete: variant rows are often recreated on product edit; history must survive.
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->integer('previous_stock')->nullable();
            $table->integer('quantity_change');
            $table->integer('new_stock')->nullable();
            $table->string('movement_type', 64);
            $table->string('reason', 255)->nullable();
            $table->string('source', 64)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_type', 64)->nullable();
            $table->string('reference_code', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('store_id');
            $table->index('product_id');
            $table->index('variant_id');
            $table->index('movement_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
