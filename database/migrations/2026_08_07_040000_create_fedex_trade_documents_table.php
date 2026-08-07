<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fedex_trade_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->foreignId('carrier_account_id')->nullable()->constrained('carrier_accounts')->nullOnDelete();
            $table->string('document_type', 60)->default('COMMERCIAL_INVOICE');
            $table->string('fedex_document_id', 120)->nullable();
            $table->string('status', 32)->default('pending');
            $table->char('origin_country_code', 2);
            $table->char('destination_country_code', 2);
            $table->string('storage_disk', 40)->nullable();
            $table->string('storage_path', 500)->nullable();
            $table->string('original_filename', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'order_id'], 'fedex_trade_docs_store_order_index');
            $table->index(['store_id', 'fedex_document_id'], 'fedex_trade_docs_store_doc_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fedex_trade_documents');
    }
};
