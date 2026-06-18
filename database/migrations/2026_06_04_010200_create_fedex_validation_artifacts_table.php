<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fedex_validation_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('carrier_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('registration_session_id')->nullable()
                ->constrained('carrier_account_registration_sessions')->nullOnDelete();
            $table->string('environment', 16);
            $table->string('artifact_type', 64);
            $table->string('label');
            $table->string('file_path')->nullable();
            $table->json('request_summary_json')->nullable();
            $table->json('response_summary_json')->nullable();
            $table->string('fedex_transaction_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'artifact_type']);
            $table->index(['carrier_account_id', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fedex_validation_artifacts');
    }
};
