# Enterprise QA Current State Index

Read-only audit snapshot for external review before Phase 6C-1 (carrier sandbox).  
Generated: 2026-05-24 · Branch: `main` · Commit: `54a2f8d1ccfc53700512f7e6a0d5ac6541bda4b7`

Companion files:
- `ENTERPRISE_QA_AUDIT_BUNDLE.md` — full source/doc export (~3.1 MB)
- `ENTERPRISE_QA_COMMAND_OUTPUTS.md` — safe command outputs

---

## 1. Environment Summary

| Item | Value |
|------|-------|
| Laravel version | 12.53.0 |
| PHP requirement (composer.json) | `^8.2` |
| PHP runtime (artisan about) | 8.2.12 |
| Database driver (config / artisan about) | MySQL |
| Test framework | PHPUnit 11.x (`phpunit.xml`) |
| Frontend build | Vite 7 + Tailwind CSS 4 (`npm run build` → `public/build/`) |
| Dev storefront | React 19 + Vite 6 in `dev-test-storefront/` (separate `npm run build` → `dist/`) |
| Cache driver (config default) | database |
| Queue driver (config default) | database |
| Session driver (config default) | database |
| Git branch | `main` |
| Latest commit | `54a2f8d` — phase 6C-0A implemented correctly for shipment and store location awarness in the platform |
| Migrations applied | 51 migrations, all `Ran` (see command outputs) |
| Test files | 54 Feature + 9 Unit |
| `.env` in repo | No (gitignored; `.env.example` included in bundle) |

---

## 2. Completed Phase Map

| Phase / patch | Status | Evidence (tests / docs) |
|---------------|--------|-------------------------|
| **Phase 1 — SaaS foundation** (auth, permissions, security) | **Implemented** | `StorePermissionLayerTest`, `SecurityLogAndSessionTest`, `RegistrationTest`, `ProfileHardeningTest`, `TeamMemberFlowTest`, `StoreRoleAuthorizationTest`, `CurrentStoreTest`; `docs/phases/PHASE_1_SAAS_FOUNDATION_HARDENING_REPORT.md` |
| **Order lifecycle hardening** (separate order/payment/fulfillment statuses) | **Implemented** | `OrderLifecycleTest`, `OrderEventsTimelineTest`; `docs/ORDER_LIFECYCLE_HARDENING_REPORT.md` |
| **Phase 2 — Catalog** (products, variants, attributes, import) | **Implemented** | `Phase2CatalogCompletionTest`, `Phase2CatalogCleanupTest`, `VariantSystemUpgradeTest`, import test suite; `docs/phases/PHASE_2_CATALOG_COMPLETION_REPORT.md` |
| **Phase 3 — Inventory** (locations, levels, reservations, stock movements) | **Implemented** | `Phase3EnterpriseInventoryTest`, `StockMovementTest`, `Inventory`-filter tests (24); `docs/phases/PHASE_3_ENTERPRISE_INVENTORY_REPORT.md` |
| **Phase 3.5 — Onboarding / default location alignment** | **Implemented** | `Phase35StoreSettingsAlignmentTest`; covered in Phase 3 report + location services |
| **Phase 4 — Commerce core** (orders, draft/manual orders, CRM) | **Implemented** | `Phase4CommerceCoreRegressionTest`, `Phase4DraftOrderTest`, `Phase4CustomerCrmTest`, `Phase4OrderDetailTest`; `docs/phases/PHASE_4_COMMERCE_CORE_COMPLETION_REPORT.md` |
| **Phase 5 — Payments** (platform checkout, Stripe sandbox) | **Implemented** | `Phase5PlatformCheckoutStripeTest`, `Phase5PaymentUxCleanupTest`; `docs/phases/PHASE_5_PLATFORM_CHECKOUT_STRIPE_SANDBOX_REPORT.md` |
| **Phase 5 — External checkout sync** | **Implemented** | `Phase5ExternalCheckoutSyncTest`, `ExternalManagedChannelModeTest`; `docs/phases/PHASE_5_EXTERNAL_CHECKOUT_SYNC_REPORT.md` |
| **Phase 5 — Stripe Connect foundation** | **Implemented** | `Phase5StripeConnectFoundationTest`, `PaymentProviderAccountModeTest`; `docs/phases/PHASE_5_STRIPE_CONNECT_FOUNDATION_REPORT.md` |
| **Patch A — External managed channel mode** | **Implemented** | `ExternalManagedChannelModeTest`; `docs/EXTERNAL_MANAGED_CHANNEL_MODE_REPORT.md` |
| **Patch B — Stripe test/live Connect support** | **Implemented** | `StripeSandboxConnectSupportTest` (17 tests); `docs/STRIPE_SANDBOX_CONNECT_SUPPORT_REPORT.md` |
| **Phase 6A — Manual fulfillment / shipments** | **Implemented** | `Phase6ManualFulfillmentTest`, `ExternalShipmentSyncTest`; `docs/phases/PHASE_6A_MANUAL_FULFILLMENT_REPORT.md` |
| **Phase 6B — Checkout delivery methods** | **Implemented** | `Phase6CheckoutDeliveryMethodsTest`; `docs/phases/PHASE_6B_CHECKOUT_DELIVERY_METHODS_REPORT.md` |
| **Phase 6C-0A — Nearest eligible origin routing / pickup foundation** | **Implemented** | `Phase6NearestEligibleOriginRoutingTest` (5 tests); `docs/phases/PHASE_6C_0A_NEAREST_ELIGIBLE_ORIGIN_ROUTING_REPORT.md` |
| **Phase 6C-1 — Carrier sandbox** | **Not implemented** | Deferred (pause point before this work) |

**Note:** `--filter=Phase1` returns no tests; Phase 1 coverage uses security/store-permission test names instead of a `Phase1*` prefix.

---

## 3. High-Risk System Map

| Area | Current implementation | Store-scoped / secured |
|------|------------------------|-------------------------|
| **Multi-store tenancy** | `EnsureCurrentStore` middleware; `store_id` on tenant entities; store switcher in dashboard | Yes — cross-store access returns 404/deny |
| **Roles / permissions** | `StorePermission` enum + `EnsureStorePermission` middleware; pivot `store_user`; no Laravel Policy classes | Yes — route-level permission gates |
| **Catalog** | Products/variants in normalized tables; workspace controllers; import pipeline | Yes |
| **Inventory** | `InventoryItem`, `InventoryLevel`, reservations, `StockMovementRecorder` | Yes |
| **Reservations** | `InventoryReservationService` with `DB::transaction` + `lockForUpdate` | Yes |
| **Stock movements** | Audited via `stock_movements`; adjustment/reservation services record movements | Yes |
| **Checkout** | `CheckoutService`, platform API (`PlatformCheckoutController`), events | Yes — dev token resolves store |
| **Payment intents** | `payment_intents` table; Stripe platform provider | Yes |
| **Stripe Connect** | Mode-specific accounts (`PaymentProviderAccount`); hosted onboarding UI | Yes — per store |
| **External checkout sync** | `ExternalOrderSyncService` + idempotency keys | Yes — token-scoped API |
| **Orders** | Separate `order_status`, `payment_status`, `fulfillment_status` | Yes |
| **Draft / manual orders** | `DraftOrderService`, `ManualOrderConversionService` | Yes |
| **Customers** | CRM tables, notes, tags, addresses | Yes |
| **Shipments** | `ShipmentService`, manual fulfillment tables | Yes |
| **Fulfillment statuses** | `FulfillmentStatusService`; transitions on shipment events | Yes |
| **Delivery methods** | `DeliveryOptionService`, `CheckoutShippingService`, shipping methods/zones | Yes |
| **Shipping zones** | `ShippingZoneMatcher`, merchant settings UI | Yes |
| **Origin routing (6C-0A)** | `FulfillmentOriginRouter`, `LocationServiceAreaMatcher`; routing snapshots on checkout | Yes — postal/service-area based, not lat/lng |
| **Pickup location selection** | Checkout shipping selection + pickup location id | Partial — foundation only |
| **Webhooks / idempotency** | Stripe webhooks (signature verify); external sync uses `Idempotency-Key` header + `idempotency_keys` table | Partial — Stripe webhook replay dedup relies on conversion service state |
| **Audit / event timeline** | `order_events`, `checkout_events`, `security_logs`, `user_sessions` | Yes |

**Architectural note:** Catalog and much merchant UI still flow through `DashboardController` (~1,400 lines) rather than dedicated `ProductController` / `InventoryController` (those classes do not exist). Settings live on `DashboardController`, `OnboardingController`, and dedicated settings controllers — not `GeneralSettingsController`.

---

## 4. Known Deferred Items

Explicitly out of scope for completed phases (per roadmap / 6C-0A design):

- True physical nearest-origin by lat/lng / geocoding
- Carrier sandbox abstraction (Phase 6C-1 — next)
- Live carrier rates
- Label purchase
- Carrier pickup scheduling
- Tracking sync background jobs (beyond manual/external sync foundation)
- Multi-origin split fulfillment
- Pickup time slots
- Warehouse cutoff times
- Advanced tax engine
- Full subscription / SaaS billing monetization
- Production-grade public API keys, scopes, merchant webhook management (later integrations phase)

---

## 5. Audit Bundle Notes

### Included in `ENTERPRISE_QA_AUDIT_BUNDLE.md`
- Enterprise docs/reports (18 phase reports + context/roadmap)
- Routes, middleware, models, controllers, services, migrations, tests, views, config
- `# Schema Summary` for core commerce tables
- `# Search Evidence` across app/routes/views/migrations/tests/config/dev storefront

### Marked `MISSING FILE` (genuinely absent from repo)
| Expected path | Actual substitute / note |
|---------------|--------------------------|
| `routes/channels.php` | Not present (Laravel 12 default may omit) |
| `app/Providers/AuthServiceProvider.php` | Not present; only `AppServiceProvider.php` |
| `app/Http/Controllers/GeneralSettingsController.php` | Settings via `DashboardController::generalSettings`, `OnboardingController` |
| `app/Http/Controllers/ProductController.php` | Catalog via `DashboardController`, `ProductWorkspaceController`, bulk controllers |
| `app/Http/Controllers/InventoryController.php` | Inventory via services + product workspace (no dedicated controller) |

### Docs requested but not in bundle as `.md`
| File | Status |
|------|--------|
| `QA_REMEDIATION_REPORT.md` | **Missing** — only `docs/QA_REMEDIATION_REPORT.docx` exists |
| `app/Policies/*` | **No policy directory** — authorization is middleware-based |

---

## 6. Quick Self-Audit (initial pass — no fixes applied)

### 1. Are all completed phases represented by tests?
**Mostly yes**, with gaps:
- Phase 1 has strong coverage but **no `Phase1*` test prefix** (`--filter=Phase1` → 0 tests).
- Phase 6C-0A routing has only **5 dedicated tests** (`Phase6NearestEligibleOriginRoutingTest`).
- Catalog Day 17–18 polish tests exist but are not phase-numbered.

### 2. Which phases have the weakest test coverage?
- **Phase 1** (naming/discovery gap; no consolidated phase test class).
- **Phase 6C-0A routing** (5 tests — happy path + edge cases, limited geographic scenarios).
- **Onboarding UX** (partially covered via Phase 3.5; no broad UI sign-off suite).
- **Stripe live Connect with real keys** (tests use mocks/sandbox; live env not CI-verified).

### 3. Which files look highest risk?
- `app/Http/Controllers/DashboardController.php` — large surface area (catalog, orders, settings, profile).
- `app/Services/CheckoutConversionService.php` — payment success → order conversion.
- `app/Services/ExternalOrderSyncService.php` — external order ingestion.
- `app/Http/Controllers/Api/StripeWebhookController.php` — unauthenticated webhook entry (signature-verified).
- `app/Services/Payments/StripeConnectService.php` — Connect onboarding state.
- `app/Services/Fulfillment/FulfillmentOriginRouter.php` — routing correctness.

### 4. Are there any migrations without rollback?
**No.** All **51** migration files define a `down()` method (verified by scan).

### 5. Are there any routes without obvious authorization?
**Review flags (may be intentional):**
- **Guest routes:** `/signin`, `/register` (expected).
- **Stripe webhooks:** `/api/webhooks/stripe/*` — no auth middleware; relies on Stripe signature verification.
- **Developer storefront API:** token middleware (`dev.storefront.token`), not user session — correct for external channel.
- **Profile routes** (`/profileSettings`) inside auth group but **without** `store.permission:*` — user-scoped, likely intentional.
- **Read-only catalog/order list routes** may rely on group middleware only (`auth`, `current.store`) without fine-grained permission on GET — verify staff role expectations.

### 6. Are there any services writing cross-store data?
**No obvious intentional cross-store writes found** in first pass. Core services (`CheckoutService`, `ExternalOrderSyncService`, `ShipmentService`, inventory services) scope by `$store` or relationship `store_id`. Risk remains in **DashboardController** bulk actions if future edits bypass scoping — current tests include `ProductCurrentStoreTest`, `CurrentStoreTest`.

### 7. Are there any money/payment flows without idempotency?
- **External order/shipment sync:** supports `Idempotency-Key` header + DB table.
- **Platform checkout / Stripe webhooks:** conversion handled in `CheckoutConversionService`; webhook handler does not show explicit Stripe event-id dedup table — **potential replay risk** if conversion is not internally idempotent (needs reviewer verification).
- **Manual order conversion / draft conversion:** transactional but no HTTP idempotency key (merchant UI only).

### 8. Are there any inventory updates outside transaction boundaries?
**Primary paths are transactional** (`InventoryAdjustmentService`, `InventoryReservationService` use `DB::transaction` + `lockForUpdate`). Variant cache sync after movement is inside the same transaction in adjustment service. **Residual risk:** any direct model updates in controllers/blades should be grep-verified; bundle search evidence includes reservation/stock patterns.

### 9. Are there any external sync flows that can duplicate orders?
**Mitigated but not zero-risk:**
- Optional idempotency key prevents duplicate API submissions.
- Without idempotency key, duplicate POSTs could create duplicate orders unless other unique constraints exist (reviewer should verify `external_order_id` uniqueness per store).
- `ExternalManagedChannelModeTest` covers channel ownership rules.

### 10. Are there any UI controls visible without authorization?
**Possible gaps:**
- Blade views may show navigation links before server-side denial on POST (middleware enforces on mutation routes).
- Staff with `settings.view` but not `settings.manage` may see payment/shipping settings pages read-only — verify Blade `@if` guards match middleware.
- Developer diagnostics (Stripe env yes/no) restricted to local/testing in Patch B cleanup — confirm not visible in production config.

---

## 7. Command / Test Summary (see full output file)

| Command | Result |
|---------|--------|
| `composer validate` | Valid |
| Full test suite | **426 passed** (2077 assertions) |
| `npm run build` | Success (~1s) |
| `dev-test-storefront npm run build` | Success (~1.2s) |
| `--filter=Phase1` | **No tests found** (use security/store test names) |
| `--filter=Phase2` … `Phase6` | 18 / 14 / 20 / 40 / 19 passed respectively |
| Domain filters (Inventory, Checkout, Payment, External, Fulfillment, Shipment, Routing) | All passed (see `ENTERPRISE_QA_COMMAND_OUTPUTS.md`) |

**Commands failed:** None during audit run.

---

*This index is a first-pass self-audit only. No source code, migrations, or tests were modified during Phase Q Step 1.*
