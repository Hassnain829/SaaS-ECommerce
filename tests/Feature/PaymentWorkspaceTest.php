<?php

namespace Tests\Feature;

use App\Models\PaymentProviderAccount;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.default_provider' => 'stripe',
            'payments.stripe.mode' => 'test',
            'payments.stripe.key' => 'pk_test_workspace',
            'payments.stripe.secret' => 'sk_test_workspace',
            'payments.stripe.webhook_secret' => 'whsec_platform',
            'payments.stripe.connect_webhook_secret' => 'whsec_connect',
            'payments.stripe.allow_platform_sandbox_fallback' => true,
        ]);
    }

    public function test_workspace_renders_operations_console_and_real_actions(): void
    {
        [$store, $owner] = $this->storeWithUser();

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index'))
            ->assertOk()
            ->assertSee('Payment operations', false)
            ->assertSee('Payment modes', false)
            ->assertSee('How this store accepts payments', false)
            ->assertSee('Platform checkout', false)
            ->assertSee('Stripe test account', false)
            ->assertSee('Stripe live account', false)
            ->assertSee('Connect Stripe test account', false)
            ->assertSee('You will connect through Stripe hosted onboarding', false)
            ->assertSee('No Stripe secret keys are entered here', false)
            ->assertSee('data-pc-root', false)
            ->assertSee('data-pc-select="test"', false)
            ->assertSee('data-pc-select="live"', false)
            ->assertSee(route('settings.payments.stripe.connect.test', absolute: false), false)
            ->assertSee(route('settings.payments.platform-payment-mode', absolute: false), false)
            ->assertDontSee('Complete demo onboarding', false)
            ->assertDontSee('lucide@', false)
            ->assertDontSee('switchTab(', false)
            ->assertDontSee('id="tab-external"', false)
            ->getContent();

        $this->assertStringContainsString('data-turbo="false"', $html);
        $this->assertStringContainsString('Checkout is blocked until Stripe is connected', $html);
    }

    public function test_connected_account_shows_ready_state_and_bound_forms(): void
    {
        [$store, $owner] = $this->storeWithUser();
        $account = $this->connectedAccount($store);

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index'))
            ->assertOk()
            ->assertSeeText('Platform checkout is active')
            ->assertSeeText('Test checkout ready')
            ->assertSeeText('Disconnect')
            ->assertSeeText('Refresh status')
            ->content();

        $this->assertStringContainsString(route('settings.payments.stripe.connect.status', $account, false), $html);
        $this->assertStringContainsString(route('settings.payments.stripe.connect.disconnect', $account, false), $html);
        $this->assertStringContainsString('data-ui-confirm', $html);
    }

    public function test_mode_query_selects_live_panel_without_hiding_test_copy(): void
    {
        [$store, $owner] = $this->storeWithUser();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index', ['mode' => 'live']))
            ->assertOk()
            ->assertSee('data-pc-selected="live"', false)
            ->assertSee('Stripe live account', false)
            ->assertSee('Stripe test account', false);
    }

    public function test_staff_can_view_but_cannot_see_connect_forms(): void
    {
        [$store, , $staff] = $this->storeWithUser(extraRole: Store::ROLE_STAFF);

        $html = $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index'))
            ->assertOk()
            ->assertSeeText('Payment operations')
            ->assertSeeText('You can view payment accounts, but you do not have permission to change them.')
            ->assertDontSeeText('Developer diagnostics')
            ->content();

        $this->assertStringNotContainsString('id="pc-connect-test"', $html);
        $this->assertStringNotContainsString('id="pc-mode-live-form"', $html);
    }

    private function storeWithUser(string $role = Store::ROLE_OWNER, ?string $extraRole = null): array
    {
        $globalRole = Role::query()->firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create([
            'role_id' => $globalRole->id,
            'is_active' => true,
        ]);

        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'Payment Workspace Store '.Str::random(5),
            'slug' => 'payment-ws-'.Str::random(8),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => $role]);

        $extraUser = null;
        if ($extraRole) {
            $extraUser = User::factory()->create([
                'role_id' => $globalRole->id,
                'is_active' => true,
            ]);
            $store->members()->attach($extraUser->id, ['role' => $extraRole]);
        }

        return [$store, $owner, $extraUser];
    }

    private function connectedAccount(Store $store, array $overrides = []): PaymentProviderAccount
    {
        return PaymentProviderAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'provider' => 'stripe',
            'provider_account_id' => 'acct_'.$store->id,
            'mode' => 'test',
            'connection_type' => 'connect',
            'display_name' => 'Connected Stripe account',
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
