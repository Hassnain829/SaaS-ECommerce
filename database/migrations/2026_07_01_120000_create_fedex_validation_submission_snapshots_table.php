<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fedex_validation_submission_snapshots')) {
            return;
        }

        Schema::create('fedex_validation_submission_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('carrier_account_id')->constrained()->cascadeOnDelete();
            $table->string('case_reference')->nullable();
            $table->string('status', 32)->default('draft');
            $table->string('preflight_hash', 64)->nullable();
            $table->json('snapshot_manifest_json')->nullable();
            $table->json('evidence_ids_json')->nullable();
            $table->json('artifact_ids_json')->nullable();
            $table->json('waiver_ids_json')->nullable();
            $table->json('baseline_versions_json')->nullable();
            $table->string('capability_registry_version', 64)->nullable();
            $table->string('logo_sha256', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->string('invalidation_reason')->nullable();
            $table->string('export_zip_path')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'carrier_account_id', 'status'], 'fedex_val_snapshots_scope_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fedex_validation_submission_snapshots');
    }
};
