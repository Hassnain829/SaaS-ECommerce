<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('return_id')->nullable()->constrained('returns')->nullOnDelete();
            $table->foreignId('payment_intent_id')->nullable()->constrained('payment_intents')->nullOnDelete();
            $table->foreignId('payment_provider_account_id')->nullable()->constrained('payment_provider_accounts')->nullOnDelete();
            $table->string('refund_number', 64);
            $table->string('status', 32)->default('pending');
            $table->string('method', 32)->default('manual');
            $table->string('currency_code', 3);
            $table->decimal('amount', 14, 4);
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('idempotency_key', 120)->nullable();
            $table->string('provider', 40)->nullable();
            $table->string('provider_refund_id')->nullable();
            $table->string('mode', 16)->nullable();
            $table->string('provider_account_id')->nullable();
            $table->string('payment_owner', 20)->nullable();
            $table->string('order_source_snapshot', 64)->nullable();
            $table->json('routing_snapshot')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'refund_number'], 'refunds_store_number_unique');
            $table->unique(['store_id', 'idempotency_key'], 'refunds_store_idempotency_unique');
            $table->index(['store_id', 'order_id'], 'refunds_store_order_index');
            $table->index(['store_id', 'status'], 'refunds_store_status_index');
        });

        Schema::create('refund_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('refund_id')->constrained('refunds')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('unit_amount', 14, 4)->default(0);
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->decimal('tax_amount', 14, 4)->default(0);
            $table->decimal('total', 14, 4)->default(0);
            $table->string('product_name_snapshot')->nullable();
            $table->string('variant_label_snapshot')->nullable();
            $table->string('sku_snapshot')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'refund_id'], 'refund_items_store_refund_index');
            $table->index(['store_id', 'order_item_id'], 'refund_items_store_order_item_index');
        });

        Schema::create('refund_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('refund_id')->constrained('refunds')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('label')->nullable();
            $table->decimal('amount', 14, 4)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'refund_id'], 'refund_adjustments_store_refund_index');
        });

        Schema::create('exchanges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('return_id')->nullable()->constrained('returns')->nullOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained('refunds')->nullOnDelete();
            $table->string('exchange_number', 64);
            $table->string('status', 32)->default('requested');
            $table->string('currency_code', 3);
            $table->decimal('outbound_total', 14, 4)->default(0);
            $table->decimal('inbound_total', 14, 4)->default(0);
            $table->decimal('price_difference', 14, 4)->default(0);
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'exchange_number'], 'exchanges_store_number_unique');
            $table->index(['store_id', 'order_id'], 'exchanges_store_order_index');
            $table->index(['store_id', 'status'], 'exchanges_store_status_index');
        });

        Schema::create('exchange_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exchange_id')->constrained('exchanges')->cascadeOnDelete();
            $table->string('direction', 16);
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('inventory_reservation_id')->nullable()->constrained('inventory_reservations')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->decimal('line_total', 14, 4)->default(0);
            $table->string('product_name_snapshot')->nullable();
            $table->string('variant_label_snapshot')->nullable();
            $table->string('sku_snapshot')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'exchange_id'], 'exchange_items_store_exchange_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_items');
        Schema::dropIfExists('exchanges');
        Schema::dropIfExists('refund_adjustments');
        Schema::dropIfExists('refund_items');
        Schema::dropIfExists('refunds');
    }
};
