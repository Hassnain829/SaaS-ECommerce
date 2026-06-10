<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('origin_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('name', 120)->nullable();
            $table->decimal('weight_value', 10, 3);
            $table->string('weight_unit', 8)->default('lb');
            $table->decimal('length', 10, 3)->nullable();
            $table->decimal('width', 10, 3)->nullable();
            $table->decimal('height', 10, 3)->nullable();
            $table->string('dimension_unit', 8)->default('in');
            $table->string('package_type', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'order_id']);
            $table->index(['store_id', 'shipment_id']);
        });

        Schema::create('carrier_rate_quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('carrier_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('shipment_packages')->nullOnDelete();
            $table->string('provider', 32);
            $table->string('environment', 16)->default('testing');
            $table->string('origin_postal_code', 16)->nullable();
            $table->string('destination_postal_code', 16)->nullable();
            $table->string('service_code', 64)->nullable();
            $table->string('service_name', 160)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->unsignedSmallInteger('estimated_days')->nullable();
            $table->string('status', 32);
            $table->json('request_summary')->nullable();
            $table->json('response_summary')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'provider', 'created_at']);
            $table->index(['carrier_account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_rate_quotes');
        Schema::dropIfExists('shipment_packages');
    }
};
