# DR-05 Batch 1–6 Critical Correction Evidence

Date: 2026-08-18

Amendment 2026-08-19: merchant acceptance showed that webhook-only conversion made localhost checkout completion depend on a Stripe CLI listener. Confirmation now performs provider-authoritative reconciliation by retrieving the stored PaymentIntent directly from Stripe in the stored mode and connected-account context. It validates provider intent identity, checkout/store/account metadata, amount, and currency before invoking the same transactional idempotent conversion used by verified webhooks. Webhooks remain supported as asynchronous recovery. Focused payment, Connect, shipping/routing, invariant, and WordPress lifecycle coverage passed with **65 tests and 415 assertions**. The full suite passed with **1,489 tests, 2 skipped, 8,096 assertions, and 0 failures** in **213.88 seconds**.

Stripe readiness amendment 2026-08-19: read-only database and Stripe API inspection proved that local order `#1003` was a successful test PaymentIntent created on the platform account with no connected-account context. The store's separate Connect record was restricted with charges disabled, while an implicit local/testing fallback had generated a synthetic charge-enabled platform row. This made Payments truthfully show checkout blocked while connected-site health and checkout incorrectly treated the store as ready. Normal checkout now resolves only an active, default, charge-enabled, store-scoped Connect account. The platform sandbox default is false and its account creation is available only through an explicitly named developer-test method; platform keys or Stripe CLI login cannot authorize shopper payment. The current local WordPress checkout was verified by HTTP to render the Stripe connection block. Multi-store coverage proves that one owner can keep two store-specific Stripe accounts ready simultaneously and that disabling one does not affect the other. The final full suite passed with **1,494 tests, 2 skipped, 8,151 assertions, and 0 failures** in **160.94 seconds**.

Documentation review 2026-08-20: runtime corrections and the automated results recorded below remain historical evidence from the correction passes; no automated suite was rerun during this documentation-only pass. The user reports executing all ten browser scenarios, but the repository contains no scenario-by-scenario artifacts that prove their outcomes. Every scenario is therefore recorded as **User-reported executed — evidence pending**. Batches 1–6 are not fully signed off, and Batch 7 remains blocked.

## Critical corrections delivered

- Removed the direct paid-order and external shipment/order synchronization runtime surfaces. Retired v1 endpoints remain unavailable, and `POST /api/developer-storefront/orders` is no longer registered.
- Made checkout confirmation provider-authoritative: it never accepts browser payment truth, retrieves the stored intent directly from Stripe, validates checkout/store/account/mode/amount/currency, and converts a verified success idempotently. Verified webhooks remain an asynchronous recovery path.
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
- Searches passed: retired order/shipment runtime paths and classes absent; legacy token fallback/mirroring absent; checkout conversion callsites are provider-authoritative (verified Stripe webhook or validated server-to-server retrieval); WordPress PHP sleep loop absent; no bearer credential reference in browser-facing templates or JavaScript.
- `git diff --check` — passed; Git emitted only CRLF normalization warnings for three working-copy files.

### WordPress + Stripe test-mode acceptance

The original correction pass recorded reachable local WordPress and portal endpoints plus automated lifecycle coverage. That coverage simulates two initial submissions from one rendered form and a retry after a discarded WordPress response, but it does not prove the real browser/network/Stripe lifecycle. The user subsequently reported executing all ten scenarios. No committed screenshots, timestamped observations, Stripe event/PaymentIntent references, SaaS checkout/order references, inventory records, or scenario log was found, so the report cannot convert that statement into `Passed`.

| # | Scenario | Status | Evidence currently recorded | Expected invariant | Remaining concern / proof needed |
|---:|---|---|---|---|---|
| 1 | Successful Stripe test payment | User-reported executed — evidence pending | User report only; automated provider/conversion coverage is not this browser run | One SaaS checkout, one PaymentIntent, one paid/confirmed SaaS order, one inventory commitment/deduction | Record actual outcome, timestamp, checkout ID, PaymentIntent ID/account context, order ID, and reservation/movement/stock result |
| 2 | Webhook delayed; direct retrieval succeeds | User-reported executed — evidence pending | User report only; source permits validated retrieval | Checkout completes exactly once; later verified webhook replay is idempotent | Record delayed webhook condition, retrieval outcome, IDs, completed state, and replay result |
| 3 | Webhook delayed; direct retrieval temporarily unavailable | User-reported executed — evidence pending | User report only; source returns recoverable `processing` | Truthful `processing`, “do not pay again,” no false failure/duplicate, later safe completion | Record temporary provider failure, shopper state, recovery path, final IDs, and duplicate checks |
| 4 | Reload and redirect recovery | User-reported executed — evidence pending | User report only; automated/source lifecycle coverage exists | Same checkout resumes polling after reload/`return_url`; Stripe confirmation is not repeated | Record before/after checkout and PaymentIntent IDs plus observed redirect/reload states |
| 5 | Duplicate status requests and webhook replay | User-reported executed — evidence pending | User report only; automated idempotency coverage exists | One order and one inventory commitment/deduction | Record request/replay sequence and before/after checkout, order, movement, and stock records |
| 6 | Declined or failed payment | User-reported executed — evidence pending | User report only | `failed` is distinct from delayed `processing`; no order is claimed | Record Stripe test outcome, shopper message/state, checkout state, and absence of an order/stock commitment |
| 7 | Concurrent initial submissions and lost first WordPress response | User-reported executed — evidence pending | Automated fake-SaaS lifecycle test only; no real-browser artifact | Rendered form reuses one preallocated key; one checkout and one PaymentIntent | Record form attempt identity/key correlation without exposing secrets, network interruption, both submissions, and resulting provider/SaaS IDs |
| 8 | Current-store Stripe disconnected/ineligible | User-reported executed — evidence pending | User report only; source and automated multi-store readiness coverage block fallback | Checkout blocked before payment; no platform-account or WordPress-gateway fallback | Record selected store, truthful blocked UI/API outcome, and absence of a new PaymentIntent/order |
| 9 | Inventory state after retries/replays | User-reported executed — evidence pending | User report only; automated conversion invariants exist | Exactly one reservation commitment/deduction and correct final stock | Record product/variant/location, reservation, movement, and before/after stock tied to the order |
| 10 | WooCommerce/payment/shipping plugin authority | User-reported executed — evidence pending | User report only; source detects conflicts and never auto-deactivates | Plugins do not own payment/order/shipping truth; conflicts are reported; nothing is silently disabled/deleted | Record active/inactive plugin state, conflict report, connector checkout path, and confirmation that no WordPress order/payment authority was used |

**Gate verdict:** browser execution is acknowledged but not evidenced. Until all ten rows have sufficient artifacts and actual results, Batches 1–6 remain unsigned and Batch 7 must not begin.

## Current documentation-pass inventory (2026-08-20)

Preflight `git status --short` was clean. This documentation-only pass currently contains **11 modified tracked Markdown files** and **1 new Markdown plan**; application/runtime, test, migration, configuration, environment, and carrier file counts are all **0**.

Modified: `ENTERPRISE_PROJECT_CONTEXT.md`, `ENTERPRISE_ROADMAP_2026.md`, `PROJECT_BRAIN.md`, `PROJECT_STRUCTURE.md`, `README.md`, `docs/README.md`, `docs/canonical/README.md`, `docs/current/PROJECT_STATE.md`, `docs/handoffs/DEVELOPMENT_READINESS_MERCHANT_UX_REVIEW.md`, this evidence document, and `docs/plans/DR05_BATCH6_CRITICAL_FIX_SPEC.md`.

Created: `docs/plans/DR05_BATCH7_MERCHANT_CUTOVER_PLAN.md`.

## Historical correction file inventory

The inventory below describes the original Batch 1–6 correction work. It is historical evidence, not the current documentation-pass Git diff.

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
- The historical correction established that normal checkout cannot use the optional platform sandbox fallback. This document records only that non-secret configuration purpose; it does not reproduce environment values or secrets.
- Git history now contains the Batch 1–6 and follow-up correction commits, so the original claim that the work had not entered Git history is obsolete. At this documentation pass preflight, `HEAD`, `origin/main`, and `origin/HEAD` resolved to `4710b1f`, and `git status --short` was clean. This is repository-state evidence, not proof of browser acceptance.
- Batch 7 was not started.
- Batch 8 and DR-06 were not started.
