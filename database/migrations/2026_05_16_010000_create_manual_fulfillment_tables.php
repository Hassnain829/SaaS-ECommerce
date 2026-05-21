<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carriers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type', 40)->default('manual');
            $table->string('website_url')->nullable();
            $table->string('tracking_url_template')->nullable();
            $table->boolean('is_system')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        Schema::create('carrier_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('carrier_id')->constrained('carriers')->cascadeOnDelete();
            $table->string('display_name');
            $table->string('connection_type', 32)->default('manual');
            $table->string('status', 32)->default('enabled');
            $table->text('credentials_encrypted')->nullable();
            $table->json('settings')->nullable();
            $table->json('supported_countries')->nullable();
            $table->boolean('enabled_for_checkout')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('store_id');
            $table->index('carrier_id');
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'enabled_for_checkout']);
        });

        Schema::create('shipping_zones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->json('countries')->nullable();
            $table->json('regions')->nullable();
            $table->json('postal_patterns')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('store_id');
            $table->index(['store_id', 'is_active']);
            $table->index(['store_id', 'sort_order']);
        });

        Schema::create('shipping_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('shipping_zone_id')->constrained('shipping_zones')->cascadeOnDelete();
            $table->foreignId('carrier_account_id')->nullable()->constrained('carrier_accounts')->nullOnDelete();
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->string('delivery_speed_label')->nullable();
            $table->string('rate_type', 40)->default('flat');
            $table->decimal('flat_rate', 14, 2)->default(0);
            $table->decimal('free_over_amount', 14, 2)->nullable();
            $table->decimal('min_order_amount', 14, 2)->nullable();
            $table->decimal('max_order_amount', 14, 2)->nullable();
            $table->unsignedSmallInteger('estimated_min_days')->nullable();
            $table->unsignedSmallInteger('estimated_max_days')->nullable();
            $table->boolean('enabled_for_checkout')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('store_id');
            $table->index('shipping_zone_id');
            $table->index('carrier_account_id');
            $table->index(['store_id', 'code']);
            $table->index(['store_id', 'is_active', 'enabled_for_checkout']);
        });

        Schema::create('store_shipment_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('next_number')->default(1001);
            $table->timestamps();

            $table->unique('store_id');
        });

        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('shipment_number');
            $table->foreignId('origin_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('carrier_account_id')->nullable()->constrained('carrier_accounts')->nullOnDelete();
            $table->foreignId('shipping_method_id')->nullable()->constrained('shipping_methods')->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->string('tracking_number')->nullable();
            $table->string('tracking_url')->nullable();
            $table->string('carrier_service')->nullable();
            $table->unsignedInteger('package_count')->default(1);
            $table->decimal('package_weight', 10, 3)->nullable();
            $table->decimal('shipping_cost', 14, 2)->nullable();
            $table->string('label_url')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('shipped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'shipment_number']);
            $table->index('store_id');
            $table->index('order_id');
            $table->index(['store_id', 'status']);
            $table->index('origin_location_id');
            $table->index('carrier_account_id');
            $table->index('shipping_method_id');
        });

        Schema::create('shipment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->index('store_id');
            $table->index('shipment_id');
            $table->index('order_item_id');
            $table->unique(['shipment_id', 'order_item_id'], 'shipment_items_shipment_order_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('store_shipment_sequences');
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('shipping_zones');
        Schema::dropIfExists('carrier_accounts');
        Schema::dropIfExists('carriers');
    }
};
