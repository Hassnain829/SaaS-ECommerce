<?php

namespace Tests\Feature;

use App\Models\PaymentProviderAccount;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentProviderAccountModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_helpers_identify_stripe_connect_modes(): void
    {
        $store = $this->store();
        $test = $this->account($store, 'test');
        $live = $this->account($store, 'live');

        $this->assertTrue($test->isStripe());
        $this->assertTrue($test->isConnect());
        $this->assertTrue($test->isTestMode());
        $this->assertFalse($test->isLiveMode());
        $this->assertTrue($test->isActive());

        $this->assertTrue($live->isLiveMode());
        $this->assertFalse($live->isTestMode());
    }

    public function test_store_can_have_both_test_and_live_connect_accounts(): void
    {
        $store = $this->store();
        $test = $this->account($store, 'test', ['provider_account_id' => 'acct_test_1']);
        $live = $this->account($store, 'live', ['provider_account_id' => 'acct_live_1']);

        $this->assertSame(2, PaymentProviderAccount::query()->forStore($store)->stripe()->connect()->count());
        $this->assertTrue(
            PaymentProviderAccount::query()->forStore($store)->stripe()->connect()->mode('test')->whereKey($test->id)->exists()
        );
        $this->assertTrue(
            PaymentProviderAccount::query()->forStore($store)->stripe()->connect()->mode('live')->whereKey($live->id)->exists()
        );
    }

    public function test_scopes_do_not_leak_accounts_across_stores(): void
    {
        $storeA = $this->store('Store A');
        $storeB = $this->store('Store B');
        $accountA = $this->account($storeA, 'test', ['provider_account_id' => 'acct_a']);
        $this->account($storeB, 'test', ['provider_account_id' => 'acct_b']);

        $this->assertSame(
            $accountA->id,
            PaymentProviderAccount::query()->forStore($storeA)->stripe()->connect()->mode('test')->first()?->id
        );
        $this->assertNull(
            PaymentProviderAccount::query()->forStore($storeA)->stripe()->connect()->mode('test')->where('provider_account_id', 'acct_b')->first()
        );
    }

    public function test_masked_provider_account_id_shortens_display(): void
    {
        $store = $this->store();
        $account = $this->account($store, 'test', ['provider_account_id' => 'acct_1234567890abcdef']);

        $masked = $account->maskedProviderAccountId();

        $this->assertStringStartsWith('acct_', $masked);
        $this->assertStringContainsString('••••', $masked);
        $this->assertNotSame($account->provider_account_id, $masked);
    }

    public function test_account_does_not_store_merchant_secret_keys(): void
    {
        $store = $this->store();
        $account = $this->account($store, 'test');

        $serialized = json_encode($account->toArray());

        $this->assertStringNotContainsString('sk_test', (string) $serialized);
        $this->assertStringNotContainsString('sk_live', (string) $serialized);
        $this->assertStringNotContainsString('secret_key', (string) $serialized);
    }

    private function store(string $name = 'Mode Test Store'): Store
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

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

    private function account(Store $store, string $mode, array $overrides = []): PaymentProviderAccount
    {
        return PaymentProviderAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'provider' => 'stripe',
            'provider_account_id' => 'acct_'.$mode.'_'.$store->id,
            'mode' => $mode,
            'connection_type' => PaymentProviderAccount::CONNECTION_CONNECT,
            'display_name' => ucfirst($mode).' Stripe account',
            'status' => 'active',
            'is_default' => true,
            'settings' => ['account_type' => 'express'],
            'capabilities' => ['card_payments' => 'active'],
            'metadata' => [],
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'requirements_currently_due' => [],
            'onboarding_completed_at' => now(),
            'last_verified_at' => now(),
        ], $overrides));
    }
}
