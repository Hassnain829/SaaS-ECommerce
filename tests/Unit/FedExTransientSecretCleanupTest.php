<?php

namespace Tests\Unit;

use App\Models\CarrierAccountRegistrationSession;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FedExTransientSecretCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_clear_transient_fedex_secrets_removes_tokens_and_preserves_evidence(): void
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['email' => 'secret-cleanup@example.test', 'role_id' => $role->id]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'Secret Cleanup Store',
            'slug' => 'secret-cleanup-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);

        $session = CarrierAccountRegistrationSession::query()->create([
            'store_id' => $store->id,
            'provider' => CarrierAccountRegistrationSession::PROVIDER_FEDEX,
            'environment' => 'sandbox',
            'connection_model' => CarrierAccountRegistrationSession::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
            'status' => CarrierAccountRegistrationSession::STATUS_REGISTERED,
            'fedex_transaction_id' => 'keep-txn',
            'request_summary_json' => ['endpoint' => '/registration'],
            'response_summary_json' => ['credential_key_detected' => true],
            'eula_version' => 'v1',
            'carrier_account_id' => null,
            'created_by' => $owner->id,
        ]);
        $session->setAccountAuthToken('secret-auth-token', now()->addHour());
        $session->setChildCredentials('child-key', 'child-secret');
        $session->forceFill([
            'mfa_options_json' => [['raw_key' => 'email']],
            'mfa_destination_masked' => '***@example.test',
            'mfa_expires_at' => now()->addMinutes(10),
        ])->save();

        $session->clearTransientFedExSecrets();
        $session->refresh();

        $this->assertNull($session->accountAuthToken());
        $this->assertNull($session->childCredentials());
        $this->assertNull($session->mfa_options_json);
        $this->assertNull($session->mfa_destination_masked);
        $this->assertNull($session->mfa_expires_at);
        $this->assertSame('keep-txn', $session->fedex_transaction_id);
        $this->assertSame(['endpoint' => '/registration'], $session->request_summary_json);
        $this->assertTrue((bool) data_get($session->response_summary_json, 'credential_key_detected'));
        $this->assertSame('v1', $session->eula_version);
    }
}
