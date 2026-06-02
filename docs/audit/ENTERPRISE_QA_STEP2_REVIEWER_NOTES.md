# Step 2 Reviewer Notes

Concise briefing for external QA review before Phase 6C-1 (carrier sandbox).  
Sources: Step 1 audit bundle, Step 2 gap analysis, live source inspection.  
Branch: `main` · Commit: `54a2f8d` · Tests: **426 passed**

---

## Top 10 risks

1. **External order duplication** when integrators omit both `Idempotency-Key` and `external_order_number` — can double-create orders and deduct inventory (`ExternalOrderSyncService`, migration unique index allows multiple NULLs).
2. **Thin Phase 6C-0A routing tests** (5 tests) relative to fulfillment/carrier dependency on correct origin snapshots.
3. **`DashboardController` monolith** (~1,219 lines) — catalog, orders, customers, settings, profile — high regression surface before carrier UI work.
4. **GET routes without explicit view permissions** — `/orders`, `/products` rely on store membership, not `orders.view`/`catalog.view` middleware.
5. **Stripe webhook replay audit noise** — orders stay idempotent, but payment attempt rows may duplicate without event-id dedup table.
6. **Manager role blocked from `settings.manage`** — may be intentional (owner-only payments/shipping/locations) but needs product confirmation.
7. **Live Stripe Connect** — Patch B test/live separation tested in sandbox/mocks; real live keys need staging verification.
8. **External fulfillment status** stored in meta/events but order `fulfillment_status` stays unfulfilled until manual/platform shipment sync.
9. **`payment_provider_accounts` lacks DB uniqueness** per store/mode/connect — application retrieval only.
10. **No Laravel Policy layer** — authorization spread across middleware + controller checks; harder to audit new routes.

---

## Must-fix before Phase 6C-1

| Item | Why | Finding |
|------|-----|---------|
| External sync dedup contract | Carrier phase assumes one order per external checkout | QA-001 |
| Minimum routing hardening tests | Label/rate work attaches to origin snapshots | QA-007 |
| Document integrator requirement for idempotency | Prevent partner double-post inventory corruption | QA-001 |

**Not blocking 6C-1 but strongly recommended in Step 3:** webhook attempt dedup (QA-002), read-route permission middleware (QA-004).

---

## Should-fix before beta/live

- Stripe live Connect staging smoke with real `STRIPE_LIVE_*` keys (QA-020)
- Read permission middleware on merchant GET routes (QA-004)
- Manager vs owner settings authority documented and tested (QA-005)
- Browser verification of staff read-only payment/shipping UI (QA-019)
- Export `QA_REMEDIATION_REPORT.md` for reviewer tooling (QA-016)
- Consider `payment_provider_accounts` unique guard (QA-008)
- Webhook event-id or attempt dedup before production traffic (QA-002)

---

## Can defer

- Physical nearest origin (lat/lng/geocoding) — correctly deferred in 6C-0A
- Carrier sandbox, live rates, labels, tracking jobs — Phase 6C-1+ roadmap
- Multi-origin split fulfillment, pickup slots, warehouse cutoffs
- Tax engine, refunds/returns, SaaS billing
- Public API keys/scopes/webhook management product
- `DashboardController` refactor — report only until post-6C-1 window
- Laravel Policies introduction — architectural improvement, not immediate blocker

---

## Files most important for external reviewer

| Priority | File | Why |
|----------|------|-----|
| 1 | `app/Services/CheckoutConversionService.php` | Paid checkout → order; idempotency behavior |
| 2 | `app/Services/ExternalOrderSyncService.php` | External order dedup + inventory owner logic |
| 3 | `app/Http/Controllers/Api/ExternalOrderSyncController.php` | Idempotency-Key handling |
| 4 | `app/Services/Fulfillment/FulfillmentOriginRouter.php` | 6C-0A routing rules |
| 5 | `app/Services/Shipping/CheckoutShippingService.php` | Delivery selection + reservation retarget |
| 6 | `app/Services/Inventory/InventoryReservationService.php` | Stock locks and deduction |
| 7 | `app/Http/Controllers/DashboardController.php` | Largest merchant surface |
| 8 | `app/Support/StorePermission.php` | Role → permission matrix |
| 9 | `routes/web.php` + `routes/api.php` | Middleware map |
| 10 | `tests/Feature/Phase5PlatformCheckoutStripeTest.php` | Payment + webhook idempotency test |
| 11 | `tests/Feature/Phase5ExternalCheckoutSyncTest.php` | External sync safety |
| 12 | `tests/Feature/Phase6NearestEligibleOriginRoutingTest.php` | Routing coverage baseline |
| 13 | `database/migrations/2026_05_12_010000_add_external_checkout_sync_fields_to_orders_table.php` | External order uniqueness |
| 14 | `ENTERPRISE_QA_AUDIT_BUNDLE.md` | Full export |
| 15 | `ENTERPRISE_QA_GAP_REPORT.md` | Detailed findings |

---

## Exact questions for external reviewer

1. **External sync:** Is requiring `external_order_number` or `Idempotency-Key` acceptable for all integrators, or must the platform dedupe anonymous retries?
2. **Webhook idempotency:** Is checkout-state-based idempotency (`converted_order_id`) sufficient for current stage, or is Stripe `event.id` persistence mandatory before beta?
3. **RBAC model:** Should **managers** configure shipping zones, locations, and Stripe Connect, or is **owner-only** settings intentional?
4. **Read routes:** Should `GET /orders` and `GET /products` enforce `orders.view` / `catalog.view` even when all current roles include those permissions?
5. **6C-0A routing:** Are five routing tests adequate to proceed to carrier sandbox, or should tie-breaker/out-of-stock/concurrency cases block 6C-1?
6. **External fulfillment:** Is it acceptable that external `fulfillment.status` does not update order `fulfillment_status` until a future item-level sync phase?
7. **DashboardController:** Should monolith split be scheduled before carrier UI, or is test-gated incremental change acceptable?
8. **Live Stripe:** What is the minimum staging evidence required to sign off Patch B live Connect (real keys, real Connect account, webhook endpoints)?
9. **NULL unique index:** Confirm MySQL behavior on `orders_external_checkout_order_unique` with NULL `external_order_number` matches our duplicate-order concern.
10. **Phase 6C-1 scope:** Should carrier sandbox implementation depend on fixing QA-001 first, or can it proceed in parallel with integrator documentation only?

---

## Verdict snapshot

| Question | Answer |
|----------|--------|
| Proceed to Phase 6C-1 now? | **Yes with conditions** (QA-001 + QA-007 addressed in Step 3 or parallel hardening sprint) |
| Critical production blockers found? | **None confirmed** |
| Test suite trust level | **High** for completed phases (426 tests); gaps in naming, routing depth, anonymous external dedup |
| Code modified in Step 2? | **No** |

---

*Companion documents: `ENTERPRISE_QA_GAP_REPORT.md`, `ENTERPRISE_QA_RISK_REGISTER.md`, Step 1 bundle/index/command outputs.*
