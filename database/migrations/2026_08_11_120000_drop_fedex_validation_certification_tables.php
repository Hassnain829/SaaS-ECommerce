<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Defensive cleanup of retired FedEx Integrator certification tables.
 * Does not touch carrier_accounts or production CarrierApiEvent columns.
 * Does not delete filesystem artifacts (purge storage/app/fedex-validation before deploy if present).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->scrubRegionalCredentials();

        // FK-safe order: dependents that reference artifacts first, then artifacts, then peers.
        foreach ([
            'fedex_validation_external_approvals',
            'fedex_validation_submission_snapshots',
            'fedex_validation_artifacts',
            'fedex_validation_regional_accounts',
        ] as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
            }
        }
    }

    public function down(): void
    {
        // Certification tables are intentionally not recreated. Historical create migrations remain in repo.
    }

    private function scrubRegionalCredentials(): void
    {
        if (! Schema::hasTable('fedex_validation_regional_accounts')) {
            return;
        }

        $updates = [];
        foreach ([
            'account_number_encrypted',
            'account_number_hash',
            'child_key_encrypted',
            'child_secret_encrypted',
        ] as $column) {
            if (Schema::hasColumn('fedex_validation_regional_accounts', $column)) {
                $updates[$column] = null;
            }
        }

        if ($updates === []) {
            return;
        }

        DB::table('fedex_validation_regional_accounts')->update($updates);
    }
};
