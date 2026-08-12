# Phase 5 Stripe Connect Foundation Report

## Summary

Implemented Phase 5 Option 3: Payments & Channels Settings plus a Stripe Connect foundation. Store owners now have a dashboard page for payment setup, can start/continue Stripe hosted onboarding, can refresh connected account status, and can locally disable platform checkout for a store.

External Checkout Sync and Platform Stripe sandbox checkout remain intact. This phase did not add refunds, tax, coupons, shipping, fulfillment, billing, B2B, API-key management, webhooks/outbox, or automation.

## Files Inspected

- `AGENTS.md`
- `ENTERPRISE_PROJECT_CONTEXT.md`
- `ENTERPRISE_ROADMAP_2026.md`
- `PROJECT_BRAIN.md`
- `.agents/rules/PROJECT-CONTEXT.txt`
- `.agents/rules/ROADMAP.txt`
- `.agents/rules/Updated ERD similar to Shopify.txt`
- `docs/phases/PHASE_5_EXTERNAL_CHECKOUT_SYNC_REPORT.md`
- `docs/phases/PHASE_5_PLATFORM_CHECKOUT_STRIPE_SANDBOX_REPORT.md`
- `docs/phases/PHASE_4_COMMERCE_CORE_COMPLETION_REPORT.md`
- `docs/phases/PHASE_3_ENTERPRISE_INVENTORY_REPORT.md`
- Existing Phase 5 checkout/payment models, services, controllers, routes, views, dev storefront simulator, and tests.

## Files Changed

- `.env.example`
- `config/payments.php`
- `routes/web.php`
- `routes/api.php`
- `app/Models/PaymentProviderAccount.php`
- `app/Models/PaymentIntent.php`
- `app/Data/Payments/PaymentIntentResult.php`
- `app/Data/Payments/PaymentWebhookResult.php`
- `app/Services/Payments/PaymentProviderManager.php`
- `app/Services/Payments/StripePlatformPaymentProvider.php`
- `app/Services/Payments/StripeConnectService.php`
- `app/Services/CheckoutService.php`
- `app/Services/CheckoutConversionService.php`
- `app/Http/Controllers/PaymentSettingsController.php`
- `app/Http/Controllers/Api/PlatformCheckoutController.php`
- `app/Http/Controllers/Api/StripeConnectWebhookController.php`
- `resources/views/user_view/payment_settings.blade.php`
- `resources/views/layouts/user/user-Sidebar.blade.php`
- `resources/views/user_view/orders.blade.php`
- `resources/views/user_view/orderViewDetails.blade.php`
- `dev-test-storefront/src/App.jsx`
- `tests/Feature/Phase5StripeConnectFoundationTest.php`

## Env vs Database Strategy

Platform Stripe secrets remain in `.env` or the server secret manager:

- `STRIPE_KEY`
- `STRIPE_SECRET`
- `STRIPE_WEBHOOK_SECRET`
- `STRIPE_CONNECT_WEBHOOK_SECRET`
- `STRIPE_CONNECT_REFRESH_URL`
- `STRIPE_CONNECT_RETURN_URL`
- `STRIPE_CONNECT_CLIENT_ID`

Store connection state is stored in `payment_provider_accounts`. The app stores Stripe account IDs such as `acct_...`, capability/status snapshots, and onboarding state. It does not ask store owners to paste production Stripe secret keys and does not store raw store-owner Stripe secrets.

## Schema Added

Added a migration to extend `payment_provider_accounts` with:

- `provider_account_id`
- `capabilities`
- `last_verified_at`
- `created_by`
- `onboarding_completed_at`
- `charges_enabled`
- `payouts_enabled`
- `requirements_currently_due`
- `requirements_disabled_reason`
- `deleted_at`

Added `payment_intents.provider_account_id` so connected Stripe PaymentIntents can be tied back to the Stripe account ID used for the charge.

Rollback/reapply was verified.

## Payments & Channels UI

Added Settings -> Payments with:

- External checkout sync status
- Platform checkout readiness
- Connected Stripe account status
- Connect Stripe / Continue onboarding
- Refresh status
- Disable local provider

Viewing uses `settings.view`. Connect, refresh, return, and disable actions use `settings.manage`.

## Stripe Connect Onboarding

Added `StripeConnectService` to:

- create or reuse an Express connected account
- generate Stripe hosted onboarding links
- refresh account status
- apply webhook account status snapshots
- locally disable the provider

Only one store/mode default provider is maintained by service logic.

## Provider Resolution

Platform checkout now resolves the payment account in this order:

1. Active default Stripe Connect account for the current store.
2. Platform Stripe sandbox fallback only in local/testing when enabled and configured.
3. Friendly checkout block: `Platform checkout is not enabled for this store. Connect Stripe in the SaaS dashboard or use External checkout sync.`

Connected Stripe checkout passes the connected account ID to the Stripe SDK request options using `stripe_account`.

## Stripe Connect Webhook

Added:

```txt
POST /api/webhooks/stripe/connect
```

It verifies `STRIPE_CONNECT_WEBHOOK_SECRET`, handles `account.updated`, and routes connected-account payment intent success/failure/cancel events into the existing checkout conversion logic without duplicating order creation behavior.

Webhook retries remain idempotent through the existing checkout conversion safeguards.

## Dashboard / Simulator

Orders list and order detail now show platform checkout connection context, including `Connected Stripe account` or `Platform test mode` where available.

The local React simulator now initializes Stripe.js with the connected account context when the checkout response includes a connected account ID.

## Security Logs

Added/verified logs for:

- `stripe_connect_started`
- `stripe_connect_returned`
- `stripe_account_status_refreshed`
- `stripe_provider_disabled`
- `stripe_connect_webhook_account_updated`

## Tests Added / Updated

Added `tests/Feature/Phase5StripeConnectFoundationTest.php`.

Coverage includes:

- Payments page requires auth/current store
- Staff cannot start Stripe Connect
- Owner can start Connect and account row is reused
- Onboarding link redirects to Stripe
- Return route refreshes status
- Active connected account enables platform checkout
- No provider blocks checkout with friendly validation
- Sandbox fallback works only when allowed in testing
- Connect webhook verifies the Connect webhook secret
- `account.updated` updates provider status
- Connected payment success webhook converts checkout once
- Store A cannot use Store B connected account
- Disable prevents new platform checkout
- Security logs for connect/status/disable/webhook

## Commands Run

- `php -l` on new/changed PHP files: passed.
- `php artisan test --filter=Phase5StripeConnectFoundationTest`: `12 passed, 60 assertions`.
- `php artisan test --filter=Phase5PlatformCheckoutStripeTest`: `9 passed, 72 assertions`.
- `php artisan test --filter=Phase5ExternalCheckoutSyncTest`: `8 passed, 59 assertions`.
- `php artisan test --filter=DeveloperStorefront`: `8 passed, 42 assertions`.
- `php artisan test --filter=Phase4`: `20 passed, 166 assertions`.
- `php artisan test --filter=Inventory`: `11 passed, 77 assertions`.
- `composer dump-autoload`: passed.
- `php artisan optimize:clear`: passed.
- `php artisan migrate:fresh --seed`: passed.
- `php artisan inventory:backfill`: passed.
- `php artisan migrate:rollback --step=1`: passed.
- `php artisan migrate`: passed.
- `php artisan inventory:backfill`: passed.
- `php artisan test`: `333 passed, 1608 assertions`.
- `npm.cmd run build`: passed for the Laravel Vite app.
- `npm.cmd run build` in `dev-test-storefront`: passed.

## Remaining Deferrals

- Live Stripe OAuth/account-link production hardening beyond the foundation
- Refunds and payment capture management
- Tax engine
- Coupons/discount rules
- Fulfillment, shipping, carriers, and shipments
- Returns and exchanges
- SaaS billing
- Full API keys/scopes, webhooks/outbox, and automation
- PayPal, Square, and other providers

## Final Status

Complete.

Phase 5 now supports external checkout sync, platform Stripe sandbox checkout, and a store-scoped Stripe Connect foundation with store-owner setup UI, provider resolution, connected-account webhook handling, security logs, and passing regression coverage.

## Payment UX Cleanup Addendum

### User / Store Owner Terminology

The SaaS dashboard uses:

- User / store owner = person using the SaaS dashboard.
- Customer = person buying from the user/store owner site.

### Final Checkout Mode Policy

Stores choose one active checkout mode:

- External checkout
- Platform checkout

External checkout is for existing websites and checkouts that already collect payment. Platform checkout requires a connected payment provider before it can be enabled from the dashboard.

The active mode is stored on the current store in `stores.settings.checkout_mode` as either `external_checkout` or `platform_checkout`. The default is `external_checkout`.

### Platform Sandbox Clarification

The platform Stripe sandbox is a developer/testing fallback only. It is not shown as a normal user/store owner payment option.

The normal dashboard decision is now limited to External checkout, Platform checkout, and Stripe connection status. Sandbox fallback details are visible only inside the collapsed Developer diagnostics section for users who can manage settings.

### Webhook Secret Clarification

`STRIPE_WEBHOOK_SECRET` and `STRIPE_CONNECT_WEBHOOK_SECRET` are platform-level environment secrets. They are not configured per user/store owner, and users/store owners do not edit `.env`.

If local Stripe CLI forwarding uses the same webhook secret for both platform and Connect events, both environment variables may use that value locally.

### Dev Storefront Clarification

`dev-test-storefront` is a local simulator. A real storefront should use one integration path selected in the SaaS dashboard.

The simulator now exposes only:

- External checkout sync
- Platform checkout

The legacy direct dev order mode is hidden because direct final order creation is no longer the correct Phase 5 architecture.

### Cleanup Commands Run

- `php -l app\Support\CheckoutMode.php`: passed.
- `php -l app\Http\Controllers\PaymentSettingsController.php`: passed.
- `php -l app\Services\CheckoutService.php`: passed.
- `php -l tests\Feature\Phase5PaymentUxCleanupTest.php`: passed.
- `php -l app\Http\Controllers\Api\PlatformCheckoutController.php`: passed.
- `php -l app\Services\CheckoutConversionService.php`: passed.
- `php -l app\Services\Payments\PaymentProviderManager.php`: passed.
- `php -l routes\web.php`: passed.
- `php artisan test --filter=Phase5PaymentUxCleanupTest`: `9 passed, 53 assertions`.
- `php artisan test --filter=Phase5StripeConnectFoundationTest`: `12 passed, 60 assertions`.
- `php artisan test --filter=Phase5PlatformCheckoutStripeTest`: `9 passed, 72 assertions`.
- `php artisan test --filter=Phase5ExternalCheckoutSyncTest`: `8 passed, 59 assertions`.
- `php artisan test --filter=DeveloperStorefront`: `8 passed, 42 assertions`.
- `php artisan test --filter=Phase4`: `20 passed, 166 assertions`.
- `php artisan test --filter=Inventory`: `11 passed, 77 assertions`.
- `composer dump-autoload`: passed.
- `php artisan migrate:fresh --seed`: passed.
- `php artisan optimize:clear`: passed after rerun by itself. The first attempt was run in parallel with `migrate:fresh --seed`, so the cache table was temporarily absent while migrations were rebuilding.
- `php artisan inventory:backfill`: passed.
- `php artisan test`: `342 passed, 1661 assertions`.
- `npm.cmd run build`: passed.
- `npm.cmd run build` in `dev-test-storefront`: passed.

## Stripe Sandbox Connect Support Patch

Merchants can now connect a Stripe test/sandbox account separately from a live account. Platform checkout resolves payment mode safely and prevents test/live account mixing. See `docs/STRIPE_SANDBOX_CONNECT_SUPPORT_REPORT.md`.

## Stripe Connect No-Key UX Cleanup

Store owners connect Stripe through hosted onboarding only — never by pasting secret keys. Platform Stripe keys remain server-side platform configuration. See cleanup section in `docs/STRIPE_SANDBOX_CONNECT_SUPPORT_REPORT.md`.
