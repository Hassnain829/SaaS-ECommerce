<?php

namespace Tests\Feature;

use App\Data\Payments\PaymentIntentResult;
use App\Data\Payments\PaymentWebhookResult;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\PaymentIntent;
use App\Models\PaymentProviderAccount;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\SecurityLog;
use App\Models\Store;
use App\Models\User;
use App\Services\Payments\StripeConnectService;
use App\Services\Payments\StripePlatformPaymentProvider;
use App\Support\CheckoutMode;
use App\Support\OrderLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase5StripeConnectFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.default_provider' => 'stripe',
            'payments.stripe.mode' => 'test',
            'payments.stripe.key' => 'pk_test_connect_foundation',
            'payments.stripe.secret' => 'sk_test_connect_foundation',
            'payments.stripe.webhook_secret' => 'whsec_platform_checkout',
            'payments.stripe.connect_webhook_secret' => 'whsec_connect_foundation',
            'payments.stripe.allow_platform_sandbox_fallback' => true,
            'payments.stripe.modes' => [
                'test' => [
                    'key' => 'pk_test_connect_foundation',
                    'secret' => 'sk_test_connect_foundation',
                    'webhook_secret' => 'whsec_platform_checkout',
                    'connect_webhook_secret' => 'whsec_connect_foundation',
                ],
                'live' => [
                    'key' => null,
                    'secret' => null,
                    'webhook_secret' => null,
                    'connect_webhook_secret' => null,
                ],
            ],
        ]);

        $this->app->instance(StripeConnectService::class, new class(app(\App\Services\Payments\StripeConfig::class)) extends StripeConnectService
        {
            public function createOrRetrieveConnectedAccount(Store $store, User $user, string $mode = 'test'): PaymentProviderAccount
            {
                $account = PaymentProviderAccount::query()->firstOrCreate(
                    [
                        'store_id' => $store->id,
                        'provider' => 'stripe',
                        'mode' => $mode,
                        'connection_type' => 'connect',
                    ],
                    [
                        'provider_account_id' => 'acct_'.$store->id,
                        'display_name' => 'Connected Stripe account',
                        'status' => 'pending',
                        'is_default' => true,
                        'settings' => ['account_type' => 'express'],
                        'metadata' => ['fake' => true],
                        'created_by' => $user->id,
                        'charges_enabled' => false,
                        'payouts_enabled' => false,
                    ]
                );

                PaymentProviderAccount::query()
                    ->where('store_id', $store->id)
                    ->where('provider', 'stripe')
                    ->where('mode', $mode)
                    ->whereKeyNot($account->id)
                    ->update(['is_default' => false]);

                return $account->fresh();
            }

            public function createAccountOnboardingLink(PaymentProviderAccount $account, ?string $mode = null): string
            {
                return 'https://connect.stripe.test/onboarding/'.$account->provider_account_id;
            }

            public function refreshAccountStatus(PaymentProviderAccount $account): PaymentProviderAccount
            {
                $account->forceFill([
                    'status' => 'active',
                    'charges_enabled' => true,
                    'payouts_enabled' => true,
                    'requirements_currently_due' => [],
                    'requirements_disabled_reason' => null,
                    'onboarding_completed_at' => $account->onboarding_completed_at ?: now(),
                    'last_verified_at' => now(),
                ])->save();

                return $account->fresh();
            }
        });

        $this->app->instance(StripePlatformPaymentProvider::class, new class(app(\App\Services\Payments\StripeConfig::class)) extends StripePlatformPaymentProvider
        {
            public function createPaymentIntent(Checkout $checkout, array $options = []): PaymentIntentResult
            {
                $account = $options['provider_account'] ?? null;
                $providerAccountId = $account instanceof PaymentProviderAccount ? $account->provider_account_id : null;

                return new PaymentIntentResult(
                    provider: 'stripe',
                    providerIntentId: 'pi_connect_checkout_'.$checkout->id,
                    clientSecret: 'pi_connect_checkout_'.$checkout->id.'_secret_test',
                    status: 'requires_payment_method',
                    amount: (string) $checkout->grand_total,
                    currencyCode: $checkout->currency_code,
                    raw: [
                        'id' => 'pi_connect_checkout_'.$checkout->id,
                        'status' => 'requires_payment_method',
                        'account' => $providerAccountId,
                    ],
                    providerAccountId: $providerAccountId,
                );
            }

            public function retrievePaymentIntent(string $providerIntentId, ?string $mode = null): PaymentWebhookResult
            {
                $intent = PaymentIntent::query()
                    ->where('provider_intent_id', $providerIntentId)
                    ->latest('id')
                    ->first();

                return new PaymentWebhookResult(
                    eventType: 'payment_intent.succeeded',
                    providerIntentId: $providerIntentId,
                    status: 'succeeded',
                    amount: (string) ($intent?->amount ?? '24.00'),
                    currencyCode: $intent?->currency_code ?? 'USD',
                    raw: [
                        'id' => 'client_confirm_'.$providerIntentId,
                        'type' => 'payment_intent.succeeded',
                        'object' => [
                            'id' => $providerIntentId,
                            'status' => 'succeeded',
                        ],
                    ],
                    providerAccountId: $intent?->provider_account_id,
                );
            }
        });
    }

    public function test_payments_page_requires_auth_and_current_store(): void
    {
        $this->get(route('settings.payments.index'))
            ->assertRedirect(route('signin'));

        $user = $this->user();

        $this->actingAs($user)
            ->get(route('settings.payments.index'))
            ->assertRedirect(route('store-management'));
    }

    public function test_staff_cannot_start_stripe_connect(): void
    {
        [$store, , , $staff] = $this->tokenedStore('Connect Staff Store', Store::ROLE_STAFF);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.payments.stripe.connect'))
            ->assertForbidden();
    }

    public function test_owner_can_start_stripe_connect_and_reuses_provider_account(): void
    {
        [$store, , $owner] = $this->tokenedStore('Connect Owner Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.payments.stripe.connect'))
            ->assertRedirect('https://connect.stripe.test/onboarding/acct_'.$store->id);

        $this->assertDatabaseHas('payment_provider_accounts', [
            'store_id' => $store->id,
            'provider' => 'stripe',
            'provider_account_id' => 'acct_'.$store->id,
            'connection_type' => 'connect',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('security_logs', [
            'store_id' => $store->id,
            'event_type' => 'stripe_connect_test_started',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.payments.stripe.connect'))
            ->assertRedirect('https://connect.stripe.test/onboarding/acct_'.$store->id);

        $this->assertSame(1, PaymentProviderAccount::query()->where('store_id', $store->id)->where('connection_type', 'connect')->count());
    }

    public function test_return_route_refreshes_connected_account_status(): void
    {
        [$store, , $owner] = $this->tokenedStore('Connect Return Store');
        $account = $this->connectedAccount($store, ['status' => 'pending', 'charges_enabled' => false]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.stripe.return'))
            ->assertRedirect(route('settings.payments.index'));

        $account->refresh();

        $this->assertSame('active', $account->status);
        $this->assertTrue($account->charges_enabled);
        $this->assertNotNull($account->onboarding_completed_at);
        $this->assertDatabaseHas('security_logs', [
            'store_id' => $store->id,
            'event_type' => 'stripe_connect_returned',
        ]);
    }

    public function test_active_connected_stripe_account_enables_platform_checkout(): void
    {
        [$store, $token] = $this->tokenedStore('Connect Checkout Store');
        $account = $this->connectedAccount($store);
        [, $variant] = $this->product($store, ['price' => 12, 'stock' => 5]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant))
            ->assertCreated()
            ->assertJsonPath('payment.connection_type', 'connect')
            ->assertJsonPath('payment.provider_account_id', $account->provider_account_id)
            ->assertJsonPath('payment.connection_label', 'Stripe test account connected for this store');

        $this->assertDatabaseHas('checkouts', [
            'store_id' => $store->id,
            'payment_provider_account_id' => $account->id,
        ]);
        $this->assertDatabaseHas('payment_intents', [
            'store_id' => $store->id,
            'payment_provider_account_id' => $account->id,
            'provider_account_id' => $account->provider_account_id,
        ]);
    }

    public function test_no_provider_configured_blocks_platform_checkout_with_friendly_error(): void
    {
        config([
            'payments.stripe.key' => null,
            'payments.stripe.secret' => null,
            'payments.stripe.allow_platform_sandbox_fallback' => false,
        ]);

        [$store, $token] = $this->tokenedStore('Connect Blocked Store');
        [, $variant] = $this->product($store);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment'])
            ->assertJsonPath('errors.payment.0', 'Platform checkout is not enabled for this store. Connect Stripe in the SaaS dashboard or use External checkout sync.');

        $this->assertSame(0, Checkout::query()->where('store_id', $store->id)->count());
    }

    public function test_platform_sandbox_fallback_only_works_when_allowed_in_testing(): void
    {
        [$store, $token] = $this->tokenedStore('Connect Fallback Store');
        [, $variant] = $this->product($store);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant))
            ->assertCreated()
            ->assertJsonPath('payment.connection_type', 'platform')
            ->assertJsonPath('payment.connection_label', 'Platform test mode');

        $this->assertDatabaseHas('payment_provider_accounts', [
            'store_id' => $store->id,
            'connection_type' => 'platform',
            'status' => 'active',
        ]);

        config(['payments.stripe.allow_platform_sandbox_fallback' => false]);
        [$blockedStore, $blockedToken] = $this->tokenedStore('Connect No Fallback Store');
        [, $blockedVariant] = $this->product($blockedStore);

        $this->withToken($blockedToken)
            ->postJson('/api/v1/checkout', $this->payload($blockedVariant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment']);
    }

    public function test_connect_webhook_requires_valid_connect_signature(): void
    {
        $body = $this->accountUpdatedEvent('acct_invalid', true);

        $this->call('POST', '/api/webhooks/stripe/connect', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't=1,v1=invalid',
        ], $body)->assertStatus(400);
    }

    public function test_connect_account_updated_webhook_updates_provider_status(): void
    {
        [$store] = $this->tokenedStore('Connect Webhook Store');
        $account = $this->connectedAccount($store, ['status' => 'pending', 'charges_enabled' => false, 'payouts_enabled' => false]);
        $body = $this->accountUpdatedEvent($account->provider_account_id, true);

        $this->postConnectWebhook($body)->assertOk();

        $account->refresh();
        $this->assertSame('active', $account->status);
        $this->assertTrue($account->charges_enabled);
        $this->assertDatabaseHas('security_logs', [
            'store_id' => $store->id,
            'event_type' => 'stripe_connect_webhook_account_updated',
        ]);
    }

    public function test_connected_payment_success_webhook_converts_checkout_once(): void
    {
        [$store, $token] = $this->tokenedStore('Connect Webhook Payment Store');
        $account = $this->connectedAccount($store);
        [, $variant] = $this->product($store, ['price' => 12, 'stock' => 5]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant))
            ->assertCreated();

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $body = $this->paymentIntentEvent('payment_intent.succeeded', 'pi_connect_checkout_'.$checkout->id, 'succeeded', $account->provider_account_id);

        $this->postConnectWebhook($body)->assertOk();
        $this->postConnectWebhook($body)->assertOk();

        $this->assertSame(1, Order::query()->where('store_id', $store->id)->count());
        $order = Order::query()->where('store_id', $store->id)->firstOrFail();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_reference' => 'pi_connect_checkout_'.$checkout->id,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
        ]);
        $this->assertSame('Stripe test account connected for this store', data_get($order->meta, 'platform_checkout.connection_label'));
        $this->assertSame($account->provider_account_id, data_get($order->meta, 'platform_checkout.provider_account_id'));
    }

    public function test_store_cannot_use_another_store_connected_account(): void
    {
        config(['payments.stripe.allow_platform_sandbox_fallback' => false]);

        [$storeA, $tokenA] = $this->tokenedStore('Connect Store A');
        [$storeB] = $this->tokenedStore('Connect Store B');
        $this->connectedAccount($storeB);
        [, $variant] = $this->product($storeA);

        $this->withToken($tokenA)
            ->postJson('/api/v1/checkout', $this->payload($variant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment']);

        $this->assertSame(0, Checkout::query()->where('store_id', $storeA->id)->count());
    }

    public function test_disabling_connected_provider_prevents_new_platform_checkout(): void
    {
        config(['payments.stripe.allow_platform_sandbox_fallback' => false]);

        [$store, $token, $owner] = $this->tokenedStore('Connect Disable Store');
        $account = $this->connectedAccount($store);
        [, $variant] = $this->product($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.payments.stripe.disable'))
            ->assertRedirect(route('settings.payments.index'));

        $account->refresh();
        $this->assertSame('disabled', $account->status);
        $this->assertFalse($account->is_default);
        $this->assertDatabaseHas('security_logs', [
            'store_id' => $store->id,
            'event_type' => 'stripe_provider_disconnected',
            'severity' => SecurityLog::SEVERITY_WARNING,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->payload($variant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment']);
    }

    private function tokenedStore(string $name, string $extraUserRole = Store::ROLE_OWNER): array
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = $this->user(['role_id' => $role->id]);
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

        $token = 'baa_dev_test_'.Str::random(32);
        $store->forceFill([
            'developer_storefront_token_hash' => hash('sha256', $token),
            'developer_storefront_token_created_at' => now(),
        ])->save();

        $extraUser = null;
        if ($extraUserRole !== Store::ROLE_OWNER) {
            $extraUser = $this->user(['role_id' => $role->id]);
            $store->members()->attach($extraUser->id, ['role' => $extraUserRole]);
        }

        return [$store, $token, $owner, $extraUser];
    }

    private function user(array $overrides = []): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create(array_merge([
            'role_id' => $role->id,
            'is_active' => true,
        ], $overrides));
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

    private function product(Store $store, array $overrides = []): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $overrides['name'] ?? 'Connect Product',
            'slug' => 'connect-product-'.Str::random(6),
            'base_price' => $overrides['price'] ?? 12,
            'sku' => $overrides['sku'] ?? 'CONN-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => $overrides['variant_sku'] ?? $product->sku.'-D',
            'price' => $overrides['price'] ?? 12,
            'stock' => $overrides['stock'] ?? 5,
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
                'full_name' => 'Connect Buyer',
                'email' => 'connect.buyer@example.test',
                'phone' => '+15550188',
            ],
            'shipping_address' => [
                'name' => 'Connect Buyer',
                'address_line1' => '123 Connect Way',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '73301',
                'country' => 'US',
                'phone' => '+15550188',
            ],
            'billing_address' => [
                'same_as_shipping' => true,
            ],
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 2,
                ],
            ],
        ];
    }

    private function accountUpdatedEvent(string $accountId, bool $ready): string
    {
        return json_encode([
            'id' => 'evt_connect_'.Str::random(10),
            'object' => 'event',
            'type' => 'account.updated',
            'account' => $accountId,
            'data' => [
                'object' => [
                    'id' => $accountId,
                    'object' => 'account',
                    'charges_enabled' => $ready,
                    'payouts_enabled' => $ready,
                    'capabilities' => [
                        'card_payments' => $ready ? 'active' : 'pending',
                        'transfers' => $ready ? 'active' : 'pending',
                    ],
                    'requirements' => [
                        'currently_due' => $ready ? [] : ['business_profile.url'],
                        'disabled_reason' => null,
                    ],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES);
    }

    private function paymentIntentEvent(string $type, string $intentId, string $status, string $accountId): string
    {
        return json_encode([
            'id' => 'evt_connect_'.Str::random(10),
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

    private function connectSignature(string $payload): string
    {
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_connect_foundation');

        return 't='.$timestamp.',v1='.$signature;
    }

    private function postConnectWebhook(string $payload)
    {
        return $this->call('POST', '/api/webhooks/stripe/connect', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $this->connectSignature($payload),
        ], $payload);
    }
}
