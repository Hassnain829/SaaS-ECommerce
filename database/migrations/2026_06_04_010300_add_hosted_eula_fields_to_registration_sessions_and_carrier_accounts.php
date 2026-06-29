<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carrier_account_registration_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('carrier_account_registration_sessions', 'purpose')) {
                $table->string('purpose')->default('connection')->after('connection_model');
            }
            if (! Schema::hasColumn('carrier_account_registration_sessions', 'eula_document_hash')) {
                $table->string('eula_document_hash', 64)->nullable()->after('eula_version');
            }
            if (! Schema::hasColumn('carrier_account_registration_sessions', 'eula_scrolled_at')) {
                $table->timestamp('eula_scrolled_at')->nullable()->after('eula_accepted_by');
            }
            if (! Schema::hasColumn('carrier_account_registration_sessions', 'eula_read_acknowledged_at')) {
                $table->timestamp('eula_read_acknowledged_at')->nullable()->after('eula_scrolled_at');
            }
            if (! Schema::hasColumn('carrier_account_registration_sessions', 'eula_rendered_page_count')) {
                $table->unsignedSmallInteger('eula_rendered_page_count')->nullable()->after('eula_read_acknowledged_at');
            }
        });

        Schema::table('carrier_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('carrier_accounts', 'eula_document_hash')) {
                $table->string('eula_document_hash', 64)->nullable()->after('eula_version');
            }
        });
    }

    public function down(): void
    {
        Schema::table('carrier_account_registration_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'purpose',
                'eula_document_hash',
                'eula_scrolled_at',
                'eula_read_acknowledged_at',
                'eula_rendered_page_count',
            ]);
        });

        Schema::table('carrier_accounts', function (Blueprint $table) {
            $table->dropColumn('eula_document_hash');
        });
    }
};
