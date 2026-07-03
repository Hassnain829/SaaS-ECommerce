# Phase 9 — Integration Foundation Plan

> **Status:** Approved execution plan / not started in code  
> **Last updated:** 2026-07-04  
> **Authority:** This is the **approved Phase 9 execution plan** for goals, vision, current baseline, and batch order. It is **not** a root canonical document. On conflict, root canonical docs win: `ENTERPRISE_PROJECT_CONTEXT.md`, `ENTERPRISE_ROADMAP_2026.md`, `PROJECT_BRAIN.md`, `PROJECT_STRUCTURE.md`, and `AGENTS.md`.

External source document (audit basis): `PHASE_9_ENTERPRISE_INTEGRATION_ARCHITECTURE_GUIDE.md` (kept outside the repo; this file is the project-bound execution plan aligned to actual code).

### Working rule for every batch

Implement one bounded batch at a time. After each batch, run tests, show changed files and `git diff --stat`, then stop for manual review. The project owner handles all commits and pushes.

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
2. **Store-scoped everything** — never trust `store_id` from request body; resolve store from API key (merchant routes) or provider signature context (provider callbacks).
3. **Merchant-friendly Integrations UX** — plain language, not JSON/HMAC jargon in primary flows.
4. **No fake APIs** — do not expose scopes or buttons for endpoints that do not exist (returns/refunds wait for Phase 7).
5. **No secret keys in browser JS** — `dev-test-storefront` stays a simulator; production custom sites use server-side keys.
6. **Business commit + outbox in one transaction** — remote HTTP only after commit via queues.
7. **Do not change legacy `/api/developer-storefront/*` response shapes** before contract tests and simulator migration.

---

## 2. Explicit reprioritization and phase ownership

Phase 9 is being started by **explicit reprioritization** while **Phase 5R-2**, **Phase 5R-3**, and **Phase 7** remain incomplete.

| Phase | Ownership |
|-------|-----------|
| **Phase 5R-2** | Coupons |
| **Phase 5R-3** | Checkout/order totals hardening |
| **Phase 7** | Returns / refunds / exchanges |
| **Phase 8** | Markets / B2B |
| **Phase 9** | Integration foundation (this plan) |
| **Phase 10** | SaaS billing / subscriptions |

### Constraints while incomplete phases remain open

- **Do not expose return/refund APIs or scopes** until Phase 7 is implemented.
- **Do not freeze coupon payloads prematurely** — Phase 5R-2 owns coupons; Phase 9 must not lock discount/coupon shapes in public contracts before 5R-2.
- Phase 5R-3 may still change checkout/order totals behavior; document impact in 9-0 and avoid over-freezing totals fields that 5R-3 will harden.

### What Phase 9 is not

| Not Phase 9 | Belongs to |
|-------------|------------|
| Coupons / discount rules | Phase 5R-2 |
| Checkout/order totals hardening | Phase 5R-3 |
| Returns / refunds / exchanges APIs | Phase 7 |
| Markets / B2B | Phase 8 |
| SaaS billing / subscriptions | Phase 10 |
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
- `provider_webhook_events` (Stripe/provider event dedup table)
- `outbox_events` + dispatcher jobs
- `webhook_subscriptions`, `webhook_deliveries`, `webhook_delivery_attempts`
- `integration_connections`, `integration_resource_links`, `integration_sync_runs`
- WooCommerce / Shopify / WordPress connector adapters
- Settings → **Integrations** hub (API keys, webhooks, connected sites)
- OpenAPI spec and production reference examples
- Read APIs: `GET /api/v1/orders`, customers, inventory, shipments, store
- Standard response envelope (`data` + `meta.request_id`) and stable error codes

---

## 4. Auth and identity systems (keep separate)

### Merchant `/api/v1` routes

Use:

- API keys (store-scoped, test/live, scopes)
- rate limits (per key / route class)
- request IDs
- stable error codes

Store authority comes from the authenticated API key. **Never** accept request-body `store_id` as authority.

### Stripe / provider callbacks

Use:

- signature verification
- **provider-event dedup** via `provider_webhook_events`

Provider callbacks **do not** use merchant API keys.

### Three separate systems

| System | Purpose | Example |
|--------|---------|---------|
| **Client idempotency** | Replay-safe client retries for public writes | `Idempotency-Key` header + `idempotency_keys` (upgraded in 9B) |
| **Provider-event dedup** | Exactly-once processing of inbound provider events | Stripe event ID → `provider_webhook_events` |
| **External resource identity** | Durable business identity of an external order/shipment/product | `external_order_id` / `external_order_number`, resource links |

Do not collapse these into one table or one middleware.

### Legacy `/api/developer-storefront/*`

Do **not** change legacy response shapes before:

1. Phase 9-0 contract tests lock current behavior, and
2. simulator migration to a test API key (9F).

Both legacy and v1 order paths must be characterized:

- `POST /api/developer-storefront/orders` → `DeveloperStorefrontCatalogController::placeOrder`
- `POST /api/v1/external/orders` → `ExternalOrderSyncService`

---

## 5. API key rules (9A)

When implemented, API keys must be:

- **store-scoped**
- **multiple keys per store**
- **test / live modes**
- **scoped** (only scopes for endpoints that exist)

Security rules:

- **raw secret shown once** (create/rotate UI only)
- **only secret hash stored** (no raw-secret column)
- **revocation**, **expiry**, and **last-used** audit fields
- **secrets and `Authorization` headers never logged**
- **third-party credentials** needed for outbound connector calls are **encrypted, not hashed** (platform must use them later)

Merchant API key secrets are hashed because we only verify them. Connector/provider tokens are encrypted because we must call external APIs with them.

---

## 6. Code Phase 9 must reuse (do not bypass)

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

**Product write blocker:** Do **not** expose `catalog.write` or connector product push until product persistence is extracted from `Store\OnboardingController` into **one shared store-scoped service** used by Blade and API.

**SSRF pattern to reuse:** `App\Support\Security\ServerSideImageHttpUrlValidator` for webhook endpoint validation.

---

## 7. Target architecture (summary)

```text
Connected website / connector
        |
        | Bearer API key (test/live) + scopes
        v
/api/v1 + AuthenticateApiKey + RequireApiScope + RateLimit
        |
        | RequireIdempotencyKey (mutations)
        v
API controllers (merchant routes)
        |
        v
Existing domain services (Catalog, Checkout, Orders, Inventory, Fulfillment)
        |
        | same DB transaction
        +---------------------> OutboxRecorder → outbox_events
        |                           |
        v                           | queue after commit
Business tables                   v
                         webhook_deliveries / connector jobs

Stripe / provider callbacks (separate path):
  signature verify → provider_webhook_events claim → domain services
```

**Rules:**

- Never call remote APIs inside business transactions.
- Never use broad model observers as the primary outbox producer — emit from application services at valid commit boundaries.
- Client idempotency, provider-event dedup, and external resource identity remain **separate systems**.

### Integration tables (keep separate)

| Table | Role |
|-------|------|
| `outbox_events` | Transactional business events for fan-out after commit |
| `webhook_subscriptions` | Merchant outbound webhook endpoints and subscribed events |
| `webhook_deliveries` | One logical delivery per subscription + outbox event |
| `webhook_delivery_attempts` | Per-HTTP-attempt history (retries, status, truncated body) |
| `provider_webhook_events` | Inbound provider event dedup (Stripe, later connectors) |

Do not merge these tables or reuse one for another’s purpose.

---

## 8. Source-of-truth matrix (default)

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

Formalize in:

- `docs/architecture/CHANNEL_OWNERSHIP_MATRIX.md` (Phase 9-0)
- `docs/architecture/INTEGRATION_CAPABILITY_MATRIX.md` (Phase 9-0)

Logic seed: `ChannelOwnershipService`.

---

## 9. Recommended scopes (when implemented)

Issue scopes **only** for endpoints that exist.

| Group | Scopes |
|-------|--------|
| Store / catalog | `store.read`, `catalog.read`, `catalog.write` (**only after** shared product persistence extraction) |
| Inventory | `inventory.read`, `inventory.adjust` |
| Orders / checkout | `orders.read`, `orders.create`, `orders.update`, `checkouts.create`, `checkouts.read`, `checkouts.update` |
| Customers | `customers.read`, `customers.write` |
| Fulfillment | `shipments.read`, `shipments.write` |
| Integration mgmt | `webhooks.read`, `webhooks.manage` |
| **Reserved Phase 7 — do not issue** | `returns.*`, `refunds.*` |

Prefer granular scopes (`orders.create`, `inventory.adjust`) over coarse roadmap names (`orders.write`, `inventory.write`) when implementing.

---

## 10. Implementation order (batches)

Implement one bounded batch at a time. After each batch, run tests, show changed files and `git diff --stat`, then stop for manual review. The project owner handles all commits and pushes.

| Batch | Focus | Gate |
|-------|-------|------|
| **9-0** | Contracts, characterization tests, architecture docs (ownership, capability matrix, event catalog, dependency audit) | No schema until tests lock current behavior |
| **9A** | API keys, auth, scopes, rate limits, request IDs, stable errors, API resources, read APIs, Integrations UI, legacy-token migration path | Every merchant `/api/v1` route has auth + scope + rate class; secrets never logged |
| **9B** | Atomic client idempotency engine + provider webhook dedup (`provider_webhook_events`); migrate external/checkout/inventory writes and Stripe callbacks | Concurrent duplicates → one business result; duplicate Stripe events safe 2xx |
| **9C** | Transactional outbox (`outbox_events`), producers at service boundaries, worker/recovery/retention | Rollback removes event; commit includes event |
| **9D** | Signed outbound webhooks (`webhook_subscriptions`, `webhook_deliveries`, `webhook_delivery_attempts`), SSRF protection, retries, merchant diagnostics | No duplicate logical delivery; private targets blocked |
| **9F** | Shared integration connections, resource links, sync runs, reconciliation; then custom website reference (simulator → test API key, OpenAPI, examples) | Chunked import + durable links; legacy shapes preserved until migration |
| **9G** | WooCommerce, Shopify, defined WordPress connector (plugin required) | After 9A–9D proven; capability matrix enforced |
| **9E** | Automation builder | Only after 9A–9D are stable |

### Suggested internal slices within 9A (still one review stop per slice if large)

1. `api_keys` schema, issuer, revoker, security logs  
2. `AuthenticateApiKey`, scopes, per-key rate limits, stable errors + request ID  
3. API resources/DTOs, response envelope (merchant v1 only; do not change legacy shapes yet)  
4. Read APIs (store, orders, customers, inventory, shipments), cursor/`updated_since`  
5. Settings → Integrations UI and legacy-token migration path  

### Merchant UX target

**Settings → Integrations**

- Overview  
- API keys (test/live, scopes, revoke/rotate, one-time secret copy)  
- Webhooks (subscriptions, test event, delivery history)  
- Connected websites (Woo/Shopify/custom)  
- Sync activity  

Evolve from current `developer_storefront` page; keep route aliases during migration.

---

## 11. Phase 9-0 — first batch (start here when instructed)

**Goal:** Protect existing checkout, external sync, shipping, inventory, and simulator behavior before auth/schema changes.

**Do not start Phase 9-0 until explicitly instructed.**

### Documentation deliverables

1. **`docs/architecture/API_V1_CONTRACT_BASELINE.md`** — every route, auth, throttle, service boundary, legacy vs v1.
2. **`docs/architecture/CHANNEL_OWNERSHIP_MATRIX.md`** — formal rules from `ChannelOwnershipService`.
3. **`docs/architecture/OUTBOX_EVENT_CATALOG.md`** — initial event names + payload allowlists (design only).
4. **`docs/architecture/INTEGRATION_CAPABILITY_MATRIX.md`** — for custom website, WooCommerce, Shopify, and WordPress connector: capabilities, sync direction, source of truth, imports, webhooks, reconciliation, conflicts, scopes, and deferred features.
5. **`docs/integrations/PHASE_9_0_DEPENDENCY_AUDIT.md`** — queue worker checklist, external consumer decision, Phase 5R-2 / 5R-3 / Phase 7 blockers.

### Characterization tests (`tests/Feature/Integrations/`)

Must cover:

- catalog v1 list/detail  
- **legacy API response contracts** (`/api/developer-storefront/*`) — shapes must not drift  
- **both legacy and v1 order paths**  
- external order/shipment: **external identity vs idempotency** (identity required; idempotency is replay protection only)  
- platform checkout golden path  
- **duplicate Stripe events** → single order / safe 2xx  
- **cross-store isolation** and **request `store_id` override** rejection (body/query cannot switch store)  
- **secret/header log redaction** (no full secrets or `Authorization` headers in logs)  
- **rollback without partial order/inventory state** (failed sync leaves no half-created order or stock movement)  
- rate-limit baseline  

JSON fixtures under `tests/fixtures/api/v1/` or `tests/Support/Integrations/`.

### Explicit decisions before 9A

- [ ] Any real external v1 consumers today? (If no → can normalize merchant v1 envelope before public launch; still do not change legacy shapes until simulator migration.)
- [ ] Deprecation plan for `POST /api/developer-storefront/orders` vs `POST /api/v1/external/orders`.
- [ ] Queue worker + failed jobs + scheduler confirmed for production.
- [ ] Phase 5R-2 coupon impact documented — **do not freeze coupon payloads**.
- [ ] Phase 5R-3 totals-hardening impact documented — avoid over-freezing totals fields.

### Gate

Full suite green + new contract tests green → proceed to **9A**.

After 9-0: run tests, show changed files and `git diff --stat`, stop for manual review. Owner commits/pushes.

---

## 12. Known conflicts to resolve during planning

| Topic | Notes |
|-------|-------|
| Token format | Current `baa_dev_*` → target `eco_test_*` / `eco_live_*`; legacy middleware kept during transition |
| Permission names | Code has `developer_api.*`; roadmap mentions `integrations.*` — unify when building Integrations UI |
| Scope naming | Prefer granular scopes over roadmap’s `orders.write` / `inventory.write` |
| Dual order APIs | Legacy `placeOrder` vs `ExternalOrderSyncService` — both need contract tests and deprecation path |
| Roadmap returns scopes in 9A | Do not issue; Phase 7 owns returns/refunds |
| Roadmap billing phase | SaaS billing is **Phase 10**, not Phase 8 |
| Roadmap Markets/B2B | **Phase 8** |
| Incomplete 5R-2 / 5R-3 / 7 | Explicit reprioritization; do not expose return/refund APIs/scopes; do not freeze coupon payloads |
| Webhook / outbox tables | Keep `outbox_events`, `webhook_subscriptions`, `webhook_deliveries`, `webhook_delivery_attempts`, `provider_webhook_events` separate |

---

## 13. Risks and blockers

| Risk | Mitigation |
|------|------------|
| Product save still in `Store\OnboardingController` | Extract **one shared store-scoped product persistence service** before `catalog.write` or connector product push |
| Controller idempotency races | Central atomic idempotency in 9B; concurrency tests mandatory |
| Coupons not finalized (5R-2) | Document impact in 9-0; **do not freeze coupon payloads** |
| Totals still hardening (5R-3) | Document impact in 9-0; avoid over-freezing totals fields |
| Legacy token removal too early | Dual auth period; migrate `dev-test-storefront` to test API key in 9F; preserve legacy response shapes until then |
| Uncontrolled two-way sync | Ownership matrix + capability matrix + connector conflict UI |
| Catalog serialization duplicated | Refactor in 9A into shared resources (merchant v1 only) |
| No outbound webhook infra | Complete 9C–9D before promising merchant webhooks |
| Secrets in logs | Redaction tests in 9-0; never log secrets or `Authorization` headers |

---

## 14. Definition of done (Phase 9 complete)

### Core foundation (9A–9D)

- [ ] Multiple scoped test/live API keys per store  
- [ ] Raw secret shown once; only secret hash stored  
- [ ] Revocation, expiry, last-used audit  
- [ ] Secrets and `Authorization` headers never logged  
- [ ] Third-party credentials encrypted (not hashed)  
- [ ] Per-key rate limits and audit identity  
- [ ] Stable API resources, errors, request IDs on merchant `/api/v1`  
- [ ] Provider callbacks use signature verify + `provider_webhook_events` (not API keys)  
- [ ] Existing routes migrated without commerce regression  
- [ ] Atomic client idempotency on public writes  
- [ ] Transactional outbox at service boundaries (`outbox_events`)  
- [ ] Signed outbound webhooks with separate delivery/attempt tables  
- [ ] Queue recovery and retention  
- [ ] Store-isolation and `store_id` override rejection coverage  
- [ ] OpenAPI + webhook verification docs  

### Website connectivity (9F–9G)

- [ ] Shared connections, resource links, sync runs, reconciliation  
- [ ] Custom website reference integration works end-to-end  
- [ ] Encrypted third-party credentials  
- [ ] Chunked initial import + incremental sync  
- [ ] WooCommerce for agreed capabilities  
- [ ] Shopify for agreed capabilities  
- [ ] WordPress limited to defined connector plugin contract  
- [ ] Source of truth explicit; conflicts visible and recoverable  

Until both groups pass, the project has an **integration foundation in progress**, not a finished connectivity outcome.

---

## 15. Things not to do

1. Keep one token per store as production auth.  
2. Store raw API secrets in the database.  
3. Put secret keys in browser JavaScript.  
4. Trust request `store_id`.  
5. Expose `catalog.write` or connector product push before shared product persistence extraction from `Store\OnboardingController`.  
6. Copy idempotency logic into new controllers.  
7. Call remote APIs inside business transactions.  
8. Build Shopify/Woo before API keys + idempotency + outbox.  
9. Promise universal WordPress support without a plugin/schema.  
10. Expose return/refund APIs or scopes before Phase 7.  
11. Freeze coupon payloads before Phase 5R-2.  
12. Change legacy `/api/developer-storefront/*` response shapes before contract tests and simulator migration.  
13. Use merchant API keys for Stripe/provider callbacks.  
14. Merge `outbox_events`, webhook tables, and `provider_webhook_events`.  
15. Remove legacy routes in the same batch as replacements.  
16. Mark Phase 9 done because a settings page exists.  
17. Commit or push without owner approval — owner handles all commits and pushes.

---

## 16. File map for agents

| When working on… | Start here |
|------------------|------------|
| Route inventory | `routes/api.php`, `PROJECT_STRUCTURE.md` §3 Api |
| Legacy auth | `AuthenticateDeveloperStorefrontToken`, `Store::hasDeveloperStorefrontToken()` |
| External orders (v1) | `ExternalOrderSyncController` → `ExternalOrderSyncService` |
| Legacy orders | `DeveloperStorefrontCatalogController::placeOrder` |
| Checkout API | `PlatformCheckoutController` → `CheckoutService` / `CheckoutShippingService` |
| Stripe inbound | `StripeWebhookController` → `CheckoutConversionService` |
| Ownership rules | `ChannelOwnershipService` |
| Stock changes | `InventoryAdjustmentService` (never raw variant stock updates) |
| Merchant token UI | `DeveloperStorefrontSettingsController`, `developer_storefront.blade.php` |
| Future integration code | `app/Services/Integrations/` (to be created in 9A+) |
| Tests to extend | `tests/Feature/Phase5ExternalCheckoutSyncTest.php`, `EnterpriseQaExternalOrderDedupHardeningTest.php`, `Phase5PlatformCheckoutStripeTest.php`, `DeveloperStorefrontApiTest.php` |

---

## 17. Related documents

| Document | Role |
|----------|------|
| `ENTERPRISE_PROJECT_CONTEXT.md` | **Root canonical** — product vision and architecture rules |
| `ENTERPRISE_ROADMAP_2026.md` | **Root canonical** — phase ordering; Phase 9 section |
| `PROJECT_BRAIN.md` | Condensed project memory |
| `PROJECT_STRUCTURE.md` | Folder and controller map |
| `docs/architecture/REFACTORING_BOUNDARIES.md` | Product save / controller extraction debt |
| `docs/phases/PHASE_5_EXTERNAL_CHECKOUT_SYNC_REPORT.md` | External sync origin |
| External guide | `PHASE_9_ENTERPRISE_INTEGRATION_ARCHITECTURE_GUIDE.md` (full detail) |
| This file | **Approved Phase 9 execution plan** (not root canonical) |

---

*Do not start Phase 9-0 or any implementation until explicitly instructed. First executable batch when approved: **Phase 9-0** (tests + docs only, no migrations).*
