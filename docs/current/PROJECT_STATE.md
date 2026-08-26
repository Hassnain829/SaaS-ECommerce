# Project State — 2026-08-20

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
6. DR-05 WordPress connection: **complete** (Batches 1–8). Ten browser scenarios closed by merchant confirmation; go-live checklist ships on Website; Batch 8 mapping in `docs/handoffs/DR05_BATCH8_RELEASE_EVIDENCE.md`
7. DR-06 owner/manager/staff and two-store acceptance: **complete** (automated journey in `Dr06MerchantAcceptanceTest`; DR-07 identity editing still open)
8. Actionable settings — **complete** (DR-08 in-page General Settings form)
9. Customer identity editing — **complete** (DR-07)
10. Real or hidden analytics/admin surfaces

## Connected websites (current code)

The merchant path is **Website → Connect your website**. WordPress is the customer-facing shop. Catalog, orders, customers, and shipping stay in this portal. Phase 9 API keys/webhooks remain out of this pass.

What exists now:

- Connected-site identity (`connected_sites`): store ID, public id, normalized WordPress URL, hashed credential, status, scopes, plugin version, last-seen, last health, rotation, and revocation
- One WordPress site belongs to exactly one SaaS store. Merchant UX keeps one primary WordPress storefront; the schema can hold more sites later
- Connection key generated, rotated, and revoked from the Website workspace. Only the active `connected_sites.credential_hash` authenticates; legacy store-level hashes are cleared after migration verification and are never mirrored
- Required per-store website URL (`stores.settings.connected_website_url` and the connected-site row) saved before key creation; one WordPress site may be moved only after the previous store connection is revoked
- Catalog read: `GET /api/developer-storefront/catalog` (store-scoped, includes store currency; stamps last-seen)
- Connection health: `GET /api/v1/site/health` (store, URL match, plugin version, Stripe/location/catalog readiness, last contact, catalog version, catalog-cache checkpoint)
- Catalog events: `GET /api/v1/site/events/config`, `GET /api/v1/catalog/events`, signed push to the bound WordPress `/wp-json/eco-portal/v1/events` path (connected-site catalog invalidation only)
- Platform checkout: `POST /api/v1/checkout` (SaaS-owned totals, delivery rates, Stripe PaymentIntent)
- WordPress plugin in `dev-test-wordpress/` (primary merchant connect path; plugin zip download on the Website page; connection key stays server-side and is not printed into admin HTML after save)
- Local React simulator in `dev-test-storefront/` (Advanced details only; same connection key)

**Checkout policy (Batch 1, 2026-08-14; Stripe readiness corrected 2026-08-19):** Platform checkout is the only checkout mode. External order/shipment write APIs are removed. Normal checkout requires the current store's active, default, charge-enabled Stripe Connect account. Platform test keys and Stripe CLI authentication are infrastructure/developer tools and cannot make a merchant storefront payment-ready. Stripe disconnect or incomplete onboarding blocks checkout and never falls back to website or platform-account payment. Historical `external_checkout` orders remain readable.

**Connected-site auth (corrected 2026-08-18, multi-store UX corrected 2026-08-19):** Connector APIs authenticate active connected-site credentials only, enforce scopes, require the bound HTTPS site URL in production, rate-limit failed attempts, and log `connected_site.auth_failed`. The selected store's exact WordPress address is required before its key can be created. Active normalized URLs are database-unique, each store receives a distinct credential, and revocation releases the active URL key so the same WordPress installation can be intentionally moved to another store. There is no legacy store-hash fallback or credential mirroring.

**Commerce API (Batch 3, 2026-08-15; multi-store payment isolation corrected 2026-08-19):** Platform checkout remains the only checkout path. Catalog v1 is published-only (including products without variants) with pagination, store identity, tags, and `catalog_version` / ETag. The WordPress shop reads that catalog plus categories; checkout still sends variant IDs and quantities only. Prices, tax, shipping, and stock are calculated in this portal. Guest checkout upserts the customer by email (no WordPress password sync). Stripe PaymentIntents are created here with store/account, amount, currency, and idempotency. Stripe webhooks are signature-verified, de-duplicated, and ignore a failed event after a successful conversion. Shoppers receive a confirmation token for order status and tracking. Stripe onboarding stays hosted Connect and store-scoped: one user may keep multiple stores active, but each store currently completes its own test/live connection and receives its own connected-account record. Disabling one store does not disable another. A WooCommerce Stripe plugin account cannot be reused.

**WordPress connector (Batch 4, 2026-08-15):** The plugin is a presentation client. Cart cookies store variant IDs and quantities only; catalog prices are display-only until checkout requests a portal quote. Disconnecting the portal or Stripe blocks checkout with reconnect steps. WordPress detects WooCommerce, Woo checkout/cart pages, Woo payment/shipping plugins, conflicting shortcodes, and checkout page cache, then reports exact fix steps. It never deactivates merchant plugins. Product detail and guest order-status lookup are API-backed. Phase 9 outbox/webhooks and Woo import remain incomplete.

**Catalog cache events (Batch 5, 2026-08-15):** Public product/category representations may be cached briefly on WordPress. Checkout, customers, orders, payments, and carrier credentials are not. Catalog changes write to a connected-site outbox (not the Phase 9 merchant webhook product), then a signed event is delivered to the bound WordPress URL. WordPress verifies HMAC signatures, rejects replays, invalidates cache, and repairs missed updates from `catalog_version` plus `GET /api/v1/catalog/events`. The Website workspace shows whether the website product list matches this portal. Phase 9 scoped API keys, generic outbound webhooks, and Woo import remain incomplete.

**WooCommerce catalog import (Batch 6, 2026-08-15):** The existing product importer detects a standard WooCommerce product CSV, maps simple/variable/variation rows, rejects unsupported types instead of importing them as success, generates missing SKUs deterministically, stores Woo source identity, preserves slugs with an old-to-new address map, and requires a destination location plus replace-or-preserve stock choice. Re-import updates the same catalog records. Imperial Woo headers (`Weight (lbs)`, `Length/Width/Height (in)`), `Brands`, and `GTIN, UPC, EAN, or ISBN` map onto catalog fields; variation `Images` become variant photos while the parent `Images` column remains the product gallery. Blank Stock cells import as 0; `In stock?` is additional detail, not a quantity. This does not migrate orders, customers, or payments. Phase 9 scoped API keys and generic outbound webhooks remain incomplete.

**DR-05 Batch 1–6 critical correction (2026-08-18, payment authority amended 2026-08-19):** `docs/plans/DR05_BATCH6_CRITICAL_FIX_SPEC.md` is the architecture contract. Direct paid-order and external shipment/order sync runtime endpoints are retired. Checkout creation requires a database-first idempotency claim. An authenticated store-scoped confirmation request now retrieves the stored PaymentIntent directly from Stripe in its stored mode and connected-account context; only an exact checkout/store/account metadata match with validated amount/currency and provider status `succeeded` converts the checkout. Signed Stripe webhooks remain an idempotent asynchronous recovery path, but local order confirmation no longer depends on a terminal listener. WordPress preallocates the browser-bound session, opaque form token, and one idempotency key before rendering the initial address form; concurrent submissions and lost-response retries reuse it, while missing state fails closed and rotation requires completion or an explicit reset. Connected-site URL binding and credential activation are transactional, with the active URL unique index as concurrency authority. WordPress persists `confirming` / `processing` server-side and uses nonce-protected browser polling with backoff, reload/redirect resume, and no PHP sleep loop. Catalog push delivery applies production HTTPS, A/AAAA destination filtering, no redirects, and DNS pinning. Woo re-import identity includes the exact merchant-confirmed source site; SKU linking is blocked unless explicitly approved.

**Stripe payment readiness follow-up (2026-08-19):** Merchant and WordPress readiness now resolve only the current store's ready Connect account. The local platform sandbox is an explicit developer-test facility and is never selected by normal checkout. Read-only inspection proved that test order `#1003` had instead been created on the platform test account with no connected-account context while the store's own Connect onboarding remained incomplete; that implicit path is now closed without deleting the historical test order.

**DR-06 status (2026-08-20):** Automated merchant acceptance across the ten-item owner/manager/staff two-store journey is complete in `tests/Feature/Dr06MerchantAcceptanceTest.php` (9 passed / 83 assertions on the focused sign-off run). Customer name/email/phone editing remains DR-07. Full `php artisan test` was not run in this pass. The product is still not live-ready / public-beta ready until remaining P0 and a current full-suite green run.

What this pass added:

- Merchant go-live checklist on Website → Connect your website
- `connected_site_cutovers` unique per store; acknowledgements cannot override Stripe, URL match, paid-order, delivery, or failed-import gates
- Rollback marks portal status only; it does not delete WordPress, WooCommerce, or orders
- Batch 8 contract tests for retired external write paths and platform-only checkout
- DR-06 focused owner/manager/staff two-store tests

What this pass still does not include:

- Phase 9 scoped API keys, merchant webhook subscriptions, or a generic event outbox
- WordPress shipment posting or carrier controls on the Website page
- A current full-suite green claim
- Optional human owner/manager/staff browser walkthrough before public-beta marketing claims
- Customer identity editing (DR-07)

## Deferred from the readiness gate

- Additional carrier expansion
- SaaS subscription/billing expansion
- Payment expansion beyond current foundations

## Go-live gate

Do **not** describe the overall project as live-ready / public-beta ready until:

1. the readiness document's remaining P0 items (including DR-07 customer identity editing where still open) pass; and
2. the current full-suite gate passes with evidence.

DR-05 Batches 1–8 and DR-06 automated merchant acceptance are complete. Do not claim the suite is currently green without a successful current run. Historical DR-05 amendment evidence from 2026-08-19 recorded 1,489 passed, 2 skipped, 8,096 assertions, and 0 failures, plus 65 focused tests and 415 assertions. A later historical run recorded 1,494 passed, 2 skipped, 8,151 assertions, and 0 failures. Those suites were not rerun during the 2026-08-20 Batch 7/8 or DR-06 passes. Focused Batch 7/8/DR-06 coverage was run instead. CI still requires `migrate:fresh --seed`.

The ten Batch 1–6 browser scenarios are complete by merchant confirmation dated 2026-08-20. Stripe IDs were not committed.

## Key links

- [`docs/handoffs/DEVELOPMENT_READINESS_MERCHANT_UX_REVIEW.md`](../handoffs/DEVELOPMENT_READINESS_MERCHANT_UX_REVIEW.md)
- [`docs/fedex/MODEL_A_INTEGRATOR_PROVIDER.md`](../fedex/MODEL_A_INTEGRATOR_PROVIDER.md)
- [`docs/architecture/CARRIER_CODE_STRUCTURE.md`](../architecture/CARRIER_CODE_STRUCTURE.md)
- [`docs/operations/RELEASE_CHECKLIST.md`](../operations/RELEASE_CHECKLIST.md)
- [`docs/plans/DR05_BATCH6_CRITICAL_FIX_SPEC.md`](../plans/DR05_BATCH6_CRITICAL_FIX_SPEC.md)
- [`docs/plans/DR05_BATCH7_MERCHANT_CUTOVER_PLAN.md`](../plans/DR05_BATCH7_MERCHANT_CUTOVER_PLAN.md)
- [`docs/handoffs/DR05_BATCH8_RELEASE_EVIDENCE.md`](../handoffs/DR05_BATCH8_RELEASE_EVIDENCE.md)
- [`docs/WORDPRESS_SAAS_COMMERCE_ARCHITECTURE_AND_IMPLEMENTATION_PLAN.md`](../WORDPRESS_SAAS_COMMERCE_ARCHITECTURE_AND_IMPLEMENTATION_PLAN.md)

## Documentation authority

1. Current source code, migrations, routes, configuration, and tests
2. `docs/current/PROJECT_STATE.md` (this file)
3. `docs/handoffs/DEVELOPMENT_READINESS_MERCHANT_UX_REVIEW.md`
4. FedEx Model A + carrier architecture docs
5. Root product/roadmap/structure documents
6. Active implementation plans
7. `docs/archive/**` — historical evidence only
