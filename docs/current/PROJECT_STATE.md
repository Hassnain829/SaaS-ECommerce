# Project State — 2026-08-12

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

The merchant path is **Website → Connect your website**. WordPress is the customer-facing shop. Catalog, orders, customers, and shipping stay in this portal. WooCommerce import and Phase 9 API keys/webhooks are out of this pass.

What exists now:

- One hashed connection key per store (`stores.developer_storefront_token_hash`), generated from the Website workspace
- Optional saved website URL (`stores.settings.connected_website_url`) and last catalog request (`stores.developer_storefront_last_seen_at`)
- Catalog read: `GET /api/developer-storefront/catalog` (store-scoped, includes store currency; stamps last-seen)
- External order write: `POST /api/v1/external/orders` (variant-backed, store-scoped, optional Idempotency-Key; currency must match the store)
- External shipment write: `POST /api/v1/external/shipments` (other external systems — not the WordPress plugin)
- WordPress plugin in `dev-test-wordpress/` (primary merchant connect path; plugin zip download on the Website page)
- Local React simulator in `dev-test-storefront/` (Advanced details only; same connection key)

What this pass does not include:

- Phase 9 scoped API keys, outbound webhooks, or event outbox
- Import of an existing WooCommerce/WordPress catalog into this portal
- WordPress shipment posting or carrier controls on the Website page

## Deferred from the readiness gate

- Additional carrier expansion
- SaaS subscription/billing expansion
- Payment expansion beyond current foundations

## Go-live gate

Do **not** describe the overall project as live-ready / public-beta ready until:

1. the readiness document’s P0 acceptance gates pass, and
2. the full automated suite gate passes with current evidence.

Do not claim the suite is green without a successful run. DR-04 evidence on 2026-08-13: `php artisan test` was green with 1,430 passed, 2 skipped, and 0 failures. CI now also requires `migrate:fresh --seed`.

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
