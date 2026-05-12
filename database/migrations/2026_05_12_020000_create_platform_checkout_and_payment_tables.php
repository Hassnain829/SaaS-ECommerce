<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_provider_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('mode', 20)->default('test');
            $table->string('connection_type', 40)->default('platform');
            $table->string('display_name')->nullable();
            $table->string('status', 40)->default('not_configured');
            $table->boolean('is_default')->default(false);
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'provider', 'mode'], 'payment_provider_store_provider_mode_index');
            $table->index(['store_id', 'is_default'], 'payment_provider_store_default_index');
        });

        Schema::create('checkouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('checkout_number', 64);
            $table->string('source_channel', 64)->default('dev_storefront');
            $table->string('mode', 64)->default('platform_checkout');
            $table->string('status', 40)->default('payment_pending');
            $table->string('currency_code', 8)->default('USD');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('shipping_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->string('payment_provider', 40)->default('stripe');
            $table->foreignId('payment_provider_account_id')->nullable()->constrained('payment_provider_accounts')->nullOnDelete();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('converted_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'checkout_number'], 'checkouts_store_number_unique');
            $table->index(['store_id', 'status'], 'checkouts_store_status_index');
            $table->index(['store_id', 'source_channel'], 'checkouts_store_source_index');
            $table->index('converted_order_id', 'checkouts_converted_order_index');
            $table->index('stripe_payment_intent_id', 'checkouts_stripe_intent_index');
        });

        Schema::create('checkout_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checkout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name');
            $table->string('variant_label')->nullable();
            $table->string('sku_snapshot')->nullable();
            $table->string('product_slug_snapshot')->nullable();
            $table->string('brand_name_snapshot')->nullable();
            $table->string('product_image_snapshot')->nullable();
            $table->string('product_type_snapshot')->nullable();
            $table->json('variant_details')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['checkout_id', 'product_variant_id'], 'checkout_items_checkout_variant_index');
        });

        Schema::create('checkout_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checkout_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('company')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('province_code', 32)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('country')->nullable();
            $table->string('country_code', 8)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('delivery_notes')->nullable();
            $table->timestamps();

            $table->unique(['checkout_id', 'type'], 'checkout_addresses_checkout_type_unique');
        });

        Schema::create('checkout_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checkout_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 100);
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'event_type'], 'checkout_events_store_type_index');
        });

        Schema::create('payment_intents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checkout_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_provider_account_id')->nullable()->constrained('payment_provider_accounts')->nullOnDelete();
            $table->string('provider', 40)->default('stripe');
            $table->string('mode', 20)->default('test');
            $table->string('provider_intent_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->string('status', 40)->default('requires_payment_method');
            $table->string('currency_code', 8)->default('USD');
            $table->decimal('amount', 14, 2)->default(0);
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_intent_id'], 'payment_intents_provider_intent_unique');
            $table->index(['store_id', 'status'], 'payment_intents_store_status_index');
            $table->index('checkout_id', 'payment_intents_checkout_index');
            $table->index('order_id', 'payment_intents_order_index');
        });

        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_intent_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40)->default('stripe');
            $table->string('provider_attempt_id')->nullable();
            $table->string('status', 40);
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status'], 'payment_attempts_store_status_index');
        });

        Schema::create('payment_captures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_intent_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40)->default('stripe');
            $table->string('provider_capture_id')->nullable();
            $table->string('status', 40)->default('captured');
            $table->decimal('amount', 14, 2)->default(0);
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->string('currency_code', 8)->default('USD');
            $table->json('response_payload')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status'], 'payment_captures_store_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_captures');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('payment_intents');
        Schema::dropIfExists('checkout_events');
        Schema::dropIfExists('checkout_addresses');
        Schema::dropIfExists('checkout_items');
        Schema::dropIfExists('checkouts');
        Schema::dropIfExists('payment_provider_accounts');
    }
};
