<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->string('request_hash', 64)->nullable()->after('idempotency_key');
            $table->string('provider_idempotency_key', 120)->nullable()->after('request_hash');
            $table->string('provider_status', 40)->nullable()->after('provider_refund_id');

            $table->unique(['store_id', 'provider_idempotency_key'], 'refunds_store_provider_idempotency_unique');
        });

        Schema::table('refund_items', function (Blueprint $table): void {
            $table->decimal('discount_amount', 14, 4)->default(0)->after('subtotal');
            $table->unsignedBigInteger('total_minor')->default(0)->after('total');
        });

        Schema::table('exchanges', function (Blueprint $table): void {
            $table->string('idempotency_key', 120)->nullable()->after('exchange_number');
            $table->string('request_hash', 64)->nullable()->after('idempotency_key');
            $table->decimal('balance_due', 14, 4)->default(0)->after('price_difference');
            $table->decimal('collected_amount', 14, 4)->default(0)->after('balance_due');
            $table->string('collection_method', 40)->nullable()->after('collected_amount');
            $table->string('collection_reference', 191)->nullable()->after('collection_method');
            $table->timestamp('collected_at')->nullable()->after('collection_reference');
            $table->json('collection_evidence')->nullable()->after('collected_at');

            $table->unique(['store_id', 'idempotency_key'], 'exchanges_store_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('exchanges', function (Blueprint $table): void {
            $table->dropUnique('exchanges_store_idempotency_unique');
            $table->dropColumn([
                'idempotency_key',
                'request_hash',
                'balance_due',
                'collected_amount',
                'collection_method',
                'collection_reference',
                'collected_at',
                'collection_evidence',
            ]);
        });

        Schema::table('refund_items', function (Blueprint $table): void {
            $table->dropColumn(['discount_amount', 'total_minor']);
        });

        Schema::table('refunds', function (Blueprint $table): void {
            $table->dropUnique('refunds_store_provider_idempotency_unique');
            $table->dropColumn(['request_hash', 'provider_idempotency_key', 'provider_status']);
        });
    }
};
