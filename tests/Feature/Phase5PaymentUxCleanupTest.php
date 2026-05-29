<?php

namespace Tests\Feature;

use App\Models\PaymentProviderAccount;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Support\CheckoutMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase5PaymentUxCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.default_provider' => 'stripe',
            'payments.stripe.mode' => 'test',
            'payments.stripe.key' => 'pk_test_payment_ux',
            'payments.stripe.secret' => 'sk_test_payment_ux',
            'payments.stripe.webhook_secret' => 'whsec_platform',
            'payments.stripe.connect_webhook_secret' => 'whsec_connect',
            'payments.stripe.allow_platform_sandbox_fallback' => true,
        ]);
    }

    public function test_payments_page_renders_two_user_friendly_checkout_modes(): void
    {
        [$store, $owner] = $this->storeWithUser();

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index'));

        $response->assertOk()
            ->assertSeeText('Payments & Channels')
            ->assertSeeText('How does this store accept payments?')
            ->assertSeeText('External checkout')
            ->assertSeeText('Platform checkout')
            ->assertSeeText('Current mode')
            ->assertSeeText('External checkout')
            ->assertSeeText('Connect Stripe')
            ->assertDontSeeText('Platform checkout: Sandbox');
    }

    public function test_technical_stripe_details_are_not_in_main_mode_cards_but_exist_in_diagnostics(): void
    {
        [$store, $owner] = $this->storeWithUser();

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index'))
            ->assertOk()
            ->content();

        $mainCards = Str::between($html, '<section id="checkout-mode-cards"', '<section id="stripe-provider-card"');

        $this->assertStringNotContainsString('Platform webhook', $mainCards);
        $this->assertStringNotContainsString('Connect webhook', $mainCards);
        $this->assertStringNotContainsString('Platform publishable key', $mainCards);
        $this->assertStringNotContainsString('Platform secret key', $mainCards);

        $this->assertStringContainsString('Developer diagnostics', $html);
        $this->assertStringContainsString('STRIPE_TEST_KEY configured', $html);
        $this->assertStringContainsString('STRIPE_LIVE_SECRET configured', $html);
        $this->assertStringContainsString('Platform sandbox fallback', $html);
        $this->assertStringContainsString('Enabled for local/testing', $html);
        $this->assertStringNotContainsString('STRIPE_TEST_SECRET', $mainCards);
        $this->assertStringNotContainsString('Add the live Stripe keys', $mainCards);
    }

    public function test_production_store_owner_does_not_see_developer_diagnostics(): void
    {
        app()->detectEnvironment(fn () => 'production');

        [$store, $owner] = $this->storeWithUser();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index'))
            ->assertOk()
            ->assertDontSee('Developer diagnostics', false)
            ->assertDontSee('STRIPE_TEST_KEY', false)
            ->assertDontSee('STRIPE_LIVE_SECRET', false);
    }

    public function test_payments_page_never_mentions_env_or_key_setup_in_store_owner_ui(): void
    {
        [$store, $owner] = $this->storeWithUser();

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index'))
            ->assertOk()
            ->content();

        $storeOwnerUi = Str::before($html, 'id="developer-diagnostics"');

        $this->assertStringNotContainsString('.env', $storeOwnerUi);
        $this->assertStringNotContainsString('STRIPE_TEST_SECRET', $storeOwnerUi);
        $this->assertStringNotContainsString('STRIPE_LIVE_SECRET', $storeOwnerUi);
        $this->assertStringNotContainsString('Add the live Stripe keys', $storeOwnerUi);
        $this->assertStringNotContainsString('Add the test Stripe keys', $storeOwnerUi);
        $this->assertStringNotContainsString('type="password"', $storeOwnerUi);
        $this->assertStringContainsString('You will connect through Stripe hosted onboarding', $storeOwnerUi);
        $this->assertStringContainsString('No Stripe secret keys are entered here', $storeOwnerUi);
    }

    public function test_store_owner_can_enable_external_checkout_mode(): void
    {
        [$store, $owner] = $this->storeWithUser(settings: ['checkout_mode' => CheckoutMode::PLATFORM]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.payments.mode'), ['checkout_mode' => CheckoutMode::EXTERNAL])
            ->assertRedirect(route('settings.payments.index'));

        $this->assertSame(CheckoutMode::EXTERNAL, CheckoutMode::forStore($store->fresh()));
        $this->assertDatabaseHas('security_logs', [
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'event_type' => 'payment.checkout_mode_changed',
        ]);
    }

    public function test_store_owner_cannot_enable_platform_checkout_without_active_provider(): void
    {
        config(['payments.stripe.allow_platform_sandbox_fallback' => false]);

        [$store, $owner] = $this->storeWithUser();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.payments.mode'), ['checkout_mode' => CheckoutMode::PLATFORM])
            ->assertRedirect(route('settings.payments.index'))
            ->assertSessionHasErrors(['checkout_mode']);

        $this->assertSame(CheckoutMode::EXTERNAL, CheckoutMode::forStore($store->fresh()));
    }

    public function test_store_owner_can_enable_platform_checkout_after_active_stripe_account_exists(): void
    {
        [$store, $owner] = $this->storeWithUser();
        $this->connectedAccount($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.payments.mode'), ['checkout_mode' => CheckoutMode::PLATFORM])
            ->assertRedirect(route('settings.payments.index'));

        $store->refresh();
        $this->assertSame(CheckoutMode::PLATFORM, CheckoutMode::forStore($store));
        $this->assertSame(CheckoutMode::PLATFORM, $store->settings['checkout_mode']);
        $this->assertDatabaseHas('security_logs', [
            'store_id' => $store->id,
            'event_type' => 'payment.checkout_mode_changed',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index'))
            ->assertOk()
            ->assertSeeText('Current mode')
            ->assertSeeText('Platform checkout')
            ->assertSeeText('Current mode');
    }

    public function test_stripe_card_copy_is_connection_based_not_secret_key_setup(): void
    {
        [$store, $owner] = $this->storeWithUser();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index'))
            ->assertOk()
            ->assertSeeText('Stripe')
            ->assertSeeText('Connect separate Stripe test and live accounts for platform checkout through secure Stripe hosted onboarding.')
            ->assertSeeText('You will connect through Stripe hosted onboarding')
            ->assertSeeText('No Stripe secret keys are entered here')
            ->assertDontSeeText('Paste your Stripe secret key')
            ->assertDontSeeText('edit .env')
            ->assertDontSeeText('Add the live Stripe keys')
            ->assertDontSeeText('Add the test Stripe keys');
    }

    public function test_staff_cannot_change_checkout_mode(): void
    {
        [$store, , $staff] = $this->storeWithUser(extraRole: Store::ROLE_STAFF);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.payments.mode'), ['checkout_mode' => CheckoutMode::EXTERNAL])
            ->assertForbidden();
    }

    public function test_staff_does_not_see_developer_diagnostics(): void
    {
        [$store, , $staff] = $this->storeWithUser(extraRole: Store::ROLE_STAFF);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index'))
            ->assertOk()
            ->assertDontSeeText('Developer diagnostics')
            ->assertDontSeeText('Platform webhook')
            ->assertDontSeeText('Platform secret key');
    }

    public function test_dev_storefront_hides_legacy_direct_order_mode(): void
    {
        $source = file_get_contents(base_path('dev-test-storefront/src/App.jsx'));

        $this->assertStringContainsString('Developer payload simulator', $source);
        $this->assertStringContainsString('This screen is only for local testing.', $source);
        $this->assertStringContainsString('Sync external checkout order', $source);
        $this->assertStringContainsString('Platform checkout', $source);
        $this->assertStringNotContainsString('Legacy direct dev order', $source);
    }

    private function storeWithUser(string $role = Store::ROLE_OWNER, ?string $extraRole = null, array $settings = []): array
    {
        $globalRole = Role::query()->firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create([
            'role_id' => $globalRole->id,
            'is_active' => true,
        ]);

        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'Payment UX Store '.Str::random(5),
            'slug' => 'payment-ux-'.Str::random(8),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => $settings,
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
