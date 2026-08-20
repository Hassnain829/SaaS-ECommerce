# DR-05 Batch 7 Merchant Migration and Cutover Plan

Status: **Implemented 2026-08-20** (Website → Connect your website go-live checklist)

Prepared: 2026-08-20

Prerequisite: the ten Batch 1–6 real-browser scenarios are closed by merchant confirmation dated 2026-08-20 (`docs/handoffs/DR05_BATCH6_CRITICAL_FIX_EVIDENCE.md`). Runtime lives in `MerchantCutoverService`, `MerchantCutoverController`, and `resources/views/user_view/partials/website_cutover.blade.php`. This document remains the Batch 7 contract.

## Authority and sequence

The requested root file `WORDPRESS_SAAS_COMMERCE_ARCHITECTURE_AND_IMPLEMENTATION_PLAN.md` is not tracked in the current repository or reachable Git history. The resolved current architecture authority is [`DR05_BATCH6_CRITICAL_FIX_SPEC.md`](DR05_BATCH6_CRITICAL_FIX_SPEC.md).

The execution order is fixed:

1. Close the Batch 1–6 ten-scenario browser-evidence gate.
2. Implement and verify this Batch 7 merchant migration/cutover workflow.
3. Execute Batch 8 WordPress/SaaS release recovery and acceptance.
4. Sign off DR-05 only after Batch 8 evidence.
5. Begin DR-06 owner/manager/staff acceptance across two stores.

The ten-scenario browser gate is closed by merchant confirmation dated 2026-08-20. Batch 7 runtime is implemented on the existing Website workspace. Batch 8 evidence is `docs/handoffs/DR05_BATCH8_RELEASE_EVIDENCE.md`.

## 1. Purpose

Batch 7 will give a non-technical merchant one guided, resumable workflow for migrating a WooCommerce catalog, validating the current store's commerce setup, connecting one WordPress website, switching the connector-backed pages into use, running a production smoke test, and retaining a safe rollback path.

This is controlled cutover orchestration, not a new commerce engine. Laravel remains authoritative for catalog, price, currency, inventory, tax, delivery, Stripe payment, customers, orders, confirmation, fulfillment, and audit state. WordPress remains a presentation connector.

Batch 7 must prevent activation when a critical current-store fact is broken. It must never turn an acknowledgement into proof of payment readiness, tenant ownership, checkout integrity, inventory correctness, or connection health.

## 2. Current inspected baseline

### Website / WordPress connection workspace

Existing capability:

- `routes/web.php` exposes the canonical `developer-storefront.settings` Website surface with `developer_api.view` / `developer_api.manage` permission middleware.
- `DeveloperStorefrontSettingsController::show()` resolves the current store, its primary connected site, health, catalog synchronization, published-product count, connection state, and current setup step.
- `resources/views/user_view/developer_storefront.blade.php` is already WordPress-first: plugin download, exact site address, key issue/rotate/revoke, health, catalog synchronization, conflict messages, and a test-order instruction. The React simulator is under Advanced details.

Missing orchestration:

- It does not lead the merchant through import, inventory, tax, delivery, production activation, smoke testing, and rollback as one durable workflow.
- Its current four steps do not distinguish automatically verified facts from external acknowledgements.

### Connected-site credential and URL binding

Existing capability:

- `ConnectedSite` stores `store_id`, public identity, normalized URL, active URL key, hashed credential, encrypted event-signing secret, scopes, status, plugin version, last contact/health, rotation, and revocation state.
- `ConnectedSiteService` issues/revokes the primary credential, binds the exact site URL transactionally, enforces active URL uniqueness, checks request binding, records health, and reports catalog synchronization.
- `AuthenticateDeveloperStorefrontToken` resolves an active connected-site credential and current store; production binding remains fail-closed.

Missing orchestration:

- Connection health is available, but there is no single cutover record tying a merchant's reviewed prerequisites, activation decision, smoke result, and rollback acknowledgement together.

### Stripe readiness

Existing capability:

- `PaymentProviderManager::accountForCheckout()` accepts only the current store's active, default, charge-enabled Stripe Connect account in the store's selected mode.
- Platform keys and the explicitly named developer sandbox facility cannot make normal merchant checkout ready.
- `PaymentSettingsController` and the Payments views provide store-scoped hosted Connect onboarding/status actions.
- Connected-site health derives Stripe readiness from the same checkout account resolver.

Missing orchestration:

- The Website workspace links to Payments but does not present Stripe as a durable critical cutover gate with recheck/recovery behavior.

### Product import

Existing capability:

- `ProductImportController`, `ProductImport`, `ProductImportRow`, the product-import views, and catalog import services support mapping, preview, confirmation, progress, result/report, resume/retry, WooCommerce source-site identity, unsupported-row reporting, and location/stock policy.
- WooCommerce import migrates catalog data only; it does not migrate historical orders, customers, or payments.

Missing orchestration:

- Batch 7 needs to summarize the current store's latest relevant import outcome and route the merchant to unresolved failures/unsupported rows without pretending that an upload alone is completion.

### Locations and inventory

Existing capability:

- `LocationController`, `Location`, `DefaultLocationService`, `InventoryItem`, `InventoryLevel`, `InventoryReservation`, and inventory movement records provide store-scoped stock and fulfillment locations.
- The delivery readiness service checks an active default/eligible online-fulfillment location and address completeness.

Missing orchestration:

- No Website cutover stage currently combines primary-location eligibility with imported product/variant stock review.

### Tax settings

Existing capability:

- `TaxSettingsController`, `TaxSetting`, `TaxRate`, and `resources/views/user_view/settings/taxes.blade.php` hold store-scoped tax configuration.
- `DeliverySetupStatusService` summarizes whether tax is off, included, or added.

Missing orchestration:

- The merchant must see whether tax is deliberately disabled or configured, and must be routed to the canonical tax workspace. Batch 7 must not make tax/legal advice claims.

### Shipping and delivery configuration

Existing capability:

- `DeliverySetupWizardController`, `ShippingSettingsController`, `DeliverySetupStatusService`, `ShippingZone`, `ShippingMethod`, and the delivery wizard/views provide ship-from, delivery-area, delivery-option, pricing, and provider checks.
- `DeliverySetupStatusService::assess()` exposes `is_ready` only when a usable location, active zone, checkout-enabled method, and valid configuration exist.

Missing orchestration:

- Website setup does not currently consume this readiness result as a critical cutover gate.

### Catalog visibility

Existing capability:

- `CatalogApiV1Controller` and `DeveloperStorefrontCatalogController` return current-store, published-only catalog data.
- Connected-site health reports published catalog availability and catalog revision/cache synchronization.
- The plugin renders portal shop/product/cart pages from API data.

Missing orchestration:

- Cutover needs a selected test product and proof that the bound WordPress site can actually display its current published representation.

### Test checkout, payment, confirmation, and stock

Existing capability:

- `PlatformCheckoutController` owns checkout creation, quotation, delivery selection, PaymentIntent information, and provider-authoritative confirmation.
- `StripePlatformPaymentProvider` creates/retrieves the PaymentIntent in its stored mode and connected-account context.
- `CheckoutConversionService` converts a verified success transactionally and exactly once.
- The WordPress `class-checkout-attempt.php`, `class-storefront.php`, `checkout.js`, and templates preserve one logical attempt, render Stripe, poll recoverably, and display the SaaS confirmation.

Missing orchestration:

- There is no cutover-specific marker/correlation proving that the merchant's designated smoke checkout produced the expected current-store order and inventory result.

### Orders

Existing capability:

- `routes/web.php`, `Store\DashboardController::orders()`, `Store\DashboardController::orderViewDetails()`, `resources/views/user_view/orders.blade.php`, and `orderViewDetails.blade.php` expose store-scoped order operations.
- Guest confirmation is read from `StorefrontOrderController` using the SaaS-issued confirmation token.

Missing orchestration:

- Batch 7 needs to link the test/smoke order back to the cutover stage without accepting a manually typed “paid” claim.

### Connector and plugin-conflict diagnostics

Existing capability:

- `ConnectedSiteHealthController` accepts bounded diagnostics from an authenticated connected site and records them through `ConnectedSiteService`.
- WordPress `class-conflicts.php` detects WooCommerce runtime, Woo cart/checkout assignments, payment/shipping plugins, conflicting shortcodes, and unsafe checkout caching.
- `class-admin.php` displays connection/readiness details and the four connector-backed pages. The connector reports conflicts; it never deactivates plugins.

Missing orchestration:

- Diagnostics are visible but are not organized as cutover gates with freshness, recovery actions, and explicit separation between detected facts and manual external work.

## 3. Scope

The guided workflow must cover these stages in order:

1. **Backup acknowledgement:** explain that the platform cannot verify the WordPress/WooCommerce backup; record who acknowledged having a recoverable backup and when.
2. **WooCommerce export/import completion:** derive the current store's import state; link to upload/history/result.
3. **Import failure and unsupported-product review:** block or warn based on actual failed/unsupported rows; never call a partial import complete without review.
4. **Currency readiness:** derive the store's nonblank three-letter currency and show that it is the connected checkout currency.
5. **Primary fulfillment-location readiness:** derive active/default/online-fulfillment eligibility and address completeness.
6. **Inventory review:** derive missing inventory records, zero/negative available stock, and whether at least one published test item is sellable at an eligible location.
7. **Tax readiness:** derive enabled/disabled/configured state; allow an informed business acknowledgement only for an intentional tax-off choice, not for invalid configuration.
8. **Shipping configuration:** reuse the canonical delivery readiness assessment.
9. **Eligible delivery method:** require at least one active, checkout-enabled method that is configuration-ready for the smoke-test destination.
10. **Current-store Stripe readiness:** require `PaymentProviderManager::accountForCheckout($currentStore)`; no manual override.
11. **WordPress authentication and URL binding:** require active connected-site credentials, exact bound URL, and matching reported site URL.
12. **Site diagnostics:** require a recent compatible plugin health check and no critical connection/cache/commerce conflicts.
13. **Test product visibility:** select an actual current-store published/sellable product and verify it through the connected catalog API/site.
14. **Test quotation, shipping, payment, order, and stock flow:** execute the existing connector/platform checkout and correlate one provider-authoritative result to one current-store order and one inventory effect.
15. **Confirmation visibility:** verify the SaaS confirmation token/order is visible through the connected WordPress order-status page.
16. **WooCommerce/payment/shipping conflict removal:** require a fresh connector diagnostic proving critical conflicts are absent; the merchant performs any deactivation manually.
17. **Connector-backed page assignment:** verify Portal Shop, Portal Cart, Portal Checkout, and Portal order status exist and contain the expected connector shortcodes.
18. **Redirect application:** guide the merchant to apply the intended storefront entry redirect and verify its reported target without creating an external checkout fallback.
19. **Cache-clearing guidance:** explain host/CDN/plugin steps; record acknowledgement because external cache purges cannot currently be proven by Laravel. Re-run diagnostics afterward.
20. **Production smoke test and rollback/archive acknowledgement:** correlate the smoke order, show final status, and record that rollback guidance and Woo archive retention were reviewed.

## 4. Truthful gate design

| Gate | Verification type | Critical? | Source of truth / merchant action |
|---|---|---:|---|
| Backup | Merchant acknowledgement for external/manual action | Yes for activation | Timestamped acknowledgement only; explicitly not platform-verified |
| Import completed | Derived automatically from SaaS data | Yes | Current-store `ProductImport` status and row outcomes |
| Failed/unsupported rows reviewed | Derived result plus merchant acknowledgement for disposition | Block unresolved failures; warn on consciously deferred supported limitations | Import report identifies rows; acknowledgement records the merchant's decision but cannot rewrite row status |
| Currency | Derived automatically from SaaS data | Yes | Current store currency; no WordPress override |
| Primary fulfillment location | Derived automatically from SaaS data | Yes | Current-store locations/default/online-fulfillment/address facts |
| Inventory | Derived automatically from SaaS data | Yes | Current-store item/level/reservation data for published test products |
| Tax | Derived automatically; acknowledgement only when intentionally off | Yes | Tax settings/rates; acknowledgement cannot override malformed configuration |
| Shipping setup | Derived automatically from SaaS data | Yes | `DeliverySetupStatusService::assess()` |
| Eligible delivery method | Verified through existing quote/configuration logic | Yes | Active zone/method plus a real destination quote |
| Stripe | Derived automatically from SaaS data/provider status | Yes | `PaymentProviderManager::accountForCheckout()`; no checkbox override |
| Connector credential | Derived automatically from SaaS data | Yes | Active `ConnectedSite`, scope, and credential state |
| URL binding | Checked through connected WordPress site | Yes | Saved normalized URL versus authenticated reported URL |
| Plugin compatibility/site diagnostics | Verified through existing API/diagnostic | Yes | Fresh health payload and plugin version |
| Test product visibility | Checked through connected WordPress site/API | Yes | Published catalog result and connected-site catalog revision |
| Test checkout/payment/order/stock | Verified through existing API and SaaS records | Yes | Checkout, stored PaymentIntent/account, order, reservation/movement, and stock correlation |
| Confirmation visibility | Checked through connected WordPress site | Yes | SaaS confirmation token retrieved by the connector-backed status page |
| Woo/payment/shipping conflicts | Checked through connected WordPress diagnostic | Yes | Fresh `class-conflicts.php` report; no manual success override |
| Connector page assignment | Checked through connected WordPress diagnostic | Yes | Actual pages/shortcodes reported by the plugin |
| Redirect application | Checked through connected WordPress diagnostic where implemented; otherwise manual acknowledgement | Warning until machine-check exists | Never claim verified when only acknowledged |
| External cache purge | Merchant acknowledgement plus follow-up diagnostic | Yes for activation | Platform cannot verify a host/CDN purge; diagnostic must still show safe checkout behavior |
| Production smoke test | Existing API/SaaS records plus connected-site confirmation check | Yes | Correlated checkout/order/payment/inventory/confirmation evidence |
| Rollback/archive review | Merchant acknowledgement | Yes | Timestamped acknowledgement; no deletion or automated plugin change |

Gate rules:

- Critical technical failures are never overridable by a checkbox.
- A stale diagnostic is not `ready`; the UI must show when and how to recheck.
- A warning may permit progression only when it does not weaken payment, tenant, checkout, inventory, connection, or data-safety invariants.
- “Completed” means the current store's latest facts satisfy the gate, not that the merchant visited a page.

## 5. UX placement

Extend the canonical **Website → Connect your website** workspace implemented by `developer-storefront.settings`. Do not create a second onboarding product or move normal merchants into the React developer simulator.

Proposed stepper stages:

1. **Prepare and protect data** — backup acknowledgement, Woo export/import, exceptions.
2. **Ready the store** — currency, location, inventory, tax, delivery.
3. **Connect payments and WordPress** — current-store Stripe, connector key, exact URL, health.
4. **Test the complete sale** — product visibility, quote, delivery, Stripe test payment, one order/stock/confirmation.
5. **Switch the website** — conflicts, connector pages, redirect, cache guidance.
6. **Go live safely** — production smoke test, evidence summary, rollback/archive acknowledgement.

Each stage must show:

- `Blocked`: a critical derived/checkable fact fails, with one canonical recovery action.
- `Ready`: prerequisites are true but the stage's explicit action is not complete.
- `Warning`: an external/manual fact needs acknowledgement or a noncritical limitation needs review.
- `Completed`: current facts and any legitimate acknowledgement satisfy the stage.

Merchant-facing actions should deep-link to existing canonical workspaces: Import products, Locations, Tax, Delivery setup, Payments, Products, Orders, and WordPress Settings → Eco Portal. Returning must resume the same current-store cutover.

Authorization:

- `developer_api.view` may view readiness without seeing raw secrets.
- `developer_api.manage` and the existing domain-specific manage permissions govern actions.
- Stripe, tax, delivery, import, inventory, and connection mutations retain their existing route permissions.
- Final activation/rollback should require the store owner unless the existing role policy explicitly grants an equivalent high-risk permission.
- Staff must never gain an action merely because the cutover page links to it.

The UI must never show fake success, fabricate a provider check, or hide a blocker because an earlier visit was completed.

## 6. Security and tenant boundaries

- Resolve `currentStore` for every merchant request and constrain every connected site, import, product, variant, location, inventory level, shipping method, tax record, checkout, payment account, and order to that store.
- Preserve owner/manager/staff authorization and existing domain permission middleware.
- Add explicit two-store tests for every cutover read/write; route model binding is not sufficient.
- Never expose the connected-site credential hash, plain token after its one-time display, event-signing secret, Stripe secret/client secret beyond the existing safe public checkout contract, or provider credentials in Blade/JavaScript/logs.
- WordPress may report presentation diagnostics but never payment, order, inventory, tax, shipping, or fulfillment truth.
- WordPress must not call carrier APIs or own carrier credentials.
- Never automatically deactivate WooCommerce, payment, shipping, caching, or other plugins.
- Never delete WooCommerce products, orders, customers, settings, or backups.
- Never reactivate direct paid-order/external order/shipment endpoints or an external checkout fallback.
- Merchant carrier accounts remain merchant-owned; the platform never funds postage.
- Store security-log entries should record acknowledgement, activation, rollback, and sensitive workflow transitions without secrets.

## 7. Data and migration analysis

Most readiness should be derived live from existing authoritative records. A persistent cutover record is nevertheless justified for facts that current data cannot represent safely:

- resumability across sessions/devices;
- who acknowledged the external backup, cache purge, Woo archive retention, and rollback instructions;
- which connected site and smoke order were reviewed at activation;
- activation/rollback timestamps and auditability;
- distinguishing a current recheck from a stale completed-looking page.

Proposed table (subject to implementation review): `connected_site_cutovers`.

Proposed ownership and fields:

- `id`, `store_id`, `connected_site_id`;
- `status`: `draft`, `blocked`, `ready`, `activated`, or `rolled_back`;
- `started_by`, `activated_by`, `rolled_back_by`;
- `backup_acknowledged_at`, `backup_acknowledged_by`;
- `import_exceptions_acknowledged_at`, `import_exceptions_acknowledged_by`;
- `tax_off_acknowledged_at`, `tax_off_acknowledged_by` when applicable;
- `external_cache_acknowledged_at`, `external_cache_acknowledged_by`;
- `rollback_acknowledged_at`, `rollback_acknowledged_by`;
- `woo_archive_acknowledged_at`, `woo_archive_acknowledged_by`;
- `smoke_checkout_id`, `smoke_order_id` as store-validated nullable references;
- `last_verified_at`, `activation_requested_at`, `activated_at`, `rolled_back_at`;
- `verification_snapshot` JSON containing sanitized gate labels/statuses/record IDs only, never credentials or Stripe client secrets;
- normal timestamps.

Constraints and lifecycle:

- Every related ID must belong to `store_id`; cross-store assignment fails.
- Prefer one active draft/ready/activated cutover per store and connected site, enforced transactionally.
- Rechecking recomputes technical readiness from live data; snapshots are evidence, not current truth.
- Activation is allowed only from `ready` after a fresh transactional recheck.
- Rollback preserves the record and audit history; it does not delete WooCommerce data or SaaS orders.
- Deployment must backfill nothing and create no synthetic acknowledgements. Rollback of the migration must stop if removing it would discard active production-cutover history without an explicit retention decision.
- Use the existing security log for transition audit events unless implementation inspection proves a dedicated event table is required.

If implementation can satisfy durable acknowledgements and audit/resume requirements through an already-approved, store-scoped record without overloading `ConnectedSite::last_health`, the migration may be avoided. Do not place merchant acknowledgements in `last_health`; that field is overwritten by connector diagnostics.

## 8. Exact anticipated files

Only inspected, existing files are listed here. This documentation pass does not modify them.

### Controllers

- `app/Http/Controllers/Settings/DeveloperStorefrontSettingsController.php`
- `app/Http/Controllers/Api/ConnectedSiteHealthController.php`
- `app/Http/Controllers/Catalog/ProductImportController.php` only if return-to-cutover navigation/status exposure cannot be handled in the Website controller
- `app/Http/Controllers/Settings/PaymentSettingsController.php` only for an existing-status handoff, not a second Stripe flow
- `app/Http/Controllers/Settings/DeliverySetupWizardController.php` only for return-to-cutover navigation
- `app/Http/Controllers/Store/DashboardController.php` only for smoke-order return navigation

### Services

- `app/Services/ConnectedSiteService.php`
- `app/Services/Payments/PaymentProviderManager.php` (consume existing readiness; do not weaken it)
- `app/Services/Delivery/DeliverySetupStatusService.php`
- `app/Services/CheckoutConversionService.php` only if a safe existing smoke correlation cannot be read without a change; payment conversion rules must not change

### Models

- `app/Models/ConnectedSite.php`
- `app/Models/Store.php`
- `app/Models/ProductImport.php`
- `app/Models/Location.php`
- `app/Models/InventoryItem.php`
- `app/Models/InventoryLevel.php`
- `app/Models/InventoryReservation.php`
- `app/Models/TaxSetting.php`
- `app/Models/TaxRate.php`
- `app/Models/ShippingZone.php`
- `app/Models/ShippingMethod.php`
- `app/Models/PaymentProviderAccount.php`
- `app/Models/Product.php`
- `app/Models/Order.php`

Most of these should remain read-only consumers for gate derivation. Do not add cutover fields to unrelated commerce models merely for convenience.

### Blade views

- `resources/views/user_view/developer_storefront.blade.php`
- `resources/views/user_view/product_import/result.blade.php` and `report.blade.php` only for an explicit return-to-cutover action
- `resources/views/user_view/settings/taxes.blade.php` only for an explicit return-to-cutover action
- `resources/views/user_view/delivery/setup/review.blade.php` only for an explicit return-to-cutover action
- `resources/views/user_view/orders.blade.php` and `orderViewDetails.blade.php` only for smoke-order context/navigation

### Routes

- `routes/web.php`
- `routes/api.php` only if authenticated connected-site diagnostics need a bounded extension; do not add commerce-truth writes from WordPress

### WordPress connector

- `dev-test-wordpress/wp-content/plugins/eco-portal-connector/includes/class-admin.php`
- `dev-test-wordpress/wp-content/plugins/eco-portal-connector/includes/class-api-client.php`
- `dev-test-wordpress/wp-content/plugins/eco-portal-connector/includes/class-conflicts.php`
- `dev-test-wordpress/wp-content/plugins/eco-portal-connector/includes/class-storefront.php`
- `dev-test-wordpress/wp-content/plugins/eco-portal-connector/eco-portal-connector.php`

### Tests

- `tests/Feature/MerchantWebsiteConnectTest.php`
- `tests/Feature/ConnectedSiteAuthTest.php`
- `tests/Feature/DeveloperStorefrontApiTest.php`
- `tests/Feature/WordPressConnectorHardeningTest.php`
- `tests/Feature/WooCommerceCatalogImportTest.php`
- `tests/Feature/PlatformCheckoutHardeningTest.php`
- `tests/Feature/Phase6CheckoutDeliveryMethodsTest.php`
- `tests/Unit/WordPressCheckoutAttemptTest.php`

### Optional migration

A new migration/model for the proposed `connected_site_cutovers` record is conditional on the data analysis above and must be reviewed before implementation. No filename is asserted here because it does not yet exist.

### Documentation

- `docs/current/PROJECT_STATE.md`
- `docs/handoffs/DEVELOPMENT_READINESS_MERCHANT_UX_REVIEW.md`
- `docs/handoffs/DR05_BATCH6_CRITICAL_FIX_EVIDENCE.md`
- this plan
- the future Batch 7 implementation evidence report and Batch 8 acceptance document, created only in their authorized passes

## 9. Testing plan

Focused PHPUnit coverage must prove:

1. owner can view/manage/activate/rollback within policy;
2. manager and staff retain the exact existing permission boundaries;
3. Store A cannot see or mutate Store B cutover, site, import, product, inventory, delivery, payment, checkout, or order facts;
4. activation is blocked when any critical gate fails;
5. disconnected, disabled, nondefault, wrong-mode, or not-charge-enabled Stripe remains blocked with no platform fallback;
6. revoked connector credentials, missing binding, and URL mismatch remain blocked;
7. failed/pending import and unresolved unsupported products remain truthful;
8. no configuration-ready delivery method blocks activation;
9. unpublished/unavailable test product fails visibility/readiness;
10. successful connector test produces one checkout, one PaymentIntent, one order, one inventory commitment/deduction, and a visible confirmation;
11. repeated/resumed cutover requests do not duplicate activation, checkout, order, or inventory effects;
12. stale diagnostics return to warning/blocked instead of preserving fake completion;
13. plugin conflicts block production activation;
14. no code path automatically deactivates or deletes WordPress/WooCommerce plugins or data;
15. rollback guidance and acknowledgements are recorded without claiming rollback was executed externally;
16. retired external checkout/order/shipment routes remain absent/`404`;
17. manual acknowledgements cannot override payment, tenant, checkout, inventory, connection, delivery, or provider failures.

Verification gate:

- focused PHPUnit suites for every changed domain;
- full `php artisan test` with exact current result;
- `php artisan migrate:fresh --seed` when a migration is added;
- PHP lint for changed PHP files;
- `node --check` for changed WordPress JavaScript and both relevant production builds when JavaScript/assets change;
- WordPress real-browser acceptance covering the guided workflow, smoke checkout, recovery, and rollback instructions;
- evidence of current-store and two-store behavior;
- `git diff --check`;
- no green/live-ready claim without recorded evidence.

## 10. Batch 7 definition of done

Batch 7 is complete only when:

- a non-technical merchant can complete the guided workflow without API documentation;
- every critical gate is current-store scoped, derived from authoritative data where possible, and truthful;
- knowingly broken checkout activation is impossible;
- the designated test checkout proves quotation, delivery, provider-authoritative payment, one order, one stock effect, and confirmation visibility;
- external/manual actions are visibly different from machine-verified facts;
- WooCommerce and other plugins are never silently deactivated or deleted;
- rollback and Woo archive-retention guidance is explicit;
- owner/manager/staff permissions and two-store isolation pass;
- focused tests, applicable migration/build/lint checks, and merchant confirmation of the ten Batch 1–6 browser scenarios are recorded;
- the full suite was not rerun in the Batch 7/8 pass and is not treated as currently green;
- Batch 8 mapping lives in `docs/handoffs/DR05_BATCH8_RELEASE_EVIDENCE.md` and is not implied solely because Batch 7 passed;
- DR-05 WordPress-connection workstream is signed off; the overall product is still not live/public-beta ready before DR-06 and remaining P0 gates.

## 11. Deferred work

Batch 7 explicitly excludes:

- Batch 8 implementation and acceptance execution;
- DR-06 cross-role/two-store merchant acceptance execution;
- Phase 9 API keys, generic event outbox, merchant webhook subscriptions, and automation;
- additional carriers or broader FedEx/USPS/DHL capability work;
- SaaS plans, subscriptions, invoices, or billing expansion;
- payment-provider expansion beyond the current Stripe foundation;
- historical WooCommerce order/customer/payment migration unless separately approved;
- automatic WordPress plugin deactivation or WooCommerce data deletion;
- WordPress carrier calls, shipment truth, or external checkout fallback.

Batch 7 runtime is implemented. Do not add a second onboarding product, auto-deactivate WordPress plugins, or treat acknowledgements as proof of Stripe, inventory, or checkout readiness.
