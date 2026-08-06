<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SqliteUspsPartialUniqueIndexRepairTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierSeeder::class);
    }

    public function test_repair_migration_restores_partial_usps_index_not_full_store_unique(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite-only repair migration.');
        }

        DB::statement('DROP INDEX IF EXISTS carrier_accounts_store_usps_merchant_active_unique');
        DB::statement('CREATE UNIQUE INDEX carrier_accounts_store_usps_merchant_active_unique ON carrier_accounts (store_id)');

        $broken = (string) DB::table('sqlite_master')
            ->where('type', 'index')
            ->where('name', 'carrier_accounts_store_usps_merchant_active_unique')
            ->value('sql');
        $this->assertStringNotContainsString('WHERE', strtoupper($broken));

        $migration = require database_path(
            'migrations/2026_08_06_030000_repair_sqlite_usps_partial_unique_index_after_carrier_account_rebuilds.php'
        );
        $migration->up();

        $repaired = (string) DB::table('sqlite_master')
            ->where('type', 'index')
            ->where('name', 'carrier_accounts_store_usps_merchant_active_unique')
            ->value('sql');

        $this->assertStringContainsString('WHERE', strtoupper($repaired));
        $this->assertStringContainsString("connection_mode = 'usps_merchant_label_provider'", $repaired);
        $this->assertStringContainsString("usps_authorization_status != 'disabled'", $repaired);
        $this->assertStringContainsString('deleted_at IS NULL', $repaired);
    }

    public function test_multiple_fedex_accounts_for_one_store_are_allowed_after_repair(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite-only repair migration.');
        }

        $migration = require database_path(
            'migrations/2026_08_06_030000_repair_sqlite_usps_partial_unique_index_after_carrier_account_rebuilds.php'
        );
        $migration->up();

        [$store] = $this->ownerStore('SQLite Multi FedEx Store');
        $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();

        CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'display_name' => 'FedEx A',
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'status' => CarrierAccount::STATUS_ENABLED,
        ], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));

        CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'display_name' => 'FedEx B',
            'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
        ], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));

        $this->assertSame(
            2,
            CarrierAccount::query()->where('store_id', $store->id)->where('provider', 'fedex')->count()
        );
    }

    public function test_down_does_not_recreate_full_store_unique(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite-only repair migration.');
        }

        $migration = require database_path(
            'migrations/2026_08_06_030000_repair_sqlite_usps_partial_unique_index_after_carrier_account_rebuilds.php'
        );
        $migration->up();
        $migration->down();

        $sql = (string) DB::table('sqlite_master')
            ->where('type', 'index')
            ->where('name', 'carrier_accounts_store_usps_merchant_active_unique')
            ->value('sql');

        $this->assertStringContainsString('WHERE', strtoupper($sql));
        $this->assertMatchesRegularExpression(
            '/WHERE\s+connection_mode\s*=\s*\'usps_merchant_label_provider\'/i',
            $sql
        );
        $this->assertDoesNotMatchRegularExpression(
            '/CREATE UNIQUE INDEX\s+carrier_accounts_store_usps_merchant_active_unique\s+ON\s+carrier_accounts\s*\(\s*store_id\s*\)\s*$/i',
            trim($sql)
        );
    }

    /**
     * @return array{0: Store, 1: User}
     */
    private function ownerStore(string $name): array
    {
        $role = Role::query()->firstOrCreate(['name' => 'user']);
        $user = User::factory()->create([
            'email' => Str::slug($name).'-owner@example.test',
            'role_id' => $role->id,
        ]);
        $store = Store::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->syncWithoutDetaching([$user->id => ['role' => Store::ROLE_OWNER]]);

        return [$store, $user];
    }
}
