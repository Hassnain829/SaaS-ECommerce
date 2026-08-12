# AGENTS.md — Enterprise SaaS E-Commerce Project Instructions

## Project identity

This repository is a **Laravel Blade multi-store SaaS commerce platform**. It is the merchant operations backend for catalog, inventory, orders, customers, fulfillment connectivity, and future billing/integrations — not a simple single-store webshop.

`dev-test-storefront` is a local testing simulator for catalog/order APIs. It is **not** the final production connected-channel product.

## Canonical read order

1. `AGENTS.md` (this file)
2. `docs/current/PROJECT_STATE.md`
3. `docs/handoffs/DEVELOPMENT_READINESS_MERCHANT_UX_REVIEW.md`
4. `ENTERPRISE_PROJECT_CONTEXT.md`
5. `ENTERPRISE_ROADMAP_2026.md`
6. `PROJECT_BRAIN.md`
7. `PROJECT_STRUCTURE.md`
8. Relevant active architecture / operations / plan docs
9. `.cursor/rules/*.mdc`

Implementation truth is always current source code, migrations, routes, configuration, and tests. Historical docs under `docs/archive/` are evidence only and are **not** implementation authority.

## Non-negotiable rules

### Store scoping

Every tenant read/write must resolve the **current store** and verify ownership (`store_id` or equivalent). Never trust raw IDs without same-store checks. Cross-store access must deny or 404.

Bad:

```php
Product::find($id);
```

Good:

```php
Product::query()
    ->where('store_id', $currentStore->id)
    ->whereKey($id)
    ->firstOrFail();
```

### Authorization

Preserve owner / manager / staff role restrictions. Do not weaken permissions.

### Truthful merchant UI

Do not ship fake claims, demo metrics presented as real, dead links, or inactive controls in normal merchant navigation. Unsupported scope must be hidden or clearly gated — not implied as live.

### Product editing

Canonical product editing is the product workspace (`products.edit`). Product list Edit must route there. Do not treat a list-page Edit modal as the primary product edit workflow.

### Carrier billing

Merchants connect **merchant-owned** carrier accounts and pay their own postage/carrier charges. The SaaS platform provides connectivity and must **not** become the postage payer. Do not ask normal merchants to paste carrier developer secrets when an official authorization model exists.

### FedEx / USPS / DHL

Follow `docs/current/PROJECT_STATE.md` and `docs/fedex/MODEL_A_INTEGRATOR_PROVIDER.md`.

- FedEx **Model A** is primary; certification/validation workspace is **retired** — do not reintroduce it.
- Model B is developer fallback only.
- USPS platform/Label Provider approval remains pending; do not claim general merchant-owned production labels are live.
- DHL production integration is not implemented.
- Additional carrier expansion is outside the current readiness gate.

### Testing and sign-off

Use focused tests during implementation. Before release sign-off, require full-suite verification with evidence. Do not claim the suite is green without a successful run. Do not describe the project as live-ready until readiness P0 acceptance gates and the full-suite gate pass.

## Merchant-facing language

Prefer merchant-friendly wording (product options, variants, inventory, additional details). Avoid exposing developer jargon (payload, schema, pivot, raw meta) in primary UI.
