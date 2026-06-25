<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('draft_tax_lines')) {
            return;
        }

        Schema::create('draft_tax_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('draft_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->char('jurisdiction_country_code', 2);
            $table->string('jurisdiction_region_code', 32)->default('');
            $table->decimal('rate_percent', 8, 4);
            $table->decimal('taxable_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->string('applies_to', 32);
            $table->unsignedInteger('settings_version');
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->index(['store_id', 'draft_order_id'], 'draft_tax_lines_store_draft_index');
            $table->index(['draft_order_id', 'applies_to'], 'draft_tax_lines_draft_applies_index');
            $table->index('tax_rate_id', 'draft_tax_lines_tax_rate_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draft_tax_lines');
    }
};
