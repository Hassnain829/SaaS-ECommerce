# Phase 5 Platform Checkout Stripe Sandbox Report

## Summary

Phase 5 Option 2 adds a platform checkout foundation without starting Stripe Connect, refunds, tax, coupons, shipping, billing, webhooks/outbox, or fulfillment work.

The SaaS can now create a store-scoped checkout, reserve inventory, create a Stripe sandbox PaymentIntent through a provider abstraction, receive verified Stripe webhook events, and convert a paid checkout into an auditable order exactly once.

External Checkout Sync remains intact for already-paid or externally pending orders.

## Files Inspected

- `AGENTS.md`
- `ENTERPRISE_PROJECT_CONTEXT.md`
- `ENTERPRISE_ROADMAP_2026.md`
- `PROJECT_BRAIN.md`
- `.agents/rules/PROJECT-CONTEXT.txt`
- `.agents/rules/ROADMAP.txt`
- `.agents/rules/Updated ERD similar to Shopify.txt`
- `.cursor/rules/*`
- `docs/phases/PHASE_5_EXTERNAL_CHECKOUT_SYNC_REPORT.md`
- `docs/phases/PHASE_4_COMMERCE_CORE_COMPLETION_REPORT.md`
- `docs/phases/PHASE_3_ENTERPRISE_INVENTORY_REPORT.md`
- Existing Phase 5 external checkout code/tests
- Order, checkout, inventory, storefront, and dashboard files touched by this phase

## Files Changed

- `composer.json`
- `composer.lock`
- `.env.example`
- `config/payments.php`
- `routes/api.php`
- `app/Contracts/Payments/PaymentProviderInterface.php`
- `app/Data/Payments/PaymentIntentResult.php`
- `app/Data/Payments/PaymentWebhookResult.php`
- `app/Services/Payments/PaymentProviderManager.php`
- `app/Services/Payments/StripePlatformPaymentProvider.php`
- `app/Services/CheckoutService.php`
- `app/Services/CheckoutConversionService.php`
- `app/Services/CheckoutEventRecorder.php`
- `app/Services/OrderNumberGenerator.php`
- `app/Support/OrderLifecycle.php`
- Checkout/payment models and migration
- Platform checkout API and Stripe webhook controllers
- Dashboard order list/detail views
- Developer storefront settings page
- `dev-test-storefront` package files and React simulator
- `tests/Feature/Phase5PlatformCheckoutStripeTest.php`

## Payment Configuration

Added payment config and environment placeholders:

- `PAYMENTS_DEFAULT_PROVIDER=stripe`
- `STRIPE_MODE=test`
- `STRIPE_KEY=`
- `STRIPE_SECRET=`
- `STRIPE_WEBHOOK_SECRET=`

The dashboard developer settings page shows whether Stripe sandbox keys and webhook secret are configured, without exposing secret values.

## Schema Added

Added:

- `payment_provider_accounts`
- `checkouts`
- `checkout_items`
- `checkout_addresses`
- `checkout_events`
- `payment_intents`
- `payment_attempts`
- `payment_captures`

The tables are store-scoped and include rollback-safe migration logic.

## Checkout Flow

`POST /api/v1/checkout` now:

- uses existing developer storefront Bearer token auth
- rejects raw card fields
- validates customer, items, and shipping address
- verifies variants belong to the token's store
- calculates totals server-side
- creates checkout/customer/address/item snapshots
- reserves inventory through Phase 3 inventory services
- creates a Stripe sandbox PaymentIntent through the payment provider abstraction
- returns checkout details, client secret, and publishable key

No final order is created before Stripe confirms payment.

## Stripe Webhook Flow

`POST /api/webhooks/stripe` now:

- verifies Stripe webhook signatures with `STRIPE_WEBHOOK_SECRET`
- handles `payment_intent.succeeded`
- handles `payment_intent.payment_failed`
- handles `payment_intent.canceled`

On success, checkout conversion:

- creates a final order once
- snapshots order items and addresses
- marks payment as paid
- commits and deducts reserved stock
- re-reserves through inventory services before conversion if an earlier failed attempt released the original reservation
- links payment intent to the order
- records checkout events and order events
- recalculates customer metrics

On failure/cancel, the checkout is marked failed and reserved inventory is released.

## Local Dev Confirmation Patch

Browser testing showed checkouts were being created but orders were not appearing when Stripe webhook forwarding was not running locally. The dev storefront now calls `POST /api/v1/checkout/{checkout}/confirm` after Stripe.js confirms the card payment. Laravel verifies the PaymentIntent with Stripe using the server-side secret key, then runs the same checkout conversion service used by the webhook path.

This is a local-friendly safety bridge, not a replacement for production Stripe webhooks.

## Dashboard / Simulator

Dashboard orders now recognize `platform_checkout` as `Platform checkout`, show Stripe as the gateway, and display the checkout number from order metadata.

The local `dev-test-storefront` simulator now supports:

- external paid order sync
- legacy direct dev order
- platform checkout with Stripe sandbox via Stripe.js

The simulator never sends raw card data to Laravel.

## Tests Added

Added `tests/Feature/Phase5PlatformCheckoutStripeTest.php` covering:

- token requirement
- raw card rejection
- checkout creation
- server-side totals
- payment intent persistence
- cross-store variant protection
- webhook signature rejection
- successful webhook conversion
- webhook idempotency
- failed payment inventory release
- dashboard order visibility

Stripe API calls are mocked in tests; no real Stripe network calls are made.

## Commands Run

- `composer require stripe/stripe-php`: completed after retrying with escalated network access and `--prefer-source`
- `npm.cmd install @stripe/stripe-js`: passed
- `php -l` on new controllers/services/models/migration/test: passed
- `composer dump-autoload`: passed
- `php artisan optimize:clear`: passed
- `php artisan migrate:fresh --seed`: passed
- `php artisan inventory:backfill`: passed
- `php artisan test --filter=Phase5PlatformCheckoutStripeTest`: `8 passed, 60 assertions`
- `php artisan test --filter=Phase5ExternalCheckoutSyncTest`: `8 passed, 59 assertions`
- `php artisan test --filter=DeveloperStorefront`: `8 passed, 42 assertions`
- `php artisan test --filter=Phase4`: `20 passed, 166 assertions`
- `php artisan test --filter=Inventory`: `11 passed, 77 assertions`
- `php artisan test`: `320 passed, 1536 assertions`
- `npm.cmd run build`: passed
- `php artisan migrate:rollback --step=1`: passed
- `php artisan migrate`: passed
- `php artisan test --filter=Phase5PlatformCheckoutStripeTest`: `8 passed, 60 assertions`

## Remaining Deferrals

Intentionally deferred:

- Stripe Connect / merchant-owned Stripe accounts
- production payment account onboarding
- PayPal, Square, or other providers
- full checkout sessions with cart persistence beyond this foundation
- tax engine
- coupons
- refunds and returns
- shipping/carriers/shipments
- SaaS billing
- production API key/scopes UI
- webhooks/outbox/automation beyond Stripe payment webhook

## Final Phase 5 Option 2 Status

Complete.

Platform checkout with Stripe sandbox is implemented, store-scoped, inventory-safe, webhook-driven, auditable, and covered by passing regression tests.

## Stripe Sandbox Connect Support Patch

Merchants can now connect a Stripe test/sandbox account separately from a live account. Platform checkout resolves payment mode safely and prevents test/live account mixing. See `docs/STRIPE_SANDBOX_CONNECT_SUPPORT_REPORT.md`.

## Stripe Connect No-Key UX Cleanup

Store owners connect through Stripe hosted onboarding only. See `docs/STRIPE_SANDBOX_CONNECT_SUPPORT_REPORT.md`.
