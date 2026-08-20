# DR-05 Batch 1–6 Critical Correction Evidence

Date: 2026-08-18

Amendment 2026-08-19: merchant acceptance showed that webhook-only conversion made localhost checkout completion depend on a Stripe CLI listener. Confirmation now performs provider-authoritative reconciliation by retrieving the stored PaymentIntent directly from Stripe in the stored mode and connected-account context. It validates provider intent identity, checkout/store/account metadata, amount, and currency before invoking the same transactional idempotent conversion used by verified webhooks. Webhooks remain supported as asynchronous recovery. Focused payment, Connect, shipping/routing, invariant, and WordPress lifecycle coverage passed with **65 tests and 415 assertions**. The full suite passed with **1,489 tests, 2 skipped, 8,096 assertions, and 0 failures** in **213.88 seconds**.

Stripe readiness amendment 2026-08-19: read-only database and Stripe API inspection proved that local order `#1003` was a successful test PaymentIntent created on the platform account with no connected-account context. The store's separate Connect record was restricted with charges disabled, while an implicit local/testing fallback had generated a synthetic charge-enabled platform row. This made Payments truthfully show checkout blocked while connected-site health and checkout incorrectly treated the store as ready. Normal checkout now resolves only an active, default, charge-enabled, store-scoped Connect account. The platform sandbox default is false and its account creation is available only through an explicitly named developer-test method; platform keys or Stripe CLI login cannot authorize shopper payment. The current local WordPress checkout was verified by HTTP to render the Stripe connection block. Multi-store coverage proves that one owner can keep two store-specific Stripe accounts ready simultaneously and that disabling one does not affect the other. The final full suite passed with **1,494 tests, 2 skipped, 8,151 assertions, and 0 failures** in **160.94 seconds**.

Gate close 2026-08-20: the merchant completed all ten real-browser WordPress + Stripe scenarios. This pass does **not** add or re-run those browser tests, and it does not invent Stripe PaymentIntent, checkout, or order IDs. Every scenario below is **Passed** by that merchant confirmation. Automated lifecycle coverage recorded earlier remains supporting evidence only. Batches 1–6 are signed off for this gate; Batch 7 and Batch 8 proceeded the same day.

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

The original correction pass recorded reachable local WordPress and portal endpoints plus automated lifecycle coverage. That coverage simulates two initial submissions from one rendered form and a retry after a discarded WordPress response; it is not a substitute for the real browser/network/Stripe lifecycle. The merchant completed all ten real-browser scenarios on 2026-08-20. No Stripe IDs or screenshots are committed in this repository, by request. The gate is closed on that confirmation rather than on newly invented artifacts.

| # | Scenario | Status | Evidence currently recorded | Expected invariant | Remaining concern / proof needed |
|---:|---|---|---|---|---|
| 1 | Successful Stripe test payment | Passed | Merchant-completed real-browser test, 2026-08-20; supporting automated provider/conversion coverage exists | One SaaS checkout, one PaymentIntent, one paid/confirmed SaaS order, one inventory commitment/deduction | None for this gate. Stripe IDs were not committed, by request. |
| 2 | Webhook delayed; direct retrieval succeeds | Passed | Merchant-completed real-browser test, 2026-08-20; source permits validated retrieval | Checkout completes exactly once; later verified webhook replay is idempotent | None for this gate. |
| 3 | Webhook delayed; direct retrieval temporarily unavailable | Passed | Merchant-completed real-browser test, 2026-08-20; source returns recoverable `processing` | Truthful `processing`, “do not pay again,” no false failure/duplicate, later safe completion | None for this gate. |
| 4 | Reload and redirect recovery | Passed | Merchant-completed real-browser test, 2026-08-20; automated/source lifecycle coverage exists | Same checkout resumes polling after reload/`return_url`; Stripe confirmation is not repeated | None for this gate. |
| 5 | Duplicate status requests and webhook replay | Passed | Merchant-completed real-browser test, 2026-08-20; automated idempotency coverage exists | One order and one inventory commitment/deduction | None for this gate. |
| 6 | Declined or failed payment | Passed | Merchant-completed real-browser test, 2026-08-20 | `failed` is distinct from delayed `processing`; no order is claimed | None for this gate. |
| 7 | Concurrent initial submissions and lost first WordPress response | Passed | Merchant-completed real-browser test, 2026-08-20; automated fake-SaaS lifecycle coverage also exists | Rendered form reuses one preallocated key; one checkout and one PaymentIntent | None for this gate. |
| 8 | Current-store Stripe disconnected/ineligible | Passed | Merchant-completed real-browser test, 2026-08-20; source and automated multi-store readiness coverage block fallback | Checkout blocked before payment; no platform-account or WordPress-gateway fallback | None for this gate. |
| 9 | Inventory state after retries/replays | Passed | Merchant-completed real-browser test, 2026-08-20; automated conversion invariants exist | Exactly one reservation commitment/deduction and correct final stock | None for this gate. |
| 10 | WooCommerce/payment/shipping plugin authority | Passed | Merchant-completed real-browser test, 2026-08-20; source detects conflicts and never auto-deactivates | Plugins do not own payment/order/shipping truth; conflicts are reported; nothing is silently disabled/deleted | None for this gate. |

**Gate verdict:** closed. All ten Batch 1–6 real-browser scenarios are complete. Batch 7 may proceed (and did, the same day). Do not add a replacement browser-test suite for these ten scenarios unless a production defect requires it.

## Earlier documentation-pass inventory (2026-08-20 morning)

That earlier documentation-only pass recorded a clean `git status --short` with **11 modified tracked Markdown files** and **1 new Markdown plan**. It is historical. The later Batch 7/8 runtime pass added application, migration, test, and evidence files; see `docs/handoffs/DR05_BATCH8_RELEASE_EVIDENCE.md`.

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
- Batch 7 go-live checklist was implemented 2026-08-20 after this gate closed.
- Batch 8 mapping evidence is `docs/handoffs/DR05_BATCH8_RELEASE_EVIDENCE.md`.
- DR-06 focused automated coverage started the same day; the full human owner/manager/staff journey is not signed off.
