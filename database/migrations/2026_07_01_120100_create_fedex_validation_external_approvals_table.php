<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fedex_validation_external_approvals')) {
            return;
        }

        Schema::create('fedex_validation_external_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('carrier_account_id')->constrained()->cascadeOnDelete();
            $table->string('case_reference')->nullable();
            $table->string('area', 64);
            $table->date('approval_date')->nullable();
            $table->foreignId('source_artifact_id')->nullable()->constrained('fedex_validation_artifacts')->nullOnDelete();
            $table->json('applies_to_check_keys_json')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'carrier_account_id', 'area'], 'fedex_val_ext_approvals_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fedex_validation_external_approvals');
    }
};
