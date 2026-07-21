<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('code', 100);
            $table->string('name');
            $table->string('type', 20);
            $table->decimal('value', 14, 4);
            $table->decimal('minimum_order_amount', 14, 2)->default(0);
            $table->decimal('maximum_discount_amount', 14, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('total_usage_limit')->nullable();
            $table->unsignedInteger('per_customer_usage_limit')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'code'], 'coupons_store_code_unique');
            $table->index(['store_id', 'is_active'], 'coupons_store_active_index');
        });

        Schema::create('coupon_product', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'product_id']);
        });

        Schema::create('category_coupon', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'category_id']);
        });

        Schema::create('coupon_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('checkout_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code_snapshot', 100);
            $table->decimal('discount_amount', 14, 2);
            $table->string('status', 20)->default('reserved');
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();

            $table->unique('checkout_id', 'coupon_redemptions_checkout_unique');
            $table->unique('order_id', 'coupon_redemptions_order_unique');
            $table->index(['store_id', 'coupon_id', 'status'], 'coupon_redemptions_usage_index');
            $table->index(['coupon_id', 'customer_id', 'status'], 'coupon_redemptions_customer_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('category_coupon');
        Schema::dropIfExists('coupon_product');
        Schema::dropIfExists('coupons');
    }
};
