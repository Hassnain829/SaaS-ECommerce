<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carrier_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('carrier_accounts', 'usps_authorization_status')) {
                $table->string('usps_authorization_status', 40)->nullable()->after('connection_context_json');
            }

            if (! Schema::hasColumn('carrier_accounts', 'usps_enrollment_status')) {
                $table->string('usps_enrollment_status', 40)->nullable()->after('usps_authorization_status');
            }

            if (! Schema::hasColumn('carrier_accounts', 'usps_payment_verified_at')) {
                $table->timestamp('usps_payment_verified_at')->nullable()->after('usps_enrollment_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('carrier_accounts', function (Blueprint $table): void {
            if (Schema::hasColumn('carrier_accounts', 'usps_payment_verified_at')) {
                $table->dropColumn('usps_payment_verified_at');
            }

            if (Schema::hasColumn('carrier_accounts', 'usps_enrollment_status')) {
                $table->dropColumn('usps_enrollment_status');
            }

            if (Schema::hasColumn('carrier_accounts', 'usps_authorization_status')) {
                $table->dropColumn('usps_authorization_status');
            }
        });
    }
};
