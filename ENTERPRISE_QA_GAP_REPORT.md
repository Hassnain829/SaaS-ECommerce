# Enterprise QA Gap Report

Read-only analysis from audit bundle + source inspection.  
Branch: `main` · Commit: `54a2f8d` · Step 1 tests: **426 passed**  
Generated: 2026-05-24

---

## Executive verdict

- **Can development continue to Phase 6C-1?** **YES WITH CONDITIONS**
- **Critical blockers:** None confirmed in code review or existing test suite.
- **High-priority blockers:**
  - External order sync can create duplicate orders when callers omit both `Idempotency-Key` and `external_order_number` (MySQL unique index allows multiple NULLs).
  - Phase 6C-0A origin routing has thin negative/concurrency test coverage relative to carrier-phase dependency on stable paid-order → origin → shipment flows.
  - `DashboardController` remains a large multi-domain surface (~1,219 lines) with uneven inline authorization patterns on GET routes.
- **Recommended next action:** Run Step 3 hardening focused on external-sync dedup guarantees, routing edge-case tests, and permission/read-route alignment — then proceed to Phase 6C-1 carrier sandbox behind those guardrails.

---

## Severity summary

| Severity | Count | Notes |
|----------|-------|-------|
| Critical | 0 | No confirmed cross-store money/inventory corruption paths |
| High | 3 | External sync dedup gap, routing test depth, monolith regression risk |
| Medium | 9 | Permissions, schema indexes, audit noise, fulfillment mapping, docs |
| Low | 5 | Test naming, policy pattern, documentation gaps |
| False positive / already covered | 6 | Webhook order dedup, store scoping on core APIs, migration rollbacks |
| Needs manual browser verification | 4 | Staff UI, live Connect, shipping settings UX, order detail workflows |
| Deferred feature, not a bug | 12 | Carrier sandbox, geocoding, tax, billing, public API keys, etc. |

---

## Findings

### Finding QA-001 — External orders can duplicate without `external_order_number` or `Idempotency-Key`

**Severity:** High  
**Area:** External checkout / order sync  
**Status:** Confirmed gap  
**Evidence:**
- `ExternalOrderSyncController::store()` — idempotency is optional (`Idempotency-Key` header); sync proceeds without it.
- `ExternalOrderSyncService::sync()` — service-level dedup runs only when `external_order_number` is filled (lines 64–83); otherwise always creates a new order.
- Migration `2026_05_12_010000_add_external_checkout_sync_fields_to_orders_table.php` — unique index on `(store_id, order_source, channel, external_order_number)` does not prevent duplicate rows when `external_order_number` is NULL (MySQL NULL semantics).
- Test `Phase5ExternalCheckoutSyncTest::test_duplicate_external_order_number_returns_existing_or_conflict` covers numbered duplicates only; no test for missing number + missing idempotency key.

**Why it matters:** A retrying external channel (network timeout, client bug) can create multiple paid orders and double-deduct platform inventory when inventory owner is platform.

**Recommended action:** Require `external_order_number` for external sync **or** require `Idempotency-Key` **or** add a composite dedup fallback (e.g. hash of customer+items+totals+timestamp window) with DB uniqueness where safe.

**Files to inspect/fix:**
- `app/Http/Controllers/Api/ExternalOrderSyncController.php`
- `app/Services/ExternalOrderSyncService.php`
- `database/migrations/2026_05_12_010000_add_external_checkout_sync_fields_to_orders_table.php`

**Suggested test coverage:**
- Two identical POSTs without idempotency key and without `external_order_number` → one order
- Concurrent duplicate POSTs under platform inventory owner → single deduction

**Blocker before Phase 6C-1?** **Yes (conditional)** — carrier work assumes stable order identity; duplicate external orders corrupt fulfillment state.

---

### Finding QA-002 — Stripe webhook has no provider event-id dedup table; replay creates extra payment attempts

**Severity:** Medium  
**Area:** Payments / webhooks  
**Status:** Likely gap (audit noise, not order duplication)  
**Evidence:**
- `StripeWebhookController::__invoke()` — verifies signature, dispatches to `CheckoutConversionService`; no Stripe `event.id` persistence.
- `CheckoutConversionService::handleSucceededPayment()` — always creates a new `paymentIntent->attempts()` record (lines 59–64) before checking `checkout->converted_order_id` (lines 95–101).
- Test `Phase5PlatformCheckoutStripeTest::test_stripe_success_webhook_converts_checkout_to_order_once` posts webhook twice and asserts order count stays 1 — does not assert attempt/event row counts.

**Why it matters:** Order conversion is idempotent, but replay pollutes audit tables and could confuse operational dashboards or future idempotency logic.

**Recommended action:** Either persist processed Stripe event IDs or skip attempt creation when checkout already converted; add test asserting stable attempt/event counts on replay.

**Files to inspect/fix:**
- `app/Http/Controllers/Api/StripeWebhookController.php`
- `app/Services/CheckoutConversionService.php`
- `tests/Feature/Phase5PlatformCheckoutStripeTest.php`

**Suggested test coverage:** Same `payment_intent.succeeded` webhook twice → 1 order, 1 inventory deduction, bounded attempt rows.

**Blocker before Phase 6C-1?** **No** — conversion correctness is covered; harden before beta/live.

---

### Finding QA-003 — Platform checkout webhook conversion is idempotent (duplicate paid orders prevented)

**Severity:** False positive / already covered  
**Area:** Payments / checkout conversion  
**Status:** Already covered  
**Evidence:**
- `CheckoutConversionService::handleSucceededPayment()` returns existing order when `checkout->converted_order_id` is set (lines 95–101); uses `DB::transaction`, `lockForUpdate` on payment intent and checkout.
- `PaymentCapture::firstOrCreate()` keyed on `payment_intent_id` + `provider_capture_id`.
- `Phase5PlatformCheckoutStripeTest::test_stripe_success_webhook_converts_checkout_to_order_once` — explicit double-webhook assertion.

**Why it matters:** Reviewers asked whether missing Stripe event-id table is a real gap — for **order creation**, current service state + tests indicate **acceptable for current stage**, with QA-002 caveats on audit rows.

**Recommended action:** Document that idempotency is checkout-state-based, not event-id-based; optionally add event-id table in Step 3.

**Blocker before Phase 6C-1?** **No**

---

### Finding QA-004 — Merchant GET routes omit explicit `orders.view` / `catalog.view` middleware

**Severity:** Medium  
**Area:** Roles / permissions  
**Status:** Likely gap (defense-in-depth)  
**Evidence:**
- `routes/web.php` — `GET /orders`, `GET /orders/{order}`, `GET /products`, `GET /products/view/{product}` sit inside `auth + current.store` only; no `store.permission:orders.view` or `catalog.view`.
- `StorePermission::ROLE_STAFF` includes `orders.view` and `catalog.view`, but routes do not enforce them.
- Mutations correctly use `orders.manage`, `catalog.manage`, `settings.manage` middleware.
- `OrderEventsTimelineTest::test_staff_cannot_update_order_status` — staff blocked on PATCH, not on GET.

**Why it matters:** Today’s three roles align with implicit access, but future custom roles or permission changes could expose read surfaces without route-level enforcement. Violates “server-side denial even if UI hides controls” principle.

**Recommended action:** Add read middleware to order/catalog GET routes; add staff/non-member GET denial tests.

**Files to inspect/fix:**
- `routes/web.php`
- `tests/Feature/StoreRoleAuthorizationTest.php`

**Suggested test coverage:** User attached to store with empty/custom permissions → 403 on `/orders`, `/products`.

**Blocker before Phase 6C-1?** **No** — but should-fix before beta/live.

---

### Finding QA-005 — Manager role cannot mutate settings (owner-only for payments, shipping, locations)

**Severity:** Medium  
**Area:** Roles / permissions  
**Status:** Needs reviewer verification (may be intentional)  
**Evidence:**
- `StorePermission::ROLE_MANAGER` includes `settings.view` but **not** `settings.manage`.
- Payment/shipping/location POST routes require `store.permission:settings.manage` (`routes/web.php`).
- `ExternalManagedChannelModeTest::test_staff_without_settings_permission_cannot_change_inventory_source` — staff POST blocked; no parallel manager test.
- `PaymentSettingsController::index()` passes `canManagePayments` from `canManageSettings()`.

**Why it matters:** If merchants expect managers to configure shipping zones, locations, or Stripe Connect, current RBAC blocks them. If owner-only is intentional, it should be documented in merchant-facing help.

**Recommended action:** Product decision — grant managers `settings.manage` or document owner-only settings; add manager authorization tests.

**Blocker before Phase 6C-1?** **No**

---

### Finding QA-006 — `DashboardController` is a multi-domain monolith (~1,219 lines)

**Severity:** Medium  
**Area:** Large controllers / refactoring  
**Status:** Confirmed gap (maintainability)  
**Evidence:**
- `app/Http/Controllers/DashboardController.php` — ~1,219 lines; handles auth, dashboard metrics, product list/export, orders list/detail, customers, team, settings views, profile, security sessions, store management stubs.
- Step 1 index flagged this; dedicated `ProductController` / `GeneralSettingsController` do not exist.
- Protective tests exist piecemeal: `ProductCurrentStoreTest`, `Phase4OrderDetailTest`, `StoreRoleAuthorizationTest` — not a unified controller regression suite.

**Why it matters:** Phase 6C-1 will touch fulfillment/shipment UX adjacent to order detail flows in the same controller neighborhood; large diffs increase regression risk.

**Recommended action:** Defer refactor until after 6C-1 **unless** carrier UI forces split; add targeted feature tests for any touched DashboardController methods before refactor.

**Files to inspect/fix:** `app/Http/Controllers/DashboardController.php` (future split candidates: orders, customers, profile)

**Blocker before Phase 6C-1?** **No** — report only; do not refactor now.

---

### Finding QA-007 — Phase 6C-0A routing has only five dedicated tests

**Severity:** High  
**Area:** Shipping / origin routing  
**Status:** Confirmed gap (test depth)  
**Evidence:**
- `tests/Feature/Phase6NearestEligibleOriginRoutingTest.php` — 5 tests (service-area selection, pickup reroute, exact-stock reservation math, order/shipment snapshot, external inventory owner split).
- `FulfillmentOriginRouter` — postal/service-area scoring, not lat/lng (correct for 6C-0A scope).
- No tests found for: tied routing scores, inactive location exclusion, cross-store pickup ID rejection, concurrent dual checkouts on last unit, routing failure when all locations out of stock after partial reroute.

**Why it matters:** Carrier sandbox (6C-1) will attach to origin-aware shipments; weak routing edge coverage increases risk of wrong-origin labels/rates later.

**Recommended action:** Add negative/routing matrix tests before or alongside 6C-1; do not implement geocoding (deferred).

**Files to inspect/fix:**
- `app/Services/Fulfillment/FulfillmentOriginRouter.php`
- `app/Services/Shipping/CheckoutShippingService.php`
- `tests/Feature/Phase6NearestEligibleOriginRoutingTest.php`

**Blocker before Phase 6C-1?** **Yes (conditional)** — add minimum routing hardening tests in Step 3.

---

### Finding QA-008 — `payment_provider_accounts` lacks uniqueness on store + provider + mode + connection

**Severity:** Medium  
**Area:** Migrations / Stripe Connect  
**Status:** Likely gap  
**Evidence:**
- `2026_05_12_020000_create_platform_checkout_and_payment_tables.php` — indexes on `(store_id, provider, mode)` but no unique constraint.
- `StripeConnectService::createOrRetrieveConnectedAccount()` — application-level “latest active connect account” lookup; race could create duplicate pending rows if two onboarding starts overlap.

**Why it matters:** Duplicate Connect rows could confuse merchant UI or mode selection; unlikely but messy at scale.

**Recommended action:** Add partial unique index (store, provider, mode, connection_type) where status != disabled, or enforce in transaction with lock.

**Blocker before Phase 6C-1?** **No**

---

### Finding QA-009 — External fulfillment snapshot does not update order `fulfillment_status`

**Severity:** Medium  
**Area:** Order lifecycle / external sync  
**Status:** Confirmed gap (documented in code)  
**Evidence:**
- `ExternalOrderSyncService::mapFulfillmentStatus()` — returns `FULFILLMENT_UNFULFILLED` always; comment states external snapshot is informational until item-level shipment sync.
- External payload accepts `fulfillment.status` values including `delivered`, `fulfilled` — stored in meta/events but not order-level status.

**Why it matters:** Merchants syncing shipped/delivered external orders see unfulfilled dashboard status until manual shipment or future sync — potentially confusing, not a security bug.

**Recommended action:** Document merchant behavior clearly; map safe external statuses in a later phase when item-level sync exists.

**Blocker before Phase 6C-1?** **No**

---

### Finding QA-010 — No Laravel Policy classes; authorization is middleware-only

**Severity:** Low  
**Area:** Permissions architecture  
**Status:** Confirmed gap (auditability)  
**Evidence:**
- No `app/Policies/` directory; `AppServiceProvider` only registers rate limiters.
- Authorization via `EnsureStorePermission`, `User::hasStorePermission()`, controller `abort_unless` checks.

**Why it matters:** External reviewers must grep routes/controllers instead of central policies; increases miss risk on new routes.

**Recommended action:** Long-term: introduce policies or a single authorization map document; short-term: Step 3 route audit checklist.

**Blocker before Phase 6C-1?** **No**

---

### Finding QA-011 — Inventory mutations generally flow through services with transactions and locks

**Severity:** False positive / already covered  
**Area:** Inventory / concurrency  
**Status:** Already covered  
**Evidence:**
- `InventoryAdjustmentService::setAvailable()` — `DB::transaction` + `lockForUpdate`.
- `InventoryReservationService` — transactional reserve/commit/release/deduct.
- `CheckoutShippingService::selectShippingMethod()` — `retargetReservations()` inside transaction.
- `ProductBulkController` — uses `InventoryAdjustmentService` inside `DB::transaction`.
- `OnboardingController` / product edit — `StockMovementRecorder::syncAfterVariantRebuild()` delegates to adjustment service when inventory tables exist.

**Why it matters:** Prompt asked about writes outside services — primary paths are sound; residual risk is future controller bypass, not current hot path.

**Blocker before Phase 6C-1?** **No**

---

### Finding QA-012 — Cross-store access blocked on core commerce paths

**Severity:** False positive / already covered  
**Area:** Multi-store tenancy  
**Status:** Already covered  
**Evidence:**
- `PlatformCheckoutController::show()` — aborts unless checkout `store_id` matches token store.
- `DashboardController::orderViewDetails()` — `abort(404)` on store mismatch.
- `ShipmentController::store()` / `authorizeShipment()` — store_id match required.
- `ExternalOrderSyncService::prepareItems()` — rejects variants not belonging to store.
- Tests: `Phase5PlatformCheckoutStripeTest::test_platform_checkout_rejects_cross_store_variant`, `ProductCurrentStoreTest`, `CurrentStoreTest`.

**Blocker before Phase 6C-1?** **No**

---

### Finding QA-013 — External checkout does not create Stripe PaymentIntents; raw card rejected

**Severity:** False positive / already covered  
**Area:** Payments / external sync  
**Status:** Already covered  
**Evidence:**
- `ExternalOrderSyncController` — rejects raw card fields; records payment status from payload only.
- `ExternalOrderSyncService` — no payment intent creation.
- `Phase5ExternalCheckoutSyncTest::test_external_order_rejects_raw_card_data_and_cross_store_variant`

**Blocker before Phase 6C-1?** **No**

---

### Finding QA-014 — Patch B: merchant payment UI does not expose secret key inputs

**Severity:** False positive / already covered  
**Area:** Stripe Connect UX  
**Status:** Already covered  
**Evidence:**
- `PaymentSettingsController` — hosted Connect onboarding routes only.
- `Phase5PaymentUxCleanupTest`, `StripeSandboxConnectSupportTest` — no-key merchant copy; developer diagnostics local/testing only.

**Blocker before Phase 6C-1?** **No**

---

### Finding QA-015 — All 51 migrations define `down()` rollback methods

**Severity:** False positive / already covered  
**Area:** Migrations  
**Status:** Already covered  
**Evidence:** Step 1 scan: 51 migration files, 51 with `public function down()`; `ENTERPRISE_QA_COMMAND_OUTPUTS.md` migrate:status all Ran.

**Blocker before Phase 6C-1?** **No**

---

### Finding QA-016 — `QA_REMEDIATION_REPORT.md` missing from repo

**Severity:** Low  
**Area:** Documentation  
**Status:** Confirmed gap  
**Evidence:** Only `docs/QA_REMEDIATION_REPORT.docx` exists; Step 1 bundle could not include `.md` version.

**Recommended action:** Export remediation report to markdown for reviewer tooling or attach docx to review package.

**Blocker before Phase 6C-1?** **No**

---

### Finding QA-017 — Phase 1 security tests exist but `--filter=Phase1` matches nothing

**Severity:** Low  
**Area:** Test coverage quality  
**Status:** Confirmed gap (discoverability)  
**Evidence:** `ENTERPRISE_QA_COMMAND_OUTPUTS.md` — `php artisan test --filter=Phase1` → “No tests found”; coverage lives in `StorePermissionLayerTest`, `SecurityLogAndSessionTest`, `RegistrationTest`, etc.

**Recommended action:** Rename or tag Phase 1 tests; document filter names in CI docs.

**Blocker before Phase 6C-1?** **No**

---

### Finding QA-018 — Pickup / routing UI does not claim geospatial “nearest”

**Severity:** Deferred feature, not a bug  
**Area:** Origin routing / UX truthfulness  
**Status:** Already covered  
**Evidence:**
- `FulfillmentOriginRouter` — service area + stock priority (`nearest_eligible_0a` strategy label, not distance).
- Phase 6C-0A report and roadmap defer lat/lng/geocoding.
- No merchant Blade copy found claiming GPS-based nearest store.

**Blocker before Phase 6C-1?** **No**

---

### Finding QA-019 — Staff payment/shipping settings visibility vs mutation

**Severity:** Needs manual browser verification  
**Area:** UI authorization  
**Status:** Needs reviewer verification  
**Evidence:**
- Staff has `settings.view`, not `settings.manage`.
- `payment_settings.blade.php` uses `@if($canManagePayments)` for Connect buttons.
- Server-side POST routes enforce `settings.manage` (confirmed in tests for external-inventory POST).

**Recommended action:** Browser-verify staff sees read-only payment/shipping pages with no CSRF-exploitable hidden forms.

**Blocker before Phase 6C-1?** **No**

---

### Finding QA-020 — Live Stripe Connect with real `STRIPE_LIVE_*` keys not CI-verified

**Severity:** Needs manual browser verification  
**Area:** Stripe Connect / Patch B  
**Status:** Needs reviewer verification  
**Evidence:**
- Tests mock Stripe / use test mode (`StripeSandboxConnectSupportTest`, `Phase5StripeConnectFoundationTest`).
- `.env` not in repo; live placeholders may remain `REPLACE_ME` locally.

**Recommended action:** Manual live Connect smoke test in staging with real keys before production beta.

**Blocker before Phase 6C-1?** **No** — carrier sandbox is test-mode friendly.

---

## False positives / already covered

| Topic | Verdict | Primary evidence |
|-------|---------|------------------|
| Duplicate platform checkout orders on webhook replay | Covered | `CheckoutConversionService`, `Phase5PlatformCheckoutStripeTest` |
| Cross-store checkout / external sync | Covered | Controllers + Phase 5/6 tests |
| Raw card data on APIs | Covered | Platform + external controllers |
| Inventory service boundaries | Covered | Adjustment/reservation services |
| Migration rollbacks | Covered | 51/51 `down()` methods |
| 6C-0A claiming GPS nearest | Not claimed | Router + docs |

---

## Deferred features that are not bugs

These are **out of scope** for current QA failure unless UI falsely claims they exist:

- True physical nearest origin (lat/lng / geocoding)
- Phase 6C-1 carrier sandbox abstraction, live carrier rates, label purchase
- Carrier pickup scheduling, tracking sync background jobs
- Multi-origin split fulfillment, pickup time slots, warehouse cutoffs
- Advanced tax engine, refunds/returns flows
- SaaS subscription billing monetization
- Production public API keys, scopes, merchant webhook management
- Full production storefront product (dev-test-storefront is simulator only)

---

## Manual browser verification checklist

- [ ] Staff user: open Orders, Products, Payment settings — confirm read-only where expected; confirm POST actions return 403
- [ ] Manager user: attempt location/shipping/payment mutation — confirm expected allow/deny matches product intent
- [ ] Owner: Stripe test Connect onboarding end-to-end; repeat for live mode in staging with real keys
- [ ] Platform checkout in dev storefront: delivery method change updates total and payment intent amount
- [ ] Order detail: create manual shipment; verify origin prefilled from routing snapshot
- [ ] External managed channel mode: toggle inventory owner; confirm UI copy matches behavior
- [ ] Product workspace: edit stock; confirm inventory level + stock movement appear
- [ ] Cross-store: switch store; confirm prior store’s order/product URLs return 404

---

## Recommended Step 3 plan

1. **External sync hardening (priority)** — Require stable external identity or idempotency; add duplicate-without-key test; document integrator contract.
2. **Routing test matrix** — Tie scores, out-of-stock routing failure, invalid cross-store pickup ID, reservation retarget under shipping change (extend `Phase6NearestEligibleOriginRoutingTest`).
3. **Webhook audit cleanup** — Optional Stripe event-id store or skip redundant attempts; assert replay does not inflate audit rows.
4. **Read-route permissions** — Add `orders.view` / `catalog.view` middleware to GET routes; staff denial tests.
5. **Schema guard** — Evaluate unique index on `payment_provider_accounts` for connect rows per mode.
6. **Documentation** — Export `QA_REMEDIATION_REPORT.md`; document manager vs owner settings authority.
7. **Then Phase 6C-1** — Carrier sandbox on top of stabilized order/origin/shipment lifecycle.

---

*No application code, migrations, or tests were modified to produce this report.*
