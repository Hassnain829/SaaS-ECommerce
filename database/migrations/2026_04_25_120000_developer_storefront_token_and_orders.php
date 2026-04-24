<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('developer_storefront_token_hash', 64)->nullable()->after('onboarding_completed')->unique();
            $table->timestamp('developer_storefront_token_created_at')->nullable()->after('developer_storefront_token_hash');
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 64)->unique();
            $table->string('status', 32)->default('confirmed');
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->decimal('total', 14, 2);
            $table->string('currency', 8)->default('USD');
            $table->string('source', 32)->default('developer_storefront');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name');
            $table->string('variant_label')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['developer_storefront_token_hash', 'developer_storefront_token_created_at']);
        });
    }
};
