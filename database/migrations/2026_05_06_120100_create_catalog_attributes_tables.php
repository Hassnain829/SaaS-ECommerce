<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('display_type')->default('text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('store_id');
            $table->index('slug');
            $table->unique(['store_id', 'slug']);
        });

        Schema::create('attribute_terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('swatch_value')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('attribute_id');
            $table->unique(['attribute_id', 'slug']);
        });

        Schema::create('product_attributes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->boolean('is_variation')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('product_id');
            $table->index('attribute_id');
            $table->unique(['product_id', 'attribute_id']);
        });

        Schema::create('product_attribute_terms', function (Blueprint $table): void {
            $table->foreignId('product_attribute_id')->constrained('product_attributes')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('attribute_terms')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['product_attribute_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_terms');
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('attribute_terms');
        Schema::dropIfExists('attributes');
    }
};
