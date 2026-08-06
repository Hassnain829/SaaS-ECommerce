<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase6FedExLifecycleMigrationUpDownTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_08_06_020000_add_fedex_connection_lifecycle_columns.php';

    public function test_lifecycle_migration_up_and_down_execute_successfully(): void
    {
        $migration = $this->lifecycleMigration();

        $this->assertTrue(Schema::hasColumn('carrier_accounts', 'disconnected_at'));
        $this->assertTrue(Schema::hasColumn('carrier_accounts', 'replaced_at'));
        $this->assertTrue(Schema::hasColumn('carrier_accounts', 'replaced_by_carrier_account_id'));
        $this->assertTrue(Schema::hasColumn('carrier_account_registration_sessions', 'replacing_carrier_account_id'));

        $migration->down();

        $this->assertFalse(Schema::hasColumn('carrier_accounts', 'disconnected_at'));
        $this->assertFalse(Schema::hasColumn('carrier_accounts', 'replaced_at'));
        $this->assertFalse(Schema::hasColumn('carrier_accounts', 'replaced_by_carrier_account_id'));
        $this->assertFalse(Schema::hasColumn('carrier_account_registration_sessions', 'replacing_carrier_account_id'));

        $migration->up();

        $this->assertTrue(Schema::hasColumn('carrier_accounts', 'disconnected_at'));
        $this->assertTrue(Schema::hasColumn('carrier_accounts', 'replaced_at'));
        $this->assertTrue(Schema::hasColumn('carrier_accounts', 'replaced_by_carrier_account_id'));
        $this->assertTrue(Schema::hasColumn('carrier_account_registration_sessions', 'replacing_carrier_account_id'));

        // Custom FK names remain used for non-SQLite drivers (SQLite drops by columns).
        $source = file_get_contents(database_path(self::MIGRATION));
        $this->assertStringContainsString('dropForeignCompat($table, self::REPLACED_BY_FK', $source);
        $this->assertStringContainsString('$table->dropForeign($fkName)', $source);
        $this->assertStringContainsString('ca_replaced_by_carrier_account_fk', $source);

        // Dedicated USPS SQLite partial unique must remain valid after FedEx lifecycle churn.
        if (DB::getDriverName() === 'sqlite') {
            $indexes = collect(DB::select("PRAGMA index_list('carrier_accounts')"))
                ->pluck('name')
                ->all();
            $this->assertTrue(
                collect($indexes)->contains(
                    fn ($name) => $name === 'carrier_accounts_store_usps_merchant_active_unique'
                ),
                'Expected carrier_accounts_store_usps_merchant_active_unique to remain after lifecycle migration up/down'
            );
        }
    }

    private function lifecycleMigration(): object
    {
        return require database_path(self::MIGRATION);
    }
}
