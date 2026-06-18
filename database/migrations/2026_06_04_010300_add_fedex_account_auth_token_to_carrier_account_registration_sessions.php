<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carrier_account_registration_sessions', function (Blueprint $table) {
            $table->text('fedex_account_auth_token_encrypted')->nullable()->after('fedex_transaction_id');
            $table->timestamp('account_auth_token_expires_at')->nullable()->after('fedex_account_auth_token_encrypted');
        });
    }

    public function down(): void
    {
        Schema::table('carrier_account_registration_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'fedex_account_auth_token_encrypted',
                'account_auth_token_expires_at',
            ]);
        });
    }
};
