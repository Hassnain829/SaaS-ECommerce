# WordPress–SaaS Commerce Architecture and Ordered Implementation Plan

**Repository:** `E_COMMERCE_OFFICE`  
**Architecture status:** Approved and locked  
**Plan status:** Ordered implementation plan; completion must be verified batch by batch  
**Document date:** 2026-08-14  
**Inspected baseline:** `SaaS-Static-Blade-till-DR-05.zip`

**Payment-authority amendment (2026-08-20):** Browser state, WordPress state, Stripe.js completion, and client-supplied payment status are never payment authority. The SaaS may convert only from provider-authoritative Stripe evidence: either a cryptographically verified webhook or authenticated server-to-server retrieval of the exact stored PaymentIntent in its stored mode and connected-account context. Retrieval must validate intent identity, checkout/store/account metadata, amount, currency, and provider status `succeeded`. Conversion remains transactional and idempotent; verified webhooks remain asynchronous recovery.

## 1. Purpose and authority

This document records the final product architecture for connecting a merchant's existing WordPress website to `E_COMMERCE_OFFICE`. It consolidates the architecture decisions made after reviewing the DR-05 implementation and discussing external checkout, platform checkout, Stripe, WordPress connectivity, data ownership, WooCommerce replacement, and WooCommerce catalog migration.

This is an architecture and implementation-plan document, not evidence that every capability described here already exists.

When this document conflicts with old descriptions of external checkout, WooCommerce synchronization, or dual platform/external ownership, this document controls the new implementation direction. Current source code and database state must still be inspected before changing or deleting anything.

Cursor or another implementation agent must:

- Read this entire document before planning a batch.
- Implement only the batch explicitly requested by the user.
- Inspect the actual repository instead of assuming the inspected ZIP remains current.
- Preserve unrelated working-tree changes.
- Run focused tests for the requested batch.
- Run the full test suite before claiming release readiness.
- Never claim a batch is complete based only on code presence.
- Never commit or push unless the user separately authorizes it.

## 2. Locked product architecture

The following decisions are final for this implementation program.

### 2.1 System roles

`E_COMMERCE_OFFICE` is the sole commerce engine and system of record.

The merchant's WordPress website remains the customer-facing presentation layer. A thin WordPress connector authenticates the site, requests public or customer-authorized information from the SaaS, sends customer intent to the SaaS, and renders the returned result.

WooCommerce is not an ongoing dependency, integration target, order authority, inventory authority, or checkout provider. It is only a legacy migration source for merchants moving to the SaaS.

```text
WordPress theme and pages
        |
        v
Thin WordPress connector
        |
        | authenticated, store-scoped API requests
        v
E_COMMERCE_OFFICE Laravel SaaS
        |-- products and variants
        |-- prices, currency, discounts, and tax
        |-- customers and addresses
        |-- locations and inventory
        |-- cart validation and checkout
        |-- Stripe PaymentIntent creation and webhooks
        |-- orders, returns, and refunds
        |-- shipping rates and fulfillment
        `-- labels and tracking
```

### 2.2 Checkout policy

Platform checkout is the only supported checkout mode.

- External checkout must be removed from active runtime behavior.
- Merchants cannot choose between platform and external checkout.
- WordPress cannot declare prices, taxes, shipping totals, stock, or payment success authoritative.
- Disconnecting or losing Stripe eligibility blocks checkout.
- Stripe failure must never cause fallback to external checkout.
- New orders must be created through the SaaS checkout lifecycle.

### 2.3 Stripe policy

- The merchant connects Stripe from the SaaS portal.
- The merchant disables the WooCommerce Stripe gateway and other conflicting WordPress payment plugins before production cutover.
- Stripe secret keys must never be stored in WordPress or exposed to browser JavaScript.
- The SaaS creates the PaymentIntent and owns payment state.
- WordPress may render Stripe's browser-safe Payment Element using values returned by the SaaS.
- Browser and WordPress payment claims remain untrusted. Provider-authoritative payment evidence is either a cryptographically verified Stripe webhook or authenticated SaaS retrieval of the exact stored PaymentIntent in its stored mode and connected-account context.
- Direct retrieval must validate intent identity, checkout/store/account metadata, amount, currency, and provider status `succeeded` before conversion.
- Conversion is transactional and idempotent. Verified Stripe webhooks remain the asynchronous recovery path.
- The merchant remains the owner/recipient of the connected payment account under the approved Stripe Connect design.

The exact Stripe connected-account configuration is a required design decision in Batch 3. The inspected source uses Express connected accounts. That does not, by itself, prove that a merchant can reuse the exact conventional Stripe account previously used by a WooCommerce gateway.

### 2.4 WooCommerce policy

- WooCommerce is a one-time migration source, not a synchronized runtime system.
- Merchants export their WooCommerce catalog and import it into the SaaS.
- After validation and cutover, WooCommerce commerce functionality and conflicting payment/shipping plugins are deactivated.
- The connector must not depend on WooCommerce APIs, hooks, product tables, cart, checkout, customers, orders, or payment gateways.
- WooCommerce must not be deleted immediately. A database and file backup remains available during a defined rollback/archive period.
- A product CSV does not migrate historical customers, orders, refunds, reviews, subscriptions, saved payment methods, or every plugin-specific field. Those require separate migration scope or a read-only legacy archive.

### 2.5 Carrier policy

- Merchants connect merchant-owned carrier accounts.
- Merchants pay their own postage and carrier charges.
- The SaaS provides connectivity and must not become the postage payer.
- The WordPress connector never calls carrier APIs directly.
- The connector displays only delivery services returned by the SaaS after current-store, location, account, country, and capability gates are applied.
- FedEx, USPS, and DHL availability must follow the current authoritative project state. The connector must not invent or overstate carrier readiness.

## 3. Authoritative data ownership

| Domain | Authoritative owner | WordPress connector responsibility |
| --- | --- | --- |
| Products and variants | SaaS | Fetch and display published data; optionally cache public representations |
| Categories and product images | SaaS | Fetch and display; invalidate public cache when notified |
| Prices and currency | SaaS | Display returned values; never calculate authoritative prices |
| Locations and inventory | SaaS | Display availability; never maintain or deduct authoritative stock |
| Cart intent | Browser/connector temporarily | Hold variant IDs and quantities; request an authoritative SaaS quote |
| Discounts and tax | SaaS | Display calculated results |
| Shipping rates | SaaS | Display eligible returned services |
| Stripe checkout | SaaS and Stripe | Render browser-safe Stripe UI from SaaS-created checkout data |
| Customers and addresses | SaaS | Submit customer intent and display authorized account data |
| Orders | SaaS | Display confirmation, status, and customer-authorized history |
| Fulfillment, labels, and tracking | SaaS | Display customer-visible status and tracking |
| WordPress content and theme | WordPress | Own editorial pages, theme, layout, and non-commerce content |

The phrase "synchronize everything with WordPress" must not be used to create two authorities. Public catalog representations may be cached. Operational commerce data remains in the SaaS and is requested when needed.

## 4. Thin connector contract

### 4.1 What the connector must do

- Connect one WordPress site to one SaaS store.
- Authenticate server-to-server requests.
- Fetch published products, variants, categories, and images.
- Display product and category information.
- Send selected variant IDs and quantities to the SaaS.
- Request an authoritative cart quotation.
- Send customer and address input to the SaaS.
- Request available shipping rates.
- Start a platform checkout session.
- Render the Stripe Payment Element with browser-safe data returned by the SaaS.
- Display order confirmation and tracking returned by the SaaS.
- Optionally cache public catalog responses for performance.
- Invalidate affected cache entries after authenticated SaaS events.
- Reconcile after missed cache-invalidation events.
- Report connection health and merchant-actionable errors.

### 4.2 What the connector must never do

- Own or manage WordPress/WooCommerce product records as the commerce authority.
- Maintain an independent inventory ledger.
- Deduct stock.
- Calculate authoritative product prices, discounts, tax, or shipping totals.
- Choose the authoritative fulfillment location.
- Call FedEx, USPS, DHL, or another carrier directly.
- Create authoritative customers or orders locally.
- Create Stripe PaymentIntents.
- Store Stripe secret keys.
- Process authoritative Stripe webhooks.
- Declare payment successful from browser state alone.
- Issue authoritative refunds or returns.
- Purchase labels or execute fulfillment.
- Fall back to WooCommerce or external checkout.

The connector may render catalog, product, cart, address, shipping, checkout, confirmation, tracking, and customer-authorized order-history views. Rendering API-backed presentation does not make the connector a commerce engine.

## 5. Locked checkout request flow

1. WordPress requests published products from the SaaS.
2. The customer selects a platform product variant.
3. The connector temporarily holds only platform variant IDs and requested quantities.
4. The connector sends the cart intent to the SaaS.
5. The SaaS validates store ownership, publication status, price, currency, quantity, and inventory.
6. The customer submits their address through the WordPress storefront.
7. The connector forwards the address to the SaaS.
8. The SaaS validates the address and calculates discounts, tax, fulfillment location, and eligible shipping rates.
9. The SaaS creates the checkout, inventory reservation, and Stripe PaymentIntent.
10. WordPress renders Stripe's secure browser payment interface.
11. WordPress requests confirmation from the SaaS after Stripe.js returns, without asserting payment success.
12. The SaaS obtains provider-authoritative evidence through authenticated retrieval of the exact stored PaymentIntent or a cryptographically verified Stripe webhook. Retrieval uses the stored mode and connected-account context and validates identity, checkout/store/account metadata, amount, currency, and `succeeded` status.
13. The SaaS finalizes the order transactionally exactly once, commits stock exactly once, and treats later verified webhook delivery as idempotent asynchronous recovery; WordPress requests and displays the SaaS order confirmation.

Every mutation in this flow must be current-store scoped and idempotent where retries are possible.

## 6. Inspected baseline and known gaps

The inspected DR-05 ZIP already contains useful foundations:

- A WordPress connector that creates its own catalog, cart, and checkout presentation pages.
- A server-side WordPress API client.
- A platform checkout endpoint and checkout service.
- Platform variant and currency validation.
- Customer upsert behavior.
- Fulfillment-origin selection.
- Totals calculation and inventory reservation.
- SaaS-side Stripe PaymentIntent creation.
- Checkout-to-order conversion and stock commitment after payment success.
- Stripe Connect onboarding based on Express connected accounts.
- A generic CSV/XLSX product importer with mappings for products, SKUs, prices, categories, tags, dimensions, images, stock, and structured variants.

The inspected baseline also contains important defects or incomplete areas:

- Active external order and shipment endpoints and services remain.
- External and platform checkout branches remain in the connector and portal.
- Stripe disconnect can fall back to external mode.
- The existing site credential is not a sufficiently scoped production connected-site authorization model.
- There is no complete reliable event outbox, site delivery, replay protection, retry, and reconciliation path for connector cache invalidation.
- The WordPress connector remains a prototype rather than a production-hardened presentation client.
- The generic importer has no reliable WooCommerce source preset.
- WooCommerce parent/variation relationships, attributes, source IDs, missing SKUs, sale prices, tax fields, slugs, unsupported product types, and location assignment are not fully handled.
- A WooCommerce product export does not cover historical customer/order migration.
- Active roadmap/context documentation still contains older external-checkout and WooCommerce-adapter assumptions.

These are starting-point observations, not permission to delete similarly named unrelated features or historical data.

## 7. Ordered implementation batches

Only one batch should be implemented per user request. Each batch begins with repository inspection and ends with evidence. Do not begin the next batch merely because the current batch appears straightforward.

## Batch 1 — Platform-only checkout and external-checkout removal

### Objective

Remove the dual-mode architecture so every later API, connector, Stripe, import, and cutover capability is built against one platform-owned checkout model.

### Required preflight

- Run `git status --short`.
- Record unrelated working-tree changes.
- Run the focused checkout, payment, order, inventory, website-connection, refund, and fulfillment tests.
- Run the full PHPUnit suite and preserve the baseline result.
- Inventory database/schema fields and real records associated with external checkout.
- Search routes, PHP, Blade, JavaScript, configuration, tests, active documentation, and the WordPress connector for external-mode references.

### Implementation scope

- Remove active external order and shipment API routes.
- Remove active external order and shipment controllers/services.
- Remove external API rate limiting that has no remaining consumer.
- Remove merchant-selectable checkout ownership and external inventory controls.
- Remove external payment/shipping panels and copy.
- Remove external checkout branches from the WordPress connector.
- Collapse checkout ownership decisions to platform ownership.
- Change Stripe disconnect behavior from external fallback to checkout blocked.
- Prevent new external orders and shipments.
- Retain safe read-only interpretation of historical external records when real data requires it.
- Use forward migrations for schema changes; do not delete old migrations.
- Update active documentation after behavior changes, without rewriting archived historical evidence.

### Important inspected targets

Inspect, confirm, and then change or remove the current equivalents of:

- `ExternalOrderSyncController`
- `ExternalShipmentSyncController`
- `ExternalOrderSyncService`
- `ExternalShipmentSyncService`
- `/api/v1/external/orders`
- `/api/v1/external/shipments`
- `external_panel.blade.php`
- `CheckoutMode`
- `ChannelOwnershipService`
- external-mode branches in payment and shipping settings
- external-mode branches in the WordPress connector

Do not remove carrier billing constants or language merely because they contain `external`; merchant-owned carrier billing is a separate valid concern.

### Acceptance gate

- No merchant-facing external checkout selection remains.
- Removed external endpoints are absent or return 404.
- New orders can originate only through platform checkout.
- Stripe disconnect blocks checkout and never falls back.
- No connector request selects external mode.
- Historical records remain safely readable when required.
- Negative tests prove external checkout cannot be reactivated.
- Focused tests pass.
- Full-suite results are reported honestly.

## Batch 2 — Secure connected-site identity and authorization

### Objective

Replace the prototype store token with a production-capable, store-scoped WordPress site connection.

### Implementation scope

- Introduce a connected-site identity with store ID, normalized site URL, public identifier, hashed credential, status, scopes, plugin version, last-seen time, rotation timestamps, and revocation state.
- Enforce that one WordPress site belongs to exactly one SaaS store.
- Permit a schema that can support more than one connected site per SaaS store later, while initially allowing one primary active WordPress storefront in merchant UX.
- Add explicit least-privilege scopes such as catalog read, checkout creation/read, order read, shipping quote, tracking read, customer authentication, and health.
- Keep the connection credential server-side in WordPress and out of rendered HTML and browser JavaScript.
- Add credential rotation and revocation.
- Bind every connector API read/write to the authenticated current store.
- Add production HTTPS, expected-host/site checks, rate limiting, and auditable authentication failures.
- Add a health endpoint and merchant-safe connection diagnostics.

### Acceptance gate

- Store A credentials cannot access Store B resources.
- Revocation immediately blocks access.
- Rotation invalidates the old credential.
- Secrets never reach browser responses.
- The plugin can report the connected store, URL match, API reachability, version compatibility, readiness gates, and last successful contact.
- Focused tenant-isolation and authentication tests pass.

## Batch 3 — Authoritative commerce API and Stripe checkout hardening

### Objective

Provide one versioned, idempotent SaaS contract that implements the locked 13-step checkout flow.

### Required design decision

Before changing Stripe onboarding, determine whether merchants must reuse an existing conventional Stripe account or may onboard a new platform-connected account. Validate the selected Connect configuration against Stripe's current supported model. Do not assume the inspected Express flow satisfies both cases.

### Implementation scope

- Harden versioned endpoints for published catalog, product detail, categories, variants, images, pagination, and update/cache versions.
- Exclude draft, deleted, disabled, and cross-store catalog data.
- Accept cart intent as platform variant IDs and quantities only.
- Calculate authoritative price, currency, discount, tax, inventory, location, shipping, and totals in the SaaS.
- Normalize customer and address input in the SaaS.
- Implement guest checkout first if storefront customer authentication is not yet complete.
- Use SaaS-owned authentication for registered storefront customers; never synchronize password hashes to WordPress.
- Return only currently eligible carrier services.
- Create inventory reservations and expire abandoned reservations safely.
- Create Stripe PaymentIntents in the SaaS with correct store/account, amount, currency, and idempotency.
- Return only browser-safe Stripe fields to WordPress.
- Verify Stripe webhook signatures and connected-account context.
- Allow authenticated, store-scoped confirmation to retrieve the exact stored PaymentIntent in its stored mode and connected-account context; validate intent identity, checkout/store/account metadata, amount, currency, and `succeeded` status before conversion.
- Handle duplicate and out-of-order webhook events.
- Finalize the order and stock commitment transactionally exactly once, regardless of which provider-authoritative path succeeds first; keep verified webhooks as asynchronous recovery.
- Provide customer-authorized order confirmation, status, and tracking endpoints.

### Acceptance gate

Test at least stale price, insufficient stock, currency mismatch, cross-store IDs, duplicate checkout submission, duplicate webhook, out-of-order webhook, failed payment, expired reservation, carrier failure, and concurrent purchase of the last unit.

The complete 13-step checkout flow must pass without WordPress owning a business calculation.

## Batch 4 — Thin WordPress connector production hardening

### Objective

Turn the current prototype into a secure presentation client without recreating WooCommerce inside the plugin.

### Implementation scope

- Retain connection administration and the server-side API client.
- Remove all external-checkout branches and controls.
- Render API-backed catalog, product, category, variant, cart, address, shipping, checkout, confirmation, tracking, and authorized order-history surfaces required by the released storefront scope.
- Keep the cart limited to temporary platform variant IDs and quantities.
- Request a fresh SaaS quote before displaying authoritative totals.
- Render Stripe's browser-safe payment interface from SaaS checkout data.
- Never create local authoritative product, stock, customer, order, payment, tax, shipping, or fulfillment records.
- Add merchant-actionable failure and reconnect states.
- Detect active WooCommerce, Woo checkout assignments, Woo payment/shipping plugins, conflicting shortcodes, and unsafe checkout caching.
- Do not automatically deactivate merchant plugins. Block production readiness and provide exact instructions.

### Acceptance gate

- No external branch remains in connector PHP or JavaScript.
- No WooCommerce runtime dependency exists.
- No Stripe secret exists in WordPress.
- No authoritative price, tax, stock, shipping, or payment decision occurs in WordPress.
- SaaS disconnection blocks checkout rather than falling back locally.
- Connector presentation works with a WordPress installation that has no WooCommerce dependency.

## Batch 5 — Catalog cache invalidation, events, and reconciliation

### Objective

Bind the storefront reliably to the SaaS without creating a second commerce authority.

### Implementation scope

- Allow short-lived caching only for public catalog/category/image representations.
- Keep checkout, customer, order, payment, and carrier credentials out of public caches.
- Implement a transactional SaaS event/outbox boundary for relevant storefront changes.
- Deliver signed, timestamped, uniquely identified events to the connected site.
- Start with events such as product published/updated/unpublished/deleted, variant updated, category updated, and inventory availability changed.
- Verify signatures, reject replays, and deduplicate event IDs in WordPress.
- Invalidate the affected cache and refetch the current SaaS representation.
- Retry failed delivery safely.
- Add scheduled checkpoint/version reconciliation so missed events are repaired.
- Provide a diagnostic full public-cache rebuild.
- Fetch private customer, order, payment, and tracking data live under appropriate authorization rather than mirroring it as WordPress commerce records.

### Acceptance gate

- SaaS catalog changes appear without manual cache clearing.
- Duplicate events do not duplicate state.
- Missed events are repaired by reconciliation.
- WordPress outage does not corrupt SaaS state.
- Private operational data is not stored in a public cache.
- SaaS remains authoritative during event and retry failures.

## Batch 6 — Reliable WooCommerce catalog migration

### Objective

Upgrade the generic importer with a WooCommerce-aware migration preset and reliable, idempotent source mapping.

### Implementation scope

- Detect standard WooCommerce CSV exports.
- Map WooCommerce ID, type, SKU, name, publication state, visibility, descriptions, regular/sale price, tax status/class, stock fields, backorders, dimensions, categories, tags, shipping class, images, parent, attributes, virtual/downloadable flags, slug/source URL, and supported metadata.
- Explicitly support simple products, variable products, and variation rows.
- Explicitly reject or report unsupported grouped, external/affiliate, subscription, booking, bundle, composite, and plugin-specific product types.
- Report option dimensions beyond the platform's supported variant model rather than silently flattening them.
- Store stable source identity using source system, source site, Woo product/variation ID, source SKU, and import batch.
- Make re-import update the same platform records.
- Create a deterministic resolution strategy for missing SKUs.
- Require the merchant to select a destination platform location for Woo's global stock.
- Require an explicit replace-versus-preserve stock choice.
- Preserve safe slugs and generate an old-to-new URL redirect map.
- Download/process images safely and report failures.
- Provide upload, source detection, mapping, validation, unsupported-data report, dry run, confirmation, progress, completion report, and safe retry.
- State clearly that product migration does not migrate all historical commerce records.

### Acceptance gate

Use representative WooCommerce fixtures for simple products, variable products, variations, missing and duplicate SKUs, parent errors, sale prices, multiple images, categories/tags, out-of-stock products, unsupported types, selected-location stock, redirect generation, and idempotent re-import.

No unsupported or failed row may be silently treated as successfully migrated.

## Batch 7 — Merchant migration and production cutover workflow

**Status (2026-08-20):** Implemented as the **Go live checklist** on Website → Connect your website. The portal never deactivates WordPress plugins or deletes WooCommerce data. Critical technical gates cannot be overridden by acknowledgements.

### Objective

Give a non-technical merchant one controlled migration and activation path.

### Readiness gates

- WordPress and database backup acknowledged.
- WooCommerce export imported.
- Import failures and unsupported products resolved or explicitly accepted.
- Store currency configured.
- Primary fulfillment location configured.
- Inventory reviewed.
- Tax configuration reviewed.
- Shipping configuration reviewed.
- At least one eligible delivery method available.
- Stripe connected and payment-capable.
- WordPress connector authenticated.
- Site diagnostics passing.
- Test product visible.
- Test checkout successful.
- Test order visible in SaaS.
- Stock commitment verified.
- Confirmation displayed.
- WooCommerce checkout/payment/shipping conflicts removed.

### Merchant cutover order

1. Back up WordPress files and database.
2. Export WooCommerce products.
3. Import and verify catalog data in the SaaS.
4. Configure currency, location, tax, inventory, shipping, and eligible carriers.
5. Connect Stripe in the SaaS.
6. Install and connect the WordPress connector on staging or during a controlled test window.
7. Run a test product, quote, shipping, payment, order, and stock flow.
8. Disable WooCommerce payment gateways and shipping integrations.
9. Deactivate WooCommerce.
10. Assign the connector-backed catalog, cart, and checkout pages.
11. Apply old-to-new product URL redirects.
12. Clear WordPress, page, object, reverse-proxy, and CDN caches as applicable.
13. Run a production smoke test.
14. Retain the WooCommerce backup/read-only archive through the rollback period.

The connector must never silently deactivate plugins or delete WooCommerce data.

### Acceptance gate

A merchant can complete the on-page workflow without API documentation, cannot activate a knowingly broken checkout, and has an explicit rollback path.

## Batch 8 — End-to-end release recovery and acceptance

**Status (2026-08-20):** Acceptance mapping completed in `docs/handoffs/DR05_BATCH8_RELEASE_EVIDENCE.md`. The ten Batch 1–6 browser scenarios are closed by merchant confirmation and were not re-implemented as a test suite. Full PHPUnit was not rerun in that pass. This does not make the overall product live-ready.

### Objective

Verify the architecture as one production workflow rather than declaring success from isolated tests.

### Required scenarios

- Clean WordPress installation without WooCommerce.
- Existing WooCommerce installation during migration.
- WooCommerce deactivated after validated migration.
- Conflicting WordPress Stripe/payment plugin detected.
- Simple and variable products.
- Guest checkout.
- Registered customer flow when customer authentication is in scope.
- Billing and shipping addresses.
- Tax and discounts.
- Multiple eligible shipping options.
- Stripe success, failure, cancellation, delayed direct retrieval, and delayed/duplicate webhook recovery.
- Abandoned checkout and reservation expiry.
- Duplicate submit and idempotent retry.
- Stock changing during checkout.
- Concurrent purchase of the final unit.
- Connector credential rotation and revocation.
- SaaS temporary outage.
- WordPress event endpoint temporary outage.
- Product unpublishing and cache invalidation.
- Order confirmation and tracking availability.
- Cross-store security attempts.
- Historical legacy records remaining readable where required.

### Release evidence

- Focused Laravel tests.
- WordPress connector tests.
- API contract tests.
- Tenant-isolation and authorization tests.
- Stripe webhook and idempotency tests.
- WooCommerce import fixture tests.
- End-to-end browser acceptance.
- Full PHPUnit suite.
- Build, lint, and static-analysis results applicable to changed code.
- Exact failures, skips, environment blockers, and untested areas disclosed.

## 8. Decisions that must be recorded before their dependent batch

The product architecture is locked, but these implementation policies still require explicit evidence or confirmation:

1. **Stripe connected-account configuration:** Reuse of an existing conventional merchant Stripe account versus onboarding a new platform-controlled/Express-style account. Resolve before Batch 3 Stripe changes.
2. **Storefront customer authentication:** Default recommendation is SaaS-owned authentication and scoped storefront sessions. Never synchronize passwords to WordPress.
3. **Historical WooCommerce data:** Default scope is product/catalog migration plus a read-only legacy archive. Customer/order history requires separate migration work if the merchant must see it in the SaaS.
4. **Public catalog cache:** Default is short-lived public cache with signed invalidation and scheduled reconciliation. Private commerce data remains live SaaS data.
5. **Supported WooCommerce product types:** The importer must publish an explicit capability matrix and reject unsupported structures truthfully.

An implementation agent must not invent an answer to one of these decisions merely to finish a batch.

## 9. Boundaries and deferred scope

This implementation program does not authorize:

- Restoring external checkout.
- Maintaining WooCommerce as a live commerce authority.
- Adding Shopify or other connected-channel adapters.
- Expanding carrier production claims beyond current approvals and code gates.
- Turning the WordPress connector into an independent commerce engine.
- Storing platform or provider secrets in WordPress/browser code.
- Deleting historical migrations merely because their names refer to removed behavior.
- Deleting real legacy transaction records without a verified migration/retention decision.
- Claiming public-beta/live readiness before Batch 8 evidence exists.

## 10. Per-batch execution protocol for Cursor

For every requested batch, Cursor must return an implementation plan based on the current repository before editing, then execute only after the user approves that batch plan if approval was requested.

Each batch response must include:

1. Preflight `git status --short` result.
2. Current source findings relevant to the batch.
3. Existing unrelated changes that will be preserved.
4. Exact intended files and migrations.
5. Data-compatibility and rollback considerations.
6. Focused tests to add or update.
7. Commands actually executed.
8. Exact test results.
9. Remaining failures, risks, or deferred work.
10. Confirmation that later batches were not implemented accidentally.

If actual code contradicts this document, stop and report the conflict. Do not silently preserve old external-checkout behavior and do not silently rewrite this architecture.

## 11. Final definition of done

The program is complete only when all of the following are proven:

- Platform checkout is the only active checkout mode.
- External checkout cannot be selected, invoked, or used as fallback.
- The WordPress connector is a thin, secure, store-scoped presentation client.
- The SaaS is authoritative for catalog, variants, prices, currency, inventory, customers, orders, payments, tax, shipping, fulfillment, and tracking.
- Stripe is connected through the SaaS and payment completion is provider-authoritative: validated direct retrieval of the exact stored PaymentIntent or a verified webhook, with transactional idempotent conversion and webhook recovery.
- WooCommerce and conflicting payment/shipping plugins can be deactivated after a validated migration.
- WooCommerce catalog import is source-aware, idempotent, location-aware, and truthful about unsupported data.
- Public catalog cache updates are reliable and recoverable without creating a second authority.
- Tenant isolation, retries, concurrency, webhook duplication, failures, and outages are tested.
- Merchant cutover and rollback are documented and acceptance-tested.
- Full-suite and end-to-end evidence is recorded without unsupported readiness claims.
