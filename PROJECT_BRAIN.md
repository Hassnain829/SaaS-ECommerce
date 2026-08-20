# PROJECT BRAIN — Condensed system memory

Short operating memory for agents. Volatile status: **`docs/current/PROJECT_STATE.md`**. Release P0: **`docs/handoffs/DEVELOPMENT_READINESS_MERCHANT_UX_REVIEW.md`**.

---

## What we are building

Enterprise multi-tenant SaaS ecommerce operations platform: simpler and clearer than Shopify/WooCommerce-style complexity, while remaining production-grade.

Golden rule: if the merchant is confused, the system is wrong.

---

## Architecture principles

1. **Multi-store SaaS (strict)** — every tenant entity belongs to a store; use current-store context everywhere.
2. **Hybrid data model** — core fields in tables; flexible mapped fields in `meta.custom_fields`; unmapped import extras in `meta.import_extra`.
3. **Import → Normalize → Store → Expose → Manage** — imported data must be usable in UI, not only stored.
4. **Workspace-first UX** — core editing uses dedicated workspaces (canonical product edit: `products.edit`), not modal/list bridge flows.
5. **No hacks** — direct routes, stable UX, no temporary redirects that become permanent.

---

## Current priority (2026-08-20)

1. Merchant readiness truth/gating, product workspace, and settings corrections (`docs/handoffs/DEVELOPMENT_READINESS_MERCHANT_UX_REVIEW.md`)
2. Onboarding / auth / legal recovery
3. Full-suite recovery and acceptance (do not claim green without evidence)
4. Continue remaining readiness P0 (DR-07 customer identity, settings, gating, full-suite). DR-05 and DR-06 automated acceptance are complete.
5. Phase 9 integration foundation (approved plan; **not complete**)
6. Later platform phases (billing, markets, admin, observability)

**Deferred from readiness gate:** additional carriers, SaaS subscription expansion, payment expansion.

---

## Carrier connectivity (locked)

- Merchants own carrier accounts and pay postage/carrier charges.
- Platform provides connectivity only — never the postage payer.
- **FedEx Model A** primary; US/CA production approval complete; production ops behind capability gates; certification/validation workspace **removed**.
- **Model B** developer fallback only.
- **USPS / DHL** status: see `docs/current/PROJECT_STATE.md` — do not invent readiness.
- Details: `docs/fedex/MODEL_A_INTEGRATOR_PROVIDER.md`, `docs/architecture/CARRIER_CODE_STRUCTURE.md`

---

## Completed foundations (high level)

Catalog/import/variants, commerce core, multi-location inventory, manual fulfillment, checkout delivery, provider-authoritative Stripe platform checkout, source-site-aware Woo catalog migration, connected WordPress presentation client, merchant Website go-live checklist, DR-06 automated merchant acceptance, Phase 5R tax/coupons/totals, Phase 7 returns/refunds/exchanges, Phase 11 notifications foundation, CLEAN-1–4 hygiene, FedEx Model A production connectivity (gated), USPS public API foundation. Payment conversion requires either a verified Stripe webhook or validated SaaS retrieval of the exact stored PaymentIntent; browser and WordPress claims are never authoritative. External order/shipment sync is retired runtime behavior; only historical order interpretation remains. DR-05 WordPress connection and DR-06 automated acceptance are signed off; remaining readiness P0 (including DR-07) and a current full-suite green run are still required before live-ready claims.

Historical phase/completion reports live under `docs/archive/` and are **not** current instructions.

---

## Inventory (inspected 2026-08-12)

Models 73 · Migrations 93 · Services 159 · Feature tests 122 · Unit tests 32 · Blade views 151

---

## Repository hygiene

- `php artisan project:hygiene-report`
- `php artisan project:cleanup` (dry-run default; never deletes Git-tracked files)
- `php artisan project:source-archive` (Git required)
- `php artisan project:retention` (dry-run default)

References: `docs/cleanup/SOURCE_ARCHIVE_GUIDE.md`, `docs/operations/RUNTIME_STORAGE_RETENTION.md`, `docs/architecture/REFACTORING_BOUNDARIES.md`, `docs/architecture/REFACTORING_ROADMAP.md`

---

## Decision framework

Before implementing: easier for merchant? scalable? consistent? future-proof? clean? If no → rethink.

Final line: if merchants struggle, we failed; if the system is hacky, it will collapse later.
