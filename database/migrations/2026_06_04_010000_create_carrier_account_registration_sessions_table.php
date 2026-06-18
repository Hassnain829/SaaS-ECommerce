<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrier_account_registration_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('carrier_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 32)->default('fedex');
            $table->string('environment', 16)->default('sandbox');
            $table->string('connection_model', 64)->default('integrator_provider');
            $table->string('status', 64)->default('draft');
            $table->foreignId('origin_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->text('account_number_encrypted')->nullable();
            $table->string('account_last4', 4)->nullable();
            $table->string('account_name')->nullable();
            $table->json('registration_address_json')->nullable();
            $table->boolean('residential')->nullable();
            $table->string('eula_version')->nullable();
            $table->timestamp('eula_accepted_at')->nullable();
            $table->foreignId('eula_accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mfa_method', 32)->nullable();
            $table->string('mfa_destination_masked')->nullable();
            $table->unsignedInteger('mfa_attempt_count')->default(0);
            $table->timestamp('mfa_expires_at')->nullable();
            $table->string('fedex_transaction_id')->nullable();
            $table->text('fedex_customer_key_encrypted')->nullable();
            $table->text('fedex_customer_password_encrypted')->nullable();
            $table->string('last_error_code')->nullable();
            $table->text('last_error_message')->nullable();
            $table->json('request_summary_json')->nullable();
            $table->json('response_summary_json')->nullable();
            $table->json('mfa_options_json')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'status'], 'carrier_reg_sessions_store_status_idx');
            $table->index(['carrier_account_id', 'status'], 'carrier_reg_sessions_account_status_idx');
            $table->index(['provider', 'environment'], 'carrier_reg_sessions_provider_env_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_account_registration_sessions');
    }
};
