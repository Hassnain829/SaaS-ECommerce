# Enterprise QA Risk Register

Read-only Step 2 analysis · Branch `main` · Commit `54a2f8d` · Generated 2026-05-24

| ID | Area | Risk | Severity | Probability | Impact | Current mitigation | Gap | Recommended action | Phase |
|----|------|------|----------|-------------|--------|--------------------|-----|--------------------|-------|
| R-001 | multi-store tenancy | Cross-store order/product access via ID guessing | High | Low | Critical | `EnsureCurrentStore`; controller store_id checks; 404 on mismatch | GET routes rely on implicit membership not explicit view permissions | Add read permission middleware; cross-store negative tests | Pre-6C-1 |
| R-002 | permissions | Staff/manager access broader than intended on read routes | Medium | Medium | Medium | Mutation routes use `store.permission:*`; Blade hides controls | `/orders`, `/products` GET lack `orders.view`/`catalog.view` enforcement | Route middleware + role tests | Pre-beta |
| R-003 | permissions | Manager cannot configure shipping/payments/locations | Medium | Medium | Medium | Owner has full `StorePermission::ALL` | Manager has `settings.view` only — may block operational workflows | Product decision + tests | Ongoing |
| R-004 | catalog | Regression in product list/workspace via monolith controller | Medium | Medium | Medium | `ProductCurrentStoreTest`, catalog phase tests | `DashboardController` ~1,219 lines, many domains | Targeted tests before edits; split later | 6C-1+ |
| R-005 | inventory | Double stock deduction on duplicate external orders | High | Medium | Critical | Reservation service uses transactions/locks | No dedup when `external_order_number` null and no Idempotency-Key | Require identity key or DB dedup fallback | **Step 3** |
| R-006 | inventory | Race on concurrent checkout reservations | Medium | Low | High | `lockForUpdate` in reservation/adjustment services | Limited concurrency tests | Add parallel checkout test | Step 3 |
| R-007 | checkout | Wrong totals after shipping method change | Medium | Low | High | `CheckoutShippingService` recalculates + payment intent refresh | Complex flow — partial manual coverage | Extend Phase 6B/6C checkout tests | 6C-1 |
| R-008 | payment | Duplicate paid orders from Stripe webhook replay | Medium | Low | Critical | `converted_order_id` guard + test | No Stripe `event.id` table; extra attempt rows on replay | Event-id dedup or skip redundant attempts | Step 3 |
| R-009 | payment | Test/live Stripe mode crossover | High | Low | Critical | `StripeConfig`, mode params on webhooks/checkout, Patch B tests | Live keys not CI-tested with real Stripe | Staging smoke with real live keys | Pre-live |
| R-010 | webhooks | Forged webhook if secret misconfigured | High | Low | Critical | Signature verification in `StripeWebhookController` | Misconfiguration in production env | Release checklist for webhook secrets per mode | Pre-live |
| R-011 | Stripe Connect | Duplicate Connect account rows per store/mode | Medium | Low | Medium | `StripeConnectService` retrieves latest connect account | No DB unique on connect rows | Partial unique index or transactional guard | Step 3 |
| R-012 | external sync | Integrator retries create duplicate orders | High | Medium | High | Idempotency-Key + external_order_number dedup when provided | NULL external_order_number bypasses unique index | Mandate keys in API contract | **Step 3** |
| R-013 | external sync | External channel deducts platform stock when misconfigured | Medium | Low | High | `ChannelOwnershipService` + Patch A tests | Misconfiguration by merchant | UI clarity + test for toggle | Ongoing |
| R-014 | orders | External fulfillment status ignored at order level | Medium | Medium | Medium | Events/meta record external fulfillment | `mapFulfillmentStatus()` always unfulfilled | Document; map when item sync exists | Later |
| R-015 | draft/manual orders | Draft conversion double-submit | Medium | Low | Medium | Transactional `ManualOrderConversionService` | No HTTP idempotency (UI only) | Accept for merchant UI; optional form tokens | Low |
| R-016 | customers | CRM data visible to unauthorized roles | Low | Low | Medium | Store-scoped queries | Same GET permission gap as orders | Align with R-002 | Pre-beta |
| R-017 | shipments | Shipment against wrong store order | High | Low | Critical | `ShipmentController` store_id match | Route model binding without global scope — mitigated in controller | Keep controller checks; add test | 6C-1 |
| R-018 | shipping/delivery | Wrong origin selected for address | High | Medium | High | `FulfillmentOriginRouter` service-area + stock priority | Only 5 routing tests | Expand negative/routing matrix | **Step 3** |
| R-019 | origin routing | Merchant believes routing is GPS-based | Low | Low | Medium | Docs say postal/service-area | UX must not imply geocoding | Copy review in browser | Ongoing |
| R-020 | frontend/dev storefront | Dev simulator mistaken for production API | Low | Medium | Low | Token auth; documented as simulator | No public API key product yet | Keep dev storefront minimal | Deferred |
| R-021 | migrations | Deploy blocked by migration failure | Low | Low | Critical | 51 migrations with `down()`; all Ran locally | Production drift unknown | Staging migrate dry-run | Pre-deploy |
| R-022 | performance/N+1 | Order detail page slow on large orders | Medium | Medium | Medium | Eager loading in `orderViewDetails` | Not load-tested | Profile order detail with many items/events | Beta |
| R-023 | large controllers | Dashboard change breaks unrelated domain | Medium | Medium | Medium | Broad feature test suite (426 tests) | Low isolation | Test-before-touch; defer refactor | 6C-1+ |
| R-024 | fulfillment | Partial shipment state incorrect | Medium | Low | Medium | `FulfillmentStatusService`, Phase 6A tests | Edge cases on multi-shipment partial | Add partial fulfillment regression tests | 6C-1 |
| R-025 | payment settings | Unauthorized Connect disconnect | High | Low | High | POST routes require `settings.manage` | UI visibility for staff | Browser verify + tests | Pre-beta |

---

## Severity / probability legend

- **Severity** (inherent risk level): Critical / High / Medium / Low  
- **Probability**: Low / Medium / High — likelihood in normal production use before mitigations  
- **Impact**: Low / Medium / High / Critical — business/consequence if realized  

---

*Derived from `ENTERPRISE_QA_GAP_REPORT.md` findings QA-001 through QA-020. No code modified.*
