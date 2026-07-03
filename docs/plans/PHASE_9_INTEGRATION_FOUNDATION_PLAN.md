# Phase 9 — Integration Foundation Plan

> **Status:** Planning / not started in code  
> **Last updated:** 2026-07-04  
> **Purpose:** Canonical in-repo reference for Phase 9 goals, vision, current baseline, and implementation order. Use this with `ENTERPRISE_PROJECT_CONTEXT.md`, `ENTERPRISE_ROADMAP_2026.md`, `PROJECT_BRAIN.md`, and `PROJECT_STRUCTURE.md`.

External source document (audit basis): `PHASE_9_ENTERPRISE_INTEGRATION_ARCHITECTURE_GUIDE.md` (kept outside repo in Downloads; this file is the **project-bound** version aligned to actual code).

---

## 1. Vision and north star

Phase 9 turns the platform from a **developer-token prototype** into a **production integration operating layer**.

Merchants and connected websites must be able to:

- connect custom sites, WooCommerce, Shopify, or a future WordPress connector;
- use **scoped test/live API keys** instead of one shared store token;
- receive **reliable, signed outbound webhooks** with retry history;
- depend on **atomic idempotency** for all public writes;
- observe business changes through a **transactional outbox** (same DB commit as orders/inventory);
- trust explicit **source-of-truth rules** per domain (catalog, inventory, orders, fulfillment).

The SaaS remains the **operational commerce backend** — catalog, inventory, orders, customers, fulfillment, payments — while external sites are sales channels, not parallel databases.

### Product principles (non-negotiable)

1. **Extend existing `/api/v1` and domain services** — do not build a competing integration stack.
2. **Store-scoped everything** — never trust `store_id` from request body; resolve store from API key.
3. **Merchant-friendly Integrations UX** — plain language, not JSON/HMAC jargon in primary flows.
4. **No fake APIs** — do not expose scopes or buttons for endpoints that do not exist (returns/refunds wait for Phase 7).
5. **No secret keys in browser JS** — `dev-test-storefront` stays a simulator; production custom sites use server-side keys.
6. **Business commit + outbox in one transaction** — remote HTTP only after commit via queues.

---

## 2. What Phase 9 is not

| Not Phase 9 | Belongs to |
|-------------|------------|
| SaaS billing / subscriptions | Phase 8 |
| Markets / B2B | Phase 8 |
| Returns / refunds / exchanges APIs | Phase 7 |
| Coupons / discount rules in checkout contract | Phase 5R-2 (should stabilize before freezing checkout API) |
| Full automation builder | Phase 9E (after 9A–9D stable) |
| Production live carrier labels (DHL/UPS) | Carrier roadmap / approvals |
| Replacing `dev-test-storefront` as the final storefront product | Never — it is a test simulator |

---

## 3. Current baseline (code audit — 2026-07-04)

### Implemented (prototype level)

| Area | Location | Notes |
|------|----------|-------|
| API routes | `routes/api.php` | Legacy dev storefront + v1 catalog/external/checkout + Stripe inbound webhooks |
| Auth | `AuthenticateDeveloperStorefrontToken` | One SHA-256 hashed token per store (`baa_dev_*`); attribute `developerStorefrontStore` |
| Catalog reads | `Api\CatalogApiV1Controller` | Products, detail, categories, brands, attributes — serialization in controller |
| Legacy catalog/orders | `Api\DeveloperStorefrontCatalogController` | Simpler catalog + **separate** `placeOrder` path (not `ExternalOrderSyncService`) |
| External writes | `Api\ExternalOrderSyncController`, `ExternalShipmentSyncController` | Full sync services; controller-local idempotency |
| Platform checkout | `Api\PlatformCheckoutController` | Create, delivery, shipping, confirm — no idempotency middleware |
| Inbound Stripe | `Api\StripeWebhookController`, `StripeConnectWebhookController` | Signature verify; conversion dedup via checkout/order state |
| Idempotency table | `idempotency_keys` + `IdempotencyKey` model | External order/shipment only; non-atomic; `(store_id, key)` unique |
| Channel ownership | `Services\Channels\ChannelOwnershipService` | External vs platform checkout/inventory ownership |
| Domain services | `ExternalOrderSyncService`, `ExternalShipmentSyncService`, `CheckoutService`, `CheckoutConversionService`, `CheckoutShippingService`, `InventoryAdjustmentService`, etc. | **Primary reuse layer** |
| Merchant token UI | `Settings\DeveloperStorefrontSettingsController`, `developer_storefront.blade.php` | Permissions: `developer_api.view/manage` |
| Rate limits | `AppServiceProvider` | Per-store fixed limits (`api-dev-catalog`, `api-dev-checkout`, …) |
| Tests | ~1191 passing | External sync, checkout, Stripe, partial catalog v1, channel mode |

### Not implemented (Phase 9 target)

- `api_keys` table and scoped key lifecycle
- `AuthenticateApiKey` / `RequireApiScope` middleware
- Central `IdempotencyService` with processing/claim states
- `provider_webhook_events` (Stripe event dedup table)
- `outbox_events` + dispatcher jobs
- `webhook_subscriptions`, `webhook_deliveries`, `webhook_delivery_attempts`
- `integration_connections`, `integration_resource_links`, `integration_sync_runs`
- WooCommerce / Shopify / WordPress connector adapters
- Settings → **Integrations** hub (API keys, webhooks, connected sites)
- OpenAPI spec and production reference examples
- Read APIs: `GET /api/v1/orders`, customers, inventory, shipments, store
- Standard response envelope (`data` + `meta.request_id`) and stable error codes

---

## 4. Code Phase 9 must reuse (do not bypass)

```
routes/api.php
  → AuthenticateDeveloperStorefrontToken (legacy, then migrate)
  → Api/* controllers (harden, do not duplicate routes)
  → Services:
       ExternalOrderSyncService      ← external orders + ownership + inventory
       ExternalShipmentSyncService   ← shipment sync
       CheckoutService               ← platform checkout create
       CheckoutConversionService     ← Stripe webhook → order
       CheckoutShippingService       ← delivery options
       ChannelOwnershipService       ← source-of-truth defaults
       InventoryAdjustmentService    ← all stock changes
       ProductImportProcessor        ← connector initial import pattern
       PaymentProviderManager        ← Stripe
       SecurityLogRecorder           ← API key audit events
       OrderEventRecorder / CheckoutEventRecorder  ← merchant timeline (≠ outbox)
```

**Product write blocker:** Do **not** expose `catalog.write` until product persistence is extracted from `Store\OnboardingController` into a shared `ProductApplicationService` / `ProductPersistenceService` used by Blade and API.

**SSRF pattern to reuse:** `App\Support\Security\ServerSideImageHttpUrlValidator` for webhook endpoint validation.

---

## 5. Target architecture (summary)

```text
Connected website / connector
        |
        | Bearer API key (test/live) + scopes
        v
/api/v1 + AuthenticateApiKey + RequireApiScope + RateLimit
        |
        | RequireIdempotencyKey (mutations)
        v
API / inbound webhook controllers
        |
        v
Existing domain services (Catalog, Checkout, Orders, Inventory, Fulfillment)
        |
        | same DB transaction
        +---------------------> OutboxRecorder → outbox_events
        |                           |
        v                           | queue after commit
Business tables                   v
                         webhook deliveries / connector jobs
```

**Rules:**

- Never call remote APIs inside business transactions.
- Never use broad model observers as the primary outbox producer — emit from application services at valid commit boundaries.
- Client idempotency keys and provider webhook event IDs are **separate** systems.

---

## 6. Source-of-truth matrix (default)

| Domain | Default truth after cutover | Direction |
|--------|----------------------------|-----------|
| Products / variants | SaaS | SaaS → website |
| Taxonomy / media | SaaS | SaaS → website |
| Inventory | Configurable; usually SaaS | SaaS → website (via inventory services) |
| Orders | Checkout-owning channel at creation | Website → SaaS (immutable snapshots) |
| Customers | Order source initially; SaaS operational record | Controlled sync |
| Payment status | Checkout/payment owner | Provider → SaaS (no raw card data) |
| Fulfillment / tracking | Ownership setting | Depends on owner |
| Returns / refunds | Phase 7 | Deferred |

Formalize in `docs/architecture/CHANNEL_OWNERSHIP_MATRIX.md` (Phase 9-0 deliverable). Logic seed: `ChannelOwnershipService`.

---

## 7. Recommended scopes (when implemented)

Issue scopes **only** for endpoints that exist.

| Group | Scopes |
|-------|--------|
| Store / catalog | `store.read`, `catalog.read`, `catalog.write` (after product service extraction) |
| Inventory | `inventory.read`, `inventory.adjust` |
| Orders / checkout | `orders.read`, `orders.create`, `orders.update`, `checkouts.create`, `checkouts.read`, `checkouts.update` |
| Customers | `customers.read`, `customers.write` |
| Fulfillment | `shipments.read`, `shipments.write` |
| Integration mgmt | `webhooks.read`, `webhooks.manage` |
| **Reserved Phase 7** | `returns.*`, `refunds.*` |

Align naming with guide (granular) rather than roadmap’s coarse `orders.write` / `inventory.write` when implementing.

---

## 8. Implementation order (batches)

Do **not** implement as one giant branch.

| Batch | Focus | Gate |
|-------|-------|------|
| **9-0** | Contract freeze, characterization tests, ownership/event design docs, queue audit | No schema until tests lock current behavior |
| **9A-1** | `api_keys` schema, issuer, revoker, security logs | Raw secret never in DB/logs; multi-key per store |
| **9A-2** | `AuthenticateApiKey`, scopes, per-key rate limits, stable errors + request ID | Every v1 route has auth + scope + rate class |
| **9A-3** | API resources/DTOs, response envelope, Form Requests | Serialization out of controllers |
| **9A-4** | Read APIs (store, orders, customers, inventory, shipments), cursor/`updated_since` | OpenAPI draft |
| **9B-1** | Central atomic idempotency engine + concurrency tests | One business result under parallel duplicates |
| **9B-2** | Migrate external orders/shipments/checkout/inventory writes to middleware | Remove controller duplication |
| **9B-3** | `provider_webhook_events`; migrate Stripe dedup | Duplicate Stripe events safe 2xx |
| **9C-1** | `outbox_events` schema, catalog, sanitizer | — |
| **9C-2** | Emit from `ExternalOrderSyncService`, `CheckoutConversionService`, inventory, import, product service | Rollback removes event |
| **9C-3** | Worker, lease, retry, retention, ops visibility | — |
| **9D-1** | Webhook subscriptions + delivery history UI | — |
| **9D-2** | HMAC signing, delivery jobs, SSRF protection, retries | — |
| **9F** | Custom website reference (simulator → test API key, OpenAPI, examples) | — |
| **9G-1** | WooCommerce adapter | After 9A–9D proven |
| **9G-2** | Shopify adapter | After Woo stable |
| **9G-3** | WordPress connector contract (plugin required) | No fake “any WordPress site” promise |
| **9E** | Automation builder | After 9A–9D stable |

### Merchant UX target

**Settings → Integrations**

- Overview  
- API keys (test/live, scopes, revoke/rotate, one-time secret copy)  
- Webhooks (subscriptions, test event, delivery history)  
- Connected websites (Woo/Shopify/custom)  
- Sync activity  

Evolve from current `developer_storefront` page; keep route aliases during migration.

---

## 9. Phase 9-0 — first batch (start here)

**Goal:** Protect existing checkout, external sync, shipping, inventory, and simulator behavior before auth/schema changes.

### Deliverables

1. **`docs/architecture/API_V1_CONTRACT_BASELINE.md`** — every route, auth, throttle, service boundary, legacy vs v1.
2. **`docs/architecture/CHANNEL_OWNERSHIP_MATRIX.md`** — formal rules from `ChannelOwnershipService`.
3. **`docs/architecture/OUTBOX_EVENT_CATALOG.md`** — initial event names + payload allowlists (design only).
4. **`docs/integrations/PHASE_9_0_DEPENDENCY_AUDIT.md`** — queue worker checklist, external consumer decision, Phase 5R-2 / Phase 7 blockers.
5. **Characterization tests** under `tests/Feature/Integrations/`:
   - catalog v1 list/detail  
   - legacy `developer-storefront` catalog + orders  
   - external order/shipment (idempotency replay, conflict, external identity)  
   - platform checkout golden path  
   - Stripe webhook duplicate → single order  
   - cross-store isolation  
   - rate-limit baseline  
6. **JSON fixtures** under `tests/fixtures/api/v1/` or `tests/Support/Integrations/`.

### Explicit decisions before 9A-1

- [ ] Any real external v1 consumers today? (If no → can normalize envelope before public launch.)
- [ ] Deprecation plan for `POST /api/developer-storefront/orders` vs `POST /api/v1/external/orders`.
- [ ] Queue worker + failed jobs + scheduler confirmed for production.
- [ ] Phase 5R-2 coupon impact on checkout contract freeze documented.

### Gate

Full suite green + new contract tests green → proceed to **9A-1**.

---

## 10. Known conflicts to resolve during planning

| Topic | Notes |
|-------|-------|
| Token format | Current `baa_dev_*` → target `eco_test_*` / `eco_live_*`; legacy middleware kept during transition |
| Permission names | Code has `developer_api.*`; roadmap mentions `integrations.*` — unify when building Integrations UI |
| Scope naming | Prefer guide’s granular scopes over roadmap’s `orders.write` / `inventory.write` |
| Dual order APIs | Legacy `DeveloperStorefrontCatalogController::placeOrder` vs `ExternalOrderSyncService` — both need contract tests and deprecation path |
| Guide internal numbering | Section 6 vs 13 disagree on what **9A-3** is — follow **section 6** (API refactor before Integrations UI) |
| Returns scopes in roadmap 9A | Defer until Phase 7 — guide is correct |
| Webhook table names | Guide: `webhook_subscriptions` + `webhook_delivery_attempts`; align migrations to guide |

---

## 11. Risks and blockers

| Risk | Mitigation |
|------|------------|
| Product save still in `OnboardingController` | Extract product persistence service before `catalog.write` |
| Controller idempotency races | Central atomic idempotency in 9B-1; concurrency tests mandatory |
| Coupons not finalized (5R-2) | Document checkout contract impact in 9-0; avoid freezing discount shapes prematurely |
| Legacy token removal too early | Dual auth period; migrate `dev-test-storefront` to test API key in 9F |
| Uncontrolled two-way sync | Ownership matrix + connector conflict UI |
| Catalog serialization duplicated | Refactor in 9A-3 into shared resources |
| No outbound webhook infra | Complete 9C–9D before promising merchant webhooks |

---

## 12. Definition of done (Phase 9 complete)

### Core foundation (9A–9D)

- [ ] Multiple scoped test/live API keys per store  
- [ ] Raw secret shown once; verifier-only storage  
- [ ] Per-key rate limits and audit identity  
- [ ] Stable API resources, errors, request IDs  
- [ ] Existing routes migrated without commerce regression  
- [ ] Atomic idempotency on all public writes  
- [ ] Provider webhook dedup (Stripe + pattern for connectors)  
- [ ] Transactional outbox at service boundaries  
- [ ] Queue recovery and retention  
- [ ] Signed SSRF-safe outbound webhooks with retry/history  
- [ ] Store-isolation test coverage  
- [ ] OpenAPI + webhook verification docs  

### Website connectivity (9F–9G)

- [ ] Custom website reference integration works end-to-end  
- [ ] Connection records + encrypted third-party credentials  
- [ ] Durable resource links + sync runs  
- [ ] Chunked initial import + incremental sync + reconciliation  
- [ ] WooCommerce for agreed capabilities  
- [ ] Shopify for agreed capabilities  
- [ ] WordPress limited to defined connector plugin contract  
- [ ] Source of truth explicit; conflicts visible and recoverable  

Until both groups pass, the project has an **integration foundation in progress**, not a finished connectivity outcome.

---

## 13. Things not to do

1. Keep one token per store as production auth.  
2. Store raw API secrets in the database.  
3. Put secret keys in browser JavaScript.  
4. Trust request `store_id`.  
5. Expose `catalog.write` before product-service extraction.  
6. Copy idempotency logic into new controllers.  
7. Call remote APIs inside business transactions.  
8. Build Shopify/Woo before API keys + idempotency + outbox.  
9. Promise universal WordPress support without a plugin/schema.  
10. Expose return/refund APIs before Phase 7.  
11. Remove legacy routes in the same batch as replacements.  
12. Mark Phase 9 done because a settings page exists.  

---

## 14. File map for agents

| When working on… | Start here |
|------------------|------------|
| Route inventory | `routes/api.php`, `PROJECT_STRUCTURE.md` §3 Api |
| Legacy auth | `AuthenticateDeveloperStorefrontToken`, `Store::hasDeveloperStorefrontToken()` |
| External orders | `ExternalOrderSyncController` → `ExternalOrderSyncService` |
| Checkout API | `PlatformCheckoutController` → `CheckoutService` / `CheckoutShippingService` |
| Stripe inbound | `StripeWebhookController` → `CheckoutConversionService` |
| Ownership rules | `ChannelOwnershipService` |
| Stock changes | `InventoryAdjustmentService` (never raw variant stock updates) |
| Merchant token UI | `DeveloperStorefrontSettingsController`, `developer_storefront.blade.php` |
| Future integration code | `app/Services/Integrations/` (to be created in 9A+) |
| Tests to extend | `tests/Feature/Phase5ExternalCheckoutSyncTest.php`, `EnterpriseQaExternalOrderDedupHardeningTest.php`, `Phase5PlatformCheckoutStripeTest.php`, `DeveloperStorefrontApiTest.php` |

---

## 15. Related documents

| Document | Role |
|----------|------|
| `ENTERPRISE_PROJECT_CONTEXT.md` | Product vision and architecture rules |
| `ENTERPRISE_ROADMAP_2026.md` | Phase ordering; § PHASE 9 EXECUTION |
| `PROJECT_BRAIN.md` | Condensed project memory |
| `PROJECT_STRUCTURE.md` | Folder and controller map |
| `docs/architecture/REFACTORING_BOUNDARIES.md` | Product save / controller extraction debt |
| `docs/phases/PHASE_5_EXTERNAL_CHECKOUT_SYNC_REPORT.md` | External sync origin |
| External guide | `PHASE_9_ENTERPRISE_INTEGRATION_ARCHITECTURE_GUIDE.md` (full detail) |

---

*Implementation must not begin until explicitly instructed. First executable batch: **Phase 9-0** (tests + docs only, no migrations).*
