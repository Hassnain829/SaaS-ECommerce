<?php

namespace Tests\Feature;

use App\Data\Payments\PaymentIntentResult;
use App\Data\Payments\PaymentWebhookResult;
use App\Models\Checkout;
use App\Models\PaymentIntent;
use App\Models\PaymentProviderAccount;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Payments\StripeConnectService;
use App\Services\Payments\StripePlatformPaymentProvider;
use App\Support\CheckoutMode;
use App\Support\PlatformPaymentMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StripeSandboxConnectSupportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureStripeModes();
        $this->mockStripeServices();
    }

    public function test_payments_page_shows_separate_test_and_live_stripe_cards(): void
    {
        [$store, $owner] = $this->ownedStore();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index'))
            ->assertOk()
            ->assertSeeText('Stripe test account')
            ->assertSeeText('Stripe live account')
            ->assertSeeText('Test mode is for safe sandbox payments. Live mode charges real customers.')
            ->assertSeeText('Connect separate Stripe test and live accounts for platform checkout through secure Stripe hosted onboarding.');
    }

    public function test_payments_page_never_asks_store_owner_for_stripe_keys(): void
    {
        config([
            'payments.stripe.modes.live.key' => null,
            'payments.stripe.modes.live.secret' => null,
            'payments.stripe.modes.test.key' => null,
            'payments.stripe.modes.test.secret' => null,
        ]);

        [$store, $owner] = $this->ownedStore();

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index'))
            ->assertOk();

        $mainUi = Str::before($response->content(), 'id="developer-diagnostics"');

        $this->assertStringNotContainsString('.env', $mainUi);
        $this->assertStringNotContainsString('STRIPE_LIVE_SECRET', $mainUi);
        $this->assertStringNotContainsString('STRIPE_TEST_SECRET', $mainUi);
        $this->assertStringNotContainsString('Add the live Stripe keys', $mainUi);
        $this->assertStringNotContainsString('Add the test Stripe keys', $mainUi);
        $this->assertStringContainsString('You will connect through Stripe hosted onboarding', $mainUi);
        $this->assertStringContainsString('No Stripe secret keys are entered here', $mainUi);
        $this->assertStringNotContainsString('publishable key', $mainUi);
        $this->assertStringContainsString('Contact the platform admin', $mainUi);
    }

    public function test_missing_test_config_shows_merchant_friendly_unavailable_message(): void
    {
        config([
            'payments.stripe.modes.test.key' => null,
            'payments.stripe.modes.test.secret' => null,
            'payments.stripe.key' => null,
            'payments.stripe.secret' => null,
        ]);

        [$store, $owner] = $this->ownedStore();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index'))
            ->assertOk()
            ->assertSeeText('Stripe test connection is not available on this platform environment yet')
            ->assertDontSeeText('Add the test Stripe keys');
    }

    public function test_stripe_live_connect_starts_hosted_onboarding(): void
    {
        [$store, $owner] = $this->ownedStore();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.payments.stripe.connect.live'))
            ->assertRedirect();

        $this->assertDatabaseHas('payment_provider_accounts', [
            'store_id' => $store->id,
            'mode' => 'live',
            'connection_type' => 'connect',
        ]);
    }

    public function test_missing_live_config_disables_live_connect_with_message(): void
    {
        config([
            'payments.stripe.modes.live.key' => null,
            'payments.stripe.modes.live.secret' => null,
            'payments.stripe.live_mirrors_test_keys' => false,
            'payments.stripe.allow_local_live_key_mirror' => false,
        ]);

        app()->detectEnvironment(fn () => 'production');

        [$store, $owner] = $this->ownedStore();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index'))
            ->assertOk()
            ->assertSeeText('Live Stripe connection is not available on this platform environment yet')
            ->assertDontSeeText('Add the live Stripe keys');

        app()->detectEnvironment(fn () => 'testing');
    }

    public function test_local_live_connect_enabled_when_only_test_platform_keys_exist(): void
    {
        app()->detectEnvironment(fn () => 'local');

        config([
            'payments.stripe.live_has_dedicated_env_keys' => false,
            'payments.stripe.modes.live.key' => null,
            'payments.stripe.modes.live.secret' => null,
            'payments.stripe.modes.test.key' => 'pk_test_mirror',
            'payments.stripe.modes.test.secret' => 'sk_test_mirror',
            'payments.stripe.live_mirrors_test_keys' => true,
            'payments.stripe.allow_local_live_key_mirror' => true,
        ]);

        [$store, $owner] = $this->ownedStore();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index'))
            ->assertOk()
            ->assertSeeText('Connect Stripe live account')
            ->assertDontSeeText('Live Stripe connection is not available on this platform environment yet');
    }

    public function test_real_live_keys_override_local_mirror(): void
    {
        config([
            'payments.stripe.live_has_dedicated_env_keys' => true,
            'payments.stripe.live_mirrors_test_keys' => true,
            'payments.stripe.allow_local_live_key_mirror' => true,
            'payments.stripe.modes.live.key' => 'pk_live_real',
            'payments.stripe.modes.live.secret' => 'sk_live_real',
        ]);

        $stripeConfig = app(\App\Services\Payments\StripeConfig::class);

        $this->assertTrue($stripeConfig->hasDedicatedLiveKeys());
        $this->assertFalse($stripeConfig->liveKeysMirroredFromTest());
        $this->assertSame(\App\Services\Payments\StripeConfig::LIVE_CONFIG_REAL, $stripeConfig->liveConfigSource());
    }

    public function test_placeholder_live_keys_are_not_treated_as_real_config(): void
    {
        config([
            'payments.stripe.live_has_dedicated_env_keys' => false,
            'payments.stripe.live_mirrors_test_keys' => false,
            'payments.stripe.modes.live.key' => 'pk_live_REPLACE_ME',
            'payments.stripe.modes.live.secret' => 'sk_live_REPLACE_ME',
        ]);

        $this->assertFalse(app(\App\Services\Payments\StripeConfig::class)->hasDedicatedLiveKeys());
    }

    public function test_real_live_config_shows_connect_without_local_simulation_copy(): void
    {
        config([
            'payments.stripe.live_has_dedicated_env_keys' => true,
            'payments.stripe.live_mirrors_test_keys' => false,
            'payments.stripe.modes.live.key' => 'pk_live_ui',
            'payments.stripe.modes.live.secret' => 'sk_live_ui',
        ]);

        [$store, $owner] = $this->ownedStore();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index'))
            ->assertOk()
            ->assertSeeText('Connect Stripe live account')
            ->assertDontSeeText('Local simulation: live Stripe setup is using test platform keys.');
    }

    public function test_local_mirror_shows_simulation_copy_on_live_card(): void
    {
        app()->detectEnvironment(fn () => 'local');

        config([
            'payments.stripe.live_has_dedicated_env_keys' => false,
            'payments.stripe.live_mirrors_test_keys' => true,
            'payments.stripe.modes.live.key' => 'pk_test_mirror',
            'payments.stripe.modes.live.secret' => 'sk_test_mirror',
            'payments.stripe.modes.test.key' => 'pk_test_mirror',
            'payments.stripe.modes.test.secret' => 'sk_test_mirror',
        ]);

        [$store, $owner] = $this->ownedStore();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index'))
            ->assertOk()
            ->assertSeeText('Local simulation: live Stripe setup is using test platform keys.');
    }

    public function test_store_can_hold_both_test_and_live_connected_accounts(): void
    {
        [$store] = $this->ownedStore();
        $this->connectAccount($store, 'test', 'acct_test_dual');
        $this->connectAccount($store, 'live', 'acct_live_dual');

        $manager = app(PaymentProviderManager::class);

        $this->assertSame('acct_test_dual', $manager->activeConnectedAccountForStore($store, 'test')?->provider_account_id);
        $this->assertSame('acct_live_dual', $manager->activeConnectedAccountForStore($store, 'live')?->provider_account_id);
    }

    public function test_test_checkout_uses_test_account_and_stores_mode(): void
    {
        [$store, , $token] = $this->ownedStore(withToken: true);
        PlatformPaymentMode::setForStore($store, PlatformPaymentMode::TEST);
        $testAccount = $this->connectAccount($store, 'test', 'acct_test_checkout');
        [, $variant] = $this->product($store);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant))
            ->assertCreated()
            ->assertJsonPath('payment.payment_mode', 'test')
            ->assertJsonPath('payment.publishable_key', 'pk_test_sandbox');

        $intent = PaymentIntent::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame('test', $intent->mode);
        $this->assertSame($testAccount->id, $intent->payment_provider_account_id);
    }

    public function test_live_checkout_uses_live_account_when_platform_payment_mode_is_live(): void
    {
        [$store, , $token] = $this->ownedStore(withToken: true);
        PlatformPaymentMode::setForStore($store, PlatformPaymentMode::LIVE);
        $this->connectAccount($store, 'test', 'acct_test_only');
        $liveAccount = $this->connectAccount($store, 'live', 'acct_live_checkout');
        [, $variant] = $this->product($store);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant))
            ->assertCreated()
            ->assertJsonPath('payment.payment_mode', 'live')
            ->assertJsonPath('payment.publishable_key', 'pk_live_sandbox');

        $intent = PaymentIntent::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame('live', $intent->mode);
        $this->assertSame($liveAccount->id, $intent->payment_provider_account_id);
    }

    public function test_live_checkout_cannot_use_test_connected_account(): void
    {
        config(['payments.stripe.allow_platform_sandbox_fallback' => false]);

        [$store, , $token] = $this->ownedStore(withToken: true);
        PlatformPaymentMode::setForStore($store, PlatformPaymentMode::LIVE);
        $this->connectAccount($store, 'test', 'acct_test_wrong_mode');
        [, $variant] = $this->product($store);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment']);
    }

    public function test_store_b_cannot_refresh_store_a_connect_account(): void
    {
        [$storeA, $ownerA] = $this->ownedStore();
        [$storeB, $ownerB] = $this->ownedStore('Other Store');
        $accountA = $this->connectAccount($storeA, 'test', 'acct_cross_store');

        $this->actingAs($ownerB)
            ->withSession(['current_store_id' => $storeB->id])
            ->post(route('settings.payments.stripe.connect.refresh', $accountA))
            ->assertNotFound();
    }

    public function test_connect_test_start_uses_test_mode_account_record(): void
    {
        [$store, $owner] = $this->ownedStore();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.payments.stripe.connect.test'))
            ->assertRedirect();

        $this->assertDatabaseHas('payment_provider_accounts', [
            'store_id' => $store->id,
            'provider' => 'stripe',
            'connection_type' => 'connect',
            'mode' => 'test',
        ]);
    }

    public function test_live_webhook_does_not_update_test_payment_intent(): void
    {
        [$store, , $token] = $this->ownedStore(withToken: true);
        $this->connectAccount($store, 'test', 'acct_webhook_test');
        [, $variant] = $this->product($store);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant))
            ->assertCreated();

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $intentId = 'pi_connect_checkout_'.$checkout->id;
        $body = $this->paymentIntentEvent('payment_intent.succeeded', $intentId, 'succeeded', 'acct_webhook_test');

        $this->postConnectWebhook($body, 'live')->assertOk();

        $checkout->refresh();
        $this->assertSame(Checkout::STATUS_PAYMENT_PENDING, $checkout->status);
    }

    private function configureStripeModes(): void
    {
        config([
            'payments.default_provider' => 'stripe',
            'payments.default_mode' => 'test',
            'payments.stripe.mode' => 'test',
            'payments.stripe.key' => 'pk_test_sandbox',
            'payments.stripe.secret' => 'sk_test_sandbox',
            'payments.stripe.webhook_secret' => 'whsec_test_platform',
            'payments.stripe.connect_webhook_secret' => 'whsec_test_connect',
            'payments.stripe.allow_platform_sandbox_fallback' => true,
            'payments.stripe.live_has_dedicated_env_keys' => true,
            'payments.stripe.live_mirrors_test_keys' => false,
            'payments.stripe.modes' => [
                'test' => [
                    'key' => 'pk_test_sandbox',
                    'secret' => 'sk_test_sandbox',
                    'webhook_secret' => 'whsec_test_platform',
                    'connect_webhook_secret' => 'whsec_test_connect',
                    'connect_client_id' => 'ca_test_connect',
                ],
                'live' => [
                    'key' => 'pk_live_sandbox',
                    'secret' => 'sk_live_sandbox',
                    'webhook_secret' => 'whsec_live_platform',
                    'connect_webhook_secret' => 'whsec_live_connect',
                    'connect_client_id' => 'ca_live_connect',
                ],
            ],
        ]);
    }

    private function mockStripeServices(): void
    {
        $this->app->instance(StripeConnectService::class, new class(app(\App\Services\Payments\StripeConfig::class)) extends StripeConnectService
        {
            public function createOrRetrieveConnectedAccount(Store $store, User $user, string $mode = 'test'): PaymentProviderAccount
            {
                return PaymentProviderAccount::query()->firstOrCreate(
                    [
                        'store_id' => $store->id,
                        'provider' => 'stripe',
                        'mode' => $mode,
                        'connection_type' => 'connect',
                    ],
                    [
                        'provider_account_id' => 'acct_'.$mode.'_'.$store->id,
                        'display_name' => ucfirst($mode).' Stripe account',
                        'status' => 'pending',
                        'is_default' => true,
                        'settings' => ['account_type' => 'express'],
                        'metadata' => ['fake' => true],
                        'created_by' => $user->id,
                        'charges_enabled' => false,
                        'payouts_enabled' => false,
                    ]
                );
            }

            public function createAccountOnboardingLink(PaymentProviderAccount $account, ?string $mode = null): string
            {
                return 'https://connect.stripe.test/onboarding/'.$account->provider_account_id;
            }
        });

        $this->app->instance(StripePlatformPaymentProvider::class, new class(app(\App\Services\Payments\StripeConfig::class)) extends StripePlatformPaymentProvider
        {
            public function createPaymentIntent(Checkout $checkout, array $options = []): PaymentIntentResult
            {
                $account = $options['provider_account'] ?? null;
                $mode = (string) ($options['mode'] ?? $account?->mode ?? 'test');

                return new PaymentIntentResult(
                    provider: 'stripe',
                    providerIntentId: 'pi_connect_checkout_'.$checkout->id,
                    clientSecret: 'pi_connect_checkout_'.$checkout->id.'_secret_'.$mode,
                    status: 'requires_payment_method',
                    amount: (string) $checkout->grand_total,
                    currencyCode: $checkout->currency_code,
                    raw: [
                        'id' => 'pi_connect_checkout_'.$checkout->id,
                        'status' => 'requires_payment_method',
                        'mode' => $mode,
                    ],
                    providerAccountId: $account instanceof PaymentProviderAccount ? $account->provider_account_id : null,
                    mode: $mode,
                );
            }

            public function retrievePaymentIntent(string $providerIntentId, ?string $mode = null): PaymentWebhookResult
            {
                return new PaymentWebhookResult(
                    eventType: 'payment_intent.succeeded',
                    providerIntentId: $providerIntentId,
                    status: 'succeeded',
                    amount: '24.00',
                    currencyCode: 'USD',
                    raw: ['id' => $providerIntentId, 'type' => 'payment_intent.succeeded'],
                    mode: $mode,
                );
            }
        });
    }

    /**
     * @return array{0: Store, 1: User, 2?: string}
     */
    private function ownedStore(string $name = 'Sandbox Connect Store', bool $withToken = false): array
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => ['checkout_mode' => CheckoutMode::PLATFORM],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        if (! $withToken) {
            return [$store, $owner];
        }

        $token = 'baa_dev_test_'.Str::random(32);
        $store->forceFill([
            'developer_storefront_token_hash' => hash('sha256', $token),
            'developer_storefront_token_created_at' => now(),
        ])->save();

        return [$store, $owner, $token];
    }

    private function connectAccount(Store $store, string $mode, string $providerAccountId): PaymentProviderAccount
    {
        return PaymentProviderAccount::query()->create([
            'store_id' => $store->id,
            'provider' => 'stripe',
            'provider_account_id' => $providerAccountId,
            'mode' => $mode,
            'connection_type' => 'connect',
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
        ]);
    }

    private function product(Store $store): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Sandbox Product',
            'slug' => 'sandbox-product-'.Str::random(6),
            'base_price' => 12,
            'sku' => 'SBX-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => $product->sku.'-D',
            'price' => 12,
            'stock' => 5,
        ]);

        return [$product, $variant];
    }

    private function payload(ProductVariant $variant): array
    {
        return [
            'source_channel' => 'dev_storefront',
            'currency_code' => 'USD',
            'shipping_total' => 0,
            'customer' => [
                'full_name' => 'Sandbox Buyer',
                'email' => 'sandbox.buyer@example.test',
                'phone' => '+15550199',
            ],
            'shipping_address' => [
                'name' => 'Sandbox Buyer',
                'address_line1' => '123 Sandbox Way',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '73301',
                'country' => 'US',
                'phone' => '+15550199',
            ],
            'billing_address' => ['same_as_shipping' => true],
            'items' => [['variant_id' => $variant->id, 'quantity' => 2]],
        ];
    }

    private function paymentIntentEvent(string $type, string $intentId, string $status, string $accountId): string
    {
        return json_encode([
            'id' => 'evt_'.Str::random(10),
            'object' => 'event',
            'type' => $type,
            'account' => $accountId,
            'data' => [
                'object' => [
                    'id' => $intentId,
                    'object' => 'payment_intent',
                    'status' => $status,
                    'amount' => 2400,
                    'currency' => 'usd',
                ],
            ],
        ], JSON_UNESCAPED_SLASHES);
    }

    private function postConnectWebhook(string $payload, string $mode = 'test')
    {
        $secret = $mode === 'live' ? 'whsec_live_connect' : 'whsec_test_connect';
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return $this->call('POST', '/api/webhooks/stripe/connect/'.$mode, [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ], $payload);
    }
}
