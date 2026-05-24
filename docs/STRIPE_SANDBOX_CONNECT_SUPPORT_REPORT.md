# Stripe Sandbox Connect Support Report

## Summary

Patch B adds **test/live separation** for Stripe Connect and platform checkout across config, `payment_provider_accounts`, onboarding, Payments & Channels UI, checkout provider resolution, `payment_intents`, webhooks, dev storefront copy, and tests. External checkout is unchanged and still never creates Stripe PaymentIntents.

## Files Inspected

- `.env.example`, `config/payments.php`, `config/services.php`
- `routes/web.php`, `routes/api.php`
- `app/Models/PaymentProviderAccount.php`, `PaymentIntent.php`, `PaymentAttempt.php`
- `app/Services/Payments/PaymentProviderManager.php`, `StripePlatformPaymentProvider.php`, `StripeConnectService.php`, `StripeConfig.php`
- `app/Support/PlatformPaymentMode.php`
- `app/Http/Controllers/PaymentSettingsController.php`
- `app/Http/Controllers/Api/PlatformCheckoutController.php`, `StripeWebhookController.php`, `StripeConnectWebhookController.php`
- `app/Services/CheckoutService.php`, `CheckoutConversionService.php`
- `resources/views/user_view/payment_settings.blade.php` and partials
- `dev-test-storefront/src/App.jsx`
- Phase 5/6 related feature tests

## Current Gaps Found (Stage 0 Audit)

| Question | Before patch |
|----------|----------------|
| `payment_provider_accounts.mode` | Yes (default `test`) |
| `connection_type` | Yes (`connect` / `platform`) |
| Both test + live per store | Schema yes; logic used single global `STRIPE_MODE` |
| Connect secrets | One shared legacy secret |
| Onboarding | Account Links (Express), not OAuth |
| Checkout test vs live | Global config only |
| `payment_intents.mode` | Column yes; not tied to store payment mode |
| Webhooks test vs live | Single endpoint/secret |
| Separate UI cards | No |
| External checkout PaymentIntent | Correctly avoided |

## Config / Environment Separation

- `config/payments.php` — `PAYMENTS_DEFAULT_MODE`, `payments.stripe.modes.test|live` with legacy `STRIPE_KEY` / `STRIPE_SECRET` fallback to test when `STRIPE_MODE=test`.
- `app/Services/Payments/StripeConfig.php` — `stripePublicKey`, `stripeSecretKey`, webhook/connect secrets, `isModeConfigured`, connect URLs per mode.
- `.env.example` — documents all new `STRIPE_TEST_*`, `STRIPE_LIVE_*`, and legacy vars.

## Payment Provider Account Mode Separation

- Model helpers: `isStripe`, `isConnect`, `isTestMode`, `isLiveMode`, `isActive`, scopes `forStore`, `stripe`, `connect`, `mode`, `maskedProviderAccountId`.
- One default connect account per store **per mode**; same store may hold both test and live connect records.
- No merchant secret keys stored.

## Stripe Connect Test / Live Account Flow

- `StripeConnectService` — all operations accept `mode`; test onboarding uses test secret, live uses live secret.
- Routes: `POST .../stripe/connect/test/start`, `.../live/start`, return URLs by mode, account-scoped refresh/disconnect.
- Security log events: `stripe_connect_test_started`, `stripe_connect_live_started`, `stripe_provider_disconnected`, `platform_payment_mode_changed`, etc.

## Payments & Channels UI

- Separate **Stripe test account** and **Stripe live account** cards.
- **Platform checkout payment mode** (test / live) with guardrails.
- Developer diagnostics show per-mode webhook/connect webhook status and sandbox fallback.

## Checkout Provider Resolution

- `PlatformPaymentMode` store setting (`stores.settings.platform_payment_mode`).
- `PaymentProviderManager::accountForCheckout($store, $mode)` picks active connect account for that mode only.
- Test checkout cannot use live account; live checkout requires active live connect (or fails gracefully).
- `PaymentIntent` records store `mode` and `payment_provider_account_id`.
- Platform sandbox fallback: test only, local/testing when configured.

## Webhook Mode Awareness

- `POST /api/webhooks/stripe/{mode}` and `/api/webhooks/stripe/connect/{mode}` (`test|live`); legacy routes default to `test`.
- Verification uses mode-specific secrets; conversion filters by `PaymentWebhookResult::$mode`.

## Dev Storefront Simulator

- Shows **Payment mode: Test/Live** and existing `connection_label` from checkout API.

## Tests Added / Updated

- **Added:** `StripeSandboxConnectSupportTest`, `PaymentProviderAccountModeTest`
- **Updated:** `Phase5StripeConnectFoundationTest`, `Phase5PlatformCheckoutStripeTest`, `Phase5PaymentUxCleanupTest`, `Phase6CheckoutDeliveryMethodsTest` (mock signatures)

## Commands Run

```
composer dump-autoload
php artisan test --filter=StripeSandboxConnectSupportTest     → 9 passed
php artisan test --filter=PaymentProviderAccountModeTest    → 5 passed
php artisan test --filter=Phase5StripeConnectFoundationTest   → 12 passed
php artisan test --filter=Phase5PlatformCheckoutStripeTest    → 9 passed
php artisan test --filter=Phase5PaymentUxCleanupTest          → 9 passed
php artisan test --filter=Phase5ExternalCheckoutSyncTest      → 8 passed
php artisan test                                              → 411 passed
cd dev-test-storefront && npm run build                       → passed
```

No new migrations (schema already had `mode` on accounts and intents).

## Remaining Deferrals

- Refunds, tax, coupons, SaaS billing, carriers, returns, webhook outbox
- Raw merchant Stripe secret key input (still not supported)
- Application fees / production Connect hardening beyond mode separation
- OAuth-based Connect (still Account Links)

## Final Status

**Complete** — acceptance criteria met; full suite and dev storefront build pass.
