<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fedex_validation_regional_accounts')) {
            return;
        }

        Schema::create('fedex_validation_regional_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('root_carrier_account_id');
            $table->foreign('root_carrier_account_id', 'fedex_val_reg_root_ca_fk')
                ->references('id')
                ->on('carrier_accounts')
                ->cascadeOnDelete();
            $table->string('environment', 32);
            $table->string('region', 16);
            $table->string('country_code', 2)->nullable();
            $table->text('account_number_encrypted')->nullable();
            $table->string('account_number_hash', 64)->nullable();
            $table->string('account_last4', 4)->nullable();
            $table->unsignedBigInteger('registration_session_id')->nullable();
            $table->text('child_key_encrypted')->nullable();
            $table->text('child_secret_encrypted')->nullable();
            $table->string('status', 32)->default('not_configured');
            $table->string('credential_source', 64)->nullable();
            $table->string('baseline_version', 32)->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('last_oauth_at')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->unique(
                ['store_id', 'root_carrier_account_id', 'environment', 'account_number_hash'],
                'fedex_validation_regional_accounts_unique',
            );
            $table->index(['store_id', 'root_carrier_account_id', 'region'], 'fedex_validation_regional_accounts_region_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fedex_validation_regional_accounts');
    }
};
