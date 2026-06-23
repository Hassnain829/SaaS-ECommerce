<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carrier_api_events', function (Blueprint $table) {
            $table->foreignId('registration_session_id')->nullable()
                ->after('carrier_account_id')
                ->constrained('carrier_account_registration_sessions')
                ->nullOnDelete();
            $table->string('scenario_key', 64)->nullable()->after('action');
            $table->string('test_case_key', 64)->nullable()->after('scenario_key');
            $table->string('mfa_method', 32)->nullable()->after('test_case_key');
            $table->string('label_format', 16)->nullable()->after('mfa_method');
            $table->unsignedInteger('package_count')->nullable()->after('label_format');
            $table->string('endpoint', 255)->nullable()->after('package_count');
            $table->string('http_method', 10)->nullable()->after('endpoint');
            $table->unsignedSmallInteger('http_status')->nullable()->after('http_method');
            $table->string('fedex_transaction_id', 128)->nullable()->after('http_status');
            $table->longText('request_headers_encrypted')->nullable()->after('error_message');
            $table->longText('request_body_encrypted')->nullable()->after('request_headers_encrypted');
            $table->longText('response_headers_encrypted')->nullable()->after('request_body_encrypted');
            $table->longText('response_body_encrypted')->nullable()->after('response_headers_encrypted');
            $table->timestamp('evidence_recorded_at')->nullable()->after('response_body_encrypted');

            $table->index(['store_id', 'carrier_account_id', 'action'], 'carrier_api_events_store_account_action_idx');
            $table->index(['store_id', 'carrier_account_id', 'scenario_key'], 'carrier_api_events_store_account_scenario_idx');
            $table->index(['store_id', 'carrier_account_id', 'test_case_key', 'label_format'], 'carrier_api_events_store_account_case_format_idx');
            $table->index(['fedex_transaction_id'], 'carrier_api_events_fedex_txn_idx');
        });
    }

    public function down(): void
    {
        Schema::table('carrier_api_events', function (Blueprint $table) {
            $table->dropIndex('carrier_api_events_store_account_action_idx');
            $table->dropIndex('carrier_api_events_store_account_scenario_idx');
            $table->dropIndex('carrier_api_events_store_account_case_format_idx');
            $table->dropIndex('carrier_api_events_fedex_txn_idx');
            $table->dropConstrainedForeignId('registration_session_id');
            $table->dropColumn([
                'scenario_key',
                'test_case_key',
                'mfa_method',
                'label_format',
                'package_count',
                'endpoint',
                'http_method',
                'http_status',
                'fedex_transaction_id',
                'request_headers_encrypted',
                'request_body_encrypted',
                'response_headers_encrypted',
                'response_body_encrypted',
                'evidence_recorded_at',
            ]);
        });
    }
};
