<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('sku')->nullable();
            $table->boolean('tracked')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('store_id');
            $table->index('product_id');
            $table->index('variant_id');
            $table->index(['store_id', 'sku']);
            $table->unique(['store_id', 'variant_id'], 'inventory_items_store_variant_unique');
        });

        Schema::create('inventory_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->integer('available')->default(0);
            $table->integer('reserved')->default(0);
            $table->integer('committed')->default(0);
            $table->integer('incoming')->default(0);
            $table->timestamps();

            $table->index('store_id');
            $table->index('inventory_item_id');
            $table->index('location_id');
            $table->unique(['store_id', 'inventory_item_id', 'location_id'], 'inventory_levels_store_item_location_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_levels');
        Schema::dropIfExists('inventory_items');
    }
};
