<?php

namespace Tests;

use App\Models\PaymentProviderAccount;
use App\Models\Store;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function connectReadyStripeForCheckout(Store $store, string $mode = 'test'): PaymentProviderAccount
    {
        return PaymentProviderAccount::query()->updateOrCreate(
            [
                'store_id' => $store->id,
                'provider' => 'stripe',
                'mode' => $mode,
                'connection_type' => PaymentProviderAccount::CONNECTION_CONNECT,
            ],
            [
                'provider_account_id' => 'acct_'.$mode.'_store_'.$store->id,
                'display_name' => 'Test connected Stripe account',
                'status' => 'active',
                'is_default' => true,
                'settings' => ['account_type' => 'express'],
                'charges_enabled' => true,
                'payouts_enabled' => true,
                'requirements_currently_due' => [],
                'onboarding_completed_at' => now(),
                'last_verified_at' => now(),
            ],
        );
    }

    public function withToken(string $token, string $type = 'Bearer'): static
    {
        parent::withToken($token, $type);

        // Storefront checkout callers must always supply a stable key. Individual
        // replay tests override this generated default with their deliberate key.
        return $this->withHeader('Idempotency-Key', 'test-checkout-'.Str::uuid());
    }

    protected function setUp(): void
    {
        // Cached config ignores phpunit.xml env overrides; a dev `config:cache` from
        // another environment can freeze product_import.queue_connection=database and
        // leave imports stuck "queued" in tests. Drop cache files before bootstrapping.
        $cacheDir = dirname(__DIR__).DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache';
        foreach (['config.php', 'routes-v7.php'] as $file) {
            $path = $cacheDir.DIRECTORY_SEPARATOR.$file;
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }
}
