<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carrier_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('carrier_accounts', 'connection_model')) {
                $table->string('connection_model', 64)->nullable()->after('connection_mode');
            }
            if (! Schema::hasColumn('carrier_accounts', 'fedex_integrator_account')) {
                $table->boolean('fedex_integrator_account')->default(false)->after('connection_model');
            }
            if (! Schema::hasColumn('carrier_accounts', 'registration_session_id')) {
                $table->foreignId('registration_session_id')->nullable()->after('fedex_integrator_account');
            }
            if (! Schema::hasColumn('carrier_accounts', 'eula_accepted_at')) {
                $table->timestamp('eula_accepted_at')->nullable()->after('registration_session_id');
            }
            if (! Schema::hasColumn('carrier_accounts', 'eula_version')) {
                $table->string('eula_version')->nullable()->after('eula_accepted_at');
            }
            if (! Schema::hasColumn('carrier_accounts', 'connection_context_json')) {
                $table->json('connection_context_json')->nullable()->after('eula_version');
            }
        });
    }

    public function down(): void
    {
        Schema::table('carrier_accounts', function (Blueprint $table) {
            foreach ([
                'connection_context_json',
                'eula_version',
                'eula_accepted_at',
                'registration_session_id',
                'fedex_integrator_account',
                'connection_model',
            ] as $column) {
                if (Schema::hasColumn('carrier_accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
