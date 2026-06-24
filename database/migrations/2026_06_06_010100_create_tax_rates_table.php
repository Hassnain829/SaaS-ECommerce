<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->char('country_code', 2);
            $table->string('region_code', 32)->default('');
            $table->string('name', 120);
            $table->decimal('rate_percent', 8, 4);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'country_code', 'region_code'], 'tax_rates_store_country_region_unique');
            $table->index(['store_id', 'is_active', 'priority'], 'tax_rates_store_active_priority_index');
            $table->index(['store_id', 'country_code', 'region_code'], 'tax_rates_store_jurisdiction_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
