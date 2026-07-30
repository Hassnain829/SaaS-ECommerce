<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_reasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(100);
            $table->timestamps();

            $table->unique(['store_id', 'code'], 'return_reasons_store_code_unique');
            $table->index(['store_id', 'is_active', 'sort_order'], 'return_reasons_store_active_sort_index');
        });

        Schema::create('returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('return_number', 64);
            $table->string('status', 32)->default('requested');
            $table->string('source', 40)->default('merchant');
            $table->foreignId('return_reason_id')->nullable()->constrained('return_reasons')->nullOnDelete();
            $table->text('merchant_notes')->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('manual_instructions')->nullable();
            $table->string('tracking_reference')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'return_number'], 'returns_store_number_unique');
            $table->index(['store_id', 'status'], 'returns_store_status_index');
            $table->index(['store_id', 'order_id'], 'returns_store_order_index');
        });

        Schema::create('return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->unsignedInteger('requested_quantity')->default(0);
            $table->unsignedInteger('approved_quantity')->default(0);
            $table->unsignedInteger('received_quantity')->default(0);
            $table->unsignedInteger('restocked_quantity')->default(0);
            $table->string('condition', 40)->nullable();
            $table->string('disposition', 40)->nullable();
            $table->boolean('restock')->default(false);
            $table->foreignId('restock_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('product_name_snapshot')->nullable();
            $table->string('variant_label_snapshot')->nullable();
            $table->string('sku_snapshot')->nullable();
            $table->string('product_type_snapshot')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'return_id'], 'return_items_store_return_index');
            $table->index(['store_id', 'order_item_id'], 'return_items_store_order_item_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
        Schema::dropIfExists('return_reasons');
    }
};
