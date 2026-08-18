# Project State — 2026-08-18

Concise current-state snapshot for agents and developers. This is **not** a roadmap.

## Platform identity

Laravel Blade multi-store SaaS commerce platform. Merchants connect merchant-owned selling channels and carrier accounts. The SaaS provides connectivity and operations tooling; it must not become the postage payer and must not expose fake or unsupported merchant claims.

Every tenant-owned read/write must be scoped to the active store. Owner / manager / staff authorization must remain enforced.

## Inventory (inspected 2026-08-12)

| Area | Count |
|------|------:|
| Models | 73 |
| Migrations | 93 |
| Services | 159 |
| Feature tests | 122 |
| Unit tests | 32 |
| Blade views | 151 |

## FedEx

- **Model A / Official Integrator Provider** is primary.
- Merchant connects a merchant-owned FedEx account and pays FedEx charges/postage.
- Final integrator approval is complete for the **United States and Canada only**.
- Production connection and operations are implemented and enabled behind configuration/capability/account gates.
- Certification/validation workspace, routes, services, evidence tables, and runtime tree are **retired/removed** — do not reintroduce them.
- Model B is local/testing developer fallback only.

## USPS

- Merchant EPA is **PAYER** and **RATE_HOLDER**; merchant MID is **LABEL_OWNER**.
- Platform account must never pay merchant postage.
- USPS Ship enrollment and current limited entitlement exist.
- Platform/Label Provider approval remains **pending**.
- Do not claim the merchant-owned USPS production-label flow is generally live.
- Do not ask merchants to paste USPS secrets or passwords into normal UI.

## DHL

- Business and developer accounts exist.
- Account-manager / API access response remains pending.
- Production DHL integration is **not** implemented.
- Do not invent credentials, approval, or live capability.

## Current merchant readiness P0

Authoritative handoff: [`docs/handoffs/DEVELOPMENT_READINESS_MERCHANT_UX_REVIEW.md`](../handoffs/DEVELOPMENT_READINESS_MERCHANT_UX_REVIEW.md)

Current work priority:

1. Truthful feature visibility and gating
2. One canonical product workspace
3. Truthful onboarding
4. Password recovery, email verification, legal links, POST logout, and password toggles
5. Full-suite recovery
6. Merchant WordPress website connection (DR-05) — guided Website workspace on the current store connection key and catalog/order APIs (not Phase 9)
7. Owner/manager/staff and two-store acceptance
8. Actionable settings
9. Customer identity editing
10. Real or hidden analytics/admin surfaces

## Connected websites (current code)

The merchant path is **Website → Connect your website**. WordPress is the customer-facing shop. Catalog, orders, customers, and shipping stay in this portal. Phase 9 API keys/webhooks remain out of this pass.

What exists now:

- Connected-site identity (`connected_sites`): store ID, public id, normalized WordPress URL, hashed credential, status, scopes, plugin version, last-seen, last health, rotation, and revocation
- One WordPress site belongs to exactly one SaaS store. Merchant UX keeps one primary WordPress storefront; the schema can hold more sites later
- Connection key generated, rotated, and revoked from the Website workspace. Only the active `connected_sites.credential_hash` authenticates; legacy store-level hashes are cleared after migration verification and are never mirrored
- Optional saved website URL (`stores.settings.connected_website_url` and the connected-site row)
- Catalog read: `GET /api/developer-storefront/catalog` (store-scoped, includes store currency; stamps last-seen)
- Connection health: `GET /api/v1/site/health` (store, URL match, plugin version, Stripe/location/catalog readiness, last contact, catalog version, catalog-cache checkpoint)
- Catalog events: `GET /api/v1/site/events/config`, `GET /api/v1/catalog/events`, signed push to the bound WordPress `/wp-json/eco-portal/v1/events` path (connected-site catalog invalidation only)
- Platform checkout: `POST /api/v1/checkout` (SaaS-owned totals, delivery rates, Stripe PaymentIntent)
- WordPress plugin in `dev-test-wordpress/` (primary merchant connect path; plugin zip download on the Website page; connection key stays server-side and is not printed into admin HTML after save)
- Local React simulator in `dev-test-storefront/` (Advanced details only; same connection key)

**Checkout policy (Batch 1, 2026-08-14):** Platform checkout is the only checkout mode. External order/shipment write APIs are removed. Stripe disconnect blocks checkout and never falls back to website payment. Historical `external_checkout` orders remain readable.

**Connected-site auth (corrected 2026-08-18):** Connector APIs authenticate active connected-site credentials only, enforce scopes, require the bound HTTPS site URL in production, rate-limit failed attempts, and log `connected_site.auth_failed`. Active normalized URLs are database-unique and revocation releases the active URL key. There is no legacy store-hash fallback or credential mirroring.

**Commerce API (Batch 3, 2026-08-15):** Platform checkout remains the only checkout path. Catalog v1 is published-only (including products without variants) with pagination, store identity, tags, and `catalog_version` / ETag. The WordPress shop reads that catalog plus categories; checkout still sends variant IDs and quantities only. Prices, tax, shipping, and stock are calculated in this portal. Guest checkout upserts the customer by email (no WordPress password sync). Stripe PaymentIntents are created here with store/account, amount, currency, and idempotency. Stripe webhooks are signature-verified, de-duplicated, and ignore a failed event after a successful conversion. Shoppers receive a confirmation token for order status and tracking. Stripe onboarding stays hosted Connect; a WooCommerce Stripe plugin account cannot be reused.

**WordPress connector (Batch 4, 2026-08-15):** The plugin is a presentation client. Cart cookies store variant IDs and quantities only; catalog prices are display-only until checkout requests a portal quote. Disconnecting the portal or Stripe blocks checkout with reconnect steps. WordPress detects WooCommerce, Woo checkout/cart pages, Woo payment/shipping plugins, conflicting shortcodes, and checkout page cache, then reports exact fix steps. It never deactivates merchant plugins. Product detail and guest order-status lookup are API-backed. Phase 9 outbox/webhooks and Woo import remain incomplete.

**Catalog cache events (Batch 5, 2026-08-15):** Public product/category representations may be cached briefly on WordPress. Checkout, customers, orders, payments, and carrier credentials are not. Catalog changes write to a connected-site outbox (not the Phase 9 merchant webhook product), then a signed event is delivered to the bound WordPress URL. WordPress verifies HMAC signatures, rejects replays, invalidates cache, and repairs missed updates from `catalog_version` plus `GET /api/v1/catalog/events`. The Website workspace shows whether the website product list matches this portal. Phase 9 scoped API keys, generic outbound webhooks, and Woo import remain incomplete.

**WooCommerce catalog import (Batch 6, 2026-08-15):** The existing product importer detects a standard WooCommerce product CSV, maps simple/variable/variation rows, rejects unsupported types instead of importing them as success, generates missing SKUs deterministically, stores Woo source identity, preserves slugs with an old-to-new address map, and requires a destination location plus replace-or-preserve stock choice. Re-import updates the same catalog records. Imperial Woo headers (`Weight (lbs)`, `Length/Width/Height (in)`), `Brands`, and `GTIN, UPC, EAN, or ISBN` map onto catalog fields; variation `Images` become variant photos while the parent `Images` column remains the product gallery. Blank Stock cells import as 0; `In stock?` is additional detail, not a quantity. This does not migrate orders, customers, or payments. Phase 9 scoped API keys and generic outbound webhooks remain incomplete.

**DR-05 Batch 1–6 critical correction (2026-08-18):** `docs/plans/DR05_BATCH6_CRITICAL_FIX_SPEC.md` is the locked architecture. Direct paid-order and external shipment/order sync runtime endpoints are retired. Checkout creation requires a database-first idempotency claim. Browser/WordPress confirmation is read-only polling; only a verified Stripe webhook converts a checkout. WordPress preallocates the browser-bound session, opaque form token, and one idempotency key before rendering the initial address form; concurrent submissions and lost-response retries reuse it, while missing state fails closed and rotation requires completion or an explicit reset. Connected-site URL binding and credential activation are transactional, with the active URL unique index as concurrency authority. WordPress persists `confirming` / `processing` server-side and uses nonce-protected browser polling with backoff, reload/redirect resume, and no PHP sleep loop. Catalog push delivery applies production HTTPS, A/AAAA destination filtering, no redirects, and DNS pinning. Woo re-import identity includes the exact merchant-confirmed source site; SKU linking is blocked unless explicitly approved.

What this pass does not include:

- Phase 9 scoped API keys, merchant webhook subscriptions, or a generic event outbox
- Merchant cutover / go-live stepper (Batch 7)
- WordPress shipment posting or carrier controls on the Website page

## Deferred from the readiness gate

- Additional carrier expansion
- SaaS subscription/billing expansion
- Payment expansion beyond current foundations

## Go-live gate

Do **not** describe the overall project as live-ready / public-beta ready until:

1. the readiness document’s P0 acceptance gates pass, and
2. the full automated suite gate passes with current evidence.

Do not claim the suite is green without a successful run. DR-05 final-correction evidence on 2026-08-18: `php artisan test` was green with 1,481 passed, 2 skipped, 8,049 assertions, and 0 failures in 164.86 seconds; a fresh in-memory SQLite database also passed `migrate:fresh --seed --force`. Browser-driven WordPress + Stripe test-mode acceptance remains unverified, so Batches 1–6 are not fully signed off and Batch 7 must not begin. CI requires `migrate:fresh --seed`.

## Key links

- [`docs/handoffs/DEVELOPMENT_READINESS_MERCHANT_UX_REVIEW.md`](../handoffs/DEVELOPMENT_READINESS_MERCHANT_UX_REVIEW.md)
- [`docs/fedex/MODEL_A_INTEGRATOR_PROVIDER.md`](../fedex/MODEL_A_INTEGRATOR_PROVIDER.md)
- [`docs/architecture/CARRIER_CODE_STRUCTURE.md`](../architecture/CARRIER_CODE_STRUCTURE.md)
- [`docs/operations/RELEASE_CHECKLIST.md`](../operations/RELEASE_CHECKLIST.md)

## Documentation authority

1. Current source code, migrations, routes, configuration, and tests
2. `docs/current/PROJECT_STATE.md` (this file)
3. `docs/handoffs/DEVELOPMENT_READINESS_MERCHANT_UX_REVIEW.md`
4. FedEx Model A + carrier architecture docs
5. Root product/roadmap/structure documents
6. Active implementation plans
7. `docs/archive/**` — historical evidence only
