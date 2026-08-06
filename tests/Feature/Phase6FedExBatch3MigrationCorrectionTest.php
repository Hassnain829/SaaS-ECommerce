<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class Phase6FedExBatch3MigrationCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_08_06_010000_harden_fedex_carrier_account_security_idempotency.php';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
    }

    public function test_connected_model_a_account_receives_correct_active_store_key(): void
    {
        $store = $this->store('Active Key Store');
        $this->rollbackBatch3();

        $accountId = $this->insertAccount($store, [
            'display_name' => 'Connected Model A',
            'provider_account_number' => '700257037',
            'connection_status' => 'connected',
            'environment' => 'sandbox',
        ]);

        $this->applyBatch3();

        $account = $this->account($accountId);

        $this->assertSame("store:{$store->id}:fedex:sandbox", $account->fedex_active_store_key);
        $this->assertNull($account->provider_account_number);
        $this->assertSame('700257037', Crypt::decryptString((string) $account->provider_account_number_encrypted));
        $this->assertSame('7037', $account->provider_account_last4);
    }

    public function test_two_connected_accounts_for_one_store_and_environment_stop_the_migration(): void
    {
        $store = $this->store('Conflicting Active Store');
        $this->rollbackBatch3();

        $firstId = $this->insertAccount($store, [
            'display_name' => 'Connected one',
            'provider_account_number' => '700257037',
            'connection_status' => 'connected',
        ]);
        $secondId = $this->insertAccount($store, [
            'display_name' => 'Connected two',
            'provider_account_number' => '740561073',
            'connection_status' => 'connected',
        ]);

        try {
            $this->applyBatch3();
            $this->fail('Expected the migration to stop on duplicate connected FedEx Model A accounts.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('more than one connected FedEx Model A', $exception->getMessage());
            $this->assertStringContainsString("store {$store->id}", $exception->getMessage());
            $this->assertStringContainsString((string) $firstId, $exception->getMessage());
            $this->assertStringContainsString((string) $secondId, $exception->getMessage());
        }

        // Fail-fast: no row may be rewritten before the conflict is resolved.
        $this->assertSame('700257037', $this->account($firstId)->provider_account_number);
        $this->assertSame('740561073', $this->account($secondId)->provider_account_number);
        $this->assertNull($this->account($firstId)->fedex_active_store_key);
        $this->assertNull($this->account($secondId)->fedex_active_store_key);
    }

    public function test_dedupe_preserves_the_account_referenced_by_the_registration_session(): void
    {
        $store = $this->store('Session Reference Store');
        $this->rollbackBatch3();

        $sessionId = $this->insertSession($store);
        $connectedId = $this->insertAccount($store, [
            'display_name' => 'Connected duplicate',
            'connection_status' => 'connected',
            'registration_session_id' => $sessionId,
        ]);
        $referencedId = $this->insertAccount($store, [
            'display_name' => 'Session referenced',
            'connection_status' => 'failed',
            'registration_session_id' => $sessionId,
        ]);
        DB::table('carrier_account_registration_sessions')
            ->where('id', $sessionId)
            ->update(['carrier_account_id' => $referencedId]);

        $this->applyBatch3();

        $this->assertSame($sessionId, (int) $this->account($referencedId)->registration_session_id);
        $this->assertNull($this->account($connectedId)->registration_session_id);
        $this->assertSame(
            $referencedId,
            (int) DB::table('carrier_account_registration_sessions')->where('id', $sessionId)->value('carrier_account_id'),
        );
    }

    public function test_dedupe_updates_the_session_relation_when_another_account_is_selected(): void
    {
        $store = $this->store('Session Repair Store');
        $this->rollbackBatch3();

        $sessionId = $this->insertSession($store);
        $failedId = $this->insertAccount($store, [
            'display_name' => 'Failed duplicate',
            'connection_status' => 'failed',
            'status' => 'disabled',
            'registration_session_id' => $sessionId,
        ]);
        $connectedId = $this->insertAccount($store, [
            'display_name' => 'Connected duplicate',
            'connection_status' => 'connected',
            'registration_session_id' => $sessionId,
        ]);

        $this->assertNull(
            DB::table('carrier_account_registration_sessions')->where('id', $sessionId)->value('carrier_account_id')
        );

        $this->applyBatch3();

        $this->assertSame(
            $connectedId,
            (int) DB::table('carrier_account_registration_sessions')->where('id', $sessionId)->value('carrier_account_id'),
        );
        $this->assertSame($sessionId, (int) $this->account($connectedId)->registration_session_id);
        $this->assertNull($this->account($failedId)->registration_session_id);
    }

    public function test_cross_store_or_provider_duplicate_candidates_stop_the_migration(): void
    {
        $sessionStore = $this->store('Session Owner Store');
        $foreignStore = $this->store('Foreign Tenant Store');
        $this->rollbackBatch3();

        $sessionId = $this->insertSession($sessionStore);
        $foreignStoreAccountId = $this->insertAccount($foreignStore, [
            'display_name' => 'Foreign store duplicate',
            'provider_account_number' => '700257037',
            'connection_status' => 'connected',
            'registration_session_id' => $sessionId,
        ]);
        $foreignProviderAccountId = $this->insertAccount($sessionStore, [
            'display_name' => 'Foreign provider duplicate',
            'provider' => 'usps',
            'carrier_id' => $this->carrierId('usps'),
            'provider_account_number' => '740561073',
            'connection_status' => 'setup_required',
            'registration_session_id' => $sessionId,
        ]);
        DB::table('carrier_account_registration_sessions')
            ->where('id', $sessionId)
            ->update(['carrier_account_id' => null]);

        try {
            $this->applyBatch3();
            $this->fail('Expected the migration to stop on cross-tenant registration session duplicates.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString("registration session {$sessionId}", $exception->getMessage());
            $this->assertStringContainsString("Expected store {$sessionStore->id} and provider fedex", $exception->getMessage());
            $this->assertStringContainsString(
                "id {$foreignStoreAccountId} (store {$foreignStore->id}, provider fedex)",
                $exception->getMessage(),
            );
            $this->assertStringContainsString(
                "id {$foreignProviderAccountId} (store {$sessionStore->id}, provider usps)",
                $exception->getMessage(),
            );
        }

        $this->assertNull(
            DB::table('carrier_account_registration_sessions')->where('id', $sessionId)->value('carrier_account_id')
        );

        foreach ([$foreignStoreAccountId => '700257037', $foreignProviderAccountId => '740561073'] as $id => $number) {
            $account = $this->account($id);

            $this->assertSame($sessionId, (int) $account->registration_session_id);
            $this->assertSame($number, $account->provider_account_number);
            $this->assertNull($account->provider_account_number_encrypted);
            $this->assertNull($account->provider_account_last4);
            $this->assertNull($account->fedex_active_store_key);
        }
    }

    public function test_duplicate_accounts_lose_only_the_duplicate_session_link(): void
    {
        $store = $this->store('Duplicate Link Store');
        $this->rollbackBatch3();

        $sessionId = $this->insertSession($store);
        $keptId = $this->insertAccount($store, [
            'display_name' => 'Kept account',
            'connection_status' => 'connected',
            'provider_account_number' => '700257037',
            'registration_session_id' => $sessionId,
        ]);
        $duplicateId = $this->insertAccount($store, [
            'display_name' => 'Duplicate account',
            'connection_status' => 'setup_required',
            'provider_account_number' => '740561073',
            'registration_session_id' => $sessionId,
        ]);

        $this->applyBatch3();

        $duplicate = $this->account($duplicateId);

        $this->assertNull($duplicate->registration_session_id);
        $this->assertSame('Duplicate account', $duplicate->display_name);
        $this->assertSame((int) $store->id, (int) $duplicate->store_id);
        $this->assertSame('740561073', Crypt::decryptString((string) $duplicate->provider_account_number_encrypted));
        $this->assertSame('1073', $duplicate->provider_account_last4);
        $this->assertNull($duplicate->fedex_active_store_key);
        $this->assertSame($sessionId, (int) $this->account($keptId)->registration_session_id);
    }

    public function test_rollback_restores_plaintext_account_number_before_dropping_encrypted_columns(): void
    {
        $store = $this->store('Rollback Restore Store');
        $this->rollbackBatch3();

        $accountId = $this->insertAccount($store, [
            'display_name' => 'Rollback account',
            'provider_account_number' => '700257037',
            'connection_status' => 'connected',
        ]);

        $this->applyBatch3();
        $this->assertNull($this->account($accountId)->provider_account_number);

        $this->rollbackBatch3();

        $this->assertFalse(Schema::hasColumn('carrier_accounts', 'provider_account_number_encrypted'));
        $this->assertFalse(Schema::hasColumn('carrier_accounts', 'provider_account_last4'));
        $this->assertFalse(Schema::hasColumn('carrier_accounts', 'fedex_active_store_key'));
        $this->assertSame('700257037', $this->account($accountId)->provider_account_number);
    }

    public function test_rollback_aborts_when_encrypted_account_number_cannot_be_decrypted(): void
    {
        $store = $this->store('Rollback Abort Store');
        $this->rollbackBatch3();

        $accountId = $this->insertAccount($store, [
            'display_name' => 'Corrupted account',
            'provider_account_number' => '700257037',
            'connection_status' => 'connected',
        ]);

        $this->applyBatch3();

        DB::table('carrier_accounts')
            ->where('id', $accountId)
            ->update(['provider_account_number_encrypted' => 'not-a-valid-payload']);

        try {
            $this->rollbackBatch3();
            $this->fail('Expected the rollback to abort on an undecryptable account number.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Rollback aborted', $exception->getMessage());
            $this->assertStringContainsString((string) $accountId, $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('carrier_accounts', 'provider_account_number_encrypted'));
    }

    public function test_legacy_non_model_a_account_numbers_remain_untouched(): void
    {
        $store = $this->store('Legacy Untouched Store');
        $this->rollbackBatch3();

        $merchantId = $this->insertAccount($store, [
            'display_name' => 'FedEx merchant credentials',
            'provider_account_number' => '111111111',
            'connection_status' => 'connected',
            'connection_model' => null,
            'connection_mode' => 'fedex_merchant_credentials',
            'fedex_integrator_account' => false,
        ]);
        $uspsId = $this->insertAccount($store, [
            'display_name' => 'USPS account',
            'provider' => 'usps',
            'carrier_id' => $this->carrierId('usps'),
            'provider_account_number' => '222222222',
            'connection_status' => 'connected',
            'connection_model' => null,
            'fedex_integrator_account' => false,
        ]);

        $this->applyBatch3();

        foreach ([$merchantId => '111111111', $uspsId => '222222222'] as $id => $expected) {
            $account = $this->account($id);

            $this->assertSame($expected, $account->provider_account_number);
            $this->assertNull($account->provider_account_number_encrypted);
            $this->assertNull($account->provider_account_last4);
            $this->assertNull($account->fedex_active_store_key);
        }
    }

    private function applyBatch3(): void
    {
        $this->batch3Migration()->up();
    }

    private function rollbackBatch3(): void
    {
        $this->batch3Migration()->down();
    }

    private function batch3Migration(): object
    {
        return require database_path(self::MIGRATION);
    }

    private function account(int $id): object
    {
        return DB::table('carrier_accounts')->where('id', $id)->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insertAccount(Store $store, array $attributes = []): int
    {
        return (int) DB::table('carrier_accounts')->insertGetId(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $this->carrierId('fedex'),
            'provider' => 'fedex',
            'environment' => 'sandbox',
            'display_name' => 'FedEx account',
            'connection_type' => 'api',
            'connection_mode' => 'fedex_integrator_account',
            'connection_model' => 'integrator_provider',
            'fedex_integrator_account' => true,
            'status' => 'enabled',
            'connection_status' => 'setup_required',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    private function insertSession(Store $store): int
    {
        return (int) DB::table('carrier_account_registration_sessions')->insertGetId([
            'store_id' => $store->id,
            'provider' => 'fedex',
            'environment' => 'sandbox',
            'connection_model' => 'integrator_provider',
            'status' => 'credentials_issued',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function carrierId(string $code): int
    {
        return (int) Carrier::query()->where('code', $code)->value('id');
    }

    private function store(string $name): Store
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create([
            'email' => Str::slug($name).'-owner@example.test',
            'role_id' => $role->id,
        ]);

        return Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
    }
}
