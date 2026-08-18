# DR-05 Batch 1–6 Critical Correction Evidence

Date: 2026-08-18

Status: Runtime corrections and automated verification passed. The browser-driven WordPress + Stripe test-mode acceptance gate was not run, so the overall Batch 1–6 acceptance set is not yet fully verified and Batch 7 must not begin on this evidence alone.

## Critical corrections delivered

- Removed the direct paid-order and external shipment/order synchronization runtime surfaces. Retired v1 endpoints remain unavailable, and `POST /api/developer-storefront/orders` is no longer registered.
- Made checkout confirmation a read-only state poll. Only verified Stripe webhook controllers call checkout conversion.
- Reworked checkout idempotency as a database-first claim with a database uniqueness constraint, request hashing, owner tokens, completed-response replay, and explicit in-progress conflicts.
- Removed legacy store-hash authentication fallback and legacy credential mirroring. Active connected-site normalized URL identity is database-enforced and released on revocation. Store settings and primary-site URL binding/clearing now commit in one transaction, and credential issue/reactivation translates only the relevant unique-index race into the existing website validation error.
- Added an outbound catalog delivery guard covering production HTTPS, credentials in URLs, local hostnames, A and AAAA resolution, special-use IP ranges, redirects, DNS pinning, and production fail-closed behavior.
- Made WooCommerce import identity include the merchant-confirmed source site. Default SKU collisions fail; linking to an unowned manual record requires explicit merchant approval.
- Kept the WordPress connector presentation-only. Plugin 1.7.1 creates the HttpOnly-cookie-bound checkout session, idempotency key, and opaque form token before rendering the initial address form. Concurrent submissions and retries after a lost WordPress response must present that token and reuse the same key; a missing/mismatched session fails closed, changed details require **Start over**, and completion/reset clears the attempt. The plugin also removes PHP sleep polling, stores `confirming` / `processing` in its transient, and exposes a nonce-protected same-session AJAX action that makes one server-side SaaS status request per browser poll. Browser polling uses backoff, survives reloads and Stripe redirect returns without reconfirming payment, and keeps time-budget exhaustion recoverable.

## Database migrations and deployment conditions

- `2026_08_18_100000_harden_checkout_idempotency_claims.php` adds claim ownership/completion data and authoritative uniqueness for checkout idempotency.
- `2026_08_18_101000_enforce_active_connected_site_url_identity.php` verifies legacy credentials can be preserved in same-store connected-site records before clearing legacy hashes, rejects duplicate active normalized URLs with explicit diagnostics, and adds unique active URL identity.
- `2026_08_18_102000_add_woocommerce_source_site_identity.php` adds source-site identity to products, variants, and imports and replaces Woo source indexes with site-aware uniqueness.

No blocker was encountered on the current database or a fresh test database. Production deployment must stop and remediate data if the connected-site migration reports duplicate active normalized URLs or an unpreserved legacy credential. Rolling back the Woo migration deliberately stops if legitimate multi-site identities cannot fit the former site-agnostic unique indexes.

## Verification evidence

- `php artisan test` — **1,485 passed, 2 skipped, 8,077 assertions, 0 failed** in 169.06 seconds.
- Focused correction suite (`DeveloperStorefrontApiTest`, `ConnectedSiteAuthTest`, `MerchantWebsiteConnectTest`, `CheckoutIdempotencyClaimTest`, `Phase5PlatformCheckoutStripeTest`, `PlatformCheckoutHardeningTest`, `CheckoutPaymentInvariantTest`, `WordPressConnectorHardeningTest`, `CatalogCacheInvalidationTest`, `OutboundUrlGuardTest`, `WordPressCheckoutAttemptTest`, and `WooCommerceCatalogImportTest`) — **105 passed, 802 assertions, 0 failed** in 10.60 seconds.
- `APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan migrate:fresh --seed --force` — all migrations and seeders passed.
- PHP syntax checks passed for all **64** changed or created PHP files.
- `node --check dev-test-wordpress/wp-content/plugins/eco-portal-connector/assets/js/checkout.js` — passed with no output.
- The normal `npm.cmd run build` output cleanup was blocked by Windows `EPERM` on the existing `public/build/assets` directory while four pre-existing Node processes were active. The same Vite 7.3.1 production build was rerun with a unique isolated `--outDir`; **58 modules built successfully in 2.81 seconds**, and only that temporary output was removed.
- Searches passed: retired order/shipment runtime paths and classes absent; legacy token fallback/mirroring absent; checkout conversion callsites limited to the two verified Stripe webhook controllers; WordPress PHP sleep loop absent; no bearer credential reference in browser-facing templates or JavaScript.
- `git diff --check` — passed; Git emitted only CRLF normalization warnings for three working-copy files.

### WordPress + Stripe test-mode acceptance

The local WordPress (`127.0.0.1:8080`) and portal (`127.0.0.1:8000`) TCP endpoints were reachable without inspecting configuration. Real browser acceptance was **not executed**: Docker is not installed, and the repository has no Playwright, Puppeteer, Cypress, Selenium driver, or callable browser binary. Interactive Stripe credentials/state were not inspected, and no `.env` file was read. Source contract tests, JavaScript syntax validation, and the production build do not replace this gate.

Automated lifecycle coverage now simulates two concurrent submissions from one rendered form and a retry after discarding the first WordPress response; both prove that the fake SaaS creates/replays exactly one checkout for the preallocated key. It also proves that a POST without the browser-bound attempt fails closed and that key rotation requires cleared state. Real-browser scenario 7 remains required to validate the complete WordPress/network/Stripe path.

All ten scenarios remain **unverified**:

1. Successful test card payment → wait for `completed` → verify one portal order and one inventory commitment.
2. Delay webhook delivery → verify the shopper remains in truthful `processing`, never payment failure.
3. Reload during `processing` → verify the same transient checkout resumes polling without a second Stripe confirmation.
4. Complete a redirect-capable test payment → verify `return_url` resumes status polling.
5. Send duplicate status requests and replay the verified webhook → verify one order and one inventory commitment.
6. Use a declined/failed test payment → verify `failed` is distinct from delayed `processing` and no order is claimed.
7. Interrupt checkout creation/status networking, including two initial form submissions and a lost first WordPress response → retry the rendered logical attempt with the same preallocated idempotency key and verify no duplicate PaymentIntent/checkout.
8. Disconnect Stripe → verify checkout is blocked before payment.
9. Compare order, inventory reservation, movement, and stock after duplicate/retried events → verify exactly one commitment/deduction.
10. Confirm WooCommerce and WordPress payment plugins are inactive/not authoritative throughout the flow.

Until these pass in a real browser, Batches 1–6 are not fully signed off and Batch 7 must not begin.

## Exact file inventory

### Modified (64)

```text
ENTERPRISE_PROJECT_CONTEXT.md
ENTERPRISE_ROADMAP_2026.md
PROJECT_BRAIN.md
PROJECT_STRUCTURE.md
app/Http/Controllers/Api/DeveloperStorefrontCatalogController.php
app/Http/Controllers/Api/PlatformCheckoutController.php
app/Http/Controllers/Catalog/ProductImportController.php
app/Http/Controllers/Settings/DeveloperStorefrontSettingsController.php
app/Http/Controllers/Settings/PaymentSettingsController.php
app/Http/Middleware/AuthenticateDeveloperStorefrontToken.php
app/Models/ConnectedSite.php
app/Models/IdempotencyKey.php
app/Models/Product.php
app/Models/ProductImport.php
app/Models/ProductVariant.php
app/Models/Store.php
app/Services/Catalog/ProductImportProcessor.php
app/Services/Catalog/ProductImportSourceIdentity.php
app/Services/Catalog/ProductImportVariantFinalizer.php
app/Services/Channels/ChannelOwnershipService.php
app/Services/Checkout/CheckoutIdempotencyService.php
app/Services/ConnectedSiteCatalogEventDeliveryService.php
app/Services/ConnectedSiteService.php
app/Support/CheckoutMode.php
config/connected_sites.php
dev-test-wordpress/README.md
dev-test-wordpress/wp-content/plugins/eco-portal-connector/assets/js/checkout.js
dev-test-wordpress/wp-content/plugins/eco-portal-connector/eco-portal-connector.php
dev-test-wordpress/wp-content/plugins/eco-portal-connector/includes/class-api-client.php
dev-test-wordpress/wp-content/plugins/eco-portal-connector/includes/class-storefront.php
dev-test-wordpress/wp-content/plugins/eco-portal-connector/templates/checkout.php
docs/current/PROJECT_STATE.md
docs/handoffs/DEVELOPMENT_READINESS_MERCHANT_UX_REVIEW.md
docs/plans/PHASE_9_INTEGRATION_FOUNDATION_PLAN.md
resources/views/user_view/product_import/preview.blade.php
routes/api.php
routes/web.php
tests/Feature/CatalogCacheInvalidationTest.php
tests/Feature/CheckoutPaymentInvariantTest.php
tests/Feature/ConnectedSiteAuthTest.php
tests/Feature/DeliveryUxBatch3Test.php
tests/Feature/DeveloperStorefrontApiTest.php
tests/Feature/EnterpriseQaOriginRoutingHardeningTest.php
tests/Feature/ExternalManagedChannelModeTest.php
tests/Feature/MerchantWebsiteConnectTest.php
tests/Feature/Phase2CatalogCleanupTest.php
tests/Feature/Phase2CatalogCompletionTest.php
tests/Feature/Phase3EnterpriseInventoryTest.php
tests/Feature/Phase5PaymentUxCleanupTest.php
tests/Feature/Phase5PlatformCheckoutStripeTest.php
tests/Feature/Phase5R2CouponTest.php
tests/Feature/Phase5R3TotalsHardeningTest.php
tests/Feature/Phase5StripeConnectFoundationTest.php
tests/Feature/Phase6CheckoutDeliveryMethodsTest.php
tests/Feature/Phase6NearestEligibleOriginRoutingTest.php
tests/Feature/PlatformCheckoutHardeningTest.php
tests/Feature/PlatformCheckoutShippingTaxRecalculationTest.php
tests/Feature/PlatformCheckoutTaxTest.php
tests/Feature/PlatformOnlyCheckoutTest.php
tests/Feature/ProductTaxableFlagTest.php
tests/Feature/StripeSandboxConnectSupportTest.php
tests/Feature/WooCommerceCatalogImportTest.php
tests/Feature/WordPressConnectorHardeningTest.php
tests/TestCase.php
```

### Created (11)

```text
app/Services/Security/OutboundDnsResolver.php
app/Services/Security/OutboundUrlGuard.php
database/migrations/2026_08_18_100000_harden_checkout_idempotency_claims.php
database/migrations/2026_08_18_101000_enforce_active_connected_site_url_identity.php
database/migrations/2026_08_18_102000_add_woocommerce_source_site_identity.php
dev-test-wordpress/wp-content/plugins/eco-portal-connector/includes/class-checkout-attempt.php
docs/handoffs/DR05_BATCH6_CRITICAL_FIX_EVIDENCE.md
docs/plans/DR05_BATCH6_CRITICAL_FIX_SPEC.md
tests/Feature/CheckoutIdempotencyClaimTest.php
tests/Unit/OutboundUrlGuardTest.php
tests/Unit/WordPressCheckoutAttemptTest.php
```

### Deleted (10)

```text
app/Exceptions/ExternalOrderConflictException.php
app/Http/Controllers/Api/ExternalOrderSyncController.php
app/Http/Controllers/Api/ExternalShipmentSyncController.php
app/Services/ExternalOrderSyncService.php
app/Services/ExternalShipmentSyncService.php
tests/Feature/DeveloperStorefrontOrderEventsTest.php
tests/Feature/EnterpriseQaExternalOrderDedupHardeningTest.php
tests/Feature/ExternalShipmentSyncTest.php
tests/Feature/Phase4CommerceCoreRegressionTest.php
tests/Feature/Phase5ExternalCheckoutSyncTest.php
```

The deleted tests covered only retired direct-order/external-sync behavior. Current catalog, connected-site authentication, platform checkout, webhook conversion, tax/shipping, Woo import, WordPress connector, and related regression coverage remains in the passing suite.

## Scope and safety confirmation

- No carrier runtime code or carrier billing/authorization model was changed.
- No `.env` file contents were read or changed.
- No commit or push was performed.
- Batch 7 was not started.
