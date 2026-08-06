<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repair the USPS merchant active partial unique index after SQLite table rebuilds.
 *
 * Laravel's SQLite Schema::table() path rebuilds tables and can recreate
 * carrier_accounts_store_usps_merchant_active_unique as a full UNIQUE(store_id),
 * dropping the partial WHERE clause. That incorrectly blocks multiple FedEx
 * (and other non-USPS) carrier_accounts rows for the same store.
 *
 * This migration restores the intended USPS-only partial unique index. It does
 * not mutate FedEx records. Treat the partial index as a schema invariant:
 * down() is a documented no-op and must never recreate the broken full unique.
 */
return new class extends Migration
{
    private const INDEX_NAME = 'carrier_accounts_store_usps_merchant_active_unique';

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        if (! $this->schemaReadyForUspsPartialUnique()) {
            return;
        }

        $this->ensureUspsPartialUniqueIndex();
    }

    /**
     * Documented no-op: the intended USPS partial unique index is a schema invariant.
     * Never recreate a full UNIQUE(store_id) on rollback.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        if (! $this->schemaReadyForUspsPartialUnique()) {
            return;
        }

        // Re-assert the correct partial index if a later operation damaged it.
        $this->ensureUspsPartialUniqueIndex();
    }

    private function schemaReadyForUspsPartialUnique(): bool
    {
        return Schema::hasTable('carrier_accounts')
            && Schema::hasColumn('carrier_accounts', 'store_id')
            && Schema::hasColumn('carrier_accounts', 'connection_mode')
            && Schema::hasColumn('carrier_accounts', 'usps_authorization_status')
            && Schema::hasColumn('carrier_accounts', 'deleted_at');
    }

    private function ensureUspsPartialUniqueIndex(): void
    {
        $sql = (string) DB::table('sqlite_master')
            ->where('type', 'index')
            ->where('name', self::INDEX_NAME)
            ->value('sql');

        $isCorrectPartial = $sql !== ''
            && str_contains(strtoupper($sql), 'WHERE')
            && str_contains($sql, "connection_mode = 'usps_merchant_label_provider'")
            && str_contains($sql, "usps_authorization_status != 'disabled'")
            && str_contains($sql, 'deleted_at IS NULL');

        if ($isCorrectPartial) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS '.self::INDEX_NAME);

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS '.self::INDEX_NAME.' '
            .'ON carrier_accounts (store_id) '
            ."WHERE connection_mode = 'usps_merchant_label_provider' "
            ."AND usps_authorization_status != 'disabled' "
            .'AND deleted_at IS NULL'
        );
    }
};
