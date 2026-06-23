<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fedex_validation_artifacts', function (Blueprint $table) {
            $table->foreignId('carrier_api_event_id')->nullable()
                ->after('registration_session_id')
                ->constrained('carrier_api_events')
                ->nullOnDelete();
            $table->string('scenario_key', 64)->nullable()->after('artifact_type');
            $table->string('test_case_key', 64)->nullable()->after('scenario_key');
            $table->string('label_format', 16)->nullable()->after('test_case_key');
            $table->unsignedInteger('package_sequence')->nullable()->after('label_format');
            $table->string('artifact_role', 64)->nullable()->after('package_sequence');
            $table->string('original_filename', 255)->nullable()->after('label');
            $table->string('mime_type', 128)->nullable()->after('original_filename');
            $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
            $table->string('sha256', 64)->nullable()->after('file_size');
            $table->unsignedInteger('scan_dpi')->nullable()->after('sha256');
            $table->json('metadata_json')->nullable()->after('response_summary_json');

            $table->index(['store_id', 'carrier_account_id', 'scenario_key'], 'fedex_artifacts_store_account_scenario_idx');
            $table->index(['store_id', 'carrier_account_id', 'test_case_key', 'label_format'], 'fedex_artifacts_store_account_case_format_idx');
            $table->index(['artifact_role'], 'fedex_artifacts_role_idx');
            $table->index(['sha256'], 'fedex_artifacts_sha256_idx');
        });
    }

    public function down(): void
    {
        Schema::table('fedex_validation_artifacts', function (Blueprint $table) {
            $table->dropIndex('fedex_artifacts_store_account_scenario_idx');
            $table->dropIndex('fedex_artifacts_store_account_case_format_idx');
            $table->dropIndex('fedex_artifacts_role_idx');
            $table->dropIndex('fedex_artifacts_sha256_idx');
            $table->dropConstrainedForeignId('carrier_api_event_id');
            $table->dropColumn([
                'scenario_key',
                'test_case_key',
                'label_format',
                'package_sequence',
                'artifact_role',
                'original_filename',
                'mime_type',
                'file_size',
                'sha256',
                'scan_dpi',
                'metadata_json',
            ]);
        });
    }
};
